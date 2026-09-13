<?php
defined('APP_BOOTED') or exit;
/** @var string $q */
/** @var string $tagSlug */
/** @var string $language */
/** @var int $yearMin */
/** @var int $yearMax */
/** @var float $minRating */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var bool $hasQuery */
/** @var array $result */
/** @var array $allGenres */
/** @var array $allLangs */
/** @var array $yearRange */
/** @var string $format */
/** @var array $formatCounts */
?>
<div class="content-wrapper search-wrapper">
    <header class="page-header">
        <h1>Advanced search</h1>
        <p class="muted">Combine text search with filters to find exactly what you're after.</p>
    </header>

    <div class="search-layout">
        <form method="get" action="<?= e(url('search.php')) ?>" class="search-filters">
            <label class="filter-row">
                <span>Search text</span>
                <input type="search" name="q" value="<?= e($q) ?>" autofocus
                       placeholder="title, author, description…">
            </label>

            <label class="filter-row">
                <span>Genre</span>
                <select name="tag">
                    <option value="">Any</option>
                    <?php foreach ($allGenres as $g): ?>
                        <option value="<?= e($g['slug']) ?>" <?= $tagSlug === $g['slug'] ? 'selected' : '' ?>>
                            <?= e($g['name']) ?> (<?= e((string) $g['book_count']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="filter-row">
                <span>Language</span>
                <select name="language">
                    <option value="">Any</option>
                    <?php foreach ($allLangs as $l): ?>
                        <option value="<?= e((string) $l) ?>" <?= $language === $l ? 'selected' : '' ?>><?= e((string) $l) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="filter-row filter-year">
                <legend>Published year</legend>
                <input type="number" name="year_min" value="<?= $yearMin ?: '' ?>"
                       min="<?= e((string) ($yearRange['min'] ?? 1)) ?>" max="<?= e((string) ($yearRange['max'] ?? 2100)) ?>"
                       placeholder="from" aria-label="Year from">
                <span aria-hidden="true">–</span>
                <input type="number" name="year_max" value="<?= $yearMax ?: '' ?>"
                       min="<?= e((string) ($yearRange['min'] ?? 1)) ?>" max="<?= e((string) ($yearRange['max'] ?? 2100)) ?>"
                       placeholder="to" aria-label="Year to">
            </fieldset>

            <label class="filter-row">
                <span>Minimum rating</span>
                <select name="min_rating">
                    <option value="0" <?= $minRating <= 0 ? 'selected' : '' ?>>Any</option>
                    <?php foreach ([3, 3.5, 4, 4.5] as $r): ?>
                        <option value="<?= e((string) $r) ?>" <?= abs($minRating - $r) < 0.01 ? 'selected' : '' ?>>
                            <?= e((string) $r) ?>★ and up
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="filter-row filter-format">
                <legend>Format</legend>
                <div class="format-chips">
                    <label class="format-chip">
                        <input type="radio" name="format" value="" <?= $format === '' ? 'checked' : '' ?>>
                        <span>Any</span>
                    </label>
                    <?php foreach (BookFormat::all() as $f): ?>
                        <label class="format-chip">
                            <input type="radio" name="format" value="<?= e($f) ?>" <?= $format === $f ? 'checked' : '' ?>>
                            <span><?= e(BookFormat::label($f)) ?>
                                <small>(<?= e((string) ($formatCounts[$f] ?? 0)) ?>)</small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <label class="filter-row">
                <span>Sort by</span>
                <select name="sort">
                    <?php foreach ([
                        'relevance' => 'Relevance',
                        'title'     => 'Title',
                        'author'    => 'Author',
                        'published' => 'Published date',
                        'created'   => 'Recently added',
                        'rating'    => 'Average rating',
                        'popular'   => 'Most read',
                    ] as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= $sortBy === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <input type="hidden" name="order" value="<?= e($sortDir) ?>">

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="<?= e(url('search.php')) ?>" class="btn btn-ghost">Clear</a>
            </div>
        </form>

        <section class="search-results">
            <?php if (!$hasQuery): ?>
                <div class="empty-state search-prompt">
                    <h2>Set some filters to start</h2>
                    <p>Choose a genre, language, year range, or type a search term — or all of the above.</p>
                </div>
            <?php elseif (empty($result['items'])): ?>
                <div class="empty-state">
                    <h2>No matches</h2>
                    <p>Try loosening the filters or shortening the search term.</p>
                </div>
            <?php else: ?>
                <header class="search-results-header">
                    <h2><?= number_format($result['total']) ?> result<?= $result['total'] !== 1 ? 's' : '' ?></h2>
                </header>
                <div class="book-grid">
                    <?php foreach ($result['items'] as $b): ?>
                        <?php partial('book-card', ['book' => $b]); ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($result['pages'] > 1): ?>
                    <?php
                    $qp = [
                        'q'         => $q ?: null,
                        'tag'       => $tagSlug ?: null,
                        'language'  => $language ?: null,
                        'year_min'  => $yearMin ?: null,
                        'year_max'  => $yearMax ?: null,
                        'min_rating'=> $minRating ?: null,
                        'sort'      => $sortBy ?: null,
                        'order'     => $sortDir ?: null,
                    ];
                    ?>
                    <nav class="pagination" aria-label="Search pagination">
                        <?php for ($i = max(1, $result['page'] - 2); $i <= min($result['pages'], $result['page'] + 2); $i++):
                            $qp['page'] = $i;
                            $link = url('search.php?' . http_build_query(array_filter($qp))); ?>
                            <?php if ($i === $result['page']): ?>
                                <span aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= e($link) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
