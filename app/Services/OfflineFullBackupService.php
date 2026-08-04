<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Nightly / session-end offline backup: full MySQL dump + project zip → backup/
 */
final class OfflineFullBackupService
{
    /**
     * @return array{ok: bool, zip_path: string, zip_name: string, sql_name: string, bytes: int, message: string}
     */
    public function create(): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension required for offline backup.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $root = base_path();
        $backupDir = $root.DIRECTORY_SEPARATOR.'backup';
        if (! is_dir($backupDir) && ! mkdir($backupDir, 0755, true) && ! is_dir($backupDir)) {
            throw new RuntimeException('backup/ folder create nahi ho saki.');
        }

        $stamp = now()->format('Ymd-His');
        $sqlName = 'database-full-'.$stamp.'.sql';
        $sqlPath = $backupDir.DIRECTORY_SEPARATOR.$sqlName;
        $zipName = 'Signature-full-backup-'.$stamp.'.zip';
        $zipPath = $backupDir.DIRECTORY_SEPARATOR.$zipName;

        $this->writeSqlDump($sqlPath);

        if (! is_file($sqlPath) || filesize($sqlPath) < 50) {
            throw new RuntimeException('MySQL dump empty / fail — offline backup nahi bani.');
        }

        $this->writeRestoreDoc($backupDir, (string) config('database.connections.mysql.database'), $sqlName);

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Backup ZIP open fail.');
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            if (! $info->isFile()) {
                continue;
            }
            $path = $info->getPathname();
            if ($this->shouldSkip($root, $path, $zipPath)) {
                continue;
            }
            $local = str_replace('\\', '/', substr($path, strlen($root) + 1));
            if ($local === '' || $local === false) {
                continue;
            }
            // Prefer freshly written SQL; skip other historical dumps/zips inside backup/
            if (str_starts_with($local, 'backup/')
                && ($local !== 'backup/'.$sqlName)
                && ($local !== 'backup/OFFLINE-RESTORE.txt')
                && (
                    str_ends_with(strtolower($local), '.sql')
                    || str_ends_with(strtolower($local), '.zip')
                )
            ) {
                continue;
            }
            if (! $zip->addFile($path, $local)) {
                continue;
            }
            $count++;
        }

        $zip->close();

        if (! is_file($zipPath) || filesize($zipPath) < 1000) {
            throw new RuntimeException('Backup ZIP write fail / too small.');
        }

        $bytes = (int) filesize($zipPath);

        return [
            'ok' => true,
            'zip_path' => $zipPath,
            'zip_name' => $zipName,
            'sql_name' => $sqlName,
            'bytes' => $bytes,
            'message' => sprintf(
                'Offline backup: backup/%s (+ %s) — %s MB, %d files.',
                $zipName,
                $sqlName,
                number_format($bytes / 1024 / 1024, 1),
                $count
            ),
        ];
    }

    /**
     * Best-effort: never blocks session close if this fails.
     *
     * @return array{ok: bool, message: string, zip_name?: string}
     */
    public function createQuiet(): array
    {
        try {
            $result = $this->create();

            return [
                'ok' => true,
                'message' => $result['message'],
                'zip_name' => $result['zip_name'],
            ];
        } catch (Throwable $e) {
            Log::warning('offline_full_backup_failed', ['error' => $e->getMessage()]);
            report($e);

            return [
                'ok' => false,
                'message' => 'Offline backup fail: '.$e->getMessage(),
            ];
        }
    }

    private function writeSqlDump(string $sqlPath): void
    {
        $exporter = app(DatabaseBackupExporter::class);
        $tmp = $exporter->createBackup(true);
        $from = $tmp['path'];
        if (! is_file($from)) {
            throw new RuntimeException('Temporary SQL dump missing.');
        }
        if (! @rename($from, $sqlPath) && ! @copy($from, $sqlPath)) {
            @unlink($from);
            throw new RuntimeException('SQL dump backup/ folder mein copy fail.');
        }
        @unlink($from);
    }

    private function shouldSkip(string $root, string $fullPath, string $zipPath): bool
    {
        $realFull = realpath($fullPath) ?: $fullPath;
        $realZip = realpath($zipPath) ?: $zipPath;
        if ($realFull === $realZip) {
            return true;
        }

        $rel = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
        if ($rel === false || $rel === '') {
            return false;
        }

        $excludeParts = ['.git', 'node_modules', '.idea', '.fleet', '.vscode', '.cursor'];
        $parts = explode('/', $rel);
        foreach ($excludeParts as $ex) {
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
            return basename($rel) !== '.gitignore';
        }

        return false;
    }

    private function writeRestoreDoc(string $backupDir, string $dbName, string $sqlFile): void
    {
        $doc = <<<TXT
SOFTWARESOLUTION — OFFLINE RESTORE (Laragon / Windows)
======================================================

ZIP mein: poora code, vendor, .env, storage data, database SQL dump.


STEP 1 — Extract
----------------
Extract to: C:\\laragon\\www\\Signature


STEP 2 — MySQL database (ZAROORI)
-----------------------
1. Laragon > MySQL START
2. HeidiSQL / import-database.bat se backup/{$sqlFile} import karo

Database name: {$dbName}


STEP 3 — Laragon Apache
-----------------------
1. Laragon > Apache START
2. Browser: http://signature.test/login
3. php artisan config:clear && php artisan storage:link


Nightly: End POS Session pe yeh backup auto banti hai (backup/ folder).

TXT;
        file_put_contents($backupDir.DIRECTORY_SEPARATOR.'OFFLINE-RESTORE.txt', $doc);
    }
}
