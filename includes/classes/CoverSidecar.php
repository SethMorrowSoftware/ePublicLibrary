<?php
/**
 * CoverSidecar — finds cover art that lives beside a book on disk rather than
 * inside it.
 *
 * The folder importer copies files out of an admin-managed directory, and
 * such directories usually carry their own covers:
 *
 *   - `Title.jpg` next to `Title.epub`, as in most hand-organised libraries;
 *   - `cover.jpg` (or `folder.jpg`) in a folder holding a single book, which
 *     is how Calibre, Kavita and Komga lay things out;
 *   - the v7.x proof-of-concept's `books/covers/<md5(relative path)>.jpg`,
 *     written by generate_thumbnails.php and hand-replaced through
 *     thumbnail_admin.php, plus the underscore naming that preceded it.
 *
 * For many books those files are the only cover there is — the EPUB carries
 * an SVG cover, or no cover metadata at all, or the admin swapped in a better
 * image — so importing the book file alone silently loses them.
 */

defined('APP_BOOTED') or exit;

class CoverSidecar
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Generic cover names, honoured only when a folder holds a single book. */
    private const FOLDER_COVER_NAMES = ['cover', 'folder'];

    /** @var array<string, array{files: array<string, string>, books: int}> */
    private static array $dirCache = [];

    /**
     * Find the cover image for a book file, if one exists beside it.
     *
     * @param string $bookPath Absolute path of the book file.
     *
     * @return array{path: string, label: string}|null  The image's absolute
     *         path and a short description for the import report.
     */
    public static function find(string $bookPath): ?array
    {
        $dir  = dirname($bookPath);
        $stem = pathinfo($bookPath, PATHINFO_FILENAME);

        // 1. Same name as the book, any image extension.
        $hit = self::imageNamed($dir, $stem);
        if ($hit !== null) {
            return ['path' => $hit, 'label' => basename($hit)];
        }

        // 2. cover.jpg in a folder that holds exactly one book. With several
        //    books in the folder there is no telling which one it belongs to.
        if (self::listing($dir)['books'] === 1) {
            foreach (self::FOLDER_COVER_NAMES as $name) {
                $hit = self::imageNamed($dir, $name);
                if ($hit !== null) {
                    return ['path' => $hit, 'label' => basename($hit)];
                }
            }
        }

        // 3. The legacy covers/ folder.
        return self::legacy($bookPath);
    }

    /**
     * The v7.x layout: a `covers/` folder at the library root holding
     * `<md5(path relative to that root)>.jpg`. Walk up from the book's own
     * folder rather than trusting the scan root, so pointing the importer at
     * a sub-folder of the old library still finds its covers.
     */
    private static function legacy(string $bookPath): ?array
    {
        $projectRoot = realpath(project_path()) ?: project_path();
        $book = realpath($bookPath) ?: $bookPath;

        for ($base = dirname($book); ; $base = dirname($base)) {
            $coversDir = $base . '/covers';
            if (is_dir($coversDir)) {
                $rel = str_replace('\\', '/', substr($book, strlen($base) + 1));
                foreach (self::legacyNames($rel, basename($base)) as $name) {
                    $candidate = $coversDir . '/' . $name;
                    if (self::isImage($candidate)) {
                        return ['path' => $candidate, 'label' => 'covers/' . $name];
                    }
                }
            }
            if ($base === $projectRoot || dirname($base) === $base) {
                break;
            }
        }
        return null;
    }

    /**
     * Every filename the old code could have used for a book at $rel
     * (relative to the folder that holds covers/), most likely first.
     *
     * @return string[]
     */
    private static function legacyNames(string $rel, string $rootName): array
    {
        $names = [md5($rel) . '.jpg'];

        // index.php derived the relative path with str_replace('books/', '', …),
        // which also removed that string wherever else it occurred — so
        // books/ebooks/x.epub was hashed as "ex.epub". Cover that spelling too.
        $mangled = str_replace($rootName . '/', '', $rootName . '/' . $rel);
        if ($mangled !== $rel) {
            $names[] = md5($mangled) . '.jpg';
        }

        // The scheme before md5: separators to underscores, .epub to .jpg.
        if (preg_match('/\.epub$/i', $rel)) {
            $names[] = preg_replace('/\.epub$/i', '.jpg', str_replace('/', '_', $rel));
        }

        return $names;
    }

    /** `$stem.$ext` in $dir for any image extension, matched case-insensitively. */
    private static function imageNamed(string $dir, string $stem): ?string
    {
        $files = self::listing($dir)['files'];
        foreach (self::IMAGE_EXTENSIONS as $ext) {
            $actual = $files[strtolower($stem . '.' . $ext)] ?? null;
            if ($actual !== null && self::isImage($dir . '/' . $actual)) {
                return $dir . '/' . $actual;
            }
        }
        return null;
    }

    /**
     * One scandir() per folder, shared by every book in it: a lower-cased
     * name → real name map, plus how many book files the folder holds.
     *
     * @return array{files: array<string, string>, books: int}
     */
    private static function listing(string $dir): array
    {
        if (!isset(self::$dirCache[$dir])) {
            $files = [];
            $books = 0;
            foreach (@scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || !is_file($dir . '/' . $name)) {
                    continue;
                }
                $files[strtolower($name)] = $name;
                if (in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), BookFormat::UPLOAD_EXTENSIONS, true)) {
                    $books++;
                }
            }
            self::$dirCache[$dir] = ['files' => $files, 'books' => $books];
        }
        return self::$dirCache[$dir];
    }

    /** A readable, non-empty file that PHP recognises as an image. */
    private static function isImage(string $path): bool
    {
        return is_file($path) && (int) @filesize($path) > 0 && @getimagesize($path) !== false;
    }
}
