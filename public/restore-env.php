<?php

/**
 * Create hosting .env when missing (empty-folder disaster recovery).
 * Open: https://signature.softwaresolutions.pk/restore-env.php
 * DELETE after .env exists.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$base = dirname(__DIR__);
$envPath = $base.DIRECTORY_SEPARATOR.'.env';
$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appKey = trim((string) ($_POST['app_key'] ?? ''));
    $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
    $dbName = trim((string) ($_POST['db_database'] ?? ''));
    $dbUser = trim((string) ($_POST['db_username'] ?? ''));
    $dbPass = (string) ($_POST['db_password'] ?? '');
    $syncToken = trim((string) ($_POST['sync_token'] ?? 'SignatureSync_ChangeMe_2026'));

    if ($appKey === '' || $dbName === '' || $dbUser === '') {
        $error = 'APP_KEY, DB name aur DB username zaroori hain.';
    } else {
        if (! str_starts_with($appKey, 'base64:')) {
            $appKey = 'base64:'.$appKey;
        }
        $lines = [
            'APP_NAME=Signature',
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_URL=https://signature.softwaresolutions.pk',
            'APP_KEY='.$appKey,
            '',
            'LOG_CHANNEL=stack',
            'LOG_LEVEL=error',
            '',
            'DB_CONNECTION=mysql',
            'DB_HOST='.$dbHost,
            'DB_PORT='.$dbPort,
            'DB_DATABASE='.$dbName,
            'DB_USERNAME='.$dbUser,
            'DB_PASSWORD="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $dbPass).'"',
            '',
            'SESSION_DRIVER=file',
            'SESSION_SECURE_COOKIE=true',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
            '',
            'SYNC_ENABLED=true',
            'SYNC_ROLE=cloud',
            'SYNC_TOKEN='.$syncToken,
            'SYNC_CLOUD_READ_ONLY=true',
        ];
        $ok = @file_put_contents($envPath, implode("\n", $lines)."\n") !== false;
        if ($ok) {
            @chmod($envPath, 0600);
            // Drop stale cached config that may still point at wrong DB host.
            foreach (glob($base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'*.php') ?: [] as $cached) {
                @unlink($cached);
            }
            $done = true;
        } else {
            $error = '.env write fail — folder permissions check karo.';
        }
    }
}

$exists = is_file($envPath);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Restore .env</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 2rem auto; padding: 0 1rem; }
        label { display: block; font-weight: 600; margin-top: 0.75rem; }
        input { width: 100%; padding: 0.5rem; margin-top: 0.25rem; }
        button { margin-top: 1rem; padding: 0.6rem 1rem; font-weight: 700; }
        .ok { background: #d1fae5; padding: 0.75rem; border-radius: 8px; }
        .err { background: #fee2e2; padding: 0.75rem; border-radius: 8px; }
        .warn { background: #fef3c7; padding: 0.75rem; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>Restore hosting .env</h1>
    <?php if ($done): ?>
        <p class="ok">.env create ho gaya. Ab <a href="/login">/login</a> kholo, phir is file ko delete kar do.</p>
    <?php else: ?>
        <?php if ($exists): ?>
            <p class="warn">.env pehle se maujood hai. Form dubara overwrite karega.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post">
            <label>APP_KEY (local .env se copy)</label>
            <input name="app_key" required placeholder="base64:...">
            <label>DB_HOST</label>
            <input name="db_host" value="localhost">
            <label>DB_PORT</label>
            <input name="db_port" value="3306">
            <label>DB_DATABASE (cPanel MySQL name)</label>
            <input name="db_database" required>
            <label>DB_USERNAME</label>
            <input name="db_username" required>
            <label>DB_PASSWORD</label>
            <input name="db_password" type="password">
            <label>SYNC_TOKEN</label>
            <input name="sync_token" value="SignatureSync_ChangeMe_2026">
            <button type="submit">Create .env</button>
        </form>
    <?php endif; ?>
</body>
</html>
