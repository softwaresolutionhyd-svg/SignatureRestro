<?php

/**
 * Full offline backup: code + vendor + .env + storage + MySQL dump.
 *
 * Run from project root: php scripts/make-full-backup.php
 */

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Could not resolve project root.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension required.\n");
    exit(1);
}

$date = date('Ymd-His');
$backupDir = $root.DIRECTORY_SEPARATOR.'backup';
if (! is_dir($backupDir) && ! mkdir($backupDir, 0755, true) && ! is_dir($backupDir)) {
    fwrite(STDERR, "Could not create backup/\n");
    exit(1);
}

$sqlFile = $backupDir.DIRECTORY_SEPARATOR.'database-full-'.$date.'.sql';
$envFile = $root.DIRECTORY_SEPARATOR.'.env';
$dbName = 'signature_local';
$dbUser = 'root';
$dbPass = '';
$dbHost = '127.0.0.1';

if (is_file($envFile)) {
    $env = file_get_contents($envFile);
    if (preg_match('/^DB_DATABASE=(.+)$/m', $env, $m)) {
        $dbName = trim($m[1], " \t\"'");
    }
    if (preg_match('/^DB_USERNAME=(.+)$/m', $env, $m)) {
        $dbUser = trim($m[1], " \t\"'");
    }
    if (preg_match('/^DB_PASSWORD=(.*)$/m', $env, $m)) {
        $dbPass = trim($m[1], " \t\"'");
    }
    if (preg_match('/^DB_HOST=(.+)$/m', $env, $m)) {
        $dbHost = trim($m[1], " \t\"'");
    }
}

$mysqldump = findMysqlDump();
if ($mysqldump === null) {
    fwrite(STDERR, "mysqldump not found. Install MySQL / Laragon.\n");
    exit(1);
}

echo "Dumping database `{$dbName}`...\n";
$passArg = $dbPass !== '' ? '--password='.escapeshellarg($dbPass) : '';
$dumpCmd = sprintf(
    '"%s" --host=%s --user=%s %s --single-transaction --routines --triggers --add-drop-table %s > "%s" 2>&1',
    $mysqldump,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    $passArg,
    escapeshellarg($dbName),
    $sqlFile
);

exec($dumpCmd, $dumpOut, $dumpCode);
if ($dumpCode !== 0 || ! is_file($sqlFile) || filesize($sqlFile) < 100) {
    fwrite(STDERR, "Database dump failed.\n".implode("\n", $dumpOut)."\n");
    exit(1);
}

echo 'SQL dump: '.basename($sqlFile).' ('.round(filesize($sqlFile) / 1024 / 1024, 2)." MB)\n";

$restoreDoc = buildRestoreDoc($dbName, basename($sqlFile));
file_put_contents($backupDir.DIRECTORY_SEPARATOR.'OFFLINE-RESTORE.txt', $restoreDoc);

$dist = $root.DIRECTORY_SEPARATOR.'dist';
if (! is_dir($dist) && ! mkdir($dist, 0755, true) && ! is_dir($dist)) {
    fwrite(STDERR, "Could not create dist/\n");
    exit(1);
}

$zipName = 'Signature-full-backup-'.$date.'.zip';
$zipPath = $dist.DIRECTORY_SEPARATOR.$zipName;

if (is_file($zipPath)) {
    unlink($zipPath);
}

$excludePathParts = ['.git', 'node_modules', '.idea', '.fleet', '.vscode'];

$shouldSkip = static function (string $fullPath) use ($root, $excludePathParts, $zipPath): bool {
    $rel = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
    if ($rel === false || $rel === '') {
        return false;
    }
    if (realpath($fullPath) === realpath($zipPath)) {
        return true;
    }
    $parts = explode('/', $rel);
    foreach ($excludePathParts as $ex) {
        if (in_array($ex, $parts, true)) {
            return true;
        }
    }
    if (str_starts_with($rel, 'dist/') && str_ends_with(strtolower($rel), '.zip')) {
        return true;
    }
    if (str_starts_with($rel, 'storage/logs/') && $rel !== 'storage/logs/.gitignore') {
        return true;
    }
    if (preg_match('#^storage/framework/(cache/data|sessions|views)/#', $rel)) {
        return basename($rel) === '.gitignore' ? false : true;
    }

    return false;
};

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not open zip for writing.\n");
    exit(1);
}

$count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
    RecursiveIteratorIterator::SELF_FIRST
);

/** @var SplFileInfo $info */
foreach ($iterator as $info) {
    if (! $info->isFile()) {
        continue;
    }
    $path = $info->getPathname();
    if ($shouldSkip($path)) {
        continue;
    }
    $local = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $zip->addFile($path, $local);
    $count++;
}

$zip->close();

echo "Created: {$zipPath}\n";
echo "Files: {$count}\n";
echo "Includes: .env, vendor, storage uploads, backup/".basename($sqlFile)."\n";
echo "Read backup/OFFLINE-RESTORE.txt on the new PC.\n";

function findMysqlDump(): ?string
{
    $glob = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe');
    if ($glob !== false && isset($glob[0])) {
        return $glob[0];
    }

    return null;
}

function buildRestoreDoc(string $dbName, string $sqlFile): string
{
    return <<<TXT
SOFTWARESOLUTION — OFFLINE RESTORE (Laragon / Windows)
======================================================

ZIP mein: poora code, vendor, .env, storage data, database SQL dump.


STEP 1 — Extract
----------------
Extract to: C:\\laragon\\www\\Signature


STEP 2 — MySQL database (ZAROORI)
-----------------------
1. Laragon > MySQL START
2. Double-click: import-database.bat
   (ya HeidiSQL se backup/database-full-*.sql import karo)

Database name: {$dbName}


STEP 3 — Laragon Apache
-----------------------
1. Laragon > Apache START
2. Browser: http://signature.test/login
   Ya LAN: http://PC-IP:8080/

3. CMD:
   cd C:\\laragon\\www\\Signature
   fix-storage.bat
   php artisan config:clear
   php artisan storage:link


STEP 4 — Login
--------------
  admin@example.com / admin12345
  superadmin@example.com / admin12345


Mobile (same WiFi): http://PC-IP:8080/  (Signature only; Softwaresolution is :80)

vendor ZIP mein hai — composer offline ki zaroorat nahi.

TXT;
}
