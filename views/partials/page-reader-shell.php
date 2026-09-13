<?php
defined('APP_BOOTED') or exit;
/**
 * Shared chrome for the two page-based readers (PDF and comic).
 *
 * Both formats are read the same way — you are on page N of M, you go
 * forward and back, you zoom — so they share one shell and one JS core
 * (assets/js/page-reader/core.js). The format-specific part is only how a
 * page is turned into pixels, which each entry module supplies.
 *
 * Vars:
 *   $book, $progress, $bookmarks, $isGuest, $pageCount, $startPage
 *   $kind        'pdf' | 'comic'
 *   $extraTools  optional HTML string for format-specific toolbar buttons
 */
/** @var array $book */
/** @var array|null $progress */
/** @var array $bookmarks */
/** @var bool $isGuest */
/** @var int $pageCount */
/** @var int $startPage */
/** @var string $kind */

$sourceUrl = $kind === 'comic'
    ? url('api/comic.php?b=' . eurl($book['uuid']))
    : url('api/download.php?b=' . eurl($book['uuid']) . '&stream=1');

$startPage = max(1, min($startPage, max(1, $pageCount)));
$percent   = $pageCount > 0 ? round(($startPage / $pageCount) * 100, 1) : 0.0;
?>
<div id="reader"
     class="reader-shell page-reader page-reader-<?= e($kind) ?>"
     data-kind="<?= e($kind) ?>"
     data-book-uuid="<?= e($book['uuid']) ?>"
     data-source-url="<?= e($sourceUrl) ?>"
     data-progress-url="<?= e(url('api/progress.php')) ?>"
     data-bookmarks-url="<?= e(url('api/bookmarks.php')) ?>"
     data-page-count="<?= e((string) $pageCount) ?>"
     data-start-page="<?= e((string) $startPage) ?>"
     data-is-guest="<?= $isGuest ? '1' : '0' ?>">

    <header class="reader-header" role="banner">
        <a href="<?= e(url('book.php?b=' . eurl($book['uuid']))) ?>" class="reader-back"
           aria-label="Back to book details" title="Back to book details">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div class="reader-title">
            <h1 class="reader-book-title" title="<?= e($book['title']) ?>"><?= e($book['title']) ?></h1>
            <p class="reader-book-author"><?= e($book['author']) ?></p>
        </div>
        <div class="reader-controls" role="toolbar" aria-label="Reader controls">
            <button type="button" id="btn-pages" class="reader-btn" aria-label="Go to page" title="Pages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button type="button" id="btn-bookmarks" class="reader-btn" aria-label="Bookmarks" title="Bookmarks">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </button>
            <button type="button" id="btn-bookmark" class="reader-btn" aria-label="Bookmark this page" title="Bookmark this page">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            <button type="button" id="btn-zoom-out" class="reader-btn" aria-label="Zoom out" title="Zoom out">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM7 10h6"/></svg>
            </button>
            <button type="button" id="btn-zoom-in" class="reader-btn" aria-label="Zoom in" title="Zoom in">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6"/></svg>
            </button>
            <button type="button" id="btn-immersive" class="reader-btn" aria-label="Immersive mode" title="Immersive mode (Esc to exit)" aria-pressed="false">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
            </button>
            <button type="button" id="btn-settings" class="reader-btn" aria-label="Reader settings" title="Reader settings">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
        </div>
    </header>

    <div class="reader-viewport page-viewport" id="reader-viewport">
        <button type="button" class="reader-hotspot reader-hotspot-prev" aria-label="Previous page" data-direction="prev"></button>
        <div class="page-scroll" id="page-scroll" tabindex="0">
            <div class="page-stage" id="page-stage" aria-live="polite"></div>
        </div>
        <button type="button" class="reader-hotspot reader-hotspot-next" aria-label="Next page" data-direction="next"></button>

        <div class="page-loading" id="page-loading" hidden>
            <span class="page-spinner" aria-hidden="true"></span>
            <span class="page-loading-text">Loading…</span>
        </div>
        <div class="page-error" id="page-error" role="alert" hidden></div>
    </div>

    <div class="reader-progress-bar page-progress" role="group" aria-label="Page navigation">
        <label class="visually-hidden" for="page-slider">Page</label>
        <input type="range" id="page-slider" class="page-slider"
               min="1" max="<?= e((string) max(1, $pageCount)) ?>" step="1"
               value="<?= e((string) $startPage) ?>"
               aria-valuetext="Page <?= e((string) $startPage) ?> of <?= e((string) $pageCount) ?>">
        <div class="reader-progress-info">
            <span id="page-indicator">Page <?= e((string) $startPage) ?> of <?= e((string) $pageCount) ?></span>
            <span id="progress-percent"><?= e(number_format($percent, 1)) ?>%</span>
        </div>
    </div>

    <aside id="panel-pages" class="reader-panel" role="dialog" aria-labelledby="panel-pages-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-pages-title">Pages</h2>
            <button type="button" class="reader-panel-close" data-close="panel-pages" aria-label="Close">×</button>
        </header>
        <div class="reader-panel-content">
            <form class="page-jump" id="page-jump-form">
                <label for="page-jump-input">Go to page</label>
                <input type="number" id="page-jump-input" min="1" max="<?= e((string) max(1, $pageCount)) ?>"
                       value="<?= e((string) $startPage) ?>" inputmode="numeric">
                <button type="submit" class="btn btn-primary">Go</button>
            </form>
        </div>
        <ul class="reader-panel-list page-thumb-list" id="page-thumb-list"></ul>
    </aside>

    <aside id="panel-bookmarks" class="reader-panel" role="dialog" aria-labelledby="panel-bookmarks-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-bookmarks-title">Bookmarks</h2>
            <button type="button" class="reader-panel-close" data-close="panel-bookmarks" aria-label="Close">×</button>
        </header>
        <ul class="reader-panel-list" id="bookmarks-list">
            <?php if (!$bookmarks): ?>
                <li class="reader-panel-empty">No bookmarks yet. Use the + button to mark this page.</li>
            <?php else: ?>
                <?php foreach ($bookmarks as $b): ?>
                    <li data-cfi="<?= e($b['cfi']) ?>" data-id="<?= e((string) $b['id']) ?>">
                        <button type="button" class="bookmark-jump"><?= e($b['label'] ?: $b['chapter'] ?: $b['cfi']) ?></button>
                        <small><?= e(date('M j, Y g:ia', strtotime($b['created_at']))) ?></small>
                        <button type="button" class="bookmark-delete" aria-label="Delete bookmark">×</button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </aside>

    <aside id="panel-settings" class="reader-panel" role="dialog" aria-labelledby="panel-settings-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-settings-title">Display settings</h2>
            <button type="button" class="reader-panel-close" data-close="panel-settings" aria-label="Close">×</button>
        </header>
        <div class="reader-panel-content">
            <label class="setting-row">
                <span>Fit</span>
                <select id="setting-fit">
                    <option value="width">Fit width</option>
                    <option value="height">Fit height</option>
                    <option value="page">Whole page</option>
                    <option value="custom">Custom zoom</option>
                </select>
            </label>

            <label class="setting-row">
                <span>Zoom</span>
                <input type="range" id="setting-zoom" min="50" max="400" step="10" value="100">
                <output id="zoom-out-value">100%</output>
            </label>

            <fieldset>
                <legend>Background</legend>
                <div class="theme-options">
                    <label><input type="radio" name="reader-theme" value="auto"> Auto</label>
                    <label><input type="radio" name="reader-theme" value="light"> Light</label>
                    <label><input type="radio" name="reader-theme" value="sepia"> Sepia</label>
                    <label><input type="radio" name="reader-theme" value="dark"> Dark</label>
                </div>
            </fieldset>

            <?php if ($kind === 'comic'): ?>
            <fieldset class="setting-toggle-group">
                <legend>Comic layout</legend>
                <label class="checkbox-row">
                    <input type="checkbox" id="setting-spread">
                    <span>Two-page spread on wide screens</span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" id="setting-rtl">
                    <span>Right-to-left (manga) reading order</span>
                </label>
            </fieldset>
            <?php endif; ?>

            <fieldset class="setting-toggle-group">
                <legend>Navigation</legend>
                <label class="checkbox-row">
                    <input type="checkbox" id="setting-continuous">
                    <span>Continuous scroll</span>
                </label>
                <p class="setting-hint">Scroll through every page instead of turning one at a time.</p>
            </fieldset>

            <p class="setting-hint">
                Keyboard: <kbd>←</kbd> <kbd>→</kbd> turn pages, <kbd>Home</kbd> / <kbd>End</kbd> jump to
                the first or last page, <kbd>+</kbd> / <kbd>−</kbd> zoom, <kbd>F</kbd> immersive mode.
            </p>
        </div>
    </aside>

    <div class="reader-toast" role="status" aria-live="polite" id="reader-toast" hidden></div>
</div>

<?php if ($isGuest): ?>
<div class="reader-guest-banner" id="guest-banner">
    Reading as a guest — your place is saved in this browser only.
    <a href="<?= e(url('login.php')) ?>">Sign in to sync</a>
    <button type="button" class="banner-dismiss" aria-label="Dismiss" data-dismiss="guest-banner">×</button>
</div>
<?php endif; ?>
