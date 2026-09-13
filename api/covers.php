<?php
/**
 * Save a cover thumbnail rendered client-side from an EPUB's first image.
 *
 *   POST /api/covers.php
 *   body: { uuid, data_url }
 *
 * Admin-only — clients can't unilaterally override the cover. Phase 4 may
 * relax this to allow regular users to suggest covers that admins approve.
 */

define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

// Without this the endpoint answers GET too, and GET skips CSRF by design —
// so the only thing standing between a cross-site request and a cover
// overwrite would be the admin session cookie.
if (request_method() !== 'POST') {
    json_error('Method not allowed', 405);
}
csrf_verify_or_abort();

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    json_error('Invalid JSON body', 400);
}
$uuid    = (string) ($payload['uuid'] ?? '');
$dataUrl = (string) ($payload['data_url'] ?? '');

if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
    json_error('Invalid book UUID', 400);
}

$book = BookRepository::findByUuid($uuid);
if (!$book) {
    json_error('Book not found', 404);
}

$rel = ThumbnailService::saveFromDataUrl($dataUrl, $uuid);
if ($rel === null) {
    json_error('Could not save cover image.', 400);
}

BookRepository::update((int) $book['id'], ['cover_path' => $rel]);
AuditLogger::log('book.cover_updated', 'book', (int) $book['id']);

json_response(['ok' => true, 'cover' => asset($rel)]);
