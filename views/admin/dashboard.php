<?php
defined('APP_BOOTED') or exit;
/** @var array $stats */
/** @var array $audit */
?>
<div class="admin-page-header">
    <h1>Dashboard</h1>
    <p class="muted">Quick overview of your library.</p>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['books']) ?></div>
        <div class="stat-label">Books</div>
        <a href="<?= e(url('admin/books.php')) ?>" class="stat-link">Manage →</a>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['users']) ?></div>
        <div class="stat-label">Users</div>
        <a href="<?= e(url('admin/users.php')) ?>" class="stat-link">Manage →</a>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= e(format_bytes((int) $stats['storage_bytes'])) ?></div>
        <div class="stat-label">Book files on disk</div>
    </div>
</div>

<div class="admin-row">
    <section class="admin-card">
        <h2>Recent uploads</h2>
        <?php if (!$stats['recent_uploads']): ?>
            <p class="muted">No books uploaded yet.
                <a href="<?= e(url('admin/upload.php')) ?>">Upload your first</a>.</p>
        <?php else: ?>
            <ul class="admin-list">
                <?php foreach ($stats['recent_uploads'] as $b): ?>
                    <li>
                        <a href="<?= e(url('admin/books.php?action=edit&id=' . $b['id'])) ?>"><?= e($b['title']) ?></a>
                        <small class="muted"><?= e(date('M j', strtotime($b['created_at']))) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <h2>Recent activity</h2>
        <?php if (!$audit): ?>
            <p class="muted">No activity yet.</p>
        <?php else: ?>
            <ul class="admin-audit-list">
                <?php foreach (array_slice($audit, 0, 10) as $a): ?>
                    <li>
                        <code><?= e($a['event']) ?></code>
                        <span class="muted">
                            <?= e($a['username'] ?? 'system') ?> ·
                            <?= e(date('M j g:ia', strtotime($a['created_at']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?= e(url('admin/audit.php')) ?>">View full audit log →</a></p>
        <?php endif; ?>
    </section>
</div>
