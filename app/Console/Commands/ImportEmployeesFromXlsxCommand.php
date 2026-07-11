<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDesignation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ImportEmployeesFromXlsxCommand extends Command
{
    protected $signature = 'employees:import-xlsx
        {path : Path to STAFF DETAILS .xlsx (Name, Designation, Salary, Join date)}
        {--company= : company_id (default: from existing employees or 2)}
        {--dry-run : Preview only, no database writes}';

    protected $description = 'Import employees and designations from Excel — auto employee_no';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readXlsxRows($path);
        $parsed = $this->parseStaffRows($rows);
        if ($parsed === []) {
            $this->error('No employee rows found. Use STAFF DETAILS.xlsx (Name + Designation columns).');

            return self::FAILURE;
        }

        $companyId = $this->resolveCompanyId();
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;
        $designationsAdded = 0;

        $existingKeys = Employee::query()
            ->where('company_id', $companyId)
            ->with('designation:id,name')
            ->get(['name', 'designation_id', 'salary'])
            ->mapWithKeys(function (Employee $e) {
                $desig = $e->designation?->name ?? '';

                return [$this->employeeImportKey($e->name, $desig, (float) $e->salary) => true];
            })
            ->all();

        $designationCache = EmployeeDesignation::query()
            ->where('company_id', $companyId)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (EmployeeDesignation $d) => [mb_strtolower(trim($d->name), 'UTF-8') => $d->id])
            ->all();

        $nextNo = $this->resolveStartingEmployeeNo($companyId);

        $run = function () use (
            $parsed,
            $companyId,
            $dryRun,
            &$created,
            &$skipped,
            &$designationsAdded,
            &$existingKeys,
            &$designationCache,
            &$nextNo
        ) {
            $importedKeys = [];

            foreach ($parsed as $row) {
                $key = $this->employeeImportKey($row['name'], $row['designation'], $row['salary']);
                if (isset($existingKeys[$key]) || isset($importedKeys[$key])) {
                    $this->line("[SKIP] {$row['name']} ({$row['designation']})");
                    $skipped++;

                    continue;
                }

                $desigKey = mb_strtolower($row['designation'], 'UTF-8');
                if (! isset($designationCache[$desigKey])) {
                    if ($dryRun) {
                        $this->line("[DESIG] {$row['designation']}");
                        $designationCache[$desigKey] = 0;
                        $designationsAdded++;
                    } else {
                        $desig = EmployeeDesignation::query()->create([
                            'company_id' => $companyId,
                            'name' => $row['designation'],
                            'active' => true,
                        ]);
                        $designationCache[$desigKey] = $desig->id;
                        $designationsAdded++;
                    }
                }

                $employeeNo = sprintf('EMP-%05d', $nextNo++);

                if ($dryRun) {
                    $this->line("[NEW] {$employeeNo} | {$row['name']} | {$row['designation']} | {$row['salary']}");
                    $created++;

                    continue;
                }

                Employee::query()->create([
                    'company_id' => $companyId,
                    'employee_no' => $employeeNo,
                    'name' => $row['name'],
                    'designation_id' => $designationCache[$desigKey] ?: null,
                    'join_date' => $row['join_date'],
                    'salary' => $row['salary'],
                    'active' => true,
                ]);

                $importedKeys[$key] = true;
                $created++;
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::connection('tenant')->transaction($run);
        }

        $this->info(
            ($dryRun ? 'Dry run — ' : '')
            ."Done. Employees created: {$created}, skipped: {$skipped}, designations added: {$designationsAdded}."
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<array{name:string,designation:string,salary:float,join_date:?string,section:string}>
     */
    private function parseStaffRows(array $rows): array
    {
        $employees = [];
        $section = '';

        foreach ($rows as $row) {
            $c0 = trim((string) ($row[0] ?? ''));
            $c1 = trim((string) ($row[1] ?? ''));
            $c2 = trim((string) ($row[2] ?? ''));
            $c3 = trim((string) ($row[3] ?? ''));
            $c4 = trim((string) ($row[4] ?? ''));

            if ($c0 !== '' && $c1 === '' && $c2 === '' && stripos($c0, 'STAFF') === false && stripos($c0, 'SIGNATURE') === false) {
                $section = $c0;

                continue;
            }

            if (stripos($c0, 'S . NO') !== false || stripos($c2, 'NAME') !== false) {
                continue;
            }

            if (! is_numeric($c0) || $c2 === '' || $c3 === '') {
                continue;
            }

            $name = trim(preg_replace('/\s+/', ' ', $c2));
            $designation = trim(preg_replace('/\s+/', ' ', $c3));
            if ($name === '' || $designation === '') {
                continue;
            }

            $employees[] = [
                'name' => $name,
                'designation' => $designation,
                'salary' => is_numeric($c1) ? (float) $c1 : 0,
                'join_date' => $this->parseJoinDate($c4),
                'section' => $section,
            ];
        }

        return $employees;
    }

    private function parseJoinDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $serial = (int) $raw;
            if ($serial >= 30000 && $serial <= 60000) {
                $base = new \DateTimeImmutable('1899-12-30');

                return $base->modify("+{$serial} days")->format('Y-m-d');
            }
        }

        $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd/m/y'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function resolveCompanyId(): int
    {
        $option = $this->option('company');
        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $fromEmployee = DB::connection('tenant')
            ->table('employees')
            ->whereNotNull('company_id')
            ->value('company_id');

        if ($fromEmployee !== null) {
            return (int) $fromEmployee;
        }

        return 2;
    }

    private function resolveStartingEmployeeNo(int $companyId): int
    {
        $max = 0;

        Employee::query()
            ->where('company_id', $companyId)
            ->pluck('employee_no')
            ->each(function (string $no) use (&$max) {
                if (preg_match('/^EMP-(\d+)$/i', trim($no), $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return $max + 1;
    }

    private function employeeImportKey(string $name, string $designation, float $salary): string
    {
        return mb_strtolower(trim($name).'|'.trim($designation).'|'.number_format($salary, 2, '.', ''), 'UTF-8');
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open xlsx: '.$path);
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = simplexml_load_string($sharedXml);
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $parts = [];
                    foreach ($si->r as $r) {
                        $parts[] = (string) $r->t;
                    }
                    $shared[] = implode('', $parts);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            throw new \RuntimeException('sheet1.xml missing');
        }
        $zip->close();

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $letters = $m[1] ?? 'A';
                $idx = 0;
                foreach (str_split($letters) as $ch) {
                    $idx = $idx * 26 + (ord($ch) - 64);
                }
                $idx--;

                while (count($line) < $idx) {
                    $line[] = '';
                }

                $type = (string) ($c['t'] ?? '');
                $value = isset($c->v) ? (string) $c->v : '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $line[$idx] = $value;
            }
            $rows[] = $line;
        }

        return $rows;
    }
}
