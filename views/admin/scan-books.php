<?php
defined('APP_BOOTED') or exit;
/** @var string $scanDir */
/** @var string $defaultDir */
/** @var bool   $dirExists */
/** @var array  $extensions */
/** @var array  $results */
/** @var array  $summary */

$statusBadge = ['imported' => 'published', 'skipped' => 'hidden', 'failed' => 'removed'];
$statusLabel = ['imported' => 'imported', 'skipped' => 'skipped', 'failed' => 'failed'];
?>
<div class="admin-page-header">
    <h1>Import from a folder</h1>
    <p class="muted">Bulk-import books that are already on the server's disk —
       the legacy <code>books/</code> directory, or any other folder inside the
       installation. Files are <strong>copied</strong> into storage, so your
       originals stay where they are. Re-running is safe: anything already in
       the library is skipped by checksum.</p>
</div>

<form method="post" action="<?= e(url('admin/scan-books.php')) ?>" class="form admin-form">
    <?= csrf_field() ?>
    <label for="import-dir">Folder to scan
        <input type="text" id="import-dir" name="dir" value="<?= e($scanDir) ?>"
               spellcheck="false" autocapitalize="off" autocomplete="off">
        <small>Must be inside the installation directory. Subfolders are scanned too.
               Looks for: <?= e(implode(', ', array_map(static fn($x) => '.' . $x, $extensions))) ?>.</small>
    </label>

    <?php if (!$dirExists): ?>
        <div class="flash flash-info">
            <code><?= e($scanDir) ?></code> does not exist yet. Create it and drop your
            files in, or point the field above at another folder.
        </div>
    <?php endif; ?>

    <div class="upload-actions">
        <button type="submit" class="btn btn-primary" <?= $dirExists ? '' : 'disabled' ?>>Start import</button>
        <a href="<?= e(url('admin/upload.php')) ?>" class="btn btn-ghost">Upload files instead</a>
    </div>
</form>

<?php if ($results): ?>
    <h2 class="results-heading">
        Results
        <small class="muted">
            <?= number_format($summary['imported']) ?> imported ·
            <?= number_format($summary['skipped']) ?> skipped ·
            <?= number_format($summary['failed']) ?> failed
        </small>
    </h2>
    <table class="admin-table">
        <thead><tr><th>File</th><th>Result</th><th>Note</th></tr></thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><code title="<?= e($r['path']) ?>"><?= e(basename($r['path'])) ?></code></td>
                    <td><span class="badge badge-<?= e($statusBadge[$r['status']]) ?>"><?= e($statusLabel[$r['status']]) ?></span></td>
                    <td><?= e($r['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
