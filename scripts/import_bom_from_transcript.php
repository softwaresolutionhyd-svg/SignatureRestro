<?php

use App\Models\InventoryProduct;
use App\Models\InventoryProductUomConversion;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingBomLine;
use Illuminate\Support\Facades\DB;

$transcriptPath = 'C:\\Users\\Ali Hasnain\\.cursor\\projects\\c-laragon-www-Softwaresolution\\agent-transcripts\\ccd3f0e9-e797-46d4-920d-0219c09d6440\\ccd3f0e9-e797-46d4-920d-0219c09d6440.jsonl';

if (!is_file($transcriptPath)) {
    throw new RuntimeException("Transcript file not found: {$transcriptPath}");
}

$lines = file($transcriptPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$bomText = null;
foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (!is_array($row) || ($row['role'] ?? null) !== 'user') {
        continue;
    }
    $parts = $row['message']['content'] ?? [];
    if (!is_array($parts)) {
        continue;
    }
    foreach ($parts as $part) {
        $text = (string) ($part['text'] ?? '');
        if (str_contains($text, '[PRODUCT]') && str_contains($text, 'TOTAL FINISHED PRODUCTS')) {
            $bomText = $text;
        }
    }
}

if (!is_string($bomText) || $bomText === '') {
    throw new RuntimeException('Could not locate BOM text in transcript.');
}

$specs = [];
$current = null;
foreach (preg_split("/\R/u", $bomText) as $rawLine) {
    $line = trim((string) $rawLine);
    if ($line === '') {
        continue;
    }
    if (preg_match('/^\[PRODUCT\]\s*(.+)$/iu', $line, $m)) {
        $current = trim($m[1]);
        $specs[$current] = [];
        continue;
    }
    if ($current === null) {
        continue;
    }
    if (preg_match('/^COMPONENT\s*\|\s*(.+?)\s*\|\s*([^|]+?)\s*\|\s*([0-9]+(?:\.[0-9]+)?)/iu', $line, $m)) {
        $specs[$current][] = [
            'name' => trim($m[1]),
            'uom' => trim($m[2]),
            'qty' => (float) $m[3],
        ];
    }
}

if ($specs === []) {
    throw new RuntimeException('No BOM products parsed from text.');
}

$allProducts = InventoryProduct::query()
    ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
    ->get();

$norm = static function (string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = preg_replace('/[^[:alnum:]]+/u', '', $s) ?? $s;

    return $s;
};

$byExact = [];
$byNorm = [];
foreach ($allProducts as $p) {
    $exact = mb_strtolower(trim((string) $p->name), 'UTF-8');
    $byExact[$exact][] = $p;
    $byNorm[$norm((string) $p->name)][] = $p;
}

$resolveProduct = static function (string $name) use ($byExact, $byNorm, $norm) {
    $exact = mb_strtolower(trim($name), 'UTF-8');
    if (!empty($byExact[$exact])) {
        usort($byExact[$exact], fn ($a, $b) => ((int) $b->active <=> (int) $a->active) ?: ($a->id <=> $b->id));

        return $byExact[$exact][0];
    }

    $n = $norm($name);
    if (!empty($byNorm[$n])) {
        usort($byNorm[$n], fn ($a, $b) => ((int) $b->active <=> (int) $a->active) ?: ($a->id <=> $b->id));

        return $byNorm[$n][0];
    }

    // Simple alias fallback for names containing bracketed annotations or mixed scripts.
    if (preg_match('/maida/iu', $name)) {
        foreach (['maida', 'maida (میدہ)'] as $alias) {
            $aliasNorm = $norm($alias);
            if (!empty($byNorm[$aliasNorm])) {
                usort($byNorm[$aliasNorm], fn ($a, $b) => ((int) $b->active <=> (int) $a->active) ?: ($a->id <=> $b->id));

                return $byNorm[$aliasNorm][0];
            }
        }
    }

    return null;
};

$canonicalUom = static function (InventoryProduct $product, string $wanted): ?string {
    $wanted = trim($wanted);
    if ($wanted === '') {
        return null;
    }

    foreach ($product->uomsForForms() as $row) {
        $code = (string) ($row['uom'] ?? '');
        if ($code !== '' && strcasecmp($code, $wanted) === 0) {
            return $code;
        }
    }

    if (strcasecmp((string) $product->uom, $wanted) === 0) {
        return (string) $product->uom;
    }

    return null;
};

