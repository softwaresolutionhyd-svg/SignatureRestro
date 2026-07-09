<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DatabaseBackupExporter
{
    /**
     * @return array{path: string, filename: string}
     */
    public function createBackup(bool $fullDatabase): array
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Database backup abhi sirf MySQL ke liye hai.');
        }

        $path = storage_path('app/db-backup-'.uniqid('', true).'.sql');

        if ($fullDatabase) {
            $dump = $this->tryMysqldump();
            if ($dump !== null && $dump !== '') {
                if (file_put_contents($path, $dump) === false) {
                    throw new RuntimeException('Backup file write fail.');
                }

                return ['path' => $path, 'filename' => $this->suggestedFilename(true)];
            }
        }

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Backup file nahi bana sakay.');
        }

        fwrite($fh, $this->sqlHeader());
        if ($fullDatabase) {
            $this->writePhpDumpAllTables($fh);
        } else {
            $cid = current_company_id();
            if ($cid === null) {
                fclose($fh);
                @unlink($path);
                throw new RuntimeException('Company context missing.');
            }
            $this->writePhpDumpCompanyScoped($fh, (int) $cid);
        }
        fwrite($fh, $this->sqlFooter());
        fclose($fh);

        return ['path' => $path, 'filename' => $this->suggestedFilename($fullDatabase)];
    }

    public function suggestedFilename(bool $full): string
    {
        $suffix = $full ? 'full' : 'company_'.(current_company_id() ?? 'x');

        return 'db_backup_'.$suffix.'_'.date('Y-m-d_His').'.sql';
    }

    private function sqlHeader(): string
    {
        $db = config('database.connections.mysql.database');

        return "-- Stair DB backup\n-- ".date('c')."\n-- Database: {$db}\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    private function sqlFooter(): string
    {
        return "\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    private function tryMysqldump(): ?string
    {
        $binary = $this->resolveMysqldumpBinary();
        if ($binary === null) {
            return null;
        }

        $c = config('database.connections.mysql');
        $defaults = tempnam(sys_get_temp_dir(), 'dbcnf');
        if ($defaults === false) {
            return null;
        }

        $password = (string) ($c['password'] ?? '');
        $content = "[client]\npassword=\"".str_replace(['\\', '"'], ['\\\\', '\\"'], $password)."\"\n";
        file_put_contents($defaults, $content);
        @chmod($defaults, 0600);

        try {
            $cmd = [
                $binary,
                '--defaults-extra-file='.$defaults,
                '-h', $c['host'],
                '-P', (string) ($c['port'] ?? 3306),
                '-u', $c['username'],
                '--single-transaction',
                '--routines',
                '--skip-comments',
                '--set-charset',
                '--default-character-set=utf8mb4',
                '--no-tablespaces',
                $c['database'],
            ];

            $process = new Process($cmd);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            return $process->getOutput();
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($defaults);
        }
    }

    private function resolveMysqldumpBinary(): ?string
    {
        $configured = config('database.backup.mysqldump_path');
        if (is_string($configured) && $configured !== '') {
            if (@is_file($configured)) {
                return $configured;
            }

            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $which = new Process(['where.exe', 'mysqldump']);
            $which->run();
            if ($which->isSuccessful()) {
                $line = trim(explode("\n", $which->getOutput())[0] ?? '');
                if ($line !== '' && @is_file($line)) {
                    return $line;
                }
            }
        } else {
            $which = new Process(['which', 'mysqldump']);
            $which->run();
            if ($which->isSuccessful()) {
                $p = trim($which->getOutput());
                if ($p !== '') {
                    return $p;
                }
            }
        }

        return null;
    }

    /**
     * @param  resource  $fh
     */
    private function writePhpDumpAllTables($fh): void
    {
        foreach ($this->tableNames() as $table) {
            fwrite($fh, $this->dumpTableStructureSql($table));
            $this->streamTableData($fh, $table, null, []);
        }
    }

    /**
     * @param  resource  $fh
     */
    private function writePhpDumpCompanyScoped($fh, int $companyId): void
    {
        fwrite($fh, $this->dumpTableStructureSql('companies'));
        $this->streamTableData($fh, 'companies', 'id = ?', [$companyId]);

        foreach ($this->tableNames() as $table) {
            if ($table === 'companies') {
                continue;
            }
            if (! $this->tableHasCompanyId($table)) {
                continue;
            }
            fwrite($fh, $this->dumpTableStructureSql($table));
            $this->streamTableData($fh, $table, 'company_id = ?', [$companyId]);
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $db = config('database.connections.mysql.database');
        $key = 'Tables_in_'.$db;
        $rows = DB::select('SHOW TABLES');
        $names = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $names[] = (string) ($arr[$key] ?? reset($arr));
        }
        sort($names);

        return $names;
    }

    private function dumpTableStructureSql(string $table): string
    {
        $t = $this->assertTableName($table);
        $row = DB::selectOne('SHOW CREATE TABLE `'.$t.'`');
        if ($row === null) {
            return '';
        }
        $arr = (array) $row;
        $create = (string) ($arr['Create Table'] ?? reset($arr));

        return "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n\n";
    }

    /**
     * @param  resource  $fh
     * @param  list<mixed>  $bindings
     */
    private function streamTableData($fh, string $table, ?string $whereSql, array $bindings): void
    {
        $t = $this->assertTableName($table);
        $orderCol = $this->guessOrderColumn($t);
        $q = DB::table($t);
        if ($whereSql !== null) {
            $q->whereRaw($whereSql, $bindings);
        }
        $q->orderBy($orderCol)->chunk(200, function ($rows) use ($fh, $t) {
            $sql = $this->buildInsertSql($t, $rows);
            if ($sql !== '') {
                fwrite($fh, $sql);
            }
        });
    }

    private function buildInsertSql(string $table, $rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }
        $first = (array) $rows->first();
        $columns = array_keys($first);
        $t = $this->assertTableName($table);
        $colSql = '`'.implode('`,`', $columns).'`';
        $pdo = DB::connection()->getPdo();
        $blocks = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $parts = [];
            foreach ($columns as $col) {
                $parts[] = $this->quoteValue($pdo, $r[$col] ?? null);
            }
            $blocks[] = '('.implode(',', $parts).')';
        }

        return 'INSERT INTO `'.$t.'` ('.$colSql.') VALUES '.implode(",\n", $blocks).";\n\n";
    }

    private function quoteValue(PDO $pdo, mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if ($v instanceof \DateTimeInterface) {
            return $pdo->quote($v->format('Y-m-d H:i:s'));
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        return $pdo->quote((string) $v);
    }

    private function guessOrderColumn(string $table): string
    {
        $cols = Schema::getColumnListing($table);
        if (in_array('id', $cols, true)) {
            return 'id';
        }

        return $cols[0] ?? 'id';
    }

    private function tableHasCompanyId(string $table): bool
    {
        $t = $this->assertTableName($table);

        return Schema::hasColumn($t, 'company_id');
    }

    private function assertTableName(string $table): string
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new RuntimeException('Invalid table name.');
        }

        return $table;
    }
}
