<?php

namespace App\Console\Commands;

use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Models\InventoryProductStock;
use App\Models\InventoryUnit;
use App\Services\InventoryStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ImportProductsFromXlsxCommand extends Command
{
    protected $signature = 'inventory:import-products-xlsx
        {path : Path to .xlsx file (Name | Unit columns)}
        {--company= : company_id (default: from existing products)}
        {--dry-run : Preview only, no database writes}';

    protected $description = 'Import products from Excel — warehouse assign, for_pos OFF';

    /** @var array<string, string> Excel unit label => inventory_units.code */
    private const UNIT_MAP = [
        'KG' => 'kg',
        'PACKET' => 'pkt',
        'BOTTLE' => 'btl',
        'PIECE' => 'pcs',
        'CAN' => 'can',
        'BOX' => 'box',
        'L' => 'ltr',
        'LTR' => 'ltr',
        'LITTER' => 'ltr',
        'LITER' => 'ltr',
        'LITRE' => 'ltr',
        'CARTON' => 'ctn',
        'DOZEN' => 'dzn',
        'GRAM' => 'g',
        'GM' => 'g',
        'NOS' => 'nos',
    ];

    public function handle(InventoryStockService $stockService): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readXlsxRows($path);
        if (count($rows) < 2) {
            $this->error('Spreadsheet is empty.');

            return self::FAILURE;
        }

        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $rows[0]);
        $nameCol = array_search('name', $header, true);
        $unitCol = array_search('unit', $header, true);
        if ($nameCol === false || $unitCol === false) {
            $nameCol = 0;
            $unitCol = 1;
        }

        $companyId = $this->resolveCompanyId();
        $warehouse = $stockService->ensureWarehouse();
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $unitsCreated = 0;

        $dataRows = array_slice($rows, 1);

        $run = function () use (
            $dataRows,
            $nameCol,
            $unitCol,
            $companyId,
            $warehouse,
            $dryRun,
            &$created,
            &$updated,
            &$skipped,
            &$unitsCreated
        ) {
            foreach ($dataRows as $row) {
                $name = trim((string) ($row[$nameCol] ?? ''));
                if ($name === '') {
                    $skipped++;

                    continue;
                }

                $uom = $this->resolveUom((string) ($row[$unitCol] ?? ''), $unitsCreated, $dryRun);
                if ($uom === null) {
                    $this->warn("Skipping (unknown unit): {$name}");
                    $skipped++;

                    continue;
                }

                $product = InventoryProduct::query()
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
                    ->first();

                if ($product === null) {
                    if ($dryRun) {
                        $this->line("[NEW] {$name} ({$uom})");
                        $created++;

                        continue;
                    }

                    $product = InventoryProduct::query()->create([
                        'company_id' => $companyId,
                        'name' => $name,
                        'sku' => InventoryProduct::generateNextSku('PRD'),
                        'uom' => $uom,
                        'cost' => 0,
                        'price' => 0,
                        'profit' => 0,
                        'qty_on_hand' => 0,
                        'reorder_level' => 0,
                        'active' => true,
                        'for_pos' => false,
                        'for_purchase' => true,
                        'department_id' => $warehouse->id,
                    ]);
                    $created++;
                } else {
                    if ($dryRun) {
                        $this->line("[UPDATE] {$name} ({$uom})");
                        $updated++;

                        continue;
                    }

                    $product->update([
                        'uom' => $uom,
                        'for_pos' => false,
                        'active' => true,
                        'department_id' => $warehouse->id,
                    ]);
                    $updated++;
                }

                $product->departments()->syncWithoutDetaching([
                    $warehouse->id => ['company_id' => $companyId],
                ]);

                InventoryProductStock::query()->firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'department_id' => $warehouse->id,
                    ],
                    [
                        'company_id' => $companyId,
                        'qty_on_hand' => 0,
                    ]
                );
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::connection('tenant')->transaction($run);
        }

        $this->info(($dryRun ? 'Dry run — ' : '')."Done. Created: {$created}, updated: {$updated}, skipped: {$skipped}, units added: {$unitsCreated}.");

        return self::SUCCESS;
    }

    private function resolveUom(string $raw, int &$unitsCreated, bool $dryRun): ?string
    {
        $label = strtoupper(trim($raw));
        if ($label === '') {
            return 'pcs';
        }

        $code = self::UNIT_MAP[$label] ?? InventoryUnit::normalizeCode($label);

        if (InventoryUnit::query()->where('code', $code)->exists()) {
            return $code;
        }

        if ($dryRun) {
            return $code;
        }

        InventoryUnit::query()->create([
            'code' => $code,
            'name' => $label,
        ]);
        $unitsCreated++;

        return $code;
    }

    private function resolveCompanyId(): int
    {
        $option = $this->option('company');
        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $fromProduct = DB::connection('tenant')
            ->table('inventory_products')
            ->whereNotNull('company_id')
            ->value('company_id');

        if ($fromProduct !== null) {
            return (int) $fromProduct;
        }

        $fromCompany = DB::connection('mysql')
            ->table('companies')
            ->orderBy('id')
            ->value('id');

        return (int) ($fromCompany ?? 1);
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
