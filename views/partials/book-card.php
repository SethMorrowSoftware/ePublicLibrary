<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
$uuid   = $book['uuid'];
$title  = $book['title'] ?? 'Untitled';
$author = $book['author'] ?? 'Unknown';
$cover  = !empty($book['cover_path']) ? asset($book['cover_path']) : null;
$format = BookFormat::normalize($book['format'] ?? null);
$badge  = BookFormat::labelForBook($book);

$readUrl     = url('read.php?b=' . eurl($uuid));
$detailUrl   = url('book.php?b=' . eurl($uuid));
$downloadUrl = url('api/download.php?b=' . eurl($uuid));
?>
<article class="book-card" tabindex="0"
         data-book-uuid="<?= e($uuid) ?>"
         data-read-url="<?= e($readUrl) ?>"
         data-format="<?= e($format) ?>"
         aria-label="<?= e($title) ?> by <?= e($author) ?> (<?= e($badge) ?>)">
    <a class="book-cover-wrapper" href="<?= e($readUrl) ?>" tabindex="-1">
        <div class="book-cover" <?= $cover ? 'style="background-image:url(\'' . e($cover) . '\')"' : '' ?>>
            <?php if (!$cover): ?>
                <div class="book-cover-placeholder" aria-hidden="true">
                    <span class="placeholder-title"><?= e(mb_substr($title, 0, 24)) ?></span>
                    <span class="placeholder-author"><?= e(mb_substr($author, 0, 32)) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <span class="book-format-badge format-<?= e($format) ?>"><?= e($badge) ?></span>
        <?php if (!empty($book['page_count'])): ?>
            <span class="book-page-badge"><?= e(number_format((int) $book['page_count'])) ?> pp</span>
        <?php endif; ?>
        <div class="book-overlay" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span>Read</span>
        </div>
    </a>
    <div class="book-info">
        <h3 class="book-title" title="<?= e($title) ?>"><?= e($title) ?></h3>
        <p class="book-author"><?= e($author) ?></p>
        <div class="book-meta">
            <a href="<?= e($detailUrl) ?>" class="book-details-link">Details</a>
            <a href="<?= e($downloadUrl) ?>" class="book-download"
               download title="Download the <?= e(BookFormat::name($format)) ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span><?= e(strtoupper(BookFormat::extension($format))) ?></span>
            </a>
        </div>
    </div>
</article>
