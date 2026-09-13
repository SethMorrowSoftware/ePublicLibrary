<?php
/**
 * PDO connection factory.
 *
 * Single shared connection per request (PHP-FPM workers reuse them via
 * persistent flag if you turn it on in config).
 */

defined('APP_BOOTED') or exit;

/**
 * Return the shared PDO instance.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = (string) config('db.host', '127.0.0.1');
    $port    = (int)    config('db.port', 3306);
    $dbname  = (string) config('db.database', '');
    $user    = (string) config('db.username', '');
    $pass    = (string) config('db.password', '');
    $charset = (string) config('db.charset', 'utf8mb4');

    if ($dbname === '') {
        throw new RuntimeException('Database not configured. Run setup.php first.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES    => false,
        PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES {$charset} COLLATE " . (config('db.collation') ?: 'utf8mb4_unicode_ci') . ", time_zone = '+00:00', sql_mode='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'",
    ]);

    return $pdo;
}

/**
 * Try to connect with provided creds (used by setup.php to validate inputs).
 * Returns [true, PDO] on success or [false, errorMessage].
 */
function db_try_connect(array $creds): array
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $creds['host'] ?? '127.0.0.1',
            (int) ($creds['port'] ?? 3306),
            $creds['database'] ?? '',
            $creds['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $creds['username'] ?? '', $creds['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return [true, $pdo];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/**
 * Table-name resolver.
 *
 * Historically this applied a configurable `db.prefix`, but the prefix was
 * never real: the migrations in database/migrations/ hard-code unprefixed
 * table names and every repository queries them directly, so setting a
 * prefix produced a half-prefixed schema where nothing lined up. The knob is
 * gone; this stays as the single place table names are resolved should real
 * prefix support ever land (it would need templated migrations too).
 */
function table(string $name): string
{
    return $name;
}
