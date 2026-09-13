<?php
defined('APP_BOOTED') or exit;
/** @var string $__contents */
/** @var string|null $pageTitle */
/** @var string|null $activeNav */
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$user = current_user();

$navItems = [
    ['key' => 'dashboard',  'label' => 'Dashboard',    'href' => url('admin/index.php')],
    ['key' => 'books',      'label' => 'Books',        'href' => url('admin/books.php')],
    ['key' => 'upload',     'label' => 'Upload',       'href' => url('admin/upload.php')],
    ['key' => 'import',     'label' => 'Import folder', 'href' => url('admin/scan-books.php')],
    ['key' => 'users',      'label' => 'Users',        'href' => url('admin/users.php')],
    ['key' => 'thumbnails', 'label' => 'Thumbnails',   'href' => url('admin/thumbnails.php')],
    ['key' => 'health',     'label' => 'Health',       'href' => url('admin/health.php')],
    ['key' => 'audit',      'label' => 'Audit log',    'href' => url('admin/audit.php')],
    ['key' => 'migrate',    'label' => 'Migrations',   'href' => url('admin/migrate.php')],
];
?><!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Admin</title>
    <?php partial('head-meta', ['noIndex' => true]); ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Admin navigation">
            <a class="admin-logo" href="<?= e(url('index.php')) ?>">
                <?php partial('brand-mark', ['class' => 'admin-logo-mark']); ?>
                <span class="admin-logo-text"><?= e(config('app_name', 'ePublicLibrary')) ?></span>
            </a>
            <nav class="admin-nav">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= e($item['href']) ?>"
                       class="admin-nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                       <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="admin-user">
                <div class="admin-user-name"><?= e($user['display_name'] ?: $user['username']) ?></div>
                <div class="admin-user-meta"><?= e($user['role']) ?></div>
                <a class="admin-user-logout" href="<?= e(url('logout.php')) ?>">Sign out</a>
            </div>
        </aside>
        <main id="main-content" class="admin-main" tabindex="-1">
            <?php
            foreach (['success', 'error', 'info'] as $kind) {
                $msg = flash($kind);
                if ($msg) {
                    echo '<div class="flash flash-' . e($kind) . '" role="status">' . e($msg) . '</div>';
                }
            } ?>
            <?= $__contents ?>
        </main>
    </div>
    <script type="module" src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
