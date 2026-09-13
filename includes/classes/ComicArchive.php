<?php
/**
 * ComicArchive — read a CBZ (a ZIP of page images) page by page.
 *
 * Comics are served a page at a time by api/comic.php rather than shipped to
 * the browser whole: a 300 MB volume must not have to download before page
 * one appears, and ZipArchive can seek straight to an entry.
 *
 * Page order follows a natural sort of the entry names, so `page2.jpg` comes
 * before `page10.jpg` — plain strcmp gets that wrong, and comic archives are
 * full of unpadded numbering.
 */

defined('APP_BOOTED') or exit;

class ComicArchive
{
    /** Extensions we will hand back to a browser as a comic page. */
    public const PAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    private const EXT_MIME = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'bmp'  => 'image/bmp',
    ];

    private string $path;
    /** @var string[]|null Lazily-built, naturally-sorted list of entry names. */
    private ?array $pages = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Naturally-sorted list of page entry names inside the archive.
     * Throws RuntimeException if the archive cannot be opened.
     *
     * @return string[]
     */
    public function pages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }
        $zip = new ZipArchive();
        $rc  = $zip->open($this->path);
        if ($rc !== true) {
            throw new RuntimeException('Could not open comic archive (zip error ' . $rc . ').');
        }
        try {
            $this->pages = self::collectPages($zip);
        } finally {
            $zip->close();
        }
        return $this->pages;
    }

    public function pageCount(): int
    {
        return count($this->pages());
    }

    /**
     * Raw bytes + MIME for a 1-based page number, or null when out of range.
     *
     * @return array{data: string, mime: string, name: string}|null
     */
    public function page(int $number): ?array
    {
        $pages = $this->pages();
        if ($number < 1 || $number > count($pages)) {
            return null;
        }
        $name = $pages[$number - 1];

        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            return null;
        }
        try {
            $data = $zip->getFromName($name);
        } finally {
            $zip->close();
        }
        if ($data === false || $data === '') {
            return null;
        }
        return ['data' => $data, 'mime' => self::mimeFor($name), 'name' => $name];
    }

    /** Bytes of page 1, used as the cover. Null when the archive has no pages. */
    public function coverBytes(): ?string
    {
        $first = $this->page(1);
        return $first['data'] ?? null;
    }

    /* --------------------------------------------------------------------- */
    /*  Static helpers                                                        */
    /* --------------------------------------------------------------------- */

    public static function mimeFor(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return self::EXT_MIME[$ext] ?? 'application/octet-stream';
    }

    public static function isPageName(string $name): bool
    {
        // Skip directories, macOS resource forks, and Windows thumbnail junk.
        if (substr($name, -1) === '/') {
            return false;
        }
        $basename = basename($name);
        if ($basename === '' || $basename[0] === '.' || stripos($name, '__MACOSX/') === 0) {
            return false;
        }
        if (strcasecmp($basename, 'Thumbs.db') === 0) {
            return false;
        }
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        return in_array($ext, self::PAGE_EXTENSIONS, true);
    }

    /** @return string[] */
    private static function collectPages(ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0, $n = $zip->numFiles; $i < $n; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = (string) $stat['name'];
            if (self::isPageName($name) && (int) $stat['size'] > 0) {
                $names[] = $name;
            }
        }
        // Natural, case-insensitive order: page2.jpg before page10.jpg.
        usort($names, static fn(string $a, string $b): int => strnatcasecmp($a, $b));
        return $names;
    }

    /**
     * Quick page count for a candidate file without constructing the object.
     * Returns 0 when the file is not a readable archive.
     */
    public static function countPagesIn(string $path): int
    {
        try {
            return (new self($path))->pageCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
