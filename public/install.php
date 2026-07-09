<?php

/**
 * Web installer — visit /install.php once. After success this file deletes itself.
 * Requires: vendor/ (composer install), writable storage/ and bootstrap/cache/
 */

declare(strict_types=1);

session_start();

$base = dirname(__DIR__);
$public = __DIR__;
$lockFile = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'installer.lock';
$envExample = $base.DIRECTORY_SEPARATOR.'.env.example';
$envFile = $base.DIRECTORY_SEPARATOR.'.env';

if (is_file($lockFile)) {
    http_response_code(404);
    exit('Not found.');
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function env_line(string $key, string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    if ($value === '' || preg_match('/^[A-Za-z0-9_.@-]+$/', $value)) {
        return $key.'='.$value;
    }

    return $key.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function replace_or_append_env(string $content, string $key, string $value): string
{
    $line = env_line($key, $value);
    if (preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
        return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content);
    }

    return rtrim($content)."\n".$line."\n";
}

function php_cli_binary(): string
{
    $b = PHP_BINARY ?: '';
    if ($b !== '' && stripos($b, 'php-cgi') !== false) {
        $alt = dirname($b).DIRECTORY_SEPARATOR.(stripos(PHP_OS, 'WIN') === 0 ? 'php.exe' : 'php');
        if (is_file($alt)) {
            return $alt;
        }

        return 'php';
    }

    return $b !== '' ? $b : 'php';
}

/**
 * @return array{ok: bool, out: string, code: int}
 */
function run_command(string $cmd, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (! is_resource($proc)) {
        return ['ok' => false, 'out' => 'proc_open failed', 'code' => -1];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $out = trim(($stdout ?: '').($stderr ? "\n".$stderr : ''));

    return ['ok' => $code === 0, 'out' => $out, 'code' => $code];
}

function check_extensions(): array
{
    $need = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'];
    $missing = [];
    foreach ($need as $ext) {
        if (! extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }

    return $missing;
}

$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$error = '';
$success = '';

// ── POST: run install ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_install'])) {
    if (! is_file($envExample)) {
        $error = '.env.example missing — invalid package.';
    } elseif (! is_file($base.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
        $error = 'vendor/ missing. Is project root par `composer install --no-dev` chalayein, phir dubara try karein.';
    } else {
        $appName = trim((string) ($_POST['app_name'] ?? 'Stair'));
        $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
        $dbName = trim((string) ($_POST['db_database'] ?? ''));
        $dbUser = trim((string) ($_POST['db_username'] ?? ''));
        $dbPass = (string) ($_POST['db_password'] ?? '');
        $production = ! empty($_POST['production']);
        $enableSync = ! empty($_POST['enable_sync']);
        $syncToken = trim((string) ($_POST['sync_token'] ?? ''));
        $syncRole = trim((string) ($_POST['sync_role'] ?? 'cloud'));

        if ($appUrl === '' || $dbName === '' || $dbUser === '') {
            $error = 'APP URL, database name aur username zaroori hain.';
        } elseif (! preg_match('#^https?://#i', $appUrl)) {
            $error = 'APP URL http:// ya https:// se shuru hona chahiye.';
        } elseif (strlen($dbName) > 64 || strpbrk($dbName, ";\0\n\r\"'\\") !== false) {
            $error = 'Database name invalid (max 64 chars; semicolon / quotes / slashes allow nahi).';
        } elseif ($enableSync && $syncToken === '') {
            $error = 'Cloud sync on hai to SYNC token zaroori hai (local PC aur hosting par same hona chahiye).';
        } elseif ($enableSync && ! in_array($syncRole, ['local', 'cloud'], true)) {
            $error = 'Sync role local ya cloud hona chahiye.';
        } else {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $env = is_file($envFile) ? file_get_contents($envFile) : file_get_contents($envExample);
            if ($env === false) {
                $env = '';
            }

            $env = replace_or_append_env($env, 'APP_NAME', $appName);
            $env = replace_or_append_env($env, 'APP_ENV', $production ? 'production' : 'local');
            $env = replace_or_append_env($env, 'APP_KEY', $key);
            $env = replace_or_append_env($env, 'APP_DEBUG', $production ? 'false' : 'true');
            $env = replace_or_append_env($env, 'APP_URL', $appUrl);
            $env = replace_or_append_env($env, 'DB_CONNECTION', 'mysql');
            $env = replace_or_append_env($env, 'DB_HOST', $dbHost);
            $env = replace_or_append_env($env, 'DB_PORT', $dbPort);
            $env = replace_or_append_env($env, 'DB_DATABASE', $dbName);
            $env = replace_or_append_env($env, 'DB_USERNAME', $dbUser);
            $env = replace_or_append_env($env, 'DB_PASSWORD', $dbPass);
            $env = replace_or_append_env($env, 'LOG_LEVEL', $production ? 'error' : 'debug');
            $env = replace_or_append_env($env, 'SYNC_ENABLED', $enableSync ? 'true' : 'false');
            $env = replace_or_append_env($env, 'SYNC_ROLE', $enableSync ? $syncRole : 'cloud');
            $env = replace_or_append_env($env, 'SYNC_TOKEN', $enableSync ? $syncToken : '');
            $env = replace_or_append_env($env, 'SYNC_REMOTE_URL', '');
            if ($production) {
                $env = replace_or_append_env($env, 'SESSION_SECURE_COOKIE', 'true');
            }

            if (@file_put_contents($envFile, $env) === false) {
                $error = '.env likh nahi sakay — project root permissions check karein.';
            } else {
                $php = php_cli_binary();
                $artisan = escapeshellarg($base.DIRECTORY_SEPARATOR.'artisan');

                $migrate = run_command($php.' '.$artisan.' migrate --force', $base);
                if (! $migrate['ok']) {
                    $error = "migrate fail (code {$migrate['code']}):\n".h($migrate['out']);
                } else {
                    $seed = run_command($php.' '.$artisan.' db:seed --force', $base);
                    if (! $seed['ok']) {
                        $error = "db:seed fail (code {$seed['code']}):\n".h($seed['out']);
                    } else {
                        run_command($php.' '.$artisan.' storage:link', $base);
                        if ($production) {
                            run_command($php.' '.$artisan.' config:cache', $base);
                            run_command($php.' '.$artisan.' route:cache', $base);
                        }

                        $appStorage = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app';
                        if (! is_dir($appStorage)) {
                            @mkdir($appStorage, 0755, true);
                        }
                        @file_put_contents($lockFile, date('c').' installed via web installer');

                        $success = 'Install ho gaya. Login: admin@example.com / admin12345 — superadmin / admin12345';
                        register_shutdown_function(static function () use ($public) {
                            @unlink($public.DIRECTORY_SEPARATOR.'install.php');
                        });
                        $step = 4;
                    }
                }
            }
        }
    }
}

$missingExt = check_extensions();
$vendorOk = is_file($base.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
$writableStorage = is_writable($base.DIRECTORY_SEPARATOR.'storage');
$writableBootstrapCache = is_writable($base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');
$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$canProc = function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);

?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install — Stair</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width: 560px;">
    <div class="card shadow">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Stair — Web installer</h1>

            <?php if ($step === 4 && $success !== ''): ?>
                <div class="alert alert-success"><?= nl2br(h($success)) ?></div>
                <p class="small text-secondary mb-0">Installer file delete ho chuka hoga. Ab <a href="/login">login</a> par jayein.</p>
            <?php else: ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><pre class="small mb-0" style="white-space:pre-wrap;"><?= $error ?></pre></div>
                <?php endif; ?>

                <h2 class="h6 text-secondary mb-3">Step 1 — Server check</h2>
                <ul class="small mb-4">
                    <li>PHP 8.1+: <?= $phpOk ? '<span class="text-success">OK</span> ('.h(PHP_VERSION).')' : '<span class="text-danger">Fail</span>' ?></li>
                    <li>Extensions: <?= count($missingExt) === 0 ? '<span class="text-success">OK</span>' : '<span class="text-danger">Missing: '.h(implode(', ', $missingExt)).'</span>' ?></li>
                    <li>vendor/: <?= $vendorOk ? '<span class="text-success">OK</span>' : '<span class="text-danger">Missing — composer install chalayein</span>' ?></li>
                    <li>storage writable: <?= $writableStorage ? '<span class="text-success">OK</span>' : '<span class="text-danger">Fail</span>' ?></li>
                    <li>bootstrap/cache writable: <?= $writableBootstrapCache ? '<span class="text-success">OK</span>' : '<span class="text-danger">Fail</span>' ?></li>
                    <li>proc_open: <?= $canProc ? '<span class="text-success">OK</span>' : '<span class="text-danger">Disabled — host par CLI allow karein</span>' ?></li>
                </ul>

                <?php if (! $phpOk || count($missingExt) > 0 || ! $vendorOk || ! $writableStorage || ! $writableBootstrapCache || ! $canProc): ?>
                    <p class="text-danger small">Pehle upar wali cheezein theek karein, phir page refresh karein.</p>
                <?php else: ?>
                    <h2 class="h6 text-secondary mb-3">Step 2 — Database &amp; app</h2>
                    <p class="small text-secondary">Pehle MySQL mein <strong>khali database</strong> bana lein.</p>
                    <form method="post" action="install.php">
                        <input type="hidden" name="do_install" value="1">
                        <div class="mb-2">
                            <label class="form-label">App name</label>
                            <input class="form-control" name="app_name" value="<?= h($_POST['app_name'] ?? 'Stair') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">APP URL (no trailing slash)</label>
                            <input class="form-control" name="app_url" placeholder="http://localhost/Softwaresolution/public" value="<?= h($_POST['app_url'] ?? '') ?>" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-8 mb-2">
                                <label class="form-label">DB host</label>
                                <input class="form-control" name="db_host" value="<?= h($_POST['db_host'] ?? '127.0.0.1') ?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Port</label>
                                <input class="form-control" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Database name</label>
                            <input class="form-control" name="db_database" value="<?= h($_POST['db_database'] ?? '') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">DB username</label>
                            <input class="form-control" name="db_username" value="<?= h($_POST['db_username'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">DB password</label>
                            <input class="form-control" type="password" name="db_password" value="<?= h($_POST['db_password'] ?? '') ?>" autocomplete="new-password">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="production" value="1" id="prod" <?= ! empty($_POST['production']) || ! isset($_POST['do_install']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="prod">Production mode (APP_DEBUG off, caches)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="enable_sync" value="1" id="sync" <?= ! empty($_POST['enable_sync']) || ! isset($_POST['do_install']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sync">Online/offline cloud sync enable</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Sync role</label>
                            <select class="form-select" name="sync_role">
                                <option value="cloud" <?= ($_POST['sync_role'] ?? 'cloud') === 'cloud' ? 'selected' : '' ?>>cloud (hosting — data receive)</option>
                                <option value="local" <?= ($_POST['sync_role'] ?? '') === 'local' ? 'selected' : '' ?>>local (cafe PC — data push)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sync token (local + hosting same)</label>
                            <input class="form-control" name="sync_token" value="<?= h($_POST['sync_token'] ?? 'SignatureSync_ChangeMe_2026') ?>" autocomplete="off">
                            <div class="form-text">Local PC ke <code>.env</code> mein bhi yahi token hona chahiye.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Install</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <p class="small text-secondary text-center mt-3 mb-0">Document root <code>public/</code> hona chahiye. Install ke baad yeh page khud delete ho jata hai.</p>
</div>
</body>
</html>
