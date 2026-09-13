<?php
/**
 * Stream a book file to the requester, in whatever format it is stored.
 *
 *   GET /api/download.php?b={uuid}            → forces download (attachment)
 *   GET /api/download.php?b={uuid}&stream=1   → inline, used by the readers
 *                                               (epub.js and pdf.js both
 *                                               range-request this URL)
 *
 * Public by default. Rate-limited per IP to deter scraping.
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

$uuid = (string) ($_GET['b'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    abort(404);
}

$book = BookRepository::findByUuid($uuid);
if (!$book || $book['status'] !== 'published') {
    abort(404);
}

// Rate limit per IP — generous default
$ipBucket = 'download:ip:' . (client_ip() ?: 'unknown');
if (!rate_limit_check($ipBucket, 60, 60)) {
    abort(429, 'Too many download requests. Please slow down.');
}
rate_limit_hit($ipBucket, 60);

$path = BookFileStorage::resolveForBook($book);
if ($path === null || !is_file($path)) {
    abort(404, 'Book file is missing.');
}

$stream = !empty($_GET['stream']);
$size = filesize($path) ?: 0;

// Extension and content type follow the stored format, not a hardcoded
// "epub" — a PDF that downloads as book.epub is unopenable.
$format = BookFormat::normalize($book['format'] ?? null);
$stem = preg_replace('/[^A-Za-z0-9._-]+/', '_', $book['title'] . ' - ' . $book['author']);
$stem = trim((string) $stem, '_.') ?: 'book';
$filename = mb_substr($stem, 0, 180) . '.' . BookFormat::extension($format);

if (!$stream) {
    BookRepository::incrementDownloadCount((int) $book['id']);
    AuditLogger::log('book.download', 'book', (int) $book['id']);
}

header('Content-Type: ' . ($book['mime_type'] ?: BookFormat::mime($format)));
header('Content-Length: ' . $size);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
if ($stream) {
    header('Content-Disposition: inline');
    header('Cache-Control: private, max-age=3600');
} else {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
}

// Range support — important for epub.js partial fetches
$start = 0;
$end   = $size - 1;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] !== '' ? (int) $m[1] : 0;
    $end   = $m[2] !== '' ? (int) $m[2] : $size - 1;
    if ($start > $end || $end >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header('Content-Range: bytes */' . $size);
        exit;
    }
    header('HTTP/1.1 206 Partial Content');
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
    header('Content-Length: ' . ($end - $start + 1));
}

$fh = fopen($path, 'rb');
if ($fh === false) {
    abort(500, 'Could not open book file.');
}
// Streaming a multi-hundred-MB comic must not be killed by the default time
// limit, and must stop the moment the client goes away.
@set_time_limit(0);
ignore_user_abort(false);
while (ob_get_level() > 0) {
    ob_end_flush();
}

fseek($fh, $start);
$bufSize = 128 * 1024;
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fh) && connection_status() === CONNECTION_NORMAL) {
    $chunk = fread($fh, (int) min($bufSize, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($fh);
exit;
