<?php
defined('APP_BOOTED') or exit;
/** @var array|null $user */
$user = $user ?? current_user();

// The sort selects submit through #searchForm, whose action is index.php, so
// they only do the right thing on the library grid. Elsewhere they were noise
// that also pushed the header past the viewport on phones.
$showSort = $showSort ?? (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php');
?>
<header class="site-header">
    <div class="header-container">
        <div class="header-row">
            <a href="<?= e(url('index.php')) ?>" class="logo-container">
                <?php partial('brand-mark', ['class' => 'logo-icon']); ?>
                <span class="logo-text"><?= e(config('app_name', 'ePublicLibrary')) ?></span>
            </a>

            <form id="searchForm" action="<?= e(url('index.php')) ?>" method="get" class="search-container" role="search">
                <div class="search-input-wrapper">
                    <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search"
                           name="search"
                           id="searchInput"
                           value="<?= e($_GET['search'] ?? '') ?>"
                           placeholder="Search books..."
                           aria-label="Search the library"
                           role="combobox"
                           aria-autocomplete="list"
                           aria-expanded="false"
                           aria-controls="autocomplete-listbox"
                           autocomplete="off">
                    <label class="visually-hidden" for="searchField">Search field</label>
                    <select name="field" id="searchField" class="search-field-select" aria-label="Search field">
                        <?php $field = $_GET['field'] ?? 'all'; ?>
                        <option value="all"    <?= $field === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="title"  <?= $field === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="author" <?= $field === 'author' ? 'selected' : '' ?>>Author</option>
                        <option value="genre"  <?= $field === 'genre' ? 'selected' : '' ?>>Genre</option>
                    </select>
                </div>
            </form>

            <?php if ($showSort): ?>
                <div class="sort-controls">
                    <?php $sortBy  = $_GET['sort']  ?? 'title';
                          $sortDir = $_GET['order'] ?? 'asc'; ?>
                    <label class="visually-hidden" for="sortBy">Sort by</label>
                    <select id="sortBy" name="sort" form="searchForm" title="Sort by" aria-label="Sort by">
                        <option value="title"     <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="author"    <?= $sortBy === 'author' ? 'selected' : '' ?>>Author</option>
                        <option value="published" <?= $sortBy === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="created"   <?= $sortBy === 'created' ? 'selected' : '' ?>>Recently added</option>
                        <option value="rating"    <?= $sortBy === 'rating' ? 'selected' : '' ?>>Rating</option>
                        <option value="popular"   <?= $sortBy === 'popular' ? 'selected' : '' ?>>Most read</option>
                    </select>
                    <label class="visually-hidden" for="sortOrder">Sort order</label>
                    <select id="sortOrder" name="order" form="searchForm" title="Sort order" aria-label="Sort order">
                        <option value="asc"  <?= $sortDir === 'asc' ? 'selected' : '' ?>>A → Z</option>
                        <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="header-controls">
                <button id="theme-toggle" type="button" aria-label="Toggle theme" title="Toggle dark / light mode">
                    <svg class="sun-icon" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                    <svg class="moon-icon" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                </button>

                <?php if ($user): ?>
                    <details class="user-menu" data-close-outside>
                        <summary aria-label="User menu" title="Account menu">
                            <span class="user-avatar" aria-hidden="true"><?= e(mb_substr($user['display_name'] ?: $user['username'], 0, 1)) ?></span>
                        </summary>
                        <div class="user-menu-dropdown" role="menu">
                            <div class="user-menu-name"><?= e($user['display_name'] ?: $user['username']) ?></div>
                            <div class="user-menu-email"><?= e($user['email']) ?></div>
                            <hr>
                            <a href="<?= e(url('collections.php')) ?>" role="menuitem">Your shelves</a>
                            <a href="<?= e(url('stats.php')) ?>" role="menuitem">Reading stats</a>
                            <a href="<?= e(url('search.php')) ?>" role="menuitem">Advanced search</a>
                            <a href="<?= e(url('account.php')) ?>" role="menuitem">Account</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="<?= e(url('admin/index.php')) ?>" role="menuitem">Admin</a>
                            <?php endif; ?>
                            <a href="<?= e(url('logout.php')) ?>" role="menuitem">Sign out</a>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="header-signin" href="<?= e(url('login.php')) ?>">Sign in</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
