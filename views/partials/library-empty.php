<?php
defined('APP_BOOTED') or exit;
/**
 * Empty state for the library grid, shared by the home page and the list.
 *
 * @var bool|null $filtered  true when a search or filter matched nothing;
 *                           false/absent when the library itself is empty.
 */
$filtered = !empty($filtered);
?>
<div class="empty-state">
    <div class="empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
    </div>
    <h2 class="empty-title">
        <?= $filtered ? 'No books match' : 'No books yet' ?>
    </h2>
    <p class="empty-description">
        <?= $filtered
            ? 'Try a different search term, or clear the filters to see the full library.'
            : 'The library is empty. An admin can upload EPUB, PDF, or comic files, or import a folder of books already on the server.' ?>
    </p>
    <?php if ($filtered): ?>
        <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Show the full library</a>
    <?php elseif (is_admin()): ?>
        <div class="empty-actions">
            <a class="btn btn-primary" href="<?= e(url('admin/upload.php')) ?>">Upload your first book</a>
            <a class="btn btn-ghost" href="<?= e(url('admin/scan-books.php')) ?>">Import a folder</a>
        </div>
    <?php endif; ?>
</div>
