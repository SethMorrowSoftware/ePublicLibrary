<?php
/**
 * HighlightRepository — per-user, per-book selections with optional notes.
 *
 * Backed by the `highlights` table (migration 0007). One row per highlight;
 * the cfi_range encodes the start and end of the selection so epub.js can
 * re-render it on later visits.
 */

defined('APP_BOOTED') or exit;

class HighlightRepository
{
    public const ALLOWED_COLORS = ['yellow', 'green', 'blue', 'pink', 'orange'];

    /* --------------- single-row queries --------------- */

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM highlights WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- listing --------------- */

    public static function listForBook(int $userId, int $bookId, int $limit = 500, int $offset = 0): array
    {
        $stmt = db()->prepare("SELECT * FROM highlights
                              WHERE user_id = ? AND book_id = ?
                              ORDER BY created_at ASC
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $bookId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit,  PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countForBook(int $userId, int $bookId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM highlights WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$userId, $bookId]);
        return (int) $stmt->fetchColumn();
    }

    /* --------------- mutations --------------- */

    public static function create(int $userId, int $bookId, array $data): int
    {
        $color = in_array($data['color'] ?? '', self::ALLOWED_COLORS, true)
            ? $data['color'] : 'yellow';
        $stmt = db()->prepare("INSERT INTO highlights
            (user_id, book_id, cfi_range, text, note, color, chapter, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $bookId,
            (string) ($data['cfi_range'] ?? ''),
            (string) ($data['text'] ?? ''),
            $data['note']    ?? null,
            $color,
            $data['chapter'] ?? null,
            now_utc(),
            now_utc(),
        ]);
        return (int) db()->lastInsertId();
    }

    /** Update note and/or color. Returns true if any row changed. */
    public static function update(int $userId, int $id, array $fields): bool
    {
        $h = self::findById($id);
        if (!$h || (int) $h['user_id'] !== $userId) return false;

        $set = []; $params = [];
        if (array_key_exists('note', $fields)) {
            $set[] = 'note = ?';
            $params[] = $fields['note'] !== null ? (string) $fields['note'] : null;
        }
        if (!empty($fields['color']) && in_array($fields['color'], self::ALLOWED_COLORS, true)) {
            $set[] = 'color = ?';
            $params[] = $fields['color'];
        }
        if (!$set) return false;
        $set[] = 'updated_at = ?';
        $params[] = now_utc();
        $params[] = $id;
        $stmt = db()->prepare('UPDATE highlights SET ' . implode(', ', $set) . ' WHERE id = ?');
        $stmt->execute($params);
        // Deliberately not rowCount() > 0: MySQL reports 0 affected rows when
        // the submitted values match what is already stored, and re-saving an
        // unchanged note is a success, not a 404.
        return true;
    }

    public static function delete(int $userId, int $id): bool
    {
        $stmt = db()->prepare("DELETE FROM highlights WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
