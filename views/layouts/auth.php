<?php
defined('APP_BOOTED') or exit;
/** @var string $__contents */
/** @var string|null $pageTitle */
$pageTitle = $pageTitle ?? 'Sign in';
?><!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(config('app_name', 'ePublicLibrary')) ?></title>
    <?php partial('head-meta', ['noIndex' => true]); ?>
    <link rel="stylesheet" href="<?= e(asset('css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="auth-page">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <main id="main-content" class="auth-shell">
        <a class="auth-logo" href="<?= e(url('index.php')) ?>">
            <?php partial('brand-mark', ['class' => 'auth-logo-mark']); ?>
            <span class="auth-logo-text"><?= e(config('app_name', 'ePublicLibrary')) ?></span>
        </a>
        <?php
        foreach (['success', 'error', 'info'] as $kind) {
            $msg = flash($kind);
            if ($msg) {
                echo '<div class="flash flash-' . e($kind) . '" role="status">' . e($msg) . '</div>';
            }
        }
        ?>
        <?= $__contents ?>
    </main>
    <script type="module" src="<?= e(asset('js/auth.js')) ?>"></script>
</body>
</html>
