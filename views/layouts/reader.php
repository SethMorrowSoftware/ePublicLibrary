<?php
defined('APP_BOOTED') or exit;
/** @var string      $__contents */
/** @var string|null $pageTitle */
/** @var array       $book */
/** @var string      $readerKind  'epub' | 'pdf' | 'comic' */
$pageTitle  = $pageTitle ?? ($book['title'] ?? 'Reading');
$readerKind = $readerKind ?? 'epub';

/**
 * Third-party reader engines are loaded from assets/vendor/ when an admin has
 * put them there (see assets/vendor/README.md) and from a CDN otherwise. This
 * block is the only place the app touches an external origin.
 */
$vendor = static function (string $file): ?string {
    return is_file(project_path('assets/vendor/' . $file)) ? asset('vendor/' . $file) : null;
};

$epubJs   = $vendor('epub.min.js');
$jszipJs  = $vendor('jszip.min.js');
$pdfJs    = $vendor('pdf.min.js');
$pdfWorker = $vendor('pdf.worker.min.js');
?><!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <?php partial('head-meta'); ?>
    <meta name="book-uuid" content="<?= e($book['uuid']) ?>">
    <meta name="book-title" content="<?= e($book['title']) ?>">
    <meta name="book-author" content="<?= e($book['author']) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/reader.css')) ?>">

<?php if ($readerKind === 'epub'): ?>
    <script src="<?= e($jszipJs ?: 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js') ?>"
            <?= $jszipJs ? '' : 'crossorigin="anonymous"' ?> defer></script>
    <script src="<?= e($epubJs ?: 'https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js') ?>"
            <?= $epubJs ? '' : 'crossorigin="anonymous"' ?> defer></script>
<?php elseif ($readerKind === 'pdf'): ?>
    <script src="<?= e($pdfJs ?: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js') ?>"
            <?= $pdfJs ? '' : 'crossorigin="anonymous"' ?> defer></script>
    <?php
    /**
     * pdf.js needs a worker, and a Worker script must be same-origin — no CSP
     * directive can relax that. When the library is vendored we point at the
     * local worker; otherwise we load a one-line same-origin shim that
     * importScripts() the CDN copy, which *is* allowed and keeps
     * `worker-src 'self'` intact.
     */
    ?>
    <meta name="pdf-worker-src" content="<?= e($pdfWorker ?: asset('js/pdf-worker-cdn.js')) ?>">
<?php endif; ?>
</head>
<body class="reader-page reader-<?= e($readerKind) ?>">
    <?= $__contents ?>
<?php if ($readerKind === 'epub'): ?>
    <script type="module" src="<?= e(asset('js/reader.js')) ?>"></script>
<?php elseif ($readerKind === 'pdf'): ?>
    <script type="module" src="<?= e(asset('js/pdf-reader.js')) ?>"></script>
<?php else: ?>
    <script type="module" src="<?= e(asset('js/comic-reader.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
