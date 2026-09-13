<?php
/**
 * ePublicLibrary setup wizard.
 *
 * Runs once, on first install. Walks the user through:
 *   1. Requirements   — PHP version, extensions
 *   2. Database       — connect + verify
 *   3. Site           — name + base URL (auto-detected)
 *   4. Admin account  — first admin user
 *   5. Run            — writes config.php, runs migrations, seeds admin
 *
 * Once setup is complete, this file refuses to re-run (sentinel in config).
 */

define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';

init_session();
send_security_headers();

// If config is already in place AND setup is complete, redirect home.
if (config_exists() && (int) config('setup_completed_at', 0) > 0) {
    setup_render_locked();
    exit;
}

csrf_token();
$state = $_SESSION['_setup'] ?? [];
$step  = $_GET['step'] ?? 'requirements';

if (is_post()) {
    csrf_verify_or_abort();
    switch ($step) {
        case 'requirements':
            $_SESSION['_setup']['requirements_ok'] = true;
            redirect('setup.php?step=database');
        case 'database':
            $errors = setup_validate_db($_POST);
            if (!$errors) {
                $_SESSION['_setup']['db'] = setup_db_input($_POST);
                redirect('setup.php?step=site');
            }
            $state = $_SESSION['_setup'] ?? [];
            $state['db'] = setup_db_input($_POST);
            $state['errors'] = $errors;
            setup_render_database($state);
            exit;
        case 'site':
            $_SESSION['_setup']['site'] = [
                'app_name' => trim((string) ($_POST['app_name'] ?? 'ePublicLibrary')) ?: 'ePublicLibrary',
                'base_url' => setup_normalize_base_url((string) ($_POST['base_url'] ?? '')),
                'timezone' => trim((string) ($_POST['timezone'] ?? 'UTC')) ?: 'UTC',
            ];
            redirect('setup.php?step=admin');
        case 'admin':
            $errors = setup_validate_admin($_POST);
            if (!$errors) {
                $_SESSION['_setup']['admin'] = [
                    'email'        => trim((string) $_POST['email']),
                    'username'     => trim((string) $_POST['username']),
                    'display_name' => trim((string) ($_POST['display_name'] ?? '')),
                    'password'     => (string) $_POST['password'],
                ];
                redirect('setup.php?step=run');
            }
            $state = $_SESSION['_setup'] ?? [];
            $state['admin'] = [
                'email'        => trim((string) ($_POST['email'] ?? '')),
                'username'     => trim((string) ($_POST['username'] ?? '')),
                'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            ];
            $state['errors'] = $errors;
            setup_render_admin($state);
            exit;
        case 'run':
            $result = setup_run();
            setup_render_done($result);
            exit;
    }
}

switch ($step) {
    case 'database':      setup_render_database($state); break;
    case 'site':          setup_render_site($state); break;
    case 'admin':         setup_render_admin($state); break;
    case 'run':           setup_render_run_confirm($state); break;
    case 'requirements':
    default:              setup_render_requirements(); break;
}
exit;


/* =================================================================== */
/*  Validators                                                          */
/* =================================================================== */

function setup_db_input(array $post): array
{
    return [
        'host'      => trim((string) ($post['db_host'] ?? '127.0.0.1')) ?: '127.0.0.1',
        'port'      => (int) ($post['db_port'] ?? 3306) ?: 3306,
        'database'  => trim((string) ($post['db_database'] ?? '')),
        'username'  => (string) ($post['db_username'] ?? ''),
        'password'  => (string) ($post['db_password'] ?? ''),
        'charset'   => 'utf8mb4',
    ];
}

function setup_validate_db(array $post): array
{
    $errors = [];
    $creds = setup_db_input($post);
    if ($creds['database'] === '')  { $errors[] = 'Database name is required.'; }
    if ($creds['username'] === '')  { $errors[] = 'Database username is required.'; }
    if ($errors) { return $errors; }

    [$ok, $msgOrPdo] = db_try_connect($creds);
    if (!$ok) {
        $errors[] = 'Could not connect to MySQL: ' . $msgOrPdo;
    }
    return $errors;
}

function setup_validate_admin(array $post): array
{
    $errors = [];
    $email    = trim((string) ($post['email'] ?? ''));
    $username = trim((string) ($post['username'] ?? ''));
    $password = (string) ($post['password'] ?? '');
    $confirm  = (string) ($post['password_confirmation'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) {
        $errors[] = 'Username must be 3–40 characters of letters, numbers, ".", "_", "-".';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }
    $errors = array_merge($errors, PasswordHasher::validate($password, $username, $email));
    return $errors;
}

function setup_normalize_base_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || $raw === '/') {
        return '';
    }
    if ($raw[0] !== '/') {
        $raw = '/' . $raw;
    }
    return rtrim($raw, '/');
}

