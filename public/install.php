<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | MikroPanel Web Installer
 |--------------------------------------------------------------------------
 | Place this file at: public/install.php
 | It is designed to be used with deploy/bootstrap-vps.sh.
 | The bootstrap script creates a one-time token and temporary DB credentials.
 */

session_start();

$root = dirname(__DIR__);
$storageDir = $root . '/storage/app';
$bootstrapFile = $storageDir . '/installer-bootstrap.json';
$lockFile = $storageDir . '/mikropanel-installed.lock';
$uploadDir = $storageDir . '/install-uploads';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderPage(string $title, string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' - MikroPanel Installer</title>';
    echo <<<'CSS'
<style>
:root{color-scheme:light;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#0f172a}.wrap{max-width:920px;margin:0 auto;padding:36px 18px 70px}.brand{display:flex;align-items:center;gap:14px;margin-bottom:24px}.logo{width:48px;height:48px;border-radius:14px;background:#0f172a;color:white;display:grid;place-items:center;font-weight:900;font-size:20px}.brand h1{font-size:24px;margin:0}.brand p{margin:4px 0 0;color:#64748b}.card{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 12px 35px rgba(15,23,42,.06);margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:800;margin:0 0 7px;color:#334155}input,select{width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;background:white;color:#0f172a}input:focus,select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:12px 18px;background:#0f172a;color:white;font-weight:800;cursor:pointer;text-decoration:none}.btn.blue{background:#2563eb}.btn.green{background:#15803d}.muted{color:#64748b;font-size:14px;line-height:1.6}.ok{color:#166534}.bad{color:#b91c1c}.warn{color:#92400e}.alert{border-radius:12px;padding:14px 16px;margin:14px 0;line-height:1.55}.alert.ok{background:#f0fdf4;border:1px solid #bbf7d0}.alert.bad{background:#fef2f2;border:1px solid #fecaca}.alert.warn{background:#fffbeb;border:1px solid #fde68a}.checks{list-style:none;padding:0;margin:0}.checks li{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #f1f5f9}.checks li:last-child{border-bottom:0}.pill{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:900}.pill.ok{background:#dcfce7}.pill.bad{background:#fee2e2}.steps{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:18px}.step{font-size:12px;font-weight:800;padding:7px 10px;border-radius:999px;background:#e2e8f0;color:#475569}.step.on{background:#dbeafe;color:#1d4ed8}.code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#0f172a;color:#e2e8f0;border-radius:10px;padding:12px;overflow:auto}.actions{display:flex;gap:10px;align-items:center;margin-top:18px}.small{font-size:12px}.hidden{display:none}@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.card{padding:18px}}
</style>
CSS;
    echo '</head><body><div class="wrap"><div class="brand"><div class="logo">MP</div><div><h1>MikroPanel Installer</h1><p>Secure browser-based application setup</p></div></div>';
    echo $body;
    echo '</div></body></html>';
    exit;
}

function bootstrapConfig(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function requireCsrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        renderPage('Security check failed', '<div class="card"><div class="alert bad">Invalid or expired installer session. Reload the installer and try again.</div></div>', 419);
    }
}

function envQuote(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_\.\-:\/]+$/', $value)) {
        return $value;
    }

    return '"' . str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', '\\n'], $value) . '"';
}

function envSet(string $content, string $key, string $value): string
{
    $line = $key . '=' . envQuote($value);
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

    if (preg_match($pattern, $content)) {
        return (string) preg_replace($pattern, $line, $content, 1);
    }

    return rtrim($content) . PHP_EOL . $line . PHP_EOL;
}

function commandAvailable(string $name): bool
{
    $path = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    foreach (explode(':', $path) as $dir) {
        if (is_file($dir . '/' . $name) && is_executable($dir . '/' . $name)) {
            return true;
        }
    }
    return false;
}

