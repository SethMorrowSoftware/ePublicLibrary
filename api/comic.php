<?php
/**
 * Comic page API.
 *
 *   GET /api/comic.php?b={uuid}            → { pages, title, format }
 *   GET /api/comic.php?b={uuid}&page={n}   → the raw image bytes for page n
 *
 * Pages are served individually rather than shipping the whole archive to
 * the browser: comic volumes routinely run to hundreds of megabytes, and a
 * reader that has to download all of it before showing page one is not a
 * reader. ZipArchive seeks straight to the entry, so a page costs one open
 * and one read.
 *
 * Public, like the rest of the reading surface, and rate-limited per IP.
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

$uuid = (string) ($_GET['b'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    json_error('Invalid book UUID', 400);
}

$book = BookRepository::findByUuid($uuid);
if (!$book || $book['status'] !== 'published') {
    json_error('Book not found', 404);
}
if (BookFormat::normalize($book['format'] ?? null) !== BookFormat::CBZ) {
    json_error('This book is not a comic archive', 400);
}

// One page per request means a busy reader makes a lot of them — the ceiling
// is high enough for fast page-turns plus prefetch, low enough to stop a
// scraper walking the whole archive at speed.
$bucket = 'comic:ip:' . (client_ip() ?: 'unknown');
if (!rate_limit_check($bucket, 240, 60)) {
    json_error('Too many requests — slow down.', 429);
}
rate_limit_hit($bucket, 60);

$path = BookFileStorage::resolveForBook($book);
if ($path === null || !is_file($path)) {
    json_error('Comic file is missing', 404);
}

try {
    $archive = new ComicArchive($path);
    $total   = $archive->pageCount();
} catch (Throwable $e) {
    log_error($e);
    json_error('Could not read the comic archive', 500);
}

/* ---- Manifest ---------------------------------------------------------- */

if (!isset($_GET['page'])) {
    if ($total > 0 && (int) ($book['page_count'] ?? 0) !== $total) {
        BookRepository::update((int) $book['id'], ['page_count' => $total]);
    }
    json_response([
        'uuid'   => $book['uuid'],
        'title'  => $book['title'],
        'author' => $book['author'],
        'pages'  => $total,
    ]);
}

/* ---- One page ---------------------------------------------------------- */

$page = (int) $_GET['page'];
if ($page < 1 || $page > $total) {
    json_error('Page out of range', 404);
}

$entry = $archive->page($page);
if ($entry === null) {
    json_error('Page could not be read', 404);
}

// Pages inside a stored archive never change, so let the browser and the
// service worker keep them for a long time. ETag makes revalidation cheap.
$etag = '"' . substr(hash('sha256', $book['uuid'] . ':' . $page . ':' . strlen($entry['data'])), 0, 24) . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=604800, immutable');
    exit;
}

header('Content-Type: ' . $entry['mime']);
header('Content-Length: ' . strlen($entry['data']));
header('Content-Disposition: inline; filename="page-' . $page . '.'
       . pathinfo($entry['name'], PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, max-age=604800, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $entry['data'];