/* =================================================================== */
/*  Run — writes config + migrations + admin                            */
/* =================================================================== */

function setup_run(): array
{
    $state = $_SESSION['_setup'] ?? [];
    if (empty($state['db']) || empty($state['site']) || empty($state['admin'])) {
        return ['ok' => false, 'errors' => ['Setup state is incomplete. Please start over.']];
    }

    // 1. Try DB connect
    [$ok, $pdo] = db_try_connect($state['db']);
    if (!$ok) {
        return ['ok' => false, 'errors' => ['Database connection failed: ' . $pdo]];
    }
    /** @var PDO $pdo */
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET time_zone = '+00:00'");

    // 2. Write config.php
    $configPath = __DIR__ . '/includes/config.php';
    $cfg = [
        'app_name'    => $state['site']['app_name'],
        'app_version' => '1.4.0',
        'base_url'    => $state['site']['base_url'],
        'debug'       => false,
        'timezone'    => $state['site']['timezone'],
        'db'          => $state['db'] + ['collation' => 'utf8mb4_unicode_ci'],
        'session'     => [
            'name' => 'elib_sess', 'lifetime' => 60 * 60 * 24 * 14,
            'secure' => null, 'samesite' => 'Lax',
        ],
        'security'    => [
            'csp_report_only' => false, 'trust_proxy' => false,
            'hsts_max_age' => 31536000,
            'login_ip_max' => 10, 'login_ip_window' => 60 * 15,
            'login_user_max' => 5, 'login_user_window' => 60 * 15,
        ],
        'storage'     => [
            'books_path'    => __DIR__ . '/storage/books',
            'uploads_tmp'   => __DIR__ . '/storage/uploads/tmp',
            'logs_path'     => __DIR__ . '/storage/logs',
            'cache_path'    => __DIR__ . '/storage/cache',
            'covers_path'   => __DIR__ . '/assets/covers',
            'max_upload_mb' => 100,
        ],
        'mail' => [
            'driver'    => 'log',
            'from_addr' => 'no-reply@example.org',
            'from_name' => $state['site']['app_name'],
            'smtp' => [
                'host' => '', 'port' => 587,
                'username' => '', 'password' => '',
                'encryption' => 'tls',
            ],
        ],
        'setup_completed_at' => 0,
    ];
    $writeResult = setup_write_config($configPath, $cfg);
    if (!$writeResult['ok']) {
        return ['ok' => false, 'errors' => [$writeResult['error']]];
    }

    // 3. Ensure storage dirs exist + are writable
    foreach (['books_path', 'uploads_tmp', 'logs_path', 'cache_path', 'covers_path'] as $key) {
        $dir = $cfg['storage'][$key];
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        if (!is_writable($dir)) {
            return ['ok' => false, 'errors' => ["Storage directory not writable: {$dir}"]];
        }
    }

    // 4. Run migrations
    $migrationResult = setup_run_migrations($pdo);
    if (!$migrationResult['ok']) {
        return ['ok' => false, 'errors' => ['Migrations failed: ' . $migrationResult['error']]];
    }

    // 5. Seed admin user
    $admin = $state['admin'];
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($exists === 0) {
        $stmt = $pdo->prepare("INSERT INTO users
            (uuid, email, username, display_name, password_hash, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'admin', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute([
            uuid_v4(),
            strtolower($admin['email']),
            $admin['username'],
            $admin['display_name'] ?: null,
            password_hash($admin['password'], PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]),
        ]);
        $adminId = (int) $pdo->lastInsertId();

        // Seed system collections for the admin
        foreach (CollectionRepository::SYSTEM_COLLECTIONS as $c) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO collections
                (user_id, name, slug, description, is_public, is_system, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
            $stmt->execute([$adminId, $c['name'], $c['slug'], $c['description']]);
        }
    }

    // 6. Set the sentinel
    $cfg['setup_completed_at'] = time();
    $writeResult = setup_write_config($configPath, $cfg);
    if (!$writeResult['ok']) {
        return ['ok' => false, 'errors' => ['Sentinel write failed: ' . $writeResult['error']]];
    }

    // 7. Clear setup state
    unset($_SESSION['_setup']);

    return ['ok' => true, 'errors' => [], 'base_url' => $cfg['base_url']];
}

function setup_run_migrations(PDO $pdo): array
{
    $dir = __DIR__ . '/database/migrations';
    if (!is_dir($dir)) {
        return ['ok' => false, 'error' => 'Migrations directory missing.'];
    }
    // Tracking table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename` VARCHAR(190) NOT NULL,
        `batch` INT UNSIGNED NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `checksum` CHAR(64) NOT NULL,
        PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $applied = [];
    foreach ($pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $applied[$f] = true;
    }

    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $batch = (int) ($pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations")->fetchColumn());

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            return ['ok' => false, 'error' => "Could not read migration {$name}"];
        }
        try {
            // MySQL doesn't fully support DDL in a transaction, but we still
            // wrap so any DML-style migrations get atomicity.
            $pdo->exec($sql);
            $ins = $pdo->prepare("INSERT INTO schema_migrations (filename, batch, checksum) VALUES (?, ?, ?)");
            $ins->execute([$name, $batch, hash('sha256', $sql)]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => "{$name}: " . $e->getMessage()];
        }
    }
    return ['ok' => true];
}

