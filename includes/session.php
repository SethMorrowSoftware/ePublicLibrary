<?php
/**
 * DB-backed PHP session handler.
 *
 * Writes to the `sessions` table so we can enumerate active sessions per
 * user, support "log out everywhere," and survive PHP-FPM worker resets.
 */

defined('APP_BOOTED') or exit;

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function open($savePath, $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string
    {
        $stmt = $this->pdo->prepare("SELECT payload FROM {$this->table} WHERE id = ? AND expires_at > ?");
        $stmt->execute([$id, time()]);
        $row = $stmt->fetch();
        return $row ? (string) $row['payload'] : '';
    }

    public function write($id, $data): bool
    {
        $userId   = $_SESSION['user_id'] ?? null;
        $ipBinary = client_ip_binary();
        $ua       = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $now      = time();
        $expires  = $now + (int) config('session.lifetime', 60 * 60 * 24 * 14);

        $sql = "INSERT INTO {$this->table} (id, user_id, ip_address, user_agent, payload, last_activity, expires_at)
                VALUES (:id, :uid, :ip, :ua, :payload, :last, :exp)
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    payload = VALUES(payload),
                    last_activity = VALUES(last_activity),
                    expires_at = VALUES(expires_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':uid', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ipBinary, $ipBinary === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $stmt->bindValue(':ua', $ua);
        $stmt->bindValue(':payload', $data);
        $stmt->bindValue(':last', $now, PDO::PARAM_INT);
        $stmt->bindValue(':exp', $expires, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires_at < ?");
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
}

/**
 * Configure cookie params and start the session.
 * Falls back to file sessions if the DB connection isn't usable
 * (e.g. before setup, or during error-page rendering).
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $base = app_base();
    $name = (string) config('session.name', 'elib_sess');
    $secure = config('session.secure');
    if ($secure === null) {
        $secure = request_is_https();
    }
    $samesite = (string) config('session.samesite', 'Lax');
    $lifetime = (int)    config('session.lifetime', 60 * 60 * 24 * 14);

    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0, // session cookie; PHP enforces idle TTL server-side
        'path'     => $base !== '' ? $base . '/' : '/',
        'domain'   => '',
        'secure'   => (bool) $secure,
        'httponly' => true,
        'samesite' => $samesite,
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    // Try DB-backed sessions; fall back gracefully.
    if (config_exists()) {
        try {
            $handler = new DbSessionHandler(db(), table('sessions'));
            session_set_save_handler($handler, true);
        } catch (Throwable $e) {
            // Falling back to default file sessions during e.g. setup wizard
            log_error($e);
        }
    }

    try {
        session_start();
    } catch (Throwable $e) {
        // The handler is installed before we know the `sessions` table is
        // usable, and the failure surfaces here rather than above — PHP only
        // calls read() from inside session_start(). Without this fallback a
        // database whose migrations have not run yet takes down every page,
        // including Admin → Migrations, which is the page that creates the
        // table. Degrade to PHP's own file sessions instead of locking the
        // administrator out of their own install.
        log_error($e);
        @session_abort();
        session_set_save_handler(new SessionHandler(), true);
        @session_start();
    }
}
