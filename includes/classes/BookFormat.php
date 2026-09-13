<?php
/**
 * BookFormat — the single source of truth about the file types the library
 * can hold.
 *
 * Everything that needs to map between an extension, a MIME type, a stored
 * `books.format` value, a download filename, or a reader implementation asks
 * this class instead of hard-coding "epub" somewhere new.
 *
 * CBR is deliberately absent from the canonical list: a RAR comic is
 * transcoded to CBZ on import (BookFileValidator), because no browser can
 * unpack RAR and no shared host reliably ships a RAR extension. The original
 * extension survives in `books.original_format` for display.
 */

defined('APP_BOOTED') or exit;

class BookFormat
{
    public const EPUB = 'epub';
    public const PDF  = 'pdf';
    public const CBZ  = 'cbz';

    /** Canonical formats, keyed by the value stored in `books.format`. */
    private const MAP = [
        self::EPUB => [
            'label'      => 'EPUB',
            'name'       => 'EPUB ebook',
            'ext'        => 'epub',
            'mime'       => 'application/epub+zip',
            'reader'     => 'epub',
            'paginated'  => false,
        ],
        self::PDF => [
            'label'      => 'PDF',
            'name'       => 'PDF document',
            'ext'        => 'pdf',
            'mime'       => 'application/pdf',
            'reader'     => 'pdf',
            'paginated'  => true,
        ],
        self::CBZ => [
            'label'      => 'Comic',
            'name'       => 'Comic archive',
            'ext'        => 'cbz',
            'mime'       => 'application/vnd.comicbook+zip',
            'reader'     => 'comic',
            'paginated'  => true,
        ],
    ];

    /** Upload extensions accepted from admins, including the ones we convert. */
    public const UPLOAD_EXTENSIONS = ['epub', 'pdf', 'cbz', 'cbr'];

    /** Extensions that arrive as something else and get normalised on import. */
    private const ALIASES = [
        'cbr' => self::CBZ,   // transcoded to CBZ
        'cbz' => self::CBZ,
    ];

    public static function all(): array
    {
        return array_keys(self::MAP);
    }

    public static function isValid(?string $format): bool
    {
        return $format !== null && isset(self::MAP[$format]);
    }

    /** Normalise anything (including NULL rows from before migration 0015). */
    public static function normalize(?string $format): string
    {
        $format = strtolower(trim((string) $format));
        if (isset(self::MAP[$format])) {
            return $format;
        }
        return self::ALIASES[$format] ?? self::EPUB;
    }

    /** The format a given upload extension resolves to, or null if unsupported. */
    public static function fromExtension(string $ext): ?string
    {
        $ext = strtolower(ltrim($ext, '.'));
        if (isset(self::MAP[$ext])) {
            return $ext;
        }
        return self::ALIASES[$ext] ?? null;
    }

    private static function meta(?string $format): array
    {
        return self::MAP[self::normalize($format)];
    }

    /** Short badge text: "EPUB", "PDF", "Comic". */
    public static function label(?string $format): string
    {
        return self::meta($format)['label'];
    }

    /** Long human name: "PDF document". */
    public static function name(?string $format): string
    {
        return self::meta($format)['name'];
    }

    /** Canonical on-disk / download extension, no leading dot. */
    public static function extension(?string $format): string
    {
        return self::meta($format)['ext'];
    }

    public static function mime(?string $format): string
    {
        return self::meta($format)['mime'];
    }

    /** Which reader front-end renders this format: 'epub' | 'pdf' | 'comic'. */
    public static function reader(?string $format): string
    {
        return self::meta($format)['reader'];
    }

    /**
     * True when the format is addressed by page number rather than by EPUB
     * CFI — these store progress as the locator "page:N".
     */
    public static function isPaginated(?string $format): bool
    {
        return self::meta($format)['paginated'];
    }

    /** Build the page locator these readers persist in reading_progress.cfi. */
    public static function pageLocator(int $page): string
    {
        return 'page:' . max(1, $page);
    }

    /** Parse "page:12" back to 12. Returns null for CFIs and junk. */
    public static function parsePageLocator(?string $locator): ?int
    {
        if ($locator !== null && preg_match('/^page:(\d{1,7})$/', trim($locator), $m)) {
            return max(1, (int) $m[1]);
        }
        return null;
    }

    /** The `accept` attribute for the admin upload input. */
    public static function acceptAttribute(): string
    {
        return implode(',', array_map(static fn(string $e): string => '.' . $e, self::UPLOAD_EXTENSIONS));
    }

    /** Format badge/label for a whole book row (handles legacy NULLs). */
    public static function labelForBook(array $book): string
    {
        $original = strtolower((string) ($book['original_format'] ?? ''));
        if ($original === 'cbr') {
            return 'CBR';
        }
        return self::label($book['format'] ?? null);
    }
}
