<?php

namespace App\Console\Commands;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\MaintenanceDemand;
use App\Models\MaintenanceDemandLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class MaintenanceImportDemandOdsCommand extends Command
{
    protected $signature = 'maintenance:import-demand-ods
        {path : Path to demand .ods file}
        {--requested-by=Housekeeping : Requested by name}
        {--category=General : Default line category}
        {--company= : Tenant company_id (defaults to first company)}
        {--draft : Save as draft instead of pending}';

    protected $description = 'Create a maintenance demand from an ODS file (locations per column)';

    private const LOCATION_COLUMNS = ['Gym', 'Mess Kitchen', 'Guest Room', 'MT Huts', 'Offices'];

    /** @var array<string, string> */
    private const NAME_ALIASES = [
        'phinis phenyal' => 'Finis Phenyal',
        'finis phenyal' => 'Finis Phenyal',
        'dust bin rool small' => 'Dust Bin Roll',
        'dust bin roll small' => 'Dust Bin Roll',
        'dust bin roll large' => 'Large Dust Bin (Kitchen)',
        'lemon max soap' => 'Lemon Max Bar 290GM',
        'lemon max liquid' => 'Lemon Max liquid',
        'mortien spray' => 'Mortein Spray',
        'surf kg' => 'Surf',
        'shopping bags kg' => 'Disposible Bags kg',
        'cell aa' => 'Cell AA (Box)',
        'cell aaa' => 'Cell AAA (Box)',
        'cell large' => 'Cell Mini',
        'razor gillete blue 3' => 'Gillete Razor Blue',
        'pressure cooker rubber 8x10' => 'Pressure Cooker Rubber Anexy',
        'muslim shower bfs' => 'Muslim Shower',
        'dettol floor cleaner' => 'Dettol Floor Cleaner',
        'salt pot' => 'Namak dani',
        'tooth pick (dzn)' => 'Tooth Pick',
        'aseel air wick' => 'Air Wick Spray',
        'aseel air freshner' => 'Air Freshner',
        'small dustbin' => 'Large Dust Bin (Kitchen)',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $parsed = $this->parseDemandOds($path);
        if ($parsed['lines'] === []) {
            $this->error('No demand lines found in spreadsheet.');

            return self::FAILURE;
        }

        $this->ensureLocations($parsed['locations']);
        $this->ensureCategory((string) $this->option('category'));

        $maintenanceCategory = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', ['maintenance'])
            ->first();

        if ($maintenanceCategory === null) {
            $this->error('Maintenance inventory category not found. Import items first.');

            return self::FAILURE;
        }

        $products = InventoryProduct::query()
            ->where('category_id', $maintenanceCategory->id)
            ->where('sku', '!=', 'MNT-CUSTOM')
            ->get(['id', 'sku', 'name', 'uom']);

        $placeholder = $this->customPlaceholder($maintenanceCategory);
        $lineCategory = (string) $this->option('category');
        $status = $this->option('draft') ? 'draft' : 'pending';
        $requestedBy = trim((string) $this->option('requested-by'));
        $companyId = $this->resolveCompanyId();

        $demandId = null;
        $lineCount = 0;
        $customCount = 0;

        DB::connection('tenant')->transaction(function () use (
            $parsed,
            $products,
            $placeholder,
            $lineCategory,
            $status,
            $requestedBy,
            $companyId,
            &$demandId,
            &$lineCount,
            &$customCount
        ) {
            $firstLine = $parsed['lines'][0];
            $firstProduct = $this->resolveProduct($firstLine['item_name'], $products);

            $demand = MaintenanceDemand::query()->create([
                'company_id' => $companyId,
                'product_id' => $firstProduct?->id ?? $placeholder->id,
                'requested_by' => $requestedBy,
                'qty_uom' => 0,
                'uom' => '-',
                'qty_base' => 0,
                'status' => $status,
                'demand_date' => now()->toDateString(),
                'needed_date' => null,
                'location' => $firstLine['location'],
                'demand_category' => $lineCategory,
                'note' => 'Imported from '.basename($parsed['source']),
                'created_by' => null,
            ]);

            $sumQty = 0.0;

            foreach ($parsed['lines'] as $line) {
                $product = $this->resolveProduct($line['item_name'], $products);
                $isCustom = $product === null;
                if ($isCustom) {
                    $customCount++;
                }

                $qty = (float) $line['qty'];
                $rate = (float) $line['rate'];
                $item = $product ?? $placeholder;

                MaintenanceDemandLine::query()->create([
                    'company_id' => $companyId,
                    'demand_id' => $demand->id,
                    'product_id' => $item->id,
                    'item_name' => $isCustom ? $line['item_name'] : null,
                    'is_custom' => $isCustom,
                    'line_location' => $line['location'],
                    'line_category' => $lineCategory,
                    'qty_uom' => $qty,
                    'uom' => $isCustom ? 'Nos' : ($product->uom ?: 'Nos'),
                    'qty_base' => $qty,
                    'expected_rate' => $rate,
                    'expected_total' => $qty * $rate,
                    'received_qty_uom' => 0,
                    'received_qty_base' => 0,
                    'actual_rate' => 0,
                    'actual_total' => 0,
                ]);

                $sumQty += $qty;
                $lineCount++;
            }

            $demand->update([
                'qty_uom' => $sumQty,
                'qty_base' => $sumQty,
            ]);

            $demandId = $demand->id;
        });

        $this->info("Demand #{$demandId} created ({$lineCount} line(s), {$customCount} custom item(s)).");
        $this->line('Expected total: '.number_format($parsed['expected_total'], 2));

        return self::SUCCESS;
    }

    /**
     * @return array{source:string,locations:list<string>,lines:list<array{item_name:string,location:string,qty:float,rate:float}>,expected_total:float}
     */
    private function parseDemandOds(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['source' => $path, 'locations' => self::LOCATION_COLUMNS, 'lines' => [], 'expected_total' => 0];
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();

        $dom = new \DOMDocument();
        $dom->loadXML((string) $xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');

        $tableRows = $xpath->query('//table:table-row');
        $headers = [];
        $lines = [];
        $expectedTotal = 0.0;

        foreach ($tableRows as $rowIndex => $row) {
            $values = $this->readRowValues($xpath, $row);
            if ($values === []) {
                continue;
            }

            if ($headers === []) {
                $headers = array_map(fn ($h) => trim((string) $h), $values);
                continue;
            }

            $itemName = trim((string) ($values[1] ?? $values[0] ?? ''));
            if ($itemName === '' || strcasecmp($itemName, 'Total') === 0) {
                if (strcasecmp($itemName, 'Total') === 0) {
                    $expectedTotal = (float) ($values[count($values) - 1] ?? 0);
                }
                continue;
            }

            $rate = $this->columnValue($headers, $values, 'RATE');
            if ($rate === null) {
                $rate = $this->guessRate($values);
            }
            $rate = (float) ($rate ?? 0);

            foreach (self::LOCATION_COLUMNS as $location) {
                $qtyRaw = $this->columnValue($headers, $values, $location);
                if ($qtyRaw === null || $qtyRaw === '') {
                    continue;
                }
                $qty = (float) $qtyRaw;
                if ($qty <= 0) {
                    continue;
                }

                $lines[] = [
                    'item_name' => $itemName,
                    'location' => $location,
                    'qty' => $qty,
                    'rate' => $rate,
                ];
            }
        }

        return [
            'source' => $path,
            'locations' => self::LOCATION_COLUMNS,
            'lines' => $lines,
            'expected_total' => $expectedTotal,
        ];
    }

    /**
     * @return list<string>
     */
    private function readRowValues(\DOMXPath $xpath, \DOMNode $row): array
    {
        $values = [];
        foreach ($xpath->query('table:table-cell', $row) as $cell) {
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

        return $values;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $values
     */
    private function columnValue(array $headers, array $values, string $name): ?string
    {
        $index = array_search($name, $headers, true);
        if ($index === false) {
            return null;
        }

        return isset($values[$index]) ? trim((string) $values[$index]) : null;
    }

    /**
     * @param  list<string>  $values
     */
    private function guessRate(array $values): ?float
    {
        if (count($values) >= 2) {
            $candidate = $values[count($values) - 2] ?? null;
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InventoryProduct>  $products
     */
    private function resolveProduct(string $name, $products): ?InventoryProduct
    {
        $normalized = strtolower(trim($name));
        $resolvedName = self::NAME_ALIASES[$normalized] ?? $name;

        $match = $products->first(fn (InventoryProduct $p) => strcasecmp(trim($p->name), trim($resolvedName)) === 0);
        if ($match) {
            return $match;
        }

        $match = $products->first(fn (InventoryProduct $p) => str_contains(strtolower($p->name), $normalized)
            || str_contains($normalized, strtolower($p->name)));
        if ($match) {
            return $match;
        }

        return null;
    }

    private function ensureLocations(array $locations): void
    {
        if (! Schema::connection('tenant')->hasTable('maintenance_locations')) {
            return;
        }

        foreach ($locations as $name) {
            DB::connection('tenant')->table('maintenance_locations')->updateOrInsert(
                ['name' => $name],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function ensureCategory(string $name): void
    {
        if (! Schema::connection('tenant')->hasTable('maintenance_categories')) {
            return;
        }

        DB::connection('tenant')->table('maintenance_categories')->updateOrInsert(
            ['name' => $name],
            ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
        );
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

        if ($fromCompany !== null) {
            return (int) $fromCompany;
        }

        return 1;
    }

    private function customPlaceholder(InventoryCategory $category): InventoryProduct
    {
        $existing = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->where('sku', 'MNT-CUSTOM')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InventoryProduct::query()->create([
            'category_id' => $category->id,
            'sku' => 'MNT-CUSTOM',
            'name' => 'Custom Maintenance Demand (Non-stock)',
            'uom' => 'unit',
            'cost' => 0,
            'price' => 0,
            'qty_on_hand' => 0,
            'reorder_level' => 0,
            'active' => false,
            'for_pos' => false,
            'for_purchase' => false,
            'extra_costs' => [],
            'gas_charges' => 0,
            'service_charges' => 0,
            'profit' => 0,
        ]);
    }
}