function runCommand(array $command, string $cwd, array $env = []): array
{
    if (!function_exists('proc_open')) {
        return [1, '', 'proc_open is disabled'];
    }

    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $spec, $pipes, $cwd, array_merge($_ENV, $env));
    if (!is_resource($process)) {
        return [1, '', 'Unable to start process'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return [$code, $stdout, $stderr];
}

function importSqlDump(string $file, array $db, string $cwd): array
{
    if (!function_exists('proc_open')) {
        return [1, 'proc_open is disabled'];
    }

    if (!commandAvailable('mysql')) {
        return [1, 'mysql client is not installed'];
    }

    $command = [
        'mysql',
        '--protocol=TCP',
        '--host=' . $db['host'],
        '--port=' . $db['port'],
        '--user=' . $db['username'],
        '--database=' . $db['database'],
        '--default-character-set=utf8mb4',
    ];

    $spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = array_merge($_ENV, [
        'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'MYSQL_PWD' => $db['password'],
    ]);
    $process = proc_open($command, $spec, $pipes, $cwd, $env);
    if (!is_resource($process)) {
        return [1, 'Unable to launch mysql client'];
    }

    $isGzip = str_ends_with(strtolower($file), '.gz');
    $input = $isGzip ? gzopen($file, 'rb') : fopen($file, 'rb');

    if ($input === false) {
        proc_terminate($process);
        return [1, 'Unable to open uploaded database file'];
    }

    try {
        while (!feof($input)) {
            $chunk = $isGzip ? gzread($input, 1024 * 1024) : fread($input, 1024 * 1024);
            if ($chunk === false) {
                throw new RuntimeException('Unable to read database backup');
            }
            if ($chunk !== '') {
                fwrite($pipes[0], $chunk);
            }
        }
    } catch (Throwable $e) {
        if ($isGzip) {
            gzclose($input);
        } else {
            fclose($input);
        }
        fclose($pipes[0]);
        proc_terminate($process);
        return [1, $e->getMessage()];
    }

    if ($isGzip) {
        gzclose($input);
    } else {
        fclose($input);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return [$code, trim($stdout . "\n" . $stderr)];
}

function parseUploadedEnv(string $file): array
{
    $result = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $result[trim($key)] = $value;
    }
    return $result;
}

function bootLaravel(string $root): void
{
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

if (is_file($lockFile)) {
    renderPage('Already installed', '<div class="card"><div class="alert ok"><strong>MikroPanel is already installed.</strong><br>The web installer is locked.</div><a class="btn" href="/login">Go to Login</a></div>', 403);
}

$config = bootstrapConfig($bootstrapFile);
if ($config === null) {
    $body = '<div class="card"><h2>Bootstrap required</h2><p class="muted">A blank VPS cannot be configured by browser code before Nginx/PHP/MySQL exist. Run the included <code>deploy/bootstrap-vps.sh</code> once as root. It will install the system packages, create temporary database credentials, configure port 80, and print a one-time installer URL.</p><div class="code">sudo bash deploy/bootstrap-vps.sh /var/www/mikropanel</div></div>';
    renderPage('Bootstrap required', $body, 503);
}

$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (!($_SESSION['installer_authorized'] ?? false)) {
    if ($providedToken === '' || !hash_equals((string) ($config['token'] ?? ''), $providedToken)) {
        renderPage('Installer access denied', '<div class="card"><div class="alert bad">Invalid installer token. Use the exact one-time URL printed by the VPS bootstrap script.</div></div>', 403);
    }
    $_SESSION['installer_authorized'] = true;
    $_SESSION['installer_token'] = $providedToken;
}

$token = (string) ($_SESSION['installer_token'] ?? '');
$step = (string) ($_GET['step'] ?? 'check');

$checks = [
    'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Mbstring' => extension_loaded('mbstring'),
    'OpenSSL' => extension_loaded('openssl'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'cURL' => extension_loaded('curl'),
    'Vendor dependencies' => is_file($root . '/vendor/autoload.php'),
    'Frontend build' => is_file($root . '/public/build/manifest.json'),
    'storage writable' => is_writable($root . '/storage'),
    'bootstrap/cache writable' => is_writable($root . '/bootstrap/cache'),
    '.env writable' => is_file($root . '/.env') && is_writable($root . '/.env'),
    'proc_open enabled' => function_exists('proc_open'),
    'mysql client' => commandAvailable('mysql'),
];

$allChecksOk = !in_array(false, $checks, true);

if ($step === 'check') {
    $items = '';
    foreach ($checks as $label => $ok) {
        $items .= '<li><span>' . h($label) . '</span><span class="pill ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'PASS' : 'FAIL') . '</span></li>';
    }

    $next = $allChecksOk
        ? '<a class="btn blue" href="?step=setup&amp;token=' . rawurlencode($token) . '">Continue</a>'
        : '<div class="alert bad">One or more required checks failed. Fix them before continuing.</div>';

    $body = '<div class="steps"><span class="step on">1. System Check</span><span class="step">2. Setup</span><span class="step">3. Install</span></div><div class="card"><h2>Server pre-flight</h2><ul class="checks">' . $items . '</ul><div class="actions">' . $next . '</div></div>';
    renderPage('System check', $body);
}

if (!$allChecksOk) {
    header('Location: ?step=check&token=' . rawurlencode($token));
    exit;
}

if ($step === 'setup' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $defaultUrl = (string) ($config['app_url'] ?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $maxUpload = ini_get('upload_max_filesize') ?: 'unknown';

    $body = '<div class="steps"><span class="step">1. System Check</span><span class="step on">2. Setup</span><span class="step">3. Install</span></div>';
    $body .= '<form class="card" method="post" action="?step=install&amp;token=' . rawurlencode($token) . '" enctype="multipart/form-data">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="token" value="' . h($token) . '">';
    $body .= '<h2>Installation mode</h2><div class="grid"><div><label>Mode</label><select name="mode" id="mode"><option value="fresh">Fresh installation</option><option value="restore">Restore existing MikroPanel database</option></select></div><div><label>Timezone</label><input name="timezone" value="Asia/Qatar" required></div><div class="full"><label>Application URL</label><input name="app_url" value="' . h($defaultUrl) . '" required></div></div>';
    $body .= '<div id="restoreBox" class="alert warn hidden"><strong>Restore mode:</strong> Upload your <code>database.sql.gz</code> or <code>.sql</code> backup. Uploading the old <code>.env</code> is strongly recommended so the original APP_KEY can be preserved.<br><span class="small">Current PHP upload limit: ' . h($maxUpload) . '</span><div class="grid" style="margin-top:12px"><div><label>Database backup (.sql or .sql.gz)</label><input type="file" name="database_backup" accept=".sql,.gz"></div><div><label>Old .env backup (recommended)</label><input type="file" name="old_env"></div></div></div>';
    $body .= '<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0"><h2>Administrator</h2><p class="muted">Fresh install: required. Restore: fill these fields only if you want to create/reset an administrator account.</p><div class="grid"><div><label>Admin name</label><input name="admin_name" value="Administrator"></div><div><label>Admin email</label><input name="admin_email" type="email"></div><div><label>Admin password</label><input name="admin_password" type="password" minlength="10"></div><div><label>Confirm password</label><input name="admin_password_confirmation" type="password" minlength="10"></div></div>';
    $body .= '<div class="actions"><button class="btn green" type="submit">Install MikroPanel</button></div></form>';
    $body .= <<<'JS'
<script>
const mode=document.getElementById('mode');const box=document.getElementById('restoreBox');
function sync(){box.classList.toggle('hidden',mode.value!=='restore')}mode.addEventListener('change',sync);sync();
</script>
JS;
    renderPage('Setup', $body);
}

if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    set_time_limit(0);

    $mode = (string) ($_POST['mode'] ?? 'fresh');
    $appUrl = trim((string) ($_POST['app_url'] ?? ''));
    $timezone = trim((string) ($_POST['timezone'] ?? 'Asia/Qatar'));
    $adminName = trim((string) ($_POST['admin_name'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminConfirmation = (string) ($_POST['admin_password_confirmation'] ?? '');

    $errors = [];
    if (!in_array($mode, ['fresh', 'restore'], true)) {
        $errors[] = 'Invalid installation mode.';
    }
    if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Enter a valid application URL.';
    }
    if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
        $errors[] = 'Enter a valid PHP timezone.';
    }
    if ($mode === 'fresh') {
        if ($adminName === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Admin name and valid email are required for a fresh installation.';
        }
        if (strlen($adminPassword) < 10 || $adminPassword !== $adminConfirmation) {
            $errors[] = 'Admin password must be at least 10 characters and both password fields must match.';
        }
    } elseif ($adminPassword !== '' || $adminEmail !== '' || $adminName !== '') {
        if ($adminName === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'To create/reset an admin during restore, provide name, valid email, and password.';
        }
        if (strlen($adminPassword) < 10 || $adminPassword !== $adminConfirmation) {
            $errors[] = 'Admin password must be at least 10 characters and both password fields must match.';
        }
    }

    if ($mode === 'restore' && (empty($_FILES['database_backup']) || (int) $_FILES['database_backup']['error'] !== UPLOAD_ERR_OK)) {
        $errors[] = 'Restore mode requires a database .sql or .sql.gz backup.';
    }

    if ($errors) {
        $html = '<div class="card"><div class="alert bad"><strong>Installation stopped.</strong><ul>';
        foreach ($errors as $error) {
            $html .= '<li>' . h($error) . '</li>';
        }
        $html .= '</ul></div><a class="btn" href="?step=setup&amp;token=' . rawurlencode($token) . '">Back</a></div>';
        renderPage('Validation error', $html, 422);
    }

    $db = [
        'host' => (string) $config['db_host'],
        'port' => (string) $config['db_port'],
        'database' => (string) $config['db_database'],
        'username' => (string) $config['db_username'],
        'password' => (string) $config['db_password'],
    ];

    try {
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0700, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create installer upload directory.');
        }

        $envPath = $root . '/.env';
        $env = file_get_contents($envPath);
        if ($env === false) {
            throw new RuntimeException('Unable to read .env.');
        }

        $appKey = '';
        if ($mode === 'restore' && !empty($_FILES['old_env']) && (int) $_FILES['old_env']['error'] === UPLOAD_ERR_OK) {
            $oldEnvPath = $uploadDir . '/old-env-' . bin2hex(random_bytes(6));
            if (!move_uploaded_file($_FILES['old_env']['tmp_name'], $oldEnvPath)) {
                throw new RuntimeException('Unable to save uploaded old .env file.');
            }
            $oldEnv = parseUploadedEnv($oldEnvPath);
            $appKey = trim((string) ($oldEnv['APP_KEY'] ?? ''));
            @unlink($oldEnvPath);
            if ($appKey === '') {
                throw new RuntimeException('The uploaded old .env does not contain APP_KEY.');
            }
        }

        if ($appKey === '') {
            $appKey = 'base64:' . base64_encode(random_bytes(32));
        }

        $env = envSet($env, 'APP_NAME', 'MikroPanel');
        $env = envSet($env, 'APP_ENV', 'production');
        $env = envSet($env, 'APP_KEY', $appKey);
        $env = envSet($env, 'APP_DEBUG', 'false');
        $env = envSet($env, 'APP_URL', rtrim($appUrl, '/'));
        $env = envSet($env, 'APP_TIMEZONE', $timezone);
        $env = envSet($env, 'DB_CONNECTION', 'mysql');
        $env = envSet($env, 'DB_HOST', $db['host']);
        $env = envSet($env, 'DB_PORT', $db['port']);
        $env = envSet($env, 'DB_DATABASE', $db['database']);
        $env = envSet($env, 'DB_USERNAME', $db['username']);
        $env = envSet($env, 'DB_PASSWORD', $db['password']);

        if (file_put_contents($envPath, $env, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write .env.');
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
                $db['username'],
                $db['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->query('SELECT 1');
        } catch (Throwable $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        if ($mode === 'restore') {
            $originalName = (string) $_FILES['database_backup']['name'];
            $lower = strtolower($originalName);
            if (!str_ends_with($lower, '.sql') && !str_ends_with($lower, '.sql.gz')) {
                throw new RuntimeException('Database backup must end in .sql or .sql.gz.');
            }
            $dumpPath = $uploadDir . '/database-' . bin2hex(random_bytes(6)) . (str_ends_with($lower, '.gz') ? '.sql.gz' : '.sql');
            if (!move_uploaded_file($_FILES['database_backup']['tmp_name'], $dumpPath)) {
                throw new RuntimeException('Unable to save uploaded database backup.');
            }

            [$importCode, $importOutput] = importSqlDump($dumpPath, $db, $root);
            @unlink($dumpPath);
            if ($importCode !== 0) {
                throw new RuntimeException('Database import failed: ' . $importOutput);
            }
        }

        bootLaravel($root);

        Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $migrateCode = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        if ($migrateCode !== 0) {
            throw new RuntimeException('Laravel migration failed: ' . Illuminate\Support\Facades\Artisan::output());
        }

        if ($mode === 'fresh' || $adminPassword !== '') {
            $user = App\Models\User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'email_verified_at' => now(),
                    'password' => Illuminate\Support\Facades\Hash::make($adminPassword),
                    'role' => 'admin',
                    'permissions' => [],
                    'is_active' => true,
                ]
            );
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Illuminate\Support\Facades\Artisan::call('storage:link');
        Illuminate\Support\Facades\Artisan::call('optimize:clear');
        Illuminate\Support\Facades\Artisan::call('config:cache');
        Illuminate\Support\Facades\Artisan::call('route:cache');
        Illuminate\Support\Facades\Artisan::call('view:cache');

        $lockPayload = json_encode([
            'installed_at' => date(DATE_ATOM),
            'mode' => $mode,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($lockFile, $lockPayload . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Installation completed but installer lock could not be created.');
        }
        @chmod($lockFile, 0640);
        @unlink($bootstrapFile);

        $body = '<div class="steps"><span class="step">1. System Check</span><span class="step">2. Setup</span><span class="step on">3. Complete</span></div><div class="card"><div class="alert ok"><strong>MikroPanel installation completed successfully.</strong><br>The installer is now locked.</div><p class="muted">The VPS finalizer will tighten .env permissions automatically. Open the panel and verify Router Ping/Sync, scheduler, invoice/PDF, and backup status.</p><div class="actions"><a class="btn green" href="/login">Open Login</a></div></div>';
        renderPage('Installation complete', $body);
    } catch (Throwable $e) {
        $message = h($e->getMessage());
        $body = '<div class="card"><div class="alert bad"><strong>Installation failed.</strong><br>' . $message . '</div><p class="muted">No destructive reset command is executed by this installer. Fix the reported issue and run the installer again.</p><a class="btn" href="?step=setup&amp;token=' . rawurlencode($token) . '">Back</a></div>';
        renderPage('Installation failed', $body, 500);
    }
}

header('Location: ?step=check&token=' . rawurlencode($token));
exit;
