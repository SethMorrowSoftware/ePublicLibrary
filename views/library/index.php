<?php
defined('APP_BOOTED') or exit;
/** @var array $books */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $searchTerm */
/** @var array $queryParams */

$hasSearch = $searchTerm !== '';
?>
<div class="content-wrapper">
    <?php if (is_guest()): ?>
        <div class="guest-banner" role="region" aria-label="Sign in benefits">
            <div class="guest-banner-text">
                <strong>You're browsing as a guest.</strong>
                Sign in to sync your reading progress, bookmarks, and shelves across devices.
            </div>
            <div class="guest-banner-actions">
                <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Sign in</a>
                <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Create account</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h1 class="section-title">
            <?= $hasSearch ? 'Search results' : 'The library' ?>
        </h1>
        <?php if ($total > 0): ?>
            <span class="book-count">
                <?= number_format($total) ?> book<?= $total !== 1 ? 's' : '' ?>
                <?php if ($hasSearch): ?> matching “<?= e($searchTerm) ?>”<?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$books): ?>
        <div class="empty-state">
            <div class="empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h2 class="empty-title">
                <?= $hasSearch ? 'No books match your search' : 'No books yet' ?>
            </h2>
            <p class="empty-description">
                <?= $hasSearch
                    ? 'Try a different search term, or clear filters to see the full library.'
                    : 'The library is empty. An admin can upload EPUB, PDF, or comic files to get started.' ?>
            </p>
            <?php if (!$hasSearch && is_admin()): ?>
                <a class="btn btn-primary" href="<?= e(url('admin/upload.php')) ?>">Upload your first book</a>
            <?php elseif ($hasSearch): ?>
                <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Clear search</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="book-grid" role="list">
            <?php foreach ($books as $book): ?>
                <div role="listitem"><?php partial('book-card', ['book' => $book]); ?></div>
            <?php endforeach; ?>
        </div>

        <?php partial('pagination', ['page' => $page, 'pages' => $pages, 'queryParams' => $queryParams]); ?>
    <?php endif; ?>
</div>