$missingFinished = [];
$missingComponents = [];
$uomFallbacks = [];
$updated = 0;
$created = 0;

DB::connection('tenant')->transaction(function () use (
    $specs,
    $resolveProduct,
    $canonicalUom,
    &$missingFinished,
    &$missingComponents,
    &$uomFallbacks,
    &$updated,
    &$created
) {
    foreach ($specs as $finishedName => $components) {
        $finished = $resolveProduct($finishedName);
        if (!$finished) {
            $missingFinished[] = $finishedName;
            continue;
        }

        $bucket = [];
        $lineOrder = 0;
        foreach ($components as $componentSpec) {
            $component = $resolveProduct($componentSpec['name']);
            if (!$component) {
                $missingComponents[] = $finishedName . ' -> ' . $componentSpec['name'];
                continue;
            }

            $wantedUom = (string) $componentSpec['uom'];
            $uom = $canonicalUom($component, $wantedUom);
            if ($uom === null) {
                InventoryProductUomConversion::query()->updateOrCreate(
                    [
                        'product_id' => $component->id,
                        'uom' => trim($wantedUom),
                    ],
                    [
                        'company_id' => $component->company_id,
                        'factor_to_base' => 1,
                        'active' => true,
                    ]
                );
                $uom = trim($wantedUom);
                $uomFallbacks[] = $finishedName . ' -> ' . $componentSpec['name'] . " ({$wantedUom}=>auto-factor-1)";
            }

            $key = $component->id . '|' . mb_strtolower($uom, 'UTF-8');
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'component_product_id' => (int) $component->id,
                    'qty' => 0.0,
                    'uom' => $uom,
                    'sort_order' => $lineOrder++,
                ];
            }
            $bucket[$key]['qty'] += (float) $componentSpec['qty'];
        }

        if ($bucket === []) {
            continue;
        }

        $existingBoms = ManufacturingBom::query()
            ->where('finished_product_id', $finished->id)
            ->orderByDesc('active')
            ->orderBy('id')
            ->get();

        if ($existingBoms->isNotEmpty()) {
            $bom = $existingBoms->first();
            $bom->update([
                'name' => 'Pricing Sheet Feb 2026',
                'batch_qty' => 1,
                'active' => true,
                'notes' => 'Auto-synced from pricing sheet transcript (Feb 2026).',
            ]);
            $bom->lines()->delete();
            $updated++;

            foreach ($existingBoms->skip(1) as $extra) {
                if ($extra->active) {
                    $extra->update([
                        'active' => false,
                        'notes' => 'Deactivated after auto-sync (duplicate BoM for finished product).',
                    ]);
                }
            }
        } else {
            $bom = ManufacturingBom::query()->create([
                'company_id' => $finished->company_id,
                'finished_product_id' => $finished->id,
                'name' => 'Pricing Sheet Feb 2026',
                'batch_qty' => 1,
                'active' => true,
                'notes' => 'Auto-synced from pricing sheet transcript (Feb 2026).',
            ]);
            $created++;
        }

        foreach (array_values($bucket) as $row) {
            ManufacturingBomLine::query()->create([
                'company_id' => $bom->company_id,
                'bom_id' => $bom->id,
                'component_product_id' => $row['component_product_id'],
                'qty' => round((float) $row['qty'], 6),
                'uom' => $row['uom'],
                'sort_order' => $row['sort_order'],
            ]);
        }

        $bom->refresh()->load(['lines.component.uomConversions']);
        $bom->syncFinishedProductStandardCost();
    }
});

echo "BOM sync complete\n";
echo 'Products in sheet: '.count($specs)."\n";
echo "Updated BOMs: {$updated}\n";
echo "Created BOMs: {$created}\n";
echo 'Missing finished products: '.count($missingFinished)."\n";
if ($missingFinished !== []) {
    echo '- '.implode("\n- ", $missingFinished)."\n";
}
echo 'Missing components: '.count($missingComponents)."\n";
if ($missingComponents !== []) {
    echo '- '.implode("\n- ", array_slice($missingComponents, 0, 200))."\n";
}
echo 'UOM fallbacks used: '.count($uomFallbacks)."\n";
if ($uomFallbacks !== []) {
    echo '- '.implode("\n- ", array_slice($uomFallbacks, 0, 200))."\n";
}
