<?php
/**
 * RarTranscoder — turn a RAR-packed comic (.cbr) into a CBZ.
 *
 * Browsers cannot unpack RAR and neither can PHP without the (rarely
 * installed, and now unmaintained) `rar` extension, so a .cbr is normalised
 * to a .cbz once, at import time, and the library only ever stores ZIPs.
 *
 * Two happy paths:
 *   1. Many files named .cbr are actually ZIPs — publishers and scrapers
 *      rename them constantly. `looksLikeZip()` catches that for free.
 *   2. A real RAR needs an external unpacker. We probe for the usual
 *      suspects in PATH; if none is present the import fails with a message
 *      that names them rather than silently storing an unreadable file.
 */

defined('APP_BOOTED') or exit;

class RarTranscoder
{
    /** Candidate unpackers, in preference order: [binary, args-builder]. */
    private const CANDIDATES = [
        // bsdtar (libarchive) ships with macOS and most Linux distros.
        'bsdtar' => ['-x', '-f', '{src}', '-C', '{dest}'],
        // unar / lsar from The Unarchiver.
        'unar'   => ['-quiet', '-force-overwrite', '-output-directory', '{dest}', '{src}'],
        // Official unrar, and the 7-Zip family.
        'unrar'  => ['x', '-y', '-inul', '{src}', '{dest}/'],
        '7zz'    => ['x', '-y', '-o{dest}', '{src}'],
        '7z'     => ['x', '-y', '-o{dest}', '{src}'],
        '7za'    => ['x', '-y', '-o{dest}', '{src}'],
    ];

    public static function looksLikeRar(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $magic = fread($fh, 8);
        fclose($fh);
        // RAR 1.5–4.x: "Rar!\x1A\x07\x00"; RAR 5.x: "Rar!\x1A\x07\x01\x00".
        return is_string($magic) && strncmp($magic, "Rar!\x1A\x07", 6) === 0;
    }

    public static function looksLikeZip(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $magic = fread($fh, 4);
        fclose($fh);
        return $magic === "PK\x03\x04";
    }

    /** Name of the unpacker we would use, or null when the host has none. */
    public static function availableTool(): ?string
    {
        static $found = false;
        if ($found !== false) {
            return $found;
        }
        if (extension_loaded('rar') && class_exists('RarArchive')) {
            return $found = 'php-rar';
        }
        foreach (array_keys(self::CANDIDATES) as $bin) {
            if (self::binaryExists($bin)) {
                return $found = $bin;
            }
        }
        return $found = null;
    }

    public static function isSupported(): bool
    {
        return self::availableTool() !== null;
    }

    /** Human-readable list of the unpackers we know how to drive. */
    public static function supportedToolNames(): string
    {
        return 'bsdtar, unar, unrar, or 7z';
    }

    /**
     * Convert $rarPath into a new .cbz written under $workDir.
     * Returns the path to the CBZ.
     *
     * @throws RuntimeException when no unpacker is available or extraction
     *                          yields no page images.
     */
    public static function toCbz(string $rarPath, string $workDir): string
    {
        $tool = self::availableTool();
        if ($tool === null) {
            throw new RuntimeException(
                'This server cannot unpack RAR archives. Install one of '
                . self::supportedToolNames() . ', or convert the file to .cbz before uploading.'
            );
        }

        $extractDir = rtrim($workDir, '/') . '/cbr-' . bin2hex(random_bytes(8));
        if (!@mkdir($extractDir, 0750, true) && !is_dir($extractDir)) {
            throw new RuntimeException('Could not create a working directory for the conversion.');
        }

        try {
            if ($tool === 'php-rar') {
                self::extractWithPhpRar($rarPath, $extractDir);
            } else {
                self::extractWithBinary($tool, $rarPath, $extractDir);
            }

            $images = self::collectImages($extractDir);
            if (!$images) {
                throw new RuntimeException('No page images were found inside the archive.');
            }

            $cbzPath = rtrim($workDir, '/') . '/' . bin2hex(random_bytes(8)) . '.cbz';
            self::zipImages($images, $extractDir, $cbzPath);
            return $cbzPath;
        } finally {
            self::rmTree($extractDir);
        }
    }

    /* --------------------------------------------------------------------- */

    private static function binaryExists(string $bin): bool
    {
        if (!function_exists('exec') || self::execDisabled()) {
            return false;
        }
        $out = [];
        $code = 1;
        @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
        return $code === 0 && !empty($out);
    }

    private static function execDisabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array('exec', $disabled, true);
    }

    private static function extractWithBinary(string $bin, string $src, string $dest): void
    {
        $args = self::CANDIDATES[$bin];
        $cmd  = escapeshellarg($bin);
        foreach ($args as $arg) {
            $arg = str_replace(['{src}', '{dest}'], [$src, $dest], $arg);
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $out = [];
        $code = 1;
        @exec($cmd . ' 2>&1', $out, $code);
        // Some unpackers return non-zero on warnings while still extracting,
        // so trust the output directory over the exit status.
        if ($code !== 0 && !self::collectImages($dest)) {
            throw new RuntimeException('RAR extraction failed: ' . trim(implode(' ', array_slice($out, 0, 3))));
        }
    }

    private static function extractWithPhpRar(string $src, string $dest): void
    {
        $archive = RarArchive::open($src);
        if ($archive === false) {
            throw new RuntimeException('Could not open the RAR archive.');
        }
        try {
            foreach ($archive->getEntries() ?: [] as $entry) {
                if ($entry->isDirectory()) {
                    continue;
                }
                $entry->extract($dest);
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * Every page image under $dir, naturally sorted by its path relative to
     * $dir so nested chapter folders keep their reading order.
     *
     * @return string[] absolute paths
     */
    private static function collectImages(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $found = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getSize() <= 0) {
                continue;
            }
            $rel = substr($file->getPathname(), strlen($dir) + 1);
            if (ComicArchive::isPageName($rel)) {
                $found[] = $file->getPathname();
            }
        }
        usort($found, static fn(string $a, string $b): int => strnatcasecmp($a, $b));
        return $found;
    }

    private static function zipImages(array $images, string $baseDir, string $cbzPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($cbzPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the converted .cbz file.');
        }
        // Renumber into a flat, zero-padded sequence so page order no longer
        // depends on whatever nesting the original RAR used.
        $width = max(3, strlen((string) count($images)));
        foreach (array_values($images) as $i => $abs) {
            $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $name = sprintf('%0' . $width . 'd.%s', $i + 1, $ext);
            $zip->addFile($abs, $name);
        }
        if (!$zip->close()) {
            throw new RuntimeException('Could not finalise the converted .cbz file.');
        }
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
