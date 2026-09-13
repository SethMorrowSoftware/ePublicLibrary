<?php
/**
 * ePublicLibrary configuration template.
 *
 * Copy this file to includes/config.php and edit values for your install.
 * The setup wizard (setup.php) writes this file for you on first run.
 *
 * This file is denied web access by .htaccess (includes/.htaccess + the
 * top-level rule that blocks *.example.php and config.php directly).
 */

defined('APP_BOOTED') or exit;

return [
    // ---- Site identity ------------------------------------------------------
    'app_name'    => 'ePublicLibrary',
    'app_version' => '1.4.0',

    // Auto-detected during setup. Examples:
    //   ''           → installed at the document root (https://example.org/)
    //   '/library'   → installed in a subdirectory (https://example.org/library/)
    // No trailing slash. Empty string is fine.
    'base_url'    => '',

    // Debug mode. ALWAYS false in production. Setup wizard defaults to false.
    'debug'       => false,

    // PHP timezone identifier. UTC recommended; render in user locale client-side.
    'timezone'    => 'UTC',

    // ---- Database (MySQL / MariaDB) -----------------------------------------
    'db' => [
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => 'epublibrary',
        'username'  => '',
        'password'  => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // ---- Session ------------------------------------------------------------
    'session' => [
        'name'        => 'elib_sess',
        'lifetime'    => 60 * 60 * 24 * 14,   // 14 days
        'secure'      => null,                // null = auto-detect HTTPS
        'samesite'    => 'Lax',
    ],

    // ---- Security -----------------------------------------------------------
    'security' => [
        // CSP report-only mode useful while migrating off Tailwind CDN.
        'csp_report_only' => false,

        // Trust X-Forwarded-* headers? Only enable behind a known proxy.
        'trust_proxy'     => false,

        // HSTS max-age (seconds). 0 disables.
        'hsts_max_age'    => 31536000,

        // Login throttle thresholds
        'login_ip_max'    => 10,              // attempts per window
        'login_ip_window' => 60 * 15,         // 15 min
        'login_user_max'  => 5,
        'login_user_window' => 60 * 15,
    ],

    // ---- Storage ------------------------------------------------------------
    // Absolute paths preferred. Defaults are relative to the project root.
    'storage' => [
        'books_path'    => __DIR__ . '/../storage/books',
        'uploads_tmp'   => __DIR__ . '/../storage/uploads/tmp',
        'logs_path'     => __DIR__ . '/../storage/logs',
        'cache_path'    => __DIR__ . '/../storage/cache',
        'covers_path'   => __DIR__ . '/../assets/covers',  // PUBLIC (under web root)
        'max_upload_mb' => 100,
    ],

    // ---- Mail ---------------------------------------------------------------
    // Drivers:
    //   'log'  → writes to storage/logs/mail.log (default; safe for dev)
    //   'mail' → PHP's built-in mail() — works on most cPanel hosts
    //   'smtp' → uses PHPMailer if vendored at includes/vendor/PHPMailer/
    //            (see INSTALL.md for the one-time vendor command)
    'mail' => [
        'driver'    => 'log',
        'from_addr' => 'no-reply@example.org',
        'from_name' => 'ePublicLibrary',
        'smtp' => [
            'host'       => '',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',  // 'tls' | 'ssl' | ''
        ],
    ],

    // ---- Setup sentinel -----------------------------------------------------
    // Set to a Unix timestamp once setup.php completes. Setup will refuse to
    // run while this is non-zero. To re-run setup intentionally, set to 0.
    'setup_completed_at' => 0,
];
