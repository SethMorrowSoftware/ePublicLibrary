<?php
/**
 * BookFileStorage — safe absolute-path resolution for files inside the
 * configured books root.
 *
 * Replaces the strpos('..') guard in the legacy code with a realpath-based
 * check that resists Unicode normalization and symlink tricks.
 */

defined('APP_BOOTED') or exit;

class BookFileStorage
{
    /**
     * Absolute path to the books root, with trailing slash stripped.
     * Uses config('storage.books_path').
     */
    public static function root(): string
    {
        $root = (string) config('storage.books_path', '');
        $real = realpath($root);
        if ($real === false) {
            // Try to create it once
            @mkdir($root, 0750, true);
            $real = realpath($root);
        }
        if ($real === false) {
            throw new RuntimeException('Books storage root is not accessible: ' . $root);
        }
        return rtrim($real, '/\\');
    }

    /** Path for the shard directory of a UUID, created if missing. */
    public static function shardPath(string $uuid): string
    {
        $shard = shard_for($uuid);
        $path = self::root() . '/' . $shard;
        if (!is_dir($path)) {
            @mkdir($path, 0750, true);
        }
        return $path;
    }

    /**
     * Default storage path for a book given its UUID and format extension.
     * The extension defaults to epub so pre-existing callers keep working.
     */
    public static function pathForUuid(string $uuid, string $ext = 'epub'): string
    {
        return self::shardPath($uuid) . '/' . $uuid . '.' . self::safeExt($ext);
    }

    /** Relative path stored in books.storage_path. */
    public static function relativePathFor(string $uuid, string $ext = 'epub'): string
    {
        return shard_for($uuid) . '/' . $uuid . '.' . self::safeExt($ext);
    }

    /** Extensions are app-controlled, but never build a path from raw input. */
    private static function safeExt(string $ext): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext) ?? '');
        return $ext !== '' ? $ext : 'epub';
    }

    /**
     * Resolve a book's on-disk path. Validates that the resolved absolute
     * path is inside the books root. Returns null if the file is missing
     * or the path escapes the root.
     */
    public static function resolveForBook(array $book): ?string
    {
        $stored = (string) ($book['storage_path'] ?? '');
        if ($stored === '') {
            return null;
        }
        // Treat as either absolute (already real) or relative to root.
        if ($stored[0] === '/' || preg_match('~^[A-Za-z]:[\\\\/]~', $stored)) {
            $candidate = $stored;
        } else {
            $candidate = self::root() . '/' . ltrim($stored, '/');
        }
        $real = realpath($candidate);
        if ($real === false) {
            return null;
        }
        // Compare against root + separator, otherwise a sibling directory that
        // merely shares a prefix (…/books-public next to …/books) passes.
        $rootReal = self::root();
        if ($real !== $rootReal && strpos($real, $rootReal . DIRECTORY_SEPARATOR) !== 0) {
            return null; // escapes root
        }
        return $real;
    }

    /**
     * Move a temp/quarantined file into the books root under the canonical
     * {shard}/{uuid}.{ext} name. Returns the relative storage path written
     * to books.storage_path.
     */
    public static function moveIntoStore(string $tempPath, string $uuid, string $ext = 'epub'): string
    {
        $dest = self::pathForUuid($uuid, $ext);
        if (!@rename($tempPath, $dest)) {
            // Fall back to copy + unlink (cross-device rename can fail)
            if (!@copy($tempPath, $dest)) {
                throw new RuntimeException('Failed to move uploaded file into storage.');
            }
            @unlink($tempPath);
        }
        @chmod($dest, 0640);
        return self::relativePathFor($uuid, $ext);
    }

    /**
     * Copy a file into the books root, leaving the source in place. Used by
     * the folder importer, which must not destroy the admin's originals.
     */
    public static function copyIntoStore(string $sourcePath, string $uuid, string $ext = 'epub'): string
    {
        $dest = self::pathForUuid($uuid, $ext);
        if (!@copy($sourcePath, $dest)) {
            throw new RuntimeException('Failed to copy the book file into storage.');
        }
        @chmod($dest, 0640);
        return self::relativePathFor($uuid, $ext);
    }

    /** Delete the EPUB file for a book. Idempotent. */
    public static function deleteForBook(array $book): void
    {
        $path = self::resolveForBook($book);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
