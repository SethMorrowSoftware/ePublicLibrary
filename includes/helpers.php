<?php
/**
 * Global helper functions.
 *
 * Loaded by bootstrap.php BEFORE config is read so that even config errors
 * can be rendered safely. Functions here must be free of dependencies on
 * the DB or session.
 */

defined('APP_BOOTED') or exit;

/* ----------- Configuration ------------------------------------------------ */

/**
 * Read a value from the loaded config array by dotted path.
 *   config('db.host')              → $cfg['db']['host']
 *   config('app_name', 'Library')  → with default
 */
function config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        $cfg = is_file($path) ? require $path : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    $node = $cfg;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return $default;
        }
        $node = $node[$segment];
    }
    return $node;
}

/**
 * True once includes/config.php exists (i.e. setup has populated it).
 */
function config_exists(): bool
{
    return is_file(__DIR__ . '/config.php');
}

/* ----------- Output escaping --------------------------------------------- */

/** Escape for HTML element content. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for an HTML attribute value. */
function eattr($value): string
{
    return e($value);
}

/** Escape for a URL query component. */
function eurl($value): string
{
    return rawurlencode((string) ($value ?? ''));
}

/* ----------- URL helpers -------------------------------------------------- */

/**
 * Application base URL prefix (e.g. '' for root install, '/library' for subdir).
 * Computed once from config, with a fallback that auto-detects from SCRIPT_NAME.
 */
function app_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfgBase = config('base_url');
    if ($cfgBase !== null) {
        $base = rtrim($cfgBase, '/');
        return $base;
    }
    // Auto-detect from the request. Strip the entry-script filename to find
    // the directory the app is mounted under. Handles nested entry points
    // (admin/foo.php, api/bar.php) by looking at SCRIPT_NAME.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up until the dir name does not contain admin/api etc.
    $dir = str_replace('\\', '/', dirname($script));
    // Strip trailing /admin or /api segments so base_url is the project root
    foreach (['/admin', '/api', '/views', '/includes', '/database'] as $sub) {
        if (substr($dir, -strlen($sub)) === $sub) {
            $dir = substr($dir, 0, -strlen($sub));
        }
    }
    $base = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : rtrim($dir, '/');
    return $base;
}

/**
 * Build an application URL from a relative path.
 *   url('login.php')           → '/library/login.php' or '/login.php'
 *   url('admin/upload.php')    → '/library/admin/upload.php'
 *   url('book.php?b=' . $uuid) → with query
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = app_base();
    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * A short token that changes whenever the deployed code changes. Appended to
 * CSS/JS asset URLs so a browser (or the service worker) never serves a stale
 * stylesheet against new markup.
 *
 * Derived from the configured app_version plus the mtime of this file, which
 * moves on every deploy without needing a build step.
 */
function asset_version(): string
{
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    $stamp = (string) config('app_version', '1.0.0');
    $mtime = @filemtime(__DIR__ . '/helpers.php') ?: 0;
    $v = substr(hash('sha256', $stamp . '|' . $mtime), 0, 8);
    return $v;
}

/**
 * Build an asset URL (CSS, JS, fonts, covers).
 *   asset('css/library.css') → '/library/assets/css/library.css?v=1a2b3c4d'
 *   asset('covers/x.jpg')    → '/library/assets/covers/x.jpg'
 *
 * Code assets get a cache-busting query; content assets (covers, fonts) do
 * not — they are immutable per URL already.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $built = url('assets/' . $path);
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['css', 'js', 'mjs'], true) && strpos($built, '?') === false) {
        $built .= '?v=' . asset_version();
    }
    return $built;
}

/**
 * Determine the absolute current URL (scheme + host + path) for canonical refs
 * and same-origin redirects. Honors X-Forwarded-Proto when trust_proxy is set.
 */
function current_url(): string
{
    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || (config('security.trust_proxy') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

/**
 * Redirect to an in-app path. Always routes through url() so the base prefix is honored.
 * Use absolute URLs only for external links.
 */
function redirect(string $path, int $status = 302): void
{
    $target = (strpos($path, '://') === false) ? url($path) : $path;
    header('Location: ' . $target, true, $status);
    exit;
}

/* ----------- Flash messages ---------------------------------------------- */

function flash(string $key, ?string $message = null)
{
    if (!isset($_SESSION)) {
        return null;
    }
    if ($message === null) {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
    $_SESSION['_flash'][$key] = $message;
    return null;
}

/** Sticky old() for form re-rendering after validation errors. */
function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? null;
    if ($value === null || is_array($value) || is_object($value)) {
        return $default;
    }
    return (string) $value;
}

function set_old(array $input): void
{
    if (isset($_SESSION)) {
        // Never store passwords
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $_SESSION['_old'] = $input;
    }
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ----------- UUID + time + sharding -------------------------------------- */

/** RFC 4122 v4 UUID using random_bytes (no extensions). */
function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
    $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8), substr($hex, 8, 4),
        substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
}

/** First 2 chars of a UUID — used as a directory shard. */
function shard_for(string $uuid): string
{
    return substr(preg_replace('/[^a-f0-9]/i', '', $uuid), 0, 2) ?: '00';
}

/** Current UTC time as MySQL DATETIME string. */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/* ----------- Paths -------------------------------------------------------- */

function storage_path(string $key, string $relative = ''): string
{
    $base = config('storage.' . $key);
    if ($base === null) {
        $base = __DIR__ . '/../storage';
    }
    return rtrim($base, '/\\') . ($relative !== '' ? '/' . ltrim($relative, '/\\') : '');
}

function project_path(string $relative = ''): string
{
    $root = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
    return rtrim($root, '/\\') . ($relative !== '' ? '/' . ltrim($relative, '/\\') : '');
}

/* ----------- Request helpers --------------------------------------------- */

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function client_ip_binary(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (config('security.trust_proxy') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
            $ip = $forwarded;
        }
    }
    return ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP)) ? inet_pton($ip) : null;
}

function client_ip(): ?string
{
    $bin = client_ip_binary();
    return $bin !== null ? inet_ntop($bin) : null;
}

function request_input(string $key, $default = null)
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

/** Format a byte count for humans: 1536 → "1.5 KB". */
function format_bytes(int $bytes, int $precision = 1): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $power);
    return round($value, $power === 0 ? 0 : $precision) . ' ' . $units[$power];
}

/* ----------- JSON response ------------------------------------------------ */

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): void
{
    json_response(['error' => $message] + $extra, $status);
}
