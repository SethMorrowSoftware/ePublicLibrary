<?php
/**
 * Admin: regenerate cover thumbnails for every format.
 *
 * EPUB covers come from the OPF package, comic covers from page 1 of the
 * archive, PDF covers from Imagick when the host has it. PDFs on hosts
 * without Imagick are listed separately so the page can render them in the
 * browser with pdf.js and POST the result to api/covers.php.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$processed = [];

if (is_post()) {
    csrf_verify_or_abort();
    $verb = (string) ($_POST['verb'] ?? '');

    if ($verb === 'rebuild_missing' || $verb === 'rebuild_all') {
        @set_time_limit(0);

        $where = $verb === 'rebuild_missing' ? "WHERE cover_path IS NULL OR cover_path = ''" : '';
        $rows = db()->query("SELECT id FROM books {$where} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $id) {
            $book = BookRepository::findById((int) $id);
            if (!$book) {
                continue;
            }
            $format = BookFormat::normalize($book['format'] ?? null);
            $path = BookFileStorage::resolveForBook($book);

            if ($path === null) {
                $processed[] = ['title' => $book['title'], 'ok' => false, 'reason' => 'book file is missing'];
                continue;
            }

            $rel = BookImporter::generateCover($path, $format, $book['uuid']);
            if ($rel !== null) {
                BookRepository::update((int) $book['id'], ['cover_path' => $rel]);
                $processed[] = ['title' => $book['title'], 'ok' => true, 'reason' => 'cover saved'];
            } else {
                $processed[] = [
                    'title'  => $book['title'],
                    'ok'     => false,
                    'reason' => $format === BookFormat::PDF
                        ? 'no server-side PDF renderer — use "Render PDF covers here" below'
                        : 'no usable cover image inside the file',
                ];
            }
        }
        AuditLogger::log('thumbnails.rebuild', null, null, ['verb' => $verb, 'count' => count($processed)]);
    }
}

$stats = [
    'total'   => (int) db()->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'missing' => (int) db()->query("SELECT COUNT(*) FROM books WHERE cover_path IS NULL OR cover_path = ''")->fetchColumn(),
    'covers'  => count(glob(ThumbnailService::coversDir() . '/*.jpg') ?: []),
];

// PDFs still without a cover — the browser can render these even when the
// server cannot.
$pdfPending = db()->query("SELECT uuid, title FROM books
                           WHERE format = 'pdf' AND (cover_path IS NULL OR cover_path = '')
                           ORDER BY title LIMIT 200")->fetchAll();

render('admin/thumbnails', [
    'pageTitle'   => 'Thumbnails',
    'activeNav'   => 'thumbnails',
    'stats'       => $stats,
    'processed'   => $processed,
    'pdfPending'  => $pdfPending,
    'serverPdf'   => ThumbnailService::canRasterizePdf(),
], 'admin');
