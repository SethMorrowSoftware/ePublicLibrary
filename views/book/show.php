<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
/** @var array $tags */
/** @var array|null $progress */
/** @var array $reviews */
/** @var array|null $myReview */
/** @var array $distribution */
/** @var array $related */
/** @var array $shelves */
/** @var array|null $user */

$readUrl     = url('read.php?b=' . eurl($book['uuid']));
$downloadUrl = url('api/download.php?b=' . eurl($book['uuid']));
$avg         = (float) $book['avg_rating'];
$reviewCount = (int)   $book['review_count'];
$resumeLabel = $progress && $progress['percentage'] > 0 ? 'Continue reading' : 'Read now';

$format      = BookFormat::normalize($book['format'] ?? null);
$formatBadge = BookFormat::labelForBook($book);
$pageCount   = (int) ($book['page_count'] ?? 0);

$starsFor = static function (float $avg): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $avg >= $i - 0.25 ? '★' : '☆';
    }
    return $out;
};
?>
<div class="content-wrapper book-detail-wrapper">
    <a href="<?= e(url('index.php')) ?>" class="book-back-link">← Back to library</a>

    <article class="book-detail">
        <aside class="book-detail-cover">
            <?php if (!empty($book['cover_path'])): ?>
                <img src="<?= e(asset($book['cover_path'])) ?>" alt="Cover of <?= e($book['title']) ?>">
            <?php else: ?>
                <div class="book-detail-placeholder" aria-hidden="true">
                    <span><?= e(mb_substr($book['title'], 0, 32)) ?></span>
                </div>
            <?php endif; ?>

            <div class="book-detail-actions">
                <a class="btn btn-primary btn-block" href="<?= e($readUrl) ?>"><?= e($resumeLabel) ?></a>
                <a class="btn btn-ghost btn-block" href="<?= e($downloadUrl) ?>" download>
                    Download <?= e(strtoupper(BookFormat::extension($format))) ?>
                </a>

                <?php if ($user && !empty($shelves)): ?>
                    <details class="shelf-menu" data-close-outside>
                        <summary class="btn btn-ghost btn-block shelf-menu-summary">
                            <span>Add to shelf</span>
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="shelf-menu-list" role="menu">
                            <?php foreach ($shelves as $s): ?>
                                <form method="post" action="<?= e(url('book.php?b=' . eurl($book['uuid']))) ?>" class="shelf-menu-row">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="verb" value="shelf_toggle">
                                    <input type="hidden" name="collection_id" value="<?= e((string) $s['id']) ?>">
                                    <button type="submit" role="menuitemcheckbox"
                                            aria-checked="<?= $s['contains_book'] ? 'true' : 'false' ?>"
                                            class="shelf-menu-button <?= $s['contains_book'] ? 'is-on' : '' ?>">
                                        <span class="shelf-check" aria-hidden="true"><?= $s['contains_book'] ? '✓' : '' ?></span>
                                        <span class="shelf-name"><?= e($s['name']) ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                            <a class="shelf-menu-new" href="<?= e(url('collections.php')) ?>">+ Manage shelves</a>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </aside>

        <div class="book-detail-info">
            <h1 class="book-detail-title"><?= e($book['title']) ?></h1>
            <?php if (!empty($book['subtitle'])): ?>
                <h2 class="book-detail-subtitle"><?= e($book['subtitle']) ?></h2>
            <?php endif; ?>
            <p class="book-detail-author">by <strong><?= e($book['author']) ?></strong></p>

            <p class="book-detail-format">
                <span class="badge badge-format format-<?= e($format) ?>"><?= e($formatBadge) ?></span>
                <?php if ($pageCount > 0): ?>
                    <span class="muted"><?= e(number_format($pageCount)) ?> pages</span>
                <?php endif; ?>
                <span class="muted"><?= e(format_bytes((int) $book['file_size'])) ?></span>
            </p>

            <?php if ($reviewCount > 0): ?>
                <a class="book-detail-rating" href="#reviews">
                    <span class="book-stars" aria-hidden="true"><?= e($starsFor($avg)) ?></span>
                    <strong><?= e(number_format($avg, 1)) ?></strong>
                    <span class="muted">(<?= e(number_format($reviewCount)) ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)</span>
                </a>
            <?php else: ?>
                <p class="book-detail-rating muted">No ratings yet — be the first to review.</p>
            <?php endif; ?>

            <?php if ($tags): ?>
                <ul class="book-detail-tags">
                    <?php foreach ($tags as $t): ?>
                        <li><a class="badge badge-tag" href="<?= e(url('genre.php?slug=' . eurl($t['slug']))) ?>"><?= e($t['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($book['description'])): ?>
                <section class="book-detail-description">
                    <h3>About this book</h3>
                    <?php if (!empty($book['description_html'])): ?>
                        <div><?= $book['description_html'] /* sanitized at insert */ ?></div>
                    <?php else: ?>
                        <p><?= nl2br(e($book['description'])) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <dl class="book-detail-meta">
                <?php if ($book['publisher']):       ?><dt>Publisher</dt><dd><?= e($book['publisher']) ?></dd><?php endif; ?>
                <?php if ($book['published_date']):  ?><dt>Published</dt><dd><?= e($book['published_date']) ?></dd><?php endif; ?>
                <?php if ($book['language']):        ?><dt>Language</dt><dd><?= e($book['language']) ?></dd><?php endif; ?>
                <?php if ($book['isbn']):            ?><dt>ISBN</dt><dd><?= e($book['isbn']) ?></dd><?php endif; ?>
                <dt>Format</dt><dd><?= e(BookFormat::name($format)) ?></dd>
                <?php if ($pageCount > 0): ?><dt>Pages</dt><dd><?= e(number_format($pageCount)) ?></dd><?php endif; ?>
                <dt>File size</dt><dd><?= e(format_bytes((int) $book['file_size'])) ?></dd>
                <dt>Added</dt><dd><?= e(date('M j, Y', strtotime($book['created_at']))) ?></dd>
            </dl>
        </div>
    </article>

    <section id="reviews" class="reviews-section">
        <header class="reviews-header">
            <h2>Reviews</h2>
            <?php if ($reviewCount > 0): ?>
                <p class="muted">Average rating <strong><?= e(number_format($avg, 1)) ?></strong>
                                across <?= e(number_format($reviewCount)) ?>
                                review<?= $reviewCount !== 1 ? 's' : '' ?>.</p>
            <?php endif; ?>
        </header>

        <?php if ($reviewCount > 0): ?>
            <div class="reviews-distribution" aria-label="Rating distribution">
                <?php for ($r = 5; $r >= 1; $r--):
                    $c = $distribution[$r] ?? 0;
                    $pct = $reviewCount > 0 ? round(($c / $reviewCount) * 100) : 0;
                ?>
                    <div class="dist-row">
                        <span class="dist-label" aria-hidden="true"><?= e((string) $r) ?>★</span>
                        <span class="dist-bar"><span class="dist-fill" style="width:<?= e((string) $pct) ?>%"></span></span>
                        <span class="dist-count"><?= e(number_format($c)) ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <section class="review-form-section">
                <h3><?= $myReview ? 'Your review' : 'Leave a review' ?></h3>
                <form method="post" action="<?= e(url('book.php?b=' . eurl($book['uuid']))) ?>" class="review-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="verb" value="review_submit">

                    <fieldset class="rating-input">
                        <legend>Your rating</legend>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>"
                                   <?= ($myReview && (int) $myReview['rating'] === $i) ? 'checked' : '' ?> required>
                            <label for="rating-<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                        <?php endfor; ?>
                    </fieldset>

                    <label>
                        <span>Title <small>(optional)</small></span>
                        <input type="text" name="title" maxlength="200" value="<?= e($myReview['title'] ?? '') ?>">
                    </label>
                    <label>
                        <span>Review <small>(optional)</small></span>
                        <textarea name="body" rows="4"><?= e($myReview['body'] ?? '') ?></textarea>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update review' : 'Post review' ?></button>
                        <?php if ($myReview): ?>
                            <button type="submit" form="delete-review-form" class="btn-link review-delete">Delete review</button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($myReview): ?>
                    <form id="delete-review-form" method="post" action="<?= e(url('book.php?b=' . eurl($book['uuid']))) ?>"
                          data-confirm="Delete your review?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="verb" value="review_delete">
                        <input type="hidden" name="review_id" value="<?= e((string) $myReview['id']) ?>">
                    </form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <p class="muted reviews-signin-prompt">
                <a href="<?= e(url('login.php')) ?>">Sign in</a> to leave a review.
            </p>
        <?php endif; ?>

        <?php if (empty($reviews)): ?>
            <p class="muted">No reviews yet.</p>
        <?php else: ?>
            <ul class="review-list">
                <?php foreach ($reviews as $r): ?>
                    <?php $name = $r['display_name'] ?: $r['username']; ?>
                    <li class="review-card">
                        <header class="review-card-header">
                            <span class="review-avatar" aria-hidden="true"><?= e(mb_substr($name, 0, 1)) ?></span>
                            <div>
                                <strong class="review-author"><?= e($name) ?></strong>
                                <div class="review-meta">
                                    <span class="review-stars" aria-label="<?= e((string) $r['rating']) ?> out of 5">
                                        <?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?>
                                    </span>
                                    <time datetime="<?= e($r['created_at']) ?>"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></time>
                                </div>
                            </div>
                        </header>
                        <?php if ($r['title']): ?><h4 class="review-title"><?= e($r['title']) ?></h4><?php endif; ?>
                        <?php if ($r['body']): ?><p class="review-body"><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if (!empty($related)): ?>
        <?php partial('book-rail', [
            'title'     => 'You might also like',
            'books'     => $related,
            'seeAllUrl' => $tags ? url('genre.php?slug=' . eurl($tags[0]['slug'])) : null,
        ]); ?>
    <?php endif; ?>
</div>
