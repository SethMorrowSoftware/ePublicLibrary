<?php
/**
 * Admin: browser-triggered migration runner.
 *
 * Lists all migrations in database/migrations/ alongside which have been
 * applied (tracked in schema_migrations). Apply button runs all pending
 * in lexical order.
 */
define('APP_BOOTED', true);
require __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `filename` VARCHAR(190) NOT NULL,
    `batch` INT UNSIGNED NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `checksum` CHAR(64) NOT NULL,
    PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dir = project_path('database/migrations');
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);

// Keyed by filename. (This used to run a FETCH_KEY_PAIR query first and then
// immediately re-fetch the same rows as an assoc array — but FETCH_KEY_PAIR
// throws on anything other than exactly two columns, so the page 500'd before
// reaching the second query. One query, keyed by hand.)
$applied = [];
foreach ($pdo->query("SELECT filename, executed_at, checksum FROM schema_migrations")->fetchAll() as $r) {
    $applied[$r['filename']] = $r;
}

$messages = [];

if (is_post()) {
    csrf_verify_or_abort();
    $batch = (int) ($pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations")->fetchColumn());
    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) { continue; }
        $sql = file_get_contents($file);
        if ($sql === false) {
            $messages[] = ['type' => 'error', 'text' => "Could not read {$name}"];
            continue;
        }
        try {
            $pdo->exec($sql);
            $ins = $pdo->prepare("INSERT INTO schema_migrations (filename, batch, checksum) VALUES (?, ?, ?)");
            $ins->execute([$name, $batch, hash('sha256', $sql)]);
            $messages[] = ['type' => 'success', 'text' => "Applied {$name}"];
            $applied[$name] = ['filename' => $name, 'executed_at' => now_utc(), 'checksum' => hash('sha256', $sql)];
        } catch (Throwable $e) {
            $messages[] = ['type' => 'error', 'text' => "{$name}: " . $e->getMessage()];
            log_error($e);
            break;
        }
    }
    AuditLogger::log('migrations.run', null, null, ['messages' => $messages]);
}

// Build a status list for rendering
$status = [];
foreach ($files as $file) {
    $name = basename($file);
    $sum  = hash_file('sha256', $file);
    $row  = $applied[$name] ?? null;
    $drift = ($row && $row['checksum'] !== $sum);
    $status[] = [
        'name' => $name,
        'applied' => (bool) $row,
        'executed_at' => $row['executed_at'] ?? null,
        'drift' => $drift,
    ];
}

render('admin/migrate', [
    'pageTitle' => 'Migrations',
    'activeNav' => 'migrate',
    'status'    => $status,
    'messages'  => $messages,
], 'admin');
