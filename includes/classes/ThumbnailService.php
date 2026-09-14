<?php
/**
 * ThumbnailService — generate, save, and serve book cover thumbnails.
 *
 * Covers live in {storage.covers_path}/{book_uuid}.jpg.
 * Sources: the image embedded in an EPUB or comic (via BookImporter), page 1
 * of a PDF, an image file beside an imported book, or a data URL rendered in
 * the admin's browser. Whatever the source, it is resized to a max width
 * with PHP GD.
 */

defined('APP_BOOTED') or exit;

class ThumbnailService
{
    public const TARGET_WIDTH    = 400;   // px
    public const TARGET_QUALITY  = 85;    // JPEG quality (0-100)

    /** Absolute path to the covers directory, created if missing. */
    public static function coversDir(): string
    {
        $dir = (string) config('storage.covers_path', '');
        if ($dir === '') {
            $dir = project_path('assets/covers');
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return rtrim($dir, '/\\');
    }

    /** Filesystem path for a UUID's cover. */
    public static function pathFor(string $uuid): string
    {
        return self::coversDir() . '/' . $uuid . '.jpg';
    }

    /**
     * Web-relative path (under assets/) used as books.cover_path.
     * Returns 'covers/{uuid}.jpg' which combines with asset() in templates.
     */
    public static function relPathFor(string $uuid): string
    {
        return 'covers/' . $uuid . '.jpg';
    }

    /**
     * Generate and save a cover from an EPUB. Returns the relative cover_path
     * (suitable for books.cover_path), or null if no usable cover was found.
     */
    public static function generateFromEpub(string $epubPath, string $bookUuid): ?string
    {
        try {
            $meta = EpubParser::parse($epubPath);
        } catch (Throwable $e) {
            log_error($e);
            return null;
        }
        if (empty($meta['cover_data'])) {
            return null;
        }
        return self::saveFromBytes($meta['cover_data'], $bookUuid);
    }

    /**
     * Save raw image bytes as the cover for a UUID. Resizes to TARGET_WIDTH.
     * Returns the relative path or null on failure.
     */
    public static function saveFromBytes(string $bytes, string $bookUuid): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            // Without GD nothing can be resized or converted, but a JPEG can
            // at least be stored as it is rather than dropped.
            if (strncmp($bytes, "\xFF\xD8\xFF", 3) !== 0) {
                return null;
            }
            $dest = self::pathFor($bookUuid);
            if (@file_put_contents($dest, $bytes) === false) {
                return null;
            }
            @chmod($dest, 0644);
            return self::relPathFor($bookUuid);
        }
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }
        // resize() either returns $img untouched or frees it and returns a new
        // handle, so $resized is the only handle still alive afterwards.
        $resized = self::resize($img);
        $dest = self::pathFor($bookUuid);
        $ok = imagejpeg($resized, $dest, self::TARGET_QUALITY);
        imagedestroy($resized);
        if (!$ok) {
            @unlink($dest);
            return null;
        }
        @chmod($dest, 0644);
        return self::relPathFor($bookUuid);
    }

    /**
     * Save an image file on disk as the cover for a UUID — a sidecar found
     * beside a book by the folder importer, or a legacy thumbnail. Returns
     * the relative path or null on failure.
     */
    public static function saveFromFile(string $file, string $bookUuid): ?string
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $bytes = @file_get_contents($file);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        return self::saveFromBytes($bytes, $bookUuid);
    }

    /** True when the book row points at a cover that exists on disk. */
    public static function hasCover(array $book): bool
    {
        $rel = (string) ($book['cover_path'] ?? '');
        return $rel !== '' && is_file(self::coversDir() . '/' . basename($rel));
    }

    /**
     * Rasterise page 1 of a PDF into a cover.
     *
     * Needs Imagick (which needs Ghostscript for PDFs); plenty of shared
     * hosts have neither, so this returns null rather than failing the
     * import. The Thumbnails admin page can then generate the cover in the
     * browser with pdf.js and POST it to api/covers.php.
     */
    public static function saveFromPdfFirstPage(string $pdfPath, string $bookUuid): ?string
    {
        if (!class_exists('Imagick')) {
            return null;
        }
        try {
            $im = new Imagick();
            // Set the rasterisation density BEFORE reading, or Imagick renders
            // at 72 dpi and the cover comes out soft.
            $im->setResolution(150, 150);
            $im->readImage($pdfPath . '[0]');
            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();
            $im->setImageFormat('jpeg');
            $bytes = $im->getImageBlob();
            $im->clear();
            $im->destroy();
        } catch (Throwable $e) {
            log_error($e);
            return null;
        }
        return $bytes !== '' ? self::saveFromBytes($bytes, $bookUuid) : null;
    }

    /** True when this host can rasterise PDF covers server-side. */
    public static function canRasterizePdf(): bool
    {
        if (!class_exists('Imagick')) {
            return false;
        }
        try {
            return in_array('PDF', array_map('strtoupper', Imagick::queryFormats('PDF')), true);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Save image from a base64 data URL (canvas.toDataURL output) — used by
     * the client-side cover-cache fallback.
     */
    public static function saveFromDataUrl(string $dataUrl, string $bookUuid): ?string
    {
        if (!preg_match('~^data:image/(jpe?g|png|webp);base64,(.+)$~i', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false) {
            return null;
        }
        return self::saveFromBytes($bytes, $bookUuid);
    }

    private static function resize($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= self::TARGET_WIDTH) {
            return $img;
        }
        $newW = self::TARGET_WIDTH;
        $newH = (int) round($h * ($newW / $w));
        $dst = imagecreatetruecolor($newW, $newH);
        // Preserve transparency if PNG/GIF originals
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        return $dst;
    }

    /** Delete a cover. Idempotent. */
    public static function delete(string $uuid): void
    {
        $path = self::pathFor($uuid);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
