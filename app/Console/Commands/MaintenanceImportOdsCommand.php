<?php

namespace App\Console\Commands;

use App\Models\InventoryCategory;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class MaintenanceImportOdsCommand extends Command
{
    protected $signature = 'maintenance:import-ods
        {path : Path to .ods file}
        {--replace : Delete existing maintenance items before import}';

    protected $description = 'Import maintenance items from an ODS spreadsheet (Ser, Item, Rate, Qty)';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->parseOds($path);
        if ($rows === []) {
            $this->error('No data rows found in spreadsheet.');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $this->call('maintenance:purge', ['--force' => true]);
        }

        $category = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', ['maintenance'])
            ->first();

        if ($category === null) {
            $category = InventoryCategory::query()->create([
                'name' => 'Maintenance',
                'parent_id' => null,
            ]);
        }

        $imported = 0;

        DB::connection('tenant')->transaction(function () use ($rows, $category, &$imported) {
            foreach ($rows as $row) {
                $ser = (int) $row['ser'];
                $sku = sprintf('MNT-%03d', $ser);

                $existing = InventoryProduct::query()
                    ->where('sku', $sku)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'name' => $row['name'],
                        'cost' => $row['rate'],
                        'qty_on_hand' => $row['qty'],
                    ]);
                    $product = $existing;
                } else {
                    $product = InventoryProduct::query()->create([
                        'category_id' => $category->id,
                        'sku' => $sku,
                        'name' => $row['name'],
                        'uom' => 'Nos',
                        'cost' => $row['rate'],
                        'price' => 0,
                        'qty_on_hand' => $row['qty'],
                        'reorder_level' => 0,
                        'active' => true,
                        'for_pos' => false,
                        'for_purchase' => true,
                        'extra_costs' => [],
                        'gas_charges' => 0,
                        'service_charges' => 0,
                        'profit' => 0,
                    ]);

                    if ($row['qty'] > 0) {
                        InventoryMove::query()->create([
                            'product_id' => $product->id,
                            'user_id' => null,
                            'type' => 'in',
                            'qty' => $row['qty'],
                            'uom' => 'Nos',
                            'qty_uom' => $row['qty'],
                            'factor_to_base' => 1.0,
                            'unit_cost' => $row['rate'],
                            'total_cost' => $row['qty'] * $row['rate'],
                            'qty_before' => 0,
                            'qty_after' => $row['qty'],
                            'reference' => 'maint-ods-import',
                            'note' => 'Imported opening stock from maint.ods',
                        ]);
                    }
                }

                $imported++;
            }
        });

        $this->info("Imported {$imported} maintenance item(s) from {$path}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{ser:int,name:string,rate:float,qty:float}>
     */
    private function parseOds(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');

        $out = [];
        $isHeader = true;

        foreach ($xpath->query('//table:table-row') as $row) {
            $cells = $xpath->query('table:table-cell', $row);
            $values = [];
            foreach ($cells as $cell) {
                $repeated = (int) $cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'number-columns-repeated');
                if ($repeated > 1) {
                    break;
                }
                $value = $cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0', 'value');
                if ($value !== '') {
                    $values[] = $value;
                } else {
                    $p = $xpath->query('text:p', $cell)->item(0);
                    $values[] = trim($p ? $p->textContent : '');
                }
            }
            if (count($values) < 2) {
                continue;
            }
            if ($isHeader) {
                $isHeader = false;
                continue;
            }
            $name = trim((string) ($values[1] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'ser' => (int) ($values[0] ?? count($out) + 1),
                'name' => $name,
                'rate' => (float) ($values[2] ?? 0),
                'qty' => (float) ($values[3] ?? 0),
            ];
        }

        return $out;
    }
}
