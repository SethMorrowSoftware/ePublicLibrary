<?php
/**
 * Highlights API.
 *
 *   GET    /api/highlights.php?b={book_uuid}
 *       → list current user's highlights for a book
 *
 *   POST   /api/highlights.php
 *       body: { uuid, cfi_range, text, color?, note?, chapter? }
 *       → create
 *
 *   PATCH  /api/highlights.php?id={id}
 *       body: { color?, note? }
 *       → update note or color
 *
 *   DELETE /api/highlights.php?id={id}
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

if (!is_authed()) {
    json_error('Sign in to manage highlights.', 401);
}
$user   = current_user();
$method = request_method();

if ($method === 'GET') {
    $bookUuid = (string) ($_GET['b'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid)) {
        json_error('Invalid book UUID', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    json_response(HighlightRepository::listForBook((int) $user['id'], (int) $book['id']));
}

// Method override for clients that cannot send PATCH. Checked before the
// POST branch, which would otherwise swallow the request and 400.
$isPatch = $method === 'PATCH'
    || ($method === 'POST' && strtoupper((string) ($_GET['_method'] ?? '')) === 'PATCH');

if ($isPatch) {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid id', 400);
    }
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }
    // Only forward keys the client actually sent — passing a null `note`
    // alongside a colour change would silently erase the note.
    $fields = [];
    if (array_key_exists('note', $payload)) {
        $fields['note'] = $payload['note'] !== null && $payload['note'] !== ''
            ? (string) $payload['note']
            : null;
    }
    if (array_key_exists('color', $payload)) {
        $fields['color'] = (string) $payload['color'];
    }
    if (!$fields) {
        json_error('Nothing to update', 400);
    }
    if (!HighlightRepository::update((int) $user['id'], $id, $fields)) {
        json_error('Highlight not found', 404);
    }
    json_response(['ok' => true]);
}

if ($method === 'POST') {
    csrf_verify_or_abort();
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        json_error('Invalid JSON body', 400);
    }
    $bookUuid = (string) ($payload['uuid'] ?? '');
    $cfiRange = (string) ($payload['cfi_range'] ?? '');
    $text     = (string) ($payload['text'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/i', $bookUuid) || $cfiRange === '' || $text === '') {
        json_error('uuid, cfi_range, and text are required', 400);
    }
    $book = BookRepository::findByUuid($bookUuid);
    if (!$book) {
        json_error('Book not found', 404);
    }
    $id = HighlightRepository::create((int) $user['id'], (int) $book['id'], [
        'cfi_range' => $cfiRange,
        'text'      => mb_substr($text, 0, 8000),
        'color'     => $payload['color']   ?? 'yellow',
        'note'      => $payload['note']    ?? null,
        'chapter'   => $payload['chapter'] ?? null,
    ]);
    AuditLogger::log('highlight.create', 'book', (int) $book['id'], ['highlight_id' => $id]);
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    csrf_verify_or_abort();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('Invalid id', 400);
    }
    $ok = HighlightRepository::delete((int) $user['id'], $id);
    if (!$ok) {
        json_error('Highlight not found', 404);
    }
    AuditLogger::log('highlight.delete', null, $id);
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
