<?php
/**
 * Web app manifest.
 *
 * Served from PHP rather than a static file because every URL inside it has
 * to carry the install's base path (root install vs. /library/ subdirectory),
 * and because `name` follows the admin-chosen library name.
 *
 * Linked from the layouts as <link rel="manifest" href="manifest.php">.
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

$name = (string) config('app_name', 'ePublicLibrary');

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'name'             => $name,
    'short_name'       => mb_substr($name, 0, 12),
    'description'      => 'A self-hosted library for EPUB, PDF, and comic books.',
    'id'               => url('') ?: '/',
    'start_url'        => url('index.php'),
    'scope'            => url(''),
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#fdfcfb',
    'theme_color'      => '#c2703c',
    'categories'       => ['books', 'education', 'entertainment'],
    'icons' => [
        ['src' => asset('favicon.svg'),                 'sizes' => 'any',     'type' => 'image/svg+xml'],
        ['src' => asset('icons/icon-192.png'),          'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => asset('icons/icon-512.png'),          'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => asset('icons/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        [
            'name'  => 'Search the library',
            'url'   => url('search.php'),
            'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']],
        ],
        [
            'name'  => 'Your shelves',
            'url'   => url('collections.php'),
            'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
