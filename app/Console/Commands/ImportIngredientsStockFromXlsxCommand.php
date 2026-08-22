<?php

namespace App\Console\Commands;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Services\InventoryStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class ImportIngredientsStockFromXlsxCommand extends Command
{
    protected $signature = 'inventory:import-ingredients-stock-xlsx
        {path : Path to Ingredients Excel (.xlsx)}
        {--dry-run : Preview only}
        {--zero-missing : Set stock to 0 for purchase products not listed in the file (default: true)}
        {--no-zero-missing : Do not zero products missing from the file}';

    protected $description = 'Set warehouse cost + stock exactly from Ingredients Excel (SKU, Cost, Stock in hand)';

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
        $skuCol = $this->findCol($header, ['sku']);
        $costCol = $this->findCol($header, ['cost']);
        $stockCol = $this->findCol($header, ['stock in hand', 'stock', 'qty on hand', 'qty']);

        if ($skuCol === null) {
            $this->error('SKU column not found.');

            return self::FAILURE;
        }
        if ($costCol === null || $stockCol === null) {
            $this->error('Cost and/or Stock in hand column not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $zeroMissing = ! $this->option('no-zero-missing');

        $parsed = [];
        $skipped = 0;
        foreach (array_slice($rows, 1) as $i => $row) {
            $sku = strtoupper(trim((string) ($row[$skuCol] ?? '')));
            if ($sku === '') {
                $skipped++;
                continue;
            }
            $cost = $this->parseNumber($row[$costCol] ?? '');
            $stock = $this->parseNumber($row[$stockCol] ?? '');
            if ($cost === null || $stock === null) {
                $this->warn('Row '.($i + 2).": invalid cost/stock for SKU {$sku} — skipped.");
                $skipped++;
                continue;
            }
            $parsed[$sku] = [
                'cost' => round($cost, 2),
                'stock' => round($stock, 3),
            ];
        }

        if ($parsed === []) {
            $this->error('No valid rows to import.');

            return self::FAILURE;
        }

        $this->info('Rows in file: '.count($parsed).($skipped ? " ({$skipped} skipped)" : ''));

        $updated = 0;
        $zeroed = 0;
        $missingSku = 0;
        $seenProductIds = [];

        $apply = function () use (
            $parsed,
            $stockService,
            $dryRun,
            $zeroMissing,
            &$updated,
            &$zeroed,
            &$missingSku,
            &$seenProductIds
        ) {
            foreach ($parsed as $sku => $vals) {
                $product = InventoryProduct::query()
                    ->withoutGlobalScopes()
                    ->where('sku', $sku)
                    ->first();

                if (! $product) {
                    $missingSku++;
                    $this->warn("SKU not found in DB: {$sku}");

                    continue;
                }

                $seenProductIds[] = (int) $product->id;
                $beforeStock = (float) $product->qty_on_hand;
                $beforeCost = (float) $product->cost;
                $targetStock = (float) $vals['stock'];
                $targetCost = (float) $vals['cost'];

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] %s (%s): cost %s → %s, stock %s → %s %s',
                        $sku,
                        $product->name,
                        fmt_num($beforeCost, 2),
                        fmt_num($targetCost, 2),
                        fmt_num($beforeStock, 3),
                        fmt_num($targetStock, 3),
                        $product->uom
                    ));
                    $updated++;

                    continue;
                }

                $product->update([
                    'cost' => $targetCost,
                    'qty_on_hand' => $targetStock,
                ]);

                $stockService->applyStockCheckQuantity($product->fresh(), $targetStock);
                $this->syncCostLayer($product->fresh(), $targetStock, $targetCost, $beforeStock);

                $updated++;
            }

            if (! $zeroMissing) {
                return;
            }

            InventoryProduct::query()
                ->withoutGlobalScopes()
                ->where('for_purchase', true)
                ->when($seenProductIds !== [], fn ($q) => $q->whereNotIn('id', $seenProductIds))
                ->each(function (InventoryProduct $product) use ($stockService, $dryRun, &$zeroed) {
                    if (abs((float) $product->qty_on_hand) <= 0.0005) {
                        return;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            '[dry-run] zero missing %s (%s): stock %s → 0',
                            $product->sku,
                            $product->name,
                            fmt_num((float) $product->qty_on_hand, 3)
                        ));
                        $zeroed++;

                        return;
                    }

                    $before = (float) $product->qty_on_hand;
                    $product->update(['qty_on_hand' => 0]);
                    $stockService->applyStockCheckQuantity($product->fresh(), 0);
                    $this->syncCostLayer($product->fresh(), 0, (float) $product->cost, $before);
                    $zeroed++;
                });
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::connection('tenant')->transaction($apply);
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated').": {$updated} product(s).");
        if ($missingSku > 0) {
            $this->warn("SKUs in file but not in DB: {$missingSku}");
        }
        if ($zeroMissing) {
            $this->info(($dryRun ? 'Would zero' : 'Zeroed')." missing-from-file: {$zeroed} product(s).");
        }

        return self::SUCCESS;
    }

    private function syncCostLayer(InventoryProduct $product, float $targetStock, float $unitCost, float $beforeStock): void
    {
        if (! Schema::connection('tenant')->hasTable('inventory_cost_layers')) {
            return;
        }

        InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->delete();

        if ($targetStock <= 0.0005) {
            return;
        }

        InventoryCostLayer::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'qty_remaining' => $targetStock,
            'unit_cost' => $unitCost,
            'source' => 'excel_import',
            'reference' => 'Ingredients Excel',
            'received_at' => now(),
        ]);

        if (abs($beforeStock - $targetStock) > 0.0005 && Schema::connection('tenant')->hasTable('inventory_moves')) {
            InventoryMove::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'user_id' => null,
                'type' => $targetStock > $beforeStock ? 'in' : 'out',
                'qty' => abs($targetStock - $beforeStock),
                'uom' => (string) $product->uom,
                'qty_uom' => abs($targetStock - $beforeStock),
                'factor_to_base' => 1.0,
                'unit_cost' => $unitCost,
                'total_cost' => round($unitCost * abs($targetStock - $beforeStock), 2),
                'qty_before' => $beforeStock,
                'qty_after' => $targetStock,
                'reference' => 'EXCEL-STOCK',
                'note' => 'Ingredients Excel import — set exact stock',
            ]);
        }
    }

    private function findCol(array $header, array $needles): ?int
    {
        foreach ($needles as $needle) {
            $idx = array_search($needle, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }
        foreach ($header as $idx => $label) {
            foreach ($needles as $needle) {
                if (str_contains($label, $needle)) {
                    return (int) $idx;
                }
            }
        }

        return null;
    }

    private function parseNumber(mixed $value): ?float
    {
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace([',', ' '], ['', ''], $s);
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
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
