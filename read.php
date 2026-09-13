<?php
/**
 * Reader page. Dispatches to the right front-end for the book's format:
 *
 *   epub → epub.js reflowable reader   (views/reader/epub)
 *   pdf  → pdf.js page reader          (views/reader/pdf)
 *   cbz  → comic page reader           (views/reader/comic)
 *
 * URL: /read.php?b={book_uuid}
 *
 * Public: guests can read. Reading progress and bookmarks sync to the server
 * only when a user is logged in; otherwise they fall back to localStorage.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$uuid = (string) ($_GET['b'] ?? '');
if ($uuid === '' || !preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    abort(404, 'Book not found.');
}

$book = BookRepository::findByUuid($uuid);
if (!$book || $book['status'] !== 'published') {
    abort(404, 'Book not found.');
}

BookRepository::incrementReadCount((int) $book['id']);

$format = BookFormat::normalize($book['format'] ?? null);
$reader = BookFormat::reader($format);

$user = current_user();
$progress = null;
$bookmarks = [];
if ($user) {
    $progress  = ProgressRepository::get((int) $user['id'], (int) $book['id']);
    $bookmarks = BookmarkRepository::listForBook((int) $user['id'], (int) $book['id']);
}

$vars = [
    'pageTitle'  => $book['title'],
    'book'       => $book,
    'format'     => $format,
    'readerKind' => $reader,
    'progress'   => $progress,
    'bookmarks'  => $bookmarks,
    'isGuest'    => $user === null,
];

if ($reader === 'pdf' || $reader === 'comic') {
    // Page readers need a total to resolve "page:N" into a percentage. It is
    // recorded at import; recompute and cache it for rows that predate that
    // (or where the parse came up empty).
    $vars['pageCount'] = resolve_page_count($book, $format);
    $vars['startPage'] = BookFormat::parsePageLocator($progress['cfi'] ?? null) ?? 1;
}

render('reader/' . $reader, $vars, 'reader');


/**
 * Total pages for a paginated format, filling in and persisting the value
 * when the catalogue row does not have it yet.
 */
function resolve_page_count(array $book, string $format): int
{
    $known = (int) ($book['page_count'] ?? 0);
    if ($known > 0) {
        return $known;
    }
    $path = BookFileStorage::resolveForBook($book);
    if ($path === null) {
        return 0;
    }
    try {
        $count = $format === BookFormat::CBZ
            ? ComicArchive::countPagesIn($path)
            : (int) (PdfParser::pageCountOf($path) ?? 0);
    } catch (Throwable $e) {
        log_error($e);
        return 0;
    }
    if ($count > 0) {
        BookRepository::update((int) $book['id'], ['page_count' => $count]);
    }
    return $count;
}
