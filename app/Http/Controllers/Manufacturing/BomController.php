<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingBomLine;
use App\Models\ManufacturingOrder;
use App\Models\Setting;
use App\Services\Sync\SyncAwareDelete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BomController extends Controller
{
    /**
     * Allow redirects only to same-app paths or URLs under config('app.url').
     */
    private function safeInternalReturnUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/[\r\n\0]/', $value)) {
            return null;
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }
        $base = rtrim((string) config('app.url'), '/');
        if ($base !== '' && str_starts_with($value, $base.'/')) {
            return $value;
        }

        return null;
    }

    private function redirectAfterBom(Request $request, string $status, array $indexQuery = []): RedirectResponse
    {
        $safe = $this->safeInternalReturnUrl($request->input('return'));
        if ($safe !== null) {
            return redirect()->to($safe)->with('status', $status);
        }

        return redirect()->route('manufacturing.boms.index', $indexQuery)->with('status', $status);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $finishedProductId = $request->filled('finished_product') ? $request->integer('finished_product') : null;
        $filterProduct = $finishedProductId
            ? InventoryProduct::query()->find($finishedProductId)
            : null;

        $boms = ManufacturingBom::query()
            ->with(['finishedProduct:id,sku,name,uom,qty_on_hand'])
            ->with(['lines.component' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)])])
            ->withCount('lines')
            ->when($finishedProductId, fn ($query) => $query->where('finished_product_id', $finishedProductId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhereHas('finishedProduct', function ($p) use ($q) {
                            $p->where('sku', 'like', "%{$q}%")
                                ->orWhere('name', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy(
                InventoryProduct::query()
                    ->select('name')
                    ->whereColumn('inventory_products.id', 'manufacturing_boms.finished_product_id')
                    ->limit(1)
            )
            ->orderBy('name')
            ->paginate(Setting::pageSize('manufacturing_boms_per_page', 20))
            ->withQueryString();
        $boms->getCollection()->transform(function (ManufacturingBom $bom) {
            try {
                $lineCost = (float) $bom->materialCostPerBatch();
            } catch (\Throwable $e) {
                report($e);
                $lineCost = 0.0;
            }
            $bom->setAttribute('line_cost_per_batch', $lineCost);

            return $bom;
        });

        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.index', compact('boms', 'q', 'finishedProductId', 'filterProduct', 'bomReturnPath'));
    }

    public function create(Request $request): View
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom']);
        $productOptions = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku.' — '.$p->name.' ('.$p->uom.')',
        ])->values();
        $bomProductsMeta = $this->bomProductsMeta();

        $prefillFinishedId = old('finished_product_id', $request->integer('finished_product_id')) ?: null;
        if ($prefillFinishedId && !$products->contains('id', (int) $prefillFinishedId)) {
            $prefillFinishedId = null;
        }

        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.create', compact('products', 'productOptions', 'bomProductsMeta', 'prefillFinishedId', 'bomReturnPath'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $lines = $data['lines'];
        unset($data['lines']);

        DB::connection('tenant')->transaction(function () use ($data, $lines) {
            $bom = ManufacturingBom::create($data);
            $this->syncLines($bom, $lines);
            $bom->refresh()->load(['lines.component.uomConversions']);
            $bom->syncFinishedProductStandardCost();
        });

        return $this->redirectAfterBom($request, 'BoM created.');
    }

    public function show(Request $request, ManufacturingBom $bom): View
    {
        $bom->load(['finishedProduct', 'lines.component.uomConversions']);
        $materialPerBatch = $bom->materialCostPerBatch();
        $standardPerFinished = $bom->standardCostPerFinishedUnit();
        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.show', compact('bom', 'materialPerBatch', 'standardPerFinished', 'bomReturnPath'));
    }

    public function edit(Request $request, ManufacturingBom $bom): View
    {
        $bom->load(['lines.component']);
        $products = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom']);
        $productOptions = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku.' — '.$p->name.' ('.$p->uom.')',
        ])->values();
        $bomProductsMeta = $this->bomProductsMeta();
        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.edit', compact('bom', 'products', 'productOptions', 'bomProductsMeta', 'bomReturnPath'));
    }

    public function update(Request $request, ManufacturingBom $bom): RedirectResponse
    {
        $data = $this->validated($request);
        $lines = $data['lines'];
        unset($data['lines']);

        DB::connection('tenant')->transaction(function () use ($bom, $data, $lines) {
            $bom->update($data);
            SyncAwareDelete::relation($bom->lines());
            $this->syncLines($bom, $lines);
            $bom->refresh()->load(['lines.component.uomConversions']);
            $bom->syncFinishedProductStandardCost();
        });

        return $this->redirectAfterBom($request, 'BoM updated.');
    }

    public function destroy(ManufacturingBom $bom): RedirectResponse
    {
        if (ManufacturingOrder::query()->where('bom_id', $bom->id)->where('status', ManufacturingOrder::STATUS_DONE)->exists()) {
            return redirect()->back()->withErrors('Cannot delete a BoM that has completed production orders.');
        }
        if (ManufacturingOrder::query()->where('bom_id', $bom->id)->where('status', ManufacturingOrder::STATUS_DRAFT)->exists()) {
            return redirect()->back()->withErrors('Delete draft manufacturing orders that use this BoM first.');
        }

        $bom->delete();

        return $this->redirectAfterBom($request, 'BoM deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $finishedRule = ['required', 'integer', 'exists:tenant.inventory_products,id'];
        $data = $request->validate([
            'finished_product_id' => $finishedRule,
            'name' => ['required', 'string', 'max:120'],
            'batch_qty' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
        ]);

        $data['active'] = $request->boolean('active');
        $finishedId = (int) $data['finished_product_id'];

        foreach ($data['lines'] as $row) {
            if ((int) $row['component_product_id'] === $finishedId) {
                abort(422, 'A component cannot be the same as the finished product.');
            }
        }

        foreach ($data['lines'] as $row) {
            $comp = InventoryProduct::query()
                ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
                ->findOrFail((int) $row['component_product_id']);
            $allowed = $comp->allowedUomCodes();
            $u = trim((string) $row['uom']);
            $ok = false;
            foreach ($allowed as $code) {
                if (strcasecmp($code, $u) === 0) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                abort(422, 'Invalid unit "'.$u.'" for component '.$comp->sku.'. Allowed: '.implode(', ', $allowed));
            }
        }

        $seen = [];
        foreach ($data['lines'] as $row) {
            $cid = (int) $row['component_product_id'];
            if (isset($seen[$cid])) {
                abort(422, 'Duplicate component in BoM. Combine quantities into one line.');
            }
            $seen[$cid] = true;
        }

        $product = InventoryProduct::query()->findOrFail($finishedId);
        if (!$product->active) {
            abort(422, 'Finished product must be active.');
        }

        return $data;
    }

    /**
     * @param  list<array{component_product_id: int, qty: float|int|string}>  $lines
     */
    private function syncLines(ManufacturingBom $bom, array $lines): void
    {
        $sort = 0;
        foreach ($lines as $row) {
            ManufacturingBomLine::create([
                'company_id' => $bom->company_id,
                'bom_id' => $bom->id,
                'component_product_id' => (int) $row['component_product_id'],
                'qty' => $row['qty'],
                'uom' => trim((string) $row['uom']),
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @return list<array{id:int,label:string,base_uom:string,cost:float,uoms:list<array{uom:string,factor:float}>}>
     */
    private function bomProductsMeta(): array
    {
        return InventoryProduct::query()
            ->where('active', true)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->orderBy('name')
            ->get()
            ->map(function (InventoryProduct $p) {
                $uoms = collect($p->uomsForForms())
                    ->map(function (array $row) {
                        $raw = trim((string) ($row['uom'] ?? ''));
                        $factor = (float) ($row['factor'] ?? 0);
                        $isBase = abs($factor - 1.0) < 1e-9;

                        return [
                            'uom' => $isBase ? $raw : InventoryProduct::preferredUomCode($raw),
                            'factor' => $factor,
                        ];
                    })
                    ->unique(fn (array $row) => InventoryProduct::equivalentUomFamily($row['uom']))
                    ->values()
                    ->all();

                return [
                    'id' => $p->id,
                    'label' => $p->sku.' — '.$p->name.' ('.$p->uom.')',
                    'base_uom' => $p->uom,
                    'cost' => (float) $p->cost,
                    'uoms' => $uoms,
                ];
            })
            ->values()
            ->all();
    }
}