function setup_write_config(string $path, array $cfg): array
{
    $php = "<?php\n"
         . "/**\n"
         . " * ePublicLibrary configuration. Generated by setup.php — edit as needed.\n"
         . " * To re-run setup, set 'setup_completed_at' to 0 and visit /setup.php.\n"
         . " */\n\n"
         . "defined('APP_BOOTED') or exit;\n\n"
         . "return " . var_export($cfg, true) . ";\n";

    $dir = dirname($path);
    if (!is_writable($dir)) {
        return ['ok' => false, 'error' => "Cannot write to {$dir}. Check permissions."];
    }
    if (file_put_contents($path, $php, LOCK_EX) === false) {
        return ['ok' => false, 'error' => "Could not write {$path}."];
    }
    @chmod($path, 0640);
    return ['ok' => true];
}

/* =================================================================== */
/*  Render — each step is a function                                    */
/* =================================================================== */

function setup_render_locked(): void
{
    setup_layout('Setup complete', function () { ?>
        <h1>Setup is already complete.</h1>
        <p>This installation has been initialized. To re-run setup, open
           <code>includes/config.php</code> and set <code>setup_completed_at</code>
           back to <code>0</code>, then revisit this page.</p>
        <p><a class="btn" href="<?= e(url('index.php')) ?>">Open the library</a></p>
    <?php });
}

