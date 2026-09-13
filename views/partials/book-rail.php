<?php
defined('APP_BOOTED') or exit;
/** @var string $title */
/** @var array $books */
/** @var string|null $seeAllUrl */
/** @var string|null $emptyText */
$railId = 'rail-' . substr(md5($title), 0, 10);
?>
<?php if (!empty($books) || !empty($emptyText)): ?>
<section class="book-rail" aria-labelledby="<?= e($railId) ?>-title">
    <header class="book-rail-header">
        <h2 id="<?= e($railId) ?>-title" class="book-rail-title"><?= e($title) ?></h2>
        <?php if (!empty($seeAllUrl) && !empty($books)): ?>
            <a href="<?= e($seeAllUrl) ?>" class="book-rail-more">See all →</a>
        <?php endif; ?>
    </header>

    <?php if (empty($books)): ?>
        <p class="book-rail-empty"><?= e($emptyText) ?></p>
    <?php else: ?>
        <div class="book-rail-track" role="list">
            <?php foreach ($books as $b): ?>
                <div role="listitem" class="book-rail-item">
                    <?php partial('book-card', ['book' => $b]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
