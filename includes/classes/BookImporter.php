<?php
/**
 * BookImporter — takes a validated file and turns it into a catalogue row.
 *
 * Shared by the admin uploader and the folder importer so both paths agree on
 * metadata extraction, cover generation, slugging, tagging, and the audit
 * trail. Before this existed the two copies had already drifted.
 */

defined('APP_BOOTED') or exit;

class BookImporter
{
    /**
     * Import one already-validated file.
     *
     * @param array  $verdict   Result of BookFileValidator::validate().
     * @param string $fallbackName  Original filename, used when the file
     *                              carries no title of its own.
     * @param array  $opts      move: bool — move the file (upload) or copy it
     *                                       (folder import). Default true.
     *                          uploaded_by: ?int
     *
     * @return array{id: int, uuid: string, title: string, format: string}
     * @throws RuntimeException when the file cannot be stored.
     */
    public static function import(array $verdict, string $fallbackName, array $opts = []): array
    {
        $format  = $verdict['format'];
        $srcPath = $verdict['path'];
        $move    = $opts['move'] ?? true;

        $meta = self::extractMetadata($srcPath, $format);

        $uuid = uuid_v4();
        $ext  = BookFormat::extension($format);
        $relStoragePath = $move
            ? BookFileStorage::moveIntoStore($srcPath, $uuid, $ext)
            : BookFileStorage::copyIntoStore($srcPath, $uuid, $ext);

        // Generate the cover from the file now in its final home, so a failed
        // move cannot leave a cover pointing at nothing.
        $storedPath = BookFileStorage::root() . '/' . $relStoragePath;
        $coverPath  = self::generateCover($storedPath, $format, $uuid, $meta);

        $title  = self::firstNonEmpty($meta['title'], pathinfo($fallbackName, PATHINFO_FILENAME), 'Untitled');
        $author = self::firstNonEmpty($meta['author'], 'Unknown');
        $description = $meta['description'] ?? null;

        // The file is already in storage by this point, so a failed INSERT
        // (most likely two uploads of the same book racing on the file_hash
        // unique key) would strand it there forever. Roll the filesystem back
        // before re-throwing.
        try {
            $bookId = self::insertRow([
                'uuid'             => $uuid,
                'slug'             => BookRepository::makeSlug($title),
                'title'            => mb_substr($title, 0, 500),
                'author'           => mb_substr($author, 0, 500),
                'language'         => $meta['language']  ?? null,
                'publisher'        => $meta['publisher'] ?? null,
                'published_date'   => $meta['published'] ?? null,
                'isbn'             => $meta['isbn']      ?? null,
                'description'      => $description,
                'description_html' => $description !== null ? nl2br(e($description)) : null,
                'storage_path'     => $relStoragePath,
                'cover_path'       => $coverPath,
                'file_size'        => (int) $verdict['size'],
                'file_hash'        => $verdict['hash'],
                'mime_type'        => $verdict['mime'] ?? BookFormat::mime($format),
                'format'           => $format,
                'original_format'  => $verdict['original_format'] ?? $format,
                'page_count'       => $verdict['page_count'] ?? ($meta['page_count'] ?? null),
                'status'           => 'published',
                'uploaded_by'      => $opts['uploaded_by'] ?? null,
            ]);
        } catch (Throwable $e) {
            @unlink($storedPath);
            if ($coverPath !== null) {
                ThumbnailService::delete($uuid);
            }
            throw $e;
        }

        if (!empty($meta['subjects'])) {
            TagRepository::attachToBook($bookId, $meta['subjects'], 'genre');
        }

        return ['id' => $bookId, 'uuid' => $uuid, 'title' => $title, 'format' => $format];
    }

    /** Insert the catalogue row. Split out so import() reads as a sequence. */
    private static function insertRow(array $values): int
    {
        return BookRepository::create($values);
    }

    /**
     * Format-agnostic metadata. Always returns the same shape so callers do
     * not branch on format themselves.
     */
    public static function extractMetadata(string $path, string $format): array
    {
        $base = [
            'title' => null, 'author' => null, 'language' => null,
            'publisher' => null, 'published' => null, 'isbn' => null,
            'description' => null, 'subjects' => [], 'page_count' => null,
            'cover_data' => null,
        ];

        try {
            if ($format === BookFormat::EPUB) {
                $epub = EpubParser::parse($path);
                return array_merge($base, [
                    'title'       => self::nullIfBlank($epub['title'] ?? null, 'Untitled'),
                    'author'      => self::nullIfBlank($epub['author'] ?? null, 'Unknown'),
                    'language'    => $epub['language']    ?? null,
                    'publisher'   => $epub['publisher']   ?? null,
                    'published'   => $epub['published']   ?? null,
                    'isbn'        => $epub['isbn']        ?? null,
                    'description' => $epub['description'] ?? null,
                    'subjects'    => $epub['subjects']    ?? [],
                    'cover_data'  => $epub['cover_data']  ?? null,
                ]);
            }

            if ($format === BookFormat::PDF) {
                $pdf = PdfParser::parse($path);
                return array_merge($base, [
                    'title'       => $pdf['title'],
                    'author'      => $pdf['author'],
                    'published'   => $pdf['published'],
                    'description' => $pdf['description'],
                    'subjects'    => $pdf['subjects'],
                    'page_count'  => $pdf['page_count'],
                ]);
            }

            if ($format === BookFormat::CBZ) {
                $comic = new ComicArchive($path);
                return array_merge($base, [
                    'page_count' => $comic->pageCount(),
                    'cover_data' => $comic->coverBytes(),
                ]);
            }
        } catch (Throwable $e) {
            // Metadata is best-effort: a book with a filename title is far
            // better than a failed import.
            log_error($e);
        }

        return $base;
    }

    /**
     * Save a cover for the book, returning the relative cover_path or null.
     *
     * EPUB and CBZ carry an image we can use directly. PDFs need a
     * rasteriser: Imagick when the host has it, otherwise no cover is stored
     * and the UI falls back to its typographic placeholder (the admin can
     * still generate one from the Thumbnails page, which renders page 1 in
     * the browser).
     */
    public static function generateCover(string $path, string $format, string $uuid, ?array $meta = null): ?string
    {
        try {
            if ($meta === null) {
                $meta = self::extractMetadata($path, $format);
            }
            if (!empty($meta['cover_data'])) {
                return ThumbnailService::saveFromBytes($meta['cover_data'], $uuid);
            }
            if ($format === BookFormat::PDF) {
                return ThumbnailService::saveFromPdfFirstPage($path, $uuid);
            }
        } catch (Throwable $e) {
            log_error($e);
        }
        return null;
    }

    private static function firstNonEmpty(?string ...$values): string
    {
        foreach ($values as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** EpubParser returns its own placeholders; treat those as "absent". */
    private static function nullIfBlank(?string $value, string $placeholder): ?string
    {
        $value = trim((string) $value);
        return ($value === '' || $value === $placeholder) ? null : $value;
    }
}
