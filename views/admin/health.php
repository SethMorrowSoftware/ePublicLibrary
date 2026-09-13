<?php
defined('APP_BOOTED') or exit;
/** @var array $missingFiles */
/** @var array $missingCovers */
/** @var array $orphans */
/** @var array $orphanCovers */
/** @var array $totals */
/** @var array $formatCounts */
/** @var array $capabilities */
?>
<div class="admin-page-header">
    <h1>Library health</h1>
    <p class="muted">Inconsistencies between the database and the on-disk storage.</p>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totals['books']) ?></div>
        <div class="stat-label">Books in DB</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totals['disk_files']) ?></div>
        <div class="stat-label">Book files on disk</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totals['covers']) ?></div>
        <div class="stat-label">Cover files on disk</div>
    </div>
</div>

<section class="admin-card health-card">
    <h2>Catalogue by format</h2>
    <ul class="health-list">
        <?php foreach (BookFormat::all() as $f): ?>
            <li>
                <strong><?= e(BookFormat::name($f)) ?></strong>
                <small class="muted"><?= e(number_format($formatCounts[$f] ?? 0)) ?> published</small>
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Server capabilities</h2>
    <ul class="capability-list">
        <li class="<?= $capabilities['pdf_covers'] ? 'ok' : 'warn' ?>">
            <span class="mark" aria-hidden="true"><?= $capabilities['pdf_covers'] ? '✓' : '!' ?></span>
            <span><strong>PDF cover rendering</strong> —
                <?= $capabilities['pdf_covers']
                    ? 'Imagick can rasterise PDFs server-side.'
                    : 'not available; render PDF covers from the Thumbnails page instead.' ?></span>
        </li>
        <li class="<?= $capabilities['rar'] ? 'ok' : 'warn' ?>">
            <span class="mark" aria-hidden="true"><?= $capabilities['rar'] ? '✓' : '!' ?></span>
            <span><strong>CBR conversion</strong> —
                <?= $capabilities['rar']
                    ? 'using ' . e((string) $capabilities['rar_tool']) . '.'
                    : 'no RAR unpacker found; true RAR comics are rejected on upload.' ?></span>
        </li>
    </ul>
</section>

<section class="admin-card health-card">
    <h2>Missing book files <small class="muted"><?= number_format(count($missingFiles)) ?></small></h2>
    <?php if (!$missingFiles): ?>
        <p class="muted">Every book in the database has its file on disk. ✓</p>
    <?php else: ?>
        <p class="muted">These books have a database row but no file in <code>storage/books/</code>.
           Re-upload them or hide / delete the rows from <a href="<?= e(url('admin/books.php')) ?>">Books</a>.</p>
        <ul class="health-list">
            <?php foreach ($missingFiles as $b): ?>
                <li>
                    <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>"><?= e($b['title']) ?></a>
                    <small class="muted">by <?= e($b['author']) ?> · <code><?= e($b['storage_path']) ?></code></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="admin-card health-card">
    <h2>Missing covers <small class="muted"><?= number_format(count($missingCovers)) ?></small></h2>
    <?php if (!$missingCovers): ?>
        <p class="muted">Every book has a cover image. ✓</p>
    <?php else: ?>
        <p class="muted">These books don't have a cover image on disk. Use
           <a href="<?= e(url('admin/thumbnails.php')) ?>">Thumbnails → Build missing only</a> to regenerate.</p>
        <ul class="health-list">
            <?php foreach (array_slice($missingCovers, 0, 30) as $b): ?>
                <li>
                    <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>"><?= e($b['title']) ?></a>
                    <small class="muted">by <?= e($b['author']) ?></small>
                </li>
            <?php endforeach; ?>
            <?php if (count($missingCovers) > 30): ?>
                <li class="muted">… and <?= count($missingCovers) - 30 ?> more.</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="admin-card health-card">
    <h2>Orphan book files <small class="muted"><?= number_format(count($orphans)) ?></small></h2>
    <?php if (!$orphans): ?>
        <p class="muted">Every book file on disk is tracked in the database. ✓</p>
    <?php else: ?>
        <p class="muted">These files are in <code>storage/books/</code> but have no
           database row. Usually they're leftovers from deleted books or
           partial uploads. Safe to delete unless you recognize them.</p>
        <form method="post" action="<?= e(url('admin/health.php')) ?>"
              data-confirm="Permanently delete <?= e((string) count($orphans)) ?> orphan file(s)? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="verb" value="cleanup_orphans">
            <ul class="health-list">
                <?php foreach ($orphans as $o): ?>
                    <li>
                        <label class="checkbox-row">
                            <input type="checkbox" name="orphan[]" value="<?= e($o['rel']) ?>" checked>
                            <span>
                                <code><?= e($o['rel']) ?></code>
                                <small class="muted"><?= e(number_format($o['size'] / 1024, 1)) ?> KB</small>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="submit" class="btn btn-danger">Delete selected orphans</button>
        </form>
    <?php endif; ?>
</section>

<section class="admin-card health-card">
    <h2>Orphan cover files <small class="muted"><?= number_format(count($orphanCovers)) ?></small></h2>
    <?php if (!$orphanCovers): ?>
        <p class="muted">Every cover file is referenced by a book row. ✓</p>
    <?php else: ?>
        <p class="muted">Cover images on disk that no book references. Safe to delete manually from
           <code>assets/covers/</code> if you want to reclaim space.</p>
        <details>
            <summary>Show <?= count($orphanCovers) ?> filenames</summary>
            <ul class="health-list">
                <?php foreach (array_slice($orphanCovers, 0, 50) as $c): ?>
                    <li><code><?= e($c) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</section>