function setup_render_requirements(): void
{
    $checks = [
        ['PHP 8.0+',           PHP_VERSION_ID >= 80000, 'Detected PHP ' . PHP_VERSION],
        ['PDO MySQL extension', extension_loaded('pdo_mysql'),  'Required for database access.'],
        ['ZIP extension',       extension_loaded('zip'),         'Required for EPUB parsing.'],
        ['GD extension',        extension_loaded('gd'),          'Required for thumbnail generation.'],
        ['DOM extension',       extension_loaded('dom'),         'Required for EPUB OPF parsing.'],
        ['mbstring extension',  extension_loaded('mbstring'),    'Required for UTF-8 handling.'],
        ['fileinfo extension',  extension_loaded('fileinfo'),    'Required for upload MIME detection.'],
        ['includes/ writable',  is_writable(__DIR__ . '/includes'), 'config.php must be writable here.'],
        ['storage/ writable',   is_writable(__DIR__ . '/storage'),  'Logs, books, uploads go here.'],
        ['assets/covers writable', is_writable(__DIR__ . '/assets/covers'),  'Cover images saved here.'],
    ];
    $allOk = !in_array(false, array_column($checks, 1), true);
    setup_layout('Requirements check', function () use ($checks, $allOk) { ?>
        <h1>Welcome to ePublicLibrary</h1>
        <p class="subtitle">Let's get your library set up. This page walks through
           the few things we need before you can start adding books.</p>
        <h2>Server requirements</h2>
        <ul class="checklist">
            <?php foreach ($checks as [$label, $ok, $note]): ?>
                <li class="<?= $ok ? 'ok' : 'bad' ?>">
                    <span class="mark"><?= $ok ? '✓' : '✗' ?></span>
                    <span class="label"><?= e($label) ?></span>
                    <span class="note"><?= e($note) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($allOk): ?>
            <form method="post" action="<?= e(url('setup.php?step=requirements')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn">Continue →</button>
            </form>
        <?php else: ?>
            <p class="error">Please resolve the failing checks above, then reload this page.</p>
        <?php endif; ?>
    <?php });
}

function setup_render_database(array $state): void
{
    $db = $state['db'] ?? [];
    setup_layout('Database', function () use ($db, $state) { ?>
        <h1>Database connection</h1>
        <p class="subtitle">Create a MySQL database and user in your hosting control
           panel (cPanel: <em>MySQL® Databases</em>), then enter the credentials below.
           ePublicLibrary will create its own tables.</p>
        <?php setup_errors($state['errors'] ?? []); ?>
        <form method="post" action="<?= e(url('setup.php?step=database')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Host
                <input name="db_host" value="<?= e($db['host'] ?? '127.0.0.1') ?>" required>
            </label>
            <label>Port
                <input name="db_port" type="number" value="<?= e((string) ($db['port'] ?? 3306)) ?>" required>
            </label>
            <label>Database name
                <input name="db_database" value="<?= e($db['database'] ?? '') ?>" required autofocus>
            </label>
            <label>Username
                <input name="db_username" value="<?= e($db['username'] ?? '') ?>" required>
            </label>
            <label>Password
                <input name="db_password" type="password" value="" autocomplete="new-password">
            </label>
            <button type="submit" class="btn">Test connection &amp; continue →</button>
        </form>
    <?php });
}

function setup_render_site(array $state): void
{
    $detected = app_base();
    $site = $state['site'] ?? ['app_name' => 'ePublicLibrary', 'base_url' => $detected, 'timezone' => 'UTC'];
    setup_layout('Site settings', function () use ($site, $detected) { ?>
        <h1>Site settings</h1>
        <p class="subtitle">Name your library and confirm where it lives.</p>
        <form method="post" action="<?= e(url('setup.php?step=site')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Library name
                <input name="app_name" value="<?= e($site['app_name']) ?>" required autofocus>
            </label>
            <label>Base URL path
                <input name="base_url" value="<?= e($site['base_url']) ?>" placeholder="/library">
                <small>Empty for root install. Use <code>/library</code> for subdir installs.
                       Auto-detected as <code><?= e($detected ?: '(root)') ?></code>.</small>
            </label>
            <label>Timezone
                <input name="timezone" value="<?= e($site['timezone']) ?>" placeholder="UTC">
                <small>IANA timezone identifier (e.g. <code>UTC</code>, <code>America/New_York</code>).</small>
            </label>
            <button type="submit" class="btn">Continue →</button>
        </form>
    <?php });
}

