<?php

/**
 * Temporary hosting diagnostics. DELETE this file after the site works.
 * Open: https://signature.softwaresolutions.pk/check-hosting.php
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$root = dirname(__DIR__);
$envFile = $root.DIRECTORY_SEPARATOR.'.env';
$env = [];

if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (
            (str_starts_with($v, '"') && str_ends_with($v, '"'))
            || (str_starts_with($v, "'") && str_ends_with($v, "'"))
        ) {
            $v = substr($v, 1, -1);
        }
        $env[$k] = $v;
    }
}

$dbHost = $env['DB_HOST'] ?? '(missing)';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '(missing)';
$dbUser = $env['DB_USERNAME'] ?? '(missing)';
$dbPass = $env['DB_PASSWORD'] ?? '';

$checks = [];

$checks[] = ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=')];
$checks[] = ['.env exists', $envFile, is_file($envFile)];
$checks[] = ['storage writable', $root.'/storage', is_writable($root.'/storage')];
$checks[] = ['bootstrap/cache writable', $root.'/bootstrap/cache', is_writable($root.'/bootstrap/cache')];
$checks[] = ['vendor/autoload.php', $root.'/vendor/autoload.php', is_file($root.'/vendor/autoload.php')];
$checks[] = ['build/manifest.json', $root.'/public/build/manifest.json', is_file($root.'/public/build/manifest.json')];

$dbOk = false;
$dbMsg = '';
$dbSocket = trim((string) ($env['DB_SOCKET'] ?? ''));

$hostsToTry = array_values(array_unique(array_filter([
    $dbHost !== '(missing)' ? $dbHost : null,
    '127.0.0.1',
    'localhost',
])));

$socketsToTry = array_values(array_unique(array_filter([
    $dbSocket !== '' ? $dbSocket : null,
    ini_get('pdo_mysql.default_socket') ?: null,
    ini_get('mysqli.default_socket') ?: null,
    '/var/lib/mysql/mysql.sock',
    '/var/run/mysqld/mysqld.sock',
    '/tmp/mysql.sock',
])));

foreach ($hostsToTry as $host) {
    try {
        $dsn = "mysql:host={$host};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $dbOk = true;
        $dbMsg = "OK via host={$host} — users table rows: {$count}";
        break;
    } catch (Throwable $e) {
        $dbMsg .= "host={$host} → ".$e->getMessage()."\n";
    }
}

if (! $dbOk) {
    foreach ($socketsToTry as $socket) {
        try {
            $dsn = "mysql:unix_socket={$socket};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $dbOk = true;
            $dbMsg = "OK via DB_SOCKET={$socket} — users table rows: {$count}\n";
            $dbMsg .= "Hosting .env mein add karo:\nDB_HOST=localhost\nDB_SOCKET={$socket}";
            break;
        } catch (Throwable $e) {
            $dbMsg .= "socket={$socket} → ".$e->getMessage()."\n";
        }
    }
}

$checks[] = ['MySQL connection', trim($dbMsg), $dbOk];

$logFile = $root.'/storage/logs/laravel.log';
$logTail = '';
if (is_file($logFile)) {
    $lines = file($logFile) ?: [];
    $logTail = implode('', array_slice($lines, -40));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Signature hosting check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 820px; margin: 24px auto; padding: 0 16px; }
        .ok { color: #15803d; }
        .bad { color: #b91c1c; }
        pre { background: #111827; color: #e5e7eb; padding: 12px; overflow: auto; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        .warn { background: #fef3c7; padding: 10px; border-radius: 8px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>Signature hosting check</h1>
    <div class="warn"><strong>Security:</strong> site theek hone ke baad is file ko DELETE kar do:
        <code>public/check-hosting.php</code></div>

    <h2>Config from .env</h2>
    <pre>APP_URL=<?= h($env['APP_URL'] ?? '') ?>

DB_HOST=<?= h($dbHost) ?>

DB_PORT=<?= h($dbPort) ?>

DB_DATABASE=<?= h($dbName) ?>

DB_USERNAME=<?= h($dbUser) ?>

DB_PASSWORD=<?= $dbPass === '' ? '(empty)' : '********' ?></pre>

    <h2>Checks</h2>
    <table>
        <tr><th>Check</th><th>Detail</th><th>Status</th></tr>
        <?php foreach ($checks as [$label, $detail, $ok]): ?>
            <tr>
                <td><?= h($label) ?></td>
                <td><pre style="margin:0;background:#f8fafc;color:#111"><?= h(is_string($detail) ? $detail : json_encode($detail)) ?></pre></td>
                <td class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'FAIL' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>laravel.log (last lines)</h2>
    <pre><?= $logTail !== '' ? h($logTail) : '(no log file yet — storage/logs permissions check karo)' ?></pre>
</body>
</html>
