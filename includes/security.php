<?php
/**
 * Security headers, error handling, and request gating.
 */

defined('APP_BOOTED') or exit;

/** Per-request nonce for CSP (script-src, style-src). */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * Send security headers. Called once from bootstrap.
 * Idempotent — safe to call multiple times.
 */
function send_security_headers(): void
{
    static $sent = false;
    if ($sent) {
        return;
    }
    $sent = true;

    $nonce = csp_nonce();

    // Content-Security-Policy: tight by default. The two CDN hosts are
    // allowed for the (optional) reader-page fallback when assets/vendor/
    // hasn't been populated. After vendoring locally, you can remove them.
    //
    // style-src deliberately carries NO nonce. Per CSP Level 3, a nonce or
    // hash source in style-src makes the browser IGNORE 'unsafe-inline',
    // which would block every server-rendered `style=""` attribute (cover
    // art, progress bars, rating histograms) and the <style> blocks epub.js
    // injects into each chapter iframe to apply the reader theme and font
    // size. Inline style attributes are not an XSS vector here — all of them
    // are built from escaped, server-side values — so 'unsafe-inline' is the
    // correct trade. script-src keeps its nonce and stays strict.
    $cdnHosts = 'https://cdn.jsdelivr.net https://cdnjs.cloudflare.com';
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' {$cdnHosts}",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "worker-src 'self' blob:",
        "child-src 'self' blob:",
        "frame-src 'self' blob:",
        "media-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
    $header = config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
    header($header . ': ' . $csp);

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // HSTS only on HTTPS — sending it on HTTP is harmless but pointless.
    if (request_is_https() && (int) config('security.hsts_max_age', 0) > 0) {
        $max = (int) config('security.hsts_max_age');
        header("Strict-Transport-Security: max-age={$max}; includeSubDomains");
    }
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (config('security.trust_proxy')
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return false;
}

/**
 * Custom error/exception handler. Logs to storage/logs/error.log and renders
 * a generic 500 page. Never leaks paths or messages in production.
 */
function register_error_handler(): void
{
    set_exception_handler(function (Throwable $e): void {
        log_error($e);
        render_error_page(500);
    });
    set_error_handler(function ($severity, $message, $file, $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            log_error(new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
            if (!headers_sent()) {
                render_error_page(500);
            }
        }
    });
}

function log_error(Throwable $e): void
{
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        now_utc(),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    $dir = storage_path('logs_path');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents($dir . '/error.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Render an error page. Tries view template first, falls back to inline HTML.
 */
function render_error_page(int $status, ?string $message = null): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $view = project_path('views/errors/' . $status . '.php');
    if (is_file($view)) {
        $vars = ['status' => $status, 'message' => $message];
        extract($vars, EXTR_SKIP);
        require $view;
        exit;
    }
    $label = [400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found',
              419 => 'Page Expired', 429 => 'Too Many Requests',
              500 => 'Server Error'][$status] ?? 'Error';
    echo '<!doctype html><meta charset="utf-8"><title>' . e($status . ' ' . $label) . '</title>'
       . '<h1>' . e($label) . '</h1>'
       . '<p>' . e($message ?? 'Something went wrong.') . '</p>';
    exit;
}

/**
 * Abort with a status code. Use sparingly — prefer normal flow returns.
 */
function abort(int $status, ?string $message = null): void
{
    render_error_page($status, $message);
}