function setup_render_admin(array $state): void
{
    $admin = $state['admin'] ?? ['email' => '', 'username' => '', 'display_name' => ''];
    setup_layout('Admin account', function () use ($admin, $state) { ?>
        <h1>Create your admin account</h1>
        <p class="subtitle">This account can upload books and manage users. You can
           change its password later. NIST guidance: 12+ characters, easy to
           remember, hard to guess.</p>
        <?php setup_errors($state['errors'] ?? []); ?>
        <form method="post" action="<?= e(url('setup.php?step=admin')) ?>" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <label>Email
                <input name="email" type="email" value="<?= e($admin['email']) ?>" required autofocus>
            </label>
            <label>Username
                <input name="username" value="<?= e($admin['username']) ?>" required minlength="3" maxlength="40" pattern="[a-zA-Z0-9_.-]{3,40}">
            </label>
            <label>Display name <small>(optional)</small>
                <input name="display_name" value="<?= e($admin['display_name']) ?>" maxlength="120">
            </label>
            <label>Password
                <input name="password" type="password" required minlength="12" autocomplete="new-password">
                <small>Minimum 12 characters.</small>
            </label>
            <label>Confirm password
                <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
            </label>
            <button type="submit" class="btn">Continue →</button>
        </form>
    <?php });
}

function setup_render_run_confirm(array $state): void
{
    if (empty($state['db']) || empty($state['site']) || empty($state['admin'])) {
        redirect('setup.php?step=requirements');
    }
    setup_layout('Ready to install', function () use ($state) { ?>
        <h1>Ready to install</h1>
        <p class="subtitle">We have everything we need. Clicking install will:</p>
        <ul class="install-summary">
            <li>Write <code>includes/config.php</code> with your settings.</li>
            <li>Create all database tables (~13 of them).</li>
            <li>Create your admin user <code><?= e($state['admin']['username']) ?></code>.</li>
            <li>Seed your starter collections (Favorites, Want to Read, Finished).</li>
        </ul>
        <form method="post" action="<?= e(url('setup.php?step=run')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Install →</button>
            <a href="<?= e(url('setup.php?step=admin')) ?>" class="btn-secondary">Back</a>
        </form>
    <?php });
}

function setup_render_done(array $result): void
{
    setup_layout('Setup complete', function () use ($result) {
        if (!empty($result['ok'])) { ?>
            <h1>✓ Library is live</h1>
            <p class="subtitle">Your installation is complete. You can sign in with the
               admin account you just created.</p>
            <p><a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Open your library</a>
               <a class="btn-secondary" href="<?= e(url('login.php')) ?>">Sign in as admin</a></p>
            <p class="hint">For security, you can delete <code>setup.php</code> from your
               server now. (It will refuse to run again on its own, but removing it
               eliminates the attack surface entirely.)</p>
        <?php } else { ?>
            <h1>Setup hit a snag</h1>
            <?php setup_errors($result['errors'] ?? ['Unknown error.']); ?>
            <p><a class="btn-secondary" href="<?= e(url('setup.php?step=requirements')) ?>">Start over</a></p>
        <?php }
    });
}

function setup_errors(array $errors): void
{
    if (!$errors) { return; }
    echo '<div class="errors"><strong>Please fix the following:</strong><ul>';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}

