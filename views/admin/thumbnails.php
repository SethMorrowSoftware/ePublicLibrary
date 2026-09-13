<?php
defined('APP_BOOTED') or exit;
/** @var array $stats */
/** @var array $processed */
/** @var array $pdfPending */
/** @var bool  $serverPdf */
?>
<div class="admin-page-header">
    <h1>Thumbnails</h1>
    <p class="muted">Cover images come from the EPUB package, the first page of a
       comic archive, or page 1 of a PDF. Rebuild them here after importing books
       or changing the cover source.</p>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['total']) ?></div><div class="stat-label">Books</div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['covers']) ?></div><div class="stat-label">Covers on disk</div></div>
    <div class="stat-card"><div class="stat-value"><?= number_format($stats['missing']) ?></div><div class="stat-label">Missing cover</div></div>
</div>

<form method="post" action="<?= e(url('admin/thumbnails.php')) ?>" class="form admin-form">
    <?= csrf_field() ?>
    <div class="form-actions">
        <button type="submit" name="verb" value="rebuild_missing" class="btn btn-primary">Build missing only</button>
        <button type="submit" name="verb" value="rebuild_all" class="btn btn-ghost"
                data-confirm="Re-extract covers for ALL books? This can take a while.">Rebuild all</button>
    </div>
</form>

<?php
// Same local-then-CDN rule the reader layout uses, so an admin who has
// vendored pdf.js is not sent to a third party (and it works offline).
$pdfLib    = is_file(project_path('assets/vendor/pdf.min.js'))
    ? asset('vendor/pdf.min.js')
    : 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
$pdfWorker = is_file(project_path('assets/vendor/pdf.worker.min.js'))
    ? asset('vendor/pdf.worker.min.js')
    : asset('js/pdf-worker-cdn.js');
?>
<?php if ($pdfPending): ?>
    <section class="admin-card pdf-cover-tool" id="pdf-cover-tool"
             data-covers-url="<?= e(url('api/covers.php')) ?>"
             data-download-url="<?= e(url('api/download.php')) ?>"
             data-worker-src="<?= e($pdfWorker) ?>"
             data-pending="<?= e(json_encode(array_map(
                 static fn(array $b): array => ['uuid' => $b['uuid'], 'title' => $b['title']],
                 $pdfPending
             ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
        <h2>PDF covers</h2>
        <p class="muted">
            <?php if ($serverPdf): ?>
                <?= number_format(count($pdfPending)) ?> PDF<?= count($pdfPending) === 1 ? '' : 's' ?>
                still have no cover — the server renderer could not read them. Your browser
                can try instead.
            <?php else: ?>
                This server has no PDF rasteriser (Imagick with Ghostscript), so PDF covers
                cannot be produced server-side. Your browser can render them here and upload
                the results — <?= number_format(count($pdfPending)) ?> waiting.
            <?php endif; ?>
        </p>
        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="pdf-cover-start">Render PDF covers here</button>
            <span class="muted" id="pdf-cover-status" role="status"></span>
        </div>
        <ul class="admin-list" id="pdf-cover-log"></ul>
    </section>
<?php endif; ?>

<?php if ($processed): ?>
    <h2 class="results-heading">Last run</h2>
    <table class="admin-table">
        <thead><tr><th>Book</th><th>Result</th><th>Note</th></tr></thead>
        <tbody>
            <?php foreach ($processed as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><span class="badge badge-<?= $p['ok'] ? 'published' : 'removed' ?>"><?= $p['ok'] ? 'ok' : 'skip' ?></span></td>
                    <td><?= e($p['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($pdfPending): ?>
    <script src="<?= e($pdfLib) ?>" <?= str_starts_with($pdfLib, 'http') ? 'crossorigin="anonymous"' : '' ?> defer></script>
    <script type="module" src="<?= e(asset('js/admin/pdf-covers.js')) ?>"></script>
<?php endif; ?>
