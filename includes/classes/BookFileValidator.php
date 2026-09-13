<?php
/**
 * BookFileValidator — one entry point for accepting an uploaded or imported
 * book file, whatever the format.
 *
 * Responsibilities, in order:
 *   1. Reject unsupported extensions and oversized files.
 *   2. Identify the real container from its magic bytes, not the extension —
 *      a `.cbr` is very often a plain ZIP, and a `.epub` is sometimes a PDF
 *      someone renamed.
 *   3. Run the format-specific structural check (EpubValidator for EPUB,
 *      a page sweep for comics, a header/EOF check for PDF).
 *   4. Normalise CBR to CBZ by transcoding, so downstream code and the
 *      browser only ever see ZIP comics.
 *
 * Returns a verdict array rather than throwing, because callers show the
 * message next to the offending filename in a batch upload:
 *
 *   ['ok' => true,  'format' => 'cbz', 'path' => '/tmp/...cbz',
 *    'converted' => true, 'original_format' => 'cbr',
 *    'size' => 12345, 'page_count' => 42]
 *   ['ok' => false, 'error' => 'Human-readable reason.']
 */

defined('APP_BOOTED') or exit;

class BookFileValidator
{
    /**
     * @param string      $path         Absolute path to the candidate file.
     * @param string|null $originalName Client-supplied filename, if any.
     */
    public static function validate(string $path, ?string $originalName = null): array
    {
        $name = $originalName ?? basename($path);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, BookFormat::UPLOAD_EXTENSIONS, true)) {
            return self::fail(
                'Unsupported file type “.' . ($ext ?: '?') . '”. Accepted: '
                . implode(', ', array_map(static fn($e) => '.' . $e, BookFormat::UPLOAD_EXTENSIONS)) . '.'
            );
        }

        if (!is_file($path)) {
            return self::fail('Uploaded file is missing.');
        }
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return self::fail('Uploaded file is empty.');
        }
        $maxMb = (int) config('storage.max_upload_mb', 100);
        if ($maxMb > 0 && $size > $maxMb * 1024 * 1024) {
            return self::fail("File is " . format_bytes((int) $size) . ", over the {$maxMb} MB upload limit.");
        }

        $container = self::detectContainer($path);
        if ($container === null) {
            return self::fail('File is not a ZIP or PDF container — it may be corrupt.');
        }

        // Hash the file as supplied. A converted CBR is rezipped on import and
        // ZIP output is not byte-reproducible, so hashing the result would make
        // every re-upload of the same comic look like a new book.
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            return self::fail('Could not compute a checksum for the file.');
        }

        // Route on the real container, cross-checked against the extension.
        if ($ext === 'pdf') {
            if ($container !== 'pdf') {
                return self::fail('File is named .pdf but is not a PDF.');
            }
            return self::validatePdf($path, (int) $size, $hash);
        }

        if ($container === 'pdf') {
            return self::fail('File is a PDF but is named .' . $ext . ' — rename it to .pdf and try again.');
        }

        if ($ext === 'epub') {
            $verdict = EpubValidator::validate($path, $name);
            if (!$verdict['ok']) {
                return $verdict;
            }
            return [
                'ok'              => true,
                'format'          => BookFormat::EPUB,
                'original_format' => 'epub',
                'path'            => $path,
                'hash'            => $hash,
                'converted'       => false,
                'size'            => (int) $size,
                'page_count'      => null,
                'mime'            => BookFormat::mime(BookFormat::EPUB),
            ];
        }

        // .cbz / .cbr
        return self::validateComic($path, $ext, $container, (int) $size, $hash);
    }

    /* --------------------------------------------------------------------- */

    /** 'zip' | 'rar' | 'pdf' | null, decided purely by magic bytes. */
    public static function detectContainer(string $path): ?string
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $head = fread($fh, 1024);
        fclose($fh);
        if (!is_string($head) || $head === '') {
            return null;
        }
        if (strncmp($head, "PK\x03\x04", 4) === 0
            || strncmp($head, "PK\x05\x06", 4) === 0
            || strncmp($head, "PK\x07\x08", 4) === 0) {
            return 'zip';
        }
        if (strncmp($head, "Rar!\x1A\x07", 6) === 0) {
            return 'rar';
        }
        // A conforming PDF starts with %PDF-, but files with leading junk are
        // common enough that Acrobat tolerates it within the first 1 KB.
        if (strpos($head, '%PDF-') !== false) {
            return 'pdf';
        }
        return null;
    }

    private static function validatePdf(string $path, int $size, string $hash): array
    {
        // A PDF must carry a trailer; look for %%EOF near the end rather than
        // trusting the header alone.
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return self::fail('Could not read the PDF.');
        }
        fseek($fh, max(0, $size - 2048));
        $tail = (string) fread($fh, 2048);
        fclose($fh);
        if (strpos($tail, '%%EOF') === false) {
            return self::fail('PDF appears truncated (no end-of-file marker).');
        }

        if (self::isEncryptedPdf($path)) {
            return self::fail('This PDF is password-protected or encrypted, so it cannot be displayed in the reader.');
        }

        $pages = PdfParser::pageCountOf($path);

        return [
            'ok'              => true,
            'format'          => BookFormat::PDF,
            'original_format' => 'pdf',
            'path'            => $path,
            'hash'            => $hash,
            'converted'       => false,
            'size'            => $size,
            'page_count'      => $pages,
            'mime'            => BookFormat::mime(BookFormat::PDF),
        ];
    }

    private static function validateComic(string $path, string $ext, string $container, int $size, string $hash): array
    {
        $workingPath = $path;
        $converted   = false;

        if ($container === 'rar') {
            if (!RarTranscoder::isSupported()) {
                return self::fail(
                    'This is a RAR archive and the server has no RAR unpacker installed ('
                    . RarTranscoder::supportedToolNames()
                    . '). Convert it to .cbz and upload again.'
                );
            }
            try {
                $workingPath = RarTranscoder::toCbz($path, storage_path('uploads_tmp'));
                $converted   = true;
            } catch (Throwable $e) {
                return self::fail('Could not convert the RAR comic: ' . $e->getMessage());
            }
        }

        $pages = ComicArchive::countPagesIn($workingPath);
        if ($pages < 1) {
            if ($converted) {
                @unlink($workingPath);
            }
            return self::fail('No page images were found inside the comic archive.');
        }

        $finalSize = $converted ? (int) (@filesize($workingPath) ?: $size) : $size;

        return [
            'ok'              => true,
            'format'          => BookFormat::CBZ,
            'original_format' => $ext,
            'path'            => $workingPath,
            'hash'            => $hash,
            'converted'       => $converted,
            'size'            => $finalSize,
            'page_count'      => $pages,
            'mime'            => BookFormat::mime(BookFormat::CBZ),
        ];
    }

    /**
     * Cheap encryption probe: an encrypted PDF names /Encrypt in its trailer.
     * We only reject when we are confident, since a false positive blocks a
     * perfectly readable upload.
     */
    private static function isEncryptedPdf(string $path): bool
    {
        $size = (int) (@filesize($path) ?: 0);
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        fseek($fh, max(0, $size - 4096));
        $tail = (string) fread($fh, 4096);
        fclose($fh);
        return (bool) preg_match('~trailer[^>]{0,2000}?/Encrypt\s~s', $tail)
            || (bool) preg_match('~/Encrypt\s+\d+\s+\d+\s+R~', $tail);
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