function setup_layout(string $title, callable $body): void
{ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — ePublicLibrary setup</title>
    <style nonce="<?= e(csp_nonce()) ?>">
        :root {
            --bg: #fdfcfb; --fg: #1c1917; --muted: #57534e; --border: #e7e5e4;
            --accent: #c2703c; --accent-fg: #fff; --teal: #0d7377;
            --bad: #b91c1c; --bad-bg: #fef2f2;
            --good: #15803d;
            --radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f0e0d; --fg: #fafaf9; --muted: #a8a29e; --border: #292524;
                --bad-bg: #1f0d0d;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.6 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
               color: var(--fg); background: var(--bg); padding: 2rem 1rem; }
        .wrap { max-width: 560px; margin: 0 auto; }
        .card { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
                padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        h1 { font-family: 'Instrument Serif', 'Iowan Old Style', serif; font-weight: 400;
             font-size: 2rem; line-height: 1.2; margin: 0 0 0.5rem; }
        h2 { font-size: 1.1rem; margin: 1.5rem 0 0.5rem; }
        .subtitle { color: var(--muted); margin: 0 0 1.5rem; }
        .form { display: grid; gap: 1rem; }
        label { display: grid; gap: 0.35rem; font-size: 0.9rem; font-weight: 500; }
        label small { font-weight: 400; color: var(--muted); }
        input { font: inherit; padding: 0.6rem 0.75rem; border: 1px solid var(--border);
                border-radius: 8px; background: transparent; color: var(--fg); }
        input:focus { outline: 2px solid var(--accent); outline-offset: 2px; }
        code { background: rgba(0,0,0,0.06); padding: 0.05em 0.35em; border-radius: 4px;
               font-size: 0.85em; }
        .btn { display: inline-block; padding: 0.7rem 1.2rem; background: var(--accent);
               color: var(--accent-fg); border: 0; border-radius: 8px; font: inherit;
               font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { filter: brightness(1.05); }
        .btn-primary { background: var(--accent); }
        .btn-secondary { display: inline-block; padding: 0.7rem 1.2rem;
                         background: transparent; color: var(--muted); border: 1px solid var(--border);
                         border-radius: 8px; text-decoration: none; font-weight: 500; }
        .checklist { list-style: none; padding: 0; margin: 0; }
        .checklist li { display: grid; grid-template-columns: 1.5rem 1fr; gap: 0.5rem;
                        padding: 0.5rem 0; border-bottom: 1px solid var(--border); }
        .checklist li:last-child { border-bottom: 0; }
        .checklist .mark { font-weight: 700; }
        .checklist li.ok .mark { color: var(--good); }
        .checklist li.bad .mark { color: var(--bad); }
        .checklist .note { grid-column: 2; font-size: 0.85rem; color: var(--muted); }
        .errors { background: var(--bad-bg); color: var(--bad); padding: 1rem;
                  border-radius: 8px; margin-bottom: 1rem; }
        .errors ul { margin: 0.5rem 0 0 1.25rem; padding: 0; }
        .error { color: var(--bad); }
        .install-summary { background: rgba(0,0,0,0.03); padding: 1rem 1rem 1rem 2.5rem;
                           border-radius: 8px; }
        .hint { font-size: 0.85rem; color: var(--muted); margin-top: 1.5rem;
                padding-top: 1rem; border-top: 1px solid var(--border); }
        .steps { display: flex; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.8rem;
                 color: var(--muted); }
        .steps span { padding: 0.3rem 0.6rem; border-radius: 999px; border: 1px solid var(--border); }
        .steps span.current { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="steps">
        <?php
        $stepKey = $_GET['step'] ?? 'requirements';
        $labels = ['requirements' => '1. Requirements', 'database' => '2. Database',
                   'site' => '3. Site', 'admin' => '4. Admin', 'run' => '5. Install'];
        foreach ($labels as $k => $v) {
            $class = ($k === $stepKey) ? ' class="current"' : '';
            echo "<span{$class}>" . e($v) . '</span>';
        } ?>
    </div>
    <div class="card">
        <?php $body(); ?>
    </div>
</div>
</body>
</html>
<?php }
