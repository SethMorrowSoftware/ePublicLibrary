<?php
defined('APP_BOOTED') or exit;
/** @var array $book */
/** @var array|null $progress */
/** @var array $bookmarks */
/** @var bool $isGuest */

$bookFileUrl   = url('api/download.php?b=' . eurl($book['uuid']) . '&stream=1');
$progressUrl   = url('api/progress.php');
$bookmarksUrl  = url('api/bookmarks.php');
$highlightsUrl = url('api/highlights.php');
?>
<div id="reader" class="reader-shell"
     data-book-uuid="<?= e($book['uuid']) ?>"
     data-book-url="<?= e($bookFileUrl) ?>"
     data-progress-url="<?= e($progressUrl) ?>"
     data-bookmarks-url="<?= e($bookmarksUrl) ?>"
     data-highlights-url="<?= e($highlightsUrl) ?>"
     data-is-guest="<?= $isGuest ? '1' : '0' ?>"
     <?php if ($progress): ?>data-initial-cfi="<?= e($progress['cfi'] ?? '') ?>"<?php endif; ?>>

    <header class="reader-header" role="banner">
        <a href="<?= e(url('index.php')) ?>" class="reader-back" aria-label="Back to library" title="Back to library">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div class="reader-title">
            <h1 class="reader-book-title" title="<?= e($book['title']) ?>"><?= e($book['title']) ?></h1>
            <p class="reader-book-author"><?= e($book['author']) ?></p>
        </div>
        <div class="reader-controls" role="toolbar" aria-label="Reader controls">
            <button type="button" id="btn-toc" class="reader-btn" aria-label="Table of contents" title="Table of contents">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button type="button" id="btn-search" class="reader-btn" aria-label="Search this book" title="Search in book">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
            <button type="button" id="btn-highlights" class="reader-btn" aria-label="Highlights" title="Highlights">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button type="button" id="btn-bookmarks" class="reader-btn" aria-label="Bookmarks" title="Bookmarks">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </button>
            <button type="button" id="btn-bookmark" class="reader-btn" aria-label="Toggle bookmark at current location" title="Bookmark current location">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            <button type="button" id="btn-tts" class="reader-btn" aria-label="Listen (text-to-speech)" title="Listen" aria-expanded="false" aria-controls="tts-toolbar">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M17.95 6.05a8 8 0 010 11.9M11 5L6 9H2v6h4l5 4V5z"/></svg>
            </button>
            <button type="button" id="btn-immersive" class="reader-btn" aria-label="Immersive mode (hide chrome)" title="Immersive mode (Esc to exit)" aria-pressed="false">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
            </button>
            <button type="button" id="btn-settings" class="reader-btn" aria-label="Reader settings" title="Reader settings">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
        </div>
    </header>

    <div class="reader-viewport" id="reader-viewport">
        <button type="button" class="reader-hotspot reader-hotspot-prev" aria-label="Previous page" data-direction="prev"></button>
        <div id="epub-viewer" class="epub-viewer"></div>
        <button type="button" class="reader-hotspot reader-hotspot-next" aria-label="Next page" data-direction="next"></button>
    </div>

    <div id="tts-toolbar" class="tts-toolbar" hidden role="toolbar" aria-label="Text-to-speech controls">
        <button type="button" id="tts-prev" class="tts-btn" aria-label="Previous sentence" title="Previous sentence">⏮</button>
        <button type="button" id="tts-play" class="tts-btn tts-play" aria-label="Play" title="Play">▶</button>
        <button type="button" id="tts-pause" class="tts-btn" aria-label="Pause / Resume" title="Pause">Pause</button>
        <button type="button" id="tts-next" class="tts-btn" aria-label="Next sentence" title="Next sentence">⏭</button>
        <button type="button" id="tts-stop" class="tts-btn" aria-label="Stop" title="Stop">⏹</button>
        <label class="tts-rate-label">
            <span class="visually-hidden">Speed</span>
            <input type="range" id="tts-rate" min="0.5" max="2" step="0.1" value="1">
            <output id="tts-rate-out">1x</output>
        </label>
        <select id="tts-voice" aria-label="Voice"></select>
    </div>

    <div class="reader-progress-bar" role="progressbar" aria-label="Reading progress"
         aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) ($progress['percentage'] ?? 0)) ?>">
        <button type="button" id="progress-track" class="reader-progress-track" aria-label="Seek to position">
            <div id="progress-fill" class="reader-progress-fill"
                 style="width: <?= e((string) ($progress['percentage'] ?? 0)) ?>%"></div>
        </button>
        <div class="reader-progress-info">
            <span id="progress-chapter"><?= e($progress['current_chapter'] ?? '') ?></span>
            <span id="progress-percent"><?= e(number_format((float) ($progress['percentage'] ?? 0), 1)) ?>%</span>
        </div>
    </div>

    <aside id="panel-toc" class="reader-panel" role="dialog" aria-labelledby="panel-toc-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-toc-title">Contents</h2>
            <button type="button" class="reader-panel-close" data-close="panel-toc" aria-label="Close">×</button>
        </header>
        <ol class="reader-panel-list" id="toc-list" aria-busy="true">
            <li class="reader-panel-empty">Loading…</li>
        </ol>
    </aside>

    <aside id="panel-search" class="reader-panel" role="dialog" aria-labelledby="panel-search-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-search-title">Find in book</h2>
            <button type="button" class="reader-panel-close" data-close="panel-search" aria-label="Close">×</button>
        </header>
        <div class="reader-panel-content reader-search-content">
            <input type="search" id="in-book-search-input" placeholder="Search…" autocomplete="off"
                   aria-label="Search this book">
            <p class="in-book-search-status muted" id="in-book-search-status"></p>
            <ul class="in-book-search-results" id="in-book-search-results"></ul>
        </div>
    </aside>

    <aside id="panel-highlights" class="reader-panel" role="dialog" aria-labelledby="panel-highlights-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-highlights-title">
                Highlights <span id="highlights-count" class="highlights-count-badge">0</span>
            </h2>
            <div class="highlights-header-actions">
                <button type="button" id="btn-highlights-export" class="btn-link" title="Export to Markdown">Export</button>
                <button type="button" class="reader-panel-close" data-close="panel-highlights" aria-label="Close">×</button>
            </div>
        </header>
        <ul class="reader-panel-list" id="highlights-list">
            <li class="reader-panel-empty">Select text in the book to add a highlight.</li>
        </ul>
    </aside>

    <aside id="panel-bookmarks" class="reader-panel" role="dialog" aria-labelledby="panel-bookmarks-title" hidden>
        <header class="reader-panel-header">
            <h2 id="panel-bookmarks-title">Bookmarks</h2>
            <button type="button" class="reader-panel-close" data-close="panel-bookmarks" aria-label="Close">×</button>
        </header>
        <ul class="reader-panel-list" id="bookmarks-list">
            <?php if (!$bookmarks): ?>
                <li class="reader-panel-empty">No bookmarks yet. Tap the + icon to bookmark the current page.</li>
            <?php else: ?>
                <?php foreach ($bookmarks as $b): ?>
                    <li data-cfi="<?= e($b['cfi']) ?>" data-id="<?= e((string) $b['id']) ?>">
                        <button type="button" class="bookmark-jump"><?= e($b['label'] ?: $b['chapter'] ?: 'Bookmark') ?></button>
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
            <fieldset>
                <legend>Theme</legend>
                <div class="theme-options">
                    <label><input type="radio" name="reader-theme" value="auto"> Auto</label>
                    <label><input type="radio" name="reader-theme" value="light"> Light</label>
                    <label><input type="radio" name="reader-theme" value="sepia"> Sepia</label>
                    <label><input type="radio" name="reader-theme" value="dark"> Dark</label>
                </div>
            </fieldset>

            <label class="setting-row">
                <span>Font size</span>
                <input type="range" id="setting-font-size" min="60" max="200" step="10" value="100">
                <output id="font-size-out">100%</output>
            </label>

            <label class="setting-row">
                <span>Font family</span>
                <select id="setting-font-family">
                    <option value="serif">Serif</option>
                    <option value="sans-serif">Sans-serif</option>
                    <option value="dyslexic">OpenDyslexic</option>
                </select>
            </label>

            <label class="setting-row">
                <span>Line height</span>
                <input type="range" id="setting-line-height" min="1.2" max="2.4" step="0.2" value="1.6">
                <output id="line-height-out">1.6</output>
            </label>

            <label class="setting-row">
                <span>Margins</span>
                <select id="setting-margins">
                    <option value="compact">Compact</option>
                    <option value="normal" selected>Normal</option>
                    <option value="wide">Wide</option>
                </select>
            </label>

            <fieldset class="setting-toggle-group">
                <legend>Layout</legend>
                <label class="checkbox-row">
                    <input type="checkbox" id="setting-continuous-scroll">
                    <span>Continuous scroll</span>
                </label>
                <p class="setting-hint">Reloads the reader when toggled.</p>
            </fieldset>

            <fieldset class="setting-toggle-group">
                <legend>Tools</legend>
                <label class="checkbox-row">
                    <input type="checkbox" id="setting-dictionary">
                    <span>Double-tap to look up words (dictionary)</span>
                </label>
            </fieldset>
        </div>
    </aside>

    <div class="reader-toast" role="status" aria-live="polite" id="reader-toast" hidden></div>
</div>

<?php if ($isGuest): ?>
<div class="reader-guest-banner" id="guest-banner">
    Reading as a guest — progress saves in this browser only.
    <a href="<?= e(url('login.php')) ?>">Sign in to sync</a>
    <button type="button" class="banner-dismiss" aria-label="Dismiss" data-dismiss="guest-banner">×</button>
</div>
<?php endif; ?>
