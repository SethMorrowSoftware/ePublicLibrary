<?php
defined('APP_BOOTED') or exit;
/** @var array $books */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $searchTerm */
/** @var bool $hasFilter */
/** @var string $sortLabel */
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
                · <?= e($sortLabel) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$books): ?>
        <?php partial('library-empty', ['filtered' => $hasSearch || $hasFilter]); ?>
    <?php else: ?>
        <div class="book-grid" role="list">
            <?php foreach ($books as $book): ?>
                <div role="listitem"><?php partial('book-card', ['book' => $book]); ?></div>
            <?php endforeach; ?>
        </div>

        <?php partial('pagination', ['page' => $page, 'pages' => $pages, 'queryParams' => $queryParams]); ?>
    <?php endif; ?>
</div>
