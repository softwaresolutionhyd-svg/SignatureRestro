<?php
/**
 * Recreate hosting .env for an EXISTING database (no migrate/seed).
 * Visit: https://signature.softwaresolutions.pk/restore-env.php
 * Delete this file after success.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$base = dirname(__DIR__);
$envFile = $base.DIRECTORY_SEPARATOR.'.env';
$envExample = $base.DIRECTORY_SEPARATOR.'.env.example';
$error = '';
$success = '';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function env_line(string $key, string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    if ($value === '' || preg_match('/^[A-Za-z0-9_.@\\/-]+$/', $value)) {
        return $key.'='.$value;
    }

    return $key.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function replace_or_append_env(string $content, string $key, string $value): string
{
    $line = env_line($key, $value);
    if (preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
        return (string) preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content);
    }

    return rtrim($content)."\n".$line."\n";
}

if (is_file($envFile) && empty($_POST['force'])) {
    $success = '.env already exists. Site should work — open /login. If you need to overwrite, submit with Force checked.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
    $dbName = trim((string) ($_POST['db_database'] ?? ''));
    $dbUser = trim((string) ($_POST['db_username'] ?? ''));
    $dbPass = (string) ($_POST['db_password'] ?? '');
    $syncToken = trim((string) ($_POST['sync_token'] ?? 'SignatureSync_ChangeMe_2026'));

    if ($dbName === '' || $dbUser === '') {
        $error = 'Database name aur username zaroori hain (cPanel → MySQL).';
    } else {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');
        } catch (Throwable $e) {
            $error = 'DB connect fail: '.$e->getMessage();
            $pdo = null;
        }

        if ($error === '') {
            $env = is_file($envExample) ? (string) file_get_contents($envExample) : "APP_NAME=Signature\n";
            $key = 'base64:'.base64_encode(random_bytes(32));
            $env = replace_or_append_env($env, 'APP_NAME', 'Signature');
            $env = replace_or_append_env($env, 'APP_ENV', 'production');
            $env = replace_or_append_env($env, 'APP_KEY', $key);
            $env = replace_or_append_env($env, 'APP_DEBUG', 'false');
            $env = replace_or_append_env($env, 'APP_URL', 'https://signature.softwaresolutions.pk');
            $env = replace_or_append_env($env, 'DB_CONNECTION', 'mysql');
            $env = replace_or_append_env($env, 'DB_HOST', $dbHost);
            $env = replace_or_append_env($env, 'DB_PORT', $dbPort);
            $env = replace_or_append_env($env, 'DB_DATABASE', $dbName);
            $env = replace_or_append_env($env, 'DB_USERNAME', $dbUser);
            $env = replace_or_append_env($env, 'DB_PASSWORD', $dbPass);
            $env = replace_or_append_env($env, 'LOG_LEVEL', 'error');
            $env = replace_or_append_env($env, 'SYNC_ENABLED', 'true');
            $env = replace_or_append_env($env, 'SYNC_ROLE', 'cloud');
            $env = replace_or_append_env($env, 'SYNC_TOKEN', $syncToken);
            $env = replace_or_append_env($env, 'SYNC_REMOTE_URL', '');
            $env = replace_or_append_env($env, 'SESSION_SECURE_COOKIE', 'true');

            if (@file_put_contents($envFile, $env) === false) {
                $error = '.env write fail — folder permissions check karein.';
            } else {
                $success = '.env ban gaya (existing DB, no seed). Ab /login kholo. Phir is file ko delete kar do.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Restore hosting .env</title>
    <style>
        body{font-family:system-ui,sans-serif;max-width:520px;margin:2rem auto;padding:0 1rem}
        label{display:block;margin:.75rem 0 .25rem;font-weight:600}
        input{width:100%;padding:.5rem;box-sizing:border-box}
        button{margin-top:1rem;padding:.6rem 1rem}
        .err{color:#b91c1c;background:#fef2f2;padding:.75rem;border-radius:8px}
        .ok{color:#166534;background:#f0fdf4;padding:.75rem;border-radius:8px}
        .hint{color:#64748b;font-size:.9rem}
    </style>
</head>
<body>
<h1>Restore .env</h1>
<p class="hint">Hosting files wipe ke baad .env missing thi. Existing MySQL use karo — <strong>install.php mat chalao</strong> (woh seed karke data kharab kar sakta hai).</p>
<?php if ($error !== ''): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="ok"><?= h($success) ?></p><?php endif; ?>
<form method="post">
    <label>DB host</label>
    <input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>">
    <label>DB port</label>
    <input name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>">
    <label>DB database (cPanel name)</label>
    <input name="db_database" value="<?= h($_POST['db_database'] ?? '') ?>" required>
    <label>DB username</label>
    <input name="db_username" value="<?= h($_POST['db_username'] ?? '') ?>" required>
    <label>DB password</label>
    <input name="db_password" type="password" value="">
    <label>SYNC token (local jaisa)</label>
    <input name="sync_token" value="<?= h($_POST['sync_token'] ?? 'SignatureSync_ChangeMe_2026') ?>">
    <label><input type="checkbox" name="force" value="1"> Force overwrite if .env exists</label>
    <button type="submit">Create .env</button>
</form>
</body>
</html>
