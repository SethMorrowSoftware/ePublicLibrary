<?php
/**
 * BookRepository — all books-table SQL.
 *
 * Phase 1 queries the DB directly. The legacy filesystem-scanning approach
 * (recursive iterator + regex OPF parsing on every request) is gone.
 */

defined('APP_BOOTED') or exit;

class BookRepository
{
    /* --------------- single-row queries --------------- */

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUuid(string $uuid): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE uuid = ? LIMIT 1");
        $stmt->execute([$uuid]);
        return $stmt->fetch() ?: null;
    }

    public static function findByHash(string $hash): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /* --------------- listing / search --------------- */

    /**
     * Paginated list with optional search, filters, and sort.
     *
     * Accepted opts (all optional):
     *   page       int
     *   per_page   int (1–100)
     *   search     string — query text
     *   field      'all' | 'title' | 'author' | 'genre' — restrict where to look
     *   tag_slug   string — only books tagged with this slug
     *   language   string — only books in this language code
     *   year_min   int    — only books with published_date YEAR >= this
     *   year_max   int    — only books with published_date YEAR <= this
     *   min_rating float  — only books with avg_rating >= this (and review_count > 0)
     *   format     string — 'epub' | 'pdf' | 'cbz'
     *   sort_by    'title' | 'author' | 'published' | 'created' | 'rating' | 'popular' | 'relevance'
     *   sort_dir   'asc' | 'desc'
     *
     * For 'all'-field searches with 3+ char terms, uses FULLTEXT MATCH ... AGAINST
     * in BOOLEAN MODE. Shorter terms (and other fields) fall back to LIKE.
     *
     * Returns ['items' => [...], 'total' => int, 'page' => int, 'pages' => int, 'per_page' => int].
     */
    public static function paginate(array $opts = []): array
    {
        $page    = max(1, (int) ($opts['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($opts['per_page'] ?? 25)));
        $search  = trim((string) ($opts['search'] ?? ''));
        $field   = (string) ($opts['field'] ?? 'all');
        $sortBy  = (string) ($opts['sort_by'] ?? 'title');
        $sortDir = strtolower((string) ($opts['sort_dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $allowedSort = [
            'title'     => 'title',
            'author'    => 'author',
            'published' => 'published_date',
            'created'   => 'created_at',
            'updated'   => 'updated_at',
            'rating'    => 'avg_rating',
            'popular'   => 'read_count',
        ];
        // `relevance` is special: requires FULLTEXT; falls back to title if unavailable.
        $sortCol = $allowedSort[$sortBy] ?? 'title';

        $where  = [];
        if (empty($opts['include_all_status'])) {
            $where[] = "status = 'published'";
        }
        $params = [];
        $selectScore = '';
        $useFulltext = false;

        // ---- Text search ----
        if ($search !== '') {
            $bool = self::toFulltextBoolean($search);

            if ($field === 'all' && mb_strlen($search) >= 3 && $bool !== '') {
                // FULLTEXT path — leverages ft_books_search index on (title, author, description).
                //
                // The term is bound TWICE under two distinct names on purpose:
                // with PDO::ATTR_EMULATE_PREPARES off, reusing one named
                // placeholder in both the SELECT and the WHERE raises
                // "HY093 Invalid parameter number" and every multi-word
                // search 500s. Two names, one value.
                $selectScore = ", MATCH(title, author, description) AGAINST(:ft_score IN BOOLEAN MODE) AS _score";
                $where[] = "MATCH(title, author, description) AGAINST(:ft_term IN BOOLEAN MODE)";
                $params['ft_term']  = $bool;
                $params['ft_score'] = $bool;
                $useFulltext = true;
            } else {
                $like = '%' . self::escapeLike($search) . '%';
                switch ($field) {
                    case 'title':
                        $where[]      = 'title LIKE :s_title';
                        $params['s_title'] = $like;
                        break;
                    case 'author':
                        $where[]       = 'author LIKE :s_author';
                        $params['s_author'] = $like;
                        break;
                    case 'genre':
                        $where[] = 'id IN (SELECT bt.book_id FROM book_tags bt
                                           JOIN tags t ON t.id = bt.tag_id
                                           WHERE t.name LIKE :s_tag1 OR t.slug LIKE :s_tag2)';
                        $params['s_tag1'] = $like;
                        $params['s_tag2'] = $like;
                        break;
                    case 'all':
                    default:
                        $where[] = '(title LIKE :s_all1 OR author LIKE :s_all2 OR description LIKE :s_all3)';
                        $params['s_all1'] = $like;
                        $params['s_all2'] = $like;
                        $params['s_all3'] = $like;
                        break;
                }
            }
        }

        // ---- Filters ----
        if (!empty($opts['tag_slug'])) {
            $where[] = 'id IN (SELECT bt.book_id FROM book_tags bt
                               JOIN tags t ON t.id = bt.tag_id
                               WHERE t.slug = :f_tag)';
            $params['f_tag'] = (string) $opts['tag_slug'];
        }
        if (!empty($opts['language'])) {
            $where[] = 'language = :f_lang';
            $params['f_lang'] = (string) $opts['language'];
        }
        if (isset($opts['year_min']) && $opts['year_min'] !== '' && (int) $opts['year_min'] > 0) {
            $where[] = "CAST(LEFT(published_date, 4) AS UNSIGNED) >= :f_year_min";
            $params['f_year_min'] = (int) $opts['year_min'];
        }
        if (isset($opts['year_max']) && $opts['year_max'] !== '' && (int) $opts['year_max'] > 0) {
            $where[] = "CAST(LEFT(published_date, 4) AS UNSIGNED) <= :f_year_max";
            $params['f_year_max'] = (int) $opts['year_max'];
        }
        if (isset($opts['min_rating']) && (float) $opts['min_rating'] > 0) {
            $where[] = 'avg_rating >= :f_min_rating AND review_count > 0';
            $params['f_min_rating'] = (float) $opts['min_rating'];
        }
        if (!empty($opts['format']) && BookFormat::isValid((string) $opts['format'])) {
            $where[] = 'format = :f_format';
            $params['f_format'] = (string) $opts['format'];
        }

        $whereSql = $where ? implode(' AND ', $where) : '1';

        // ---- Count ----
        // :ft_score only appears in the SELECT list, so binding it to the
        // COUNT query would itself be an "invalid parameter number".
        $countStmt = db()->prepare("SELECT COUNT(*) FROM books WHERE {$whereSql}");
        foreach ($params as $k => $v) {
            if ($k === 'ft_score') {
                continue;
            }
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        // ---- ORDER BY ----
        $orderBy = "{$sortCol} {$sortDir}, id ASC";
        if ($sortBy === 'relevance') {
            $orderBy = $useFulltext ? "_score DESC, id ASC" : "title ASC, id ASC";
        }

        // ---- List ----
        $listSql = "SELECT *{$selectScore} FROM books WHERE {$whereSql}
                    ORDER BY {$orderBy}
                    LIMIT :pp_limit OFFSET :pp_offset";
        $listStmt = db()->prepare($listSql);
        foreach ($params as $k => $v) {
            $listStmt->bindValue(':' . $k, $v);
        }
        $listStmt->bindValue(':pp_limit',  $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':pp_offset', $offset,  PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'items'    => $listStmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    }

    /** Recently added books (catalog newcomers). */
    public static function recentlyAdded(int $limit = 12): array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE status = 'published'
                              ORDER BY created_at DESC, id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Top-rated books that have meaningful review volume. */
    public static function topRated(int $limit = 12, int $minReviews = 3): array
    {
        $stmt = db()->prepare("SELECT * FROM books
                              WHERE status = 'published' AND review_count >= ?
                              ORDER BY avg_rating DESC, review_count DESC, id ASC LIMIT ?");
        $stmt->bindValue(1, $minReviews, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,      PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Most-read books — uses the read_count counter incremented on read.php load. */
    public static function mostRead(int $limit = 12): array
    {
        $stmt = db()->prepare("SELECT * FROM books WHERE status = 'published'
                              ORDER BY read_count DESC, id ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Books sharing any tag with the given book (lightweight related-books). */
    public static function relatedTo(int $bookId, int $limit = 6): array
    {
        $stmt = db()->prepare("SELECT b.*, COUNT(bt2.tag_id) AS shared_tags
                              FROM book_tags bt1
                              JOIN book_tags bt2 ON bt1.tag_id = bt2.tag_id AND bt2.book_id <> bt1.book_id
                              JOIN books b ON b.id = bt2.book_id
                              WHERE bt1.book_id = ? AND b.status = 'published'
                              GROUP BY b.id
                              ORDER BY shared_tags DESC, b.avg_rating DESC, b.id ASC
                              LIMIT ?");
        $stmt->bindValue(1, $bookId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** How many published books exist in each format (for filter chips). */
    public static function formatCounts(): array
    {
        $rows = db()->query("SELECT format, COUNT(*) AS n FROM books
                             WHERE status = 'published'
                             GROUP BY format")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[BookFormat::normalize($r['format'])] = (int) $r['n'];
        }
        return $out;
    }

    /** Distinct language codes present in the catalog (for filter dropdowns). */
    public static function distinctLanguages(): array
    {
        return db()->query("SELECT DISTINCT language FROM books
                            WHERE status='published' AND language IS NOT NULL AND language <> ''
                            ORDER BY language")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Min/max publication years (for year range UI). */
    public static function yearRange(): array
    {
        $row = db()->query("SELECT MIN(CAST(LEFT(published_date, 4) AS UNSIGNED)) AS y_min,
                                   MAX(CAST(LEFT(published_date, 4) AS UNSIGNED)) AS y_max
                            FROM books
                            WHERE status='published' AND published_date REGEXP '^[0-9]{4}'")
                   ->fetch();
        return [
            'min' => $row && $row['y_min'] ? (int) $row['y_min'] : null,
            'max' => $row && $row['y_max'] ? (int) $row['y_max'] : null,
        ];
    }

    /**
     * Convert a free-form search string into a safe FULLTEXT BOOLEAN MODE query.
     * Each word becomes a prefix-match (+word*); special chars stripped.
     */
    private static function toFulltextBoolean(string $term): string
    {
        $clean = preg_replace('/[+\-<>()~*"@]/u', ' ', $term);
        $words = preg_split('/\s+/u', trim((string) $clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($words as $w) {
            if (mb_strlen($w) >= 2) {
                $parts[] = '+' . $w . '*';
            }
        }
        return $parts ? implode(' ', $parts) : '';
    }

    /**
     * Autocomplete suggestions — titles, authors, genres matching a prefix.
     */
    public static function autocomplete(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = self::escapeLike($term) . '%';
        $suggestions = [];

        $titleStmt = db()->prepare("SELECT DISTINCT title FROM books
                                    WHERE status='published' AND title LIKE ?
                                    ORDER BY title LIMIT ?");
        $titleStmt->bindValue(1, $like);
        $titleStmt->bindValue(2, $limit, PDO::PARAM_INT);
        $titleStmt->execute();
        foreach ($titleStmt->fetchAll() as $r) { $suggestions[] = $r['title']; }

        $authorStmt = db()->prepare("SELECT DISTINCT author FROM books
                                     WHERE status='published' AND author LIKE ?
                                     ORDER BY author LIMIT ?");
        $authorStmt->bindValue(1, $like);
        $authorStmt->bindValue(2, $limit, PDO::PARAM_INT);
        $authorStmt->execute();
        foreach ($authorStmt->fetchAll() as $r) { $suggestions[] = $r['author']; }

        return array_values(array_unique($suggestions));
    }

    /* --------------- mutations --------------- */

    public static function create(array $data): int
    {
        $cols = ['uuid','slug','title','subtitle','author','language','publisher','published_date',
                 'isbn','description','description_html','storage_path','cover_path',
                 'file_size','file_hash','mime_type','format','original_format','page_count',
                 'status','uploaded_by'];
        $values = [];
        foreach ($cols as $c) {
            $values[$c] = $data[$c] ?? null;
        }
        $values['uuid']   = $values['uuid']   ?? uuid_v4();
        $values['status'] = $values['status'] ?? 'published';
        $values['format'] = BookFormat::normalize($values['format'] ?? null);
        $values['original_format'] = $values['original_format'] ?? $values['format'];
        $values['mime_type'] = $values['mime_type'] ?: BookFormat::mime($values['format']);

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO books (' . implode(', ', $cols) . ', created_at, updated_at)
                VALUES (' . $placeholders . ', ?, ?)';
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge(array_values($values), [now_utc(), now_utc()]));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = ['title','subtitle','author','language','publisher','published_date',
                    'isbn','description','description_html','cover_path','status','slug',
                    'page_count'];
        $set = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $set[] = "{$col} = ?";
                $params[] = $fields[$col];
            }
        }
        if ($set === []) {
            return;
        }
        $set[] = "updated_at = ?";
        $params[] = now_utc();
        $params[] = $id;
        $sql = "UPDATE books SET " . implode(', ', $set) . " WHERE id = ?";
        db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        db()->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
    }

    public static function incrementDownloadCount(int $id): void
    {
        db()->prepare("UPDATE books SET download_count = download_count + 1 WHERE id = ?")->execute([$id]);
    }

    public static function incrementReadCount(int $id): void
    {
        db()->prepare("UPDATE books SET read_count = read_count + 1 WHERE id = ?")->execute([$id]);
    }

    /* --------------- helpers --------------- */

    public static function totalCount(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM books")->fetchColumn();
    }

    public static function makeSlug(string $title, ?int $excludeId = null): string
    {
        $base = self::slugify($title);
        if ($base === '') {
            $base = 'book';
        }
        $candidate = $base;
        $i = 2;
        while (self::slugExists($candidate, $excludeId)) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }

    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        if ($excludeId !== null) {
            $stmt = db()->prepare("SELECT 1 FROM books WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = db()->prepare("SELECT 1 FROM books WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetchColumn();
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('~[^\pL\d]+~u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = iconv('utf-8', 'ascii//TRANSLIT//IGNORE', $value);
        $value = preg_replace('~[^-\w]+~', '', $value) ?? '';
        return substr($value, 0, 200);
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
