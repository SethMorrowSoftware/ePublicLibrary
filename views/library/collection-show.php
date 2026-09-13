<?php
defined('APP_BOOTED') or exit;
/** @var array $shelf */
/** @var array $books */
/** @var bool $isOwner */
/** @var array $owner */
?>
<div class="content-wrapper">
    <a href="<?= e(url($isOwner ? 'collections.php' : 'index.php')) ?>" class="book-back-link">
        ← <?= $isOwner ? 'All shelves' : 'Library' ?>
    </a>

    <header class="page-header">
        <h1 class="shelf-title"><?= e($shelf['name']) ?></h1>
        <p class="muted">
            <?= count($books) ?> book<?= count($books) !== 1 ? 's' : '' ?>
            <?php if (!$isOwner): ?>
                · by <strong><?= e($owner['display_name'] ?: $owner['username']) ?></strong>
            <?php endif; ?>
            <?php if ($shelf['is_public']): ?>
                <span class="badge">Public</span>
            <?php endif; ?>
        </p>
        <?php if ($shelf['description']): ?>
            <p class="shelf-description"><?= e($shelf['description']) ?></p>
        <?php endif; ?>
    </header>

    <?php if (empty($books)): ?>
        <div class="empty-state">
            <h2>This shelf is empty</h2>
            <p>
                <?= $isOwner
                    ? 'Open any book and use the "Add to shelf" button to put it here.'
                    : 'No books to show yet.' ?>
            </p>
            <?php if ($isOwner): ?>
                <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Browse the library</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php foreach ($books as $b): ?>
                <div class="shelf-book-wrapper">
                    <?php partial('book-card', ['book' => $b]); ?>
                    <?php if ($isOwner): ?>
                        <form method="post" action="<?= e(current_url()) ?>" class="shelf-book-remove"
                              data-confirm="Remove &quot;<?= e($b['title']) ?>&quot; from this shelf?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="verb" value="remove_book">
                            <input type="hidden" name="book_id" value="<?= e((string) $b['id']) ?>">
                            <button type="submit" aria-label="Remove from shelf" title="Remove from shelf">×</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
