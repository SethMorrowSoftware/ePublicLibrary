<?php
defined('APP_BOOTED') or exit;
/**
 * Shared <head> boilerplate for every layout: base/CSRF metadata, icons,
 * PWA manifest, theme colour, and the no-flash theme bootstrap.
 *
 * Vars:
 *   $noIndex  bool  — emit <meta name="robots" content="noindex"> (admin/auth)
 */
$noIndex = $noIndex ?? false;
?>
    <meta charset="utf-8">
    <meta name="app-base" content="<?= e(app_base()) ?>">
    <meta name="asset-version" content="<?= e(asset_version()) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#fdfcfb" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f0e0d" media="(prefers-color-scheme: dark)">
<?php if ($noIndex): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <link rel="icon" href="<?= e(asset('favicon.ico')) ?>" sizes="32x32">
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.php')) ?>">
    <link rel="preload" href="<?= e(asset('fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('fonts/instrument-serif-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <script nonce="<?= e(csp_nonce()) ?>">
        /* Apply the saved theme before first paint so there is no flash of the
           wrong palette. shared/theme.js re-reads the same key later. */
        try {
            var t = localStorage.getItem('elib-theme');
            if (t && t !== 'auto') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
