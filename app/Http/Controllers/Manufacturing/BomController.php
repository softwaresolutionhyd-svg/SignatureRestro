<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingBomLine;
use App\Models\ManufacturingOrder;
use App\Models\Setting;
use App\Services\Sync\SyncAwareDelete;
use App\Support\IngredientsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function printAll(Request $request): View
    {
        $boms = $this->filteredBomsForExport($request);
        $companyName = (string) Setting::get('company_name', config('app.name'));
        $q = trim((string) $request->query('q', ''));
        $iq = trim((string) $request->query('iq', ''));
        IngredientsCategory::assignWarehouseProducts();
        $ingredientProducts = $this->bomIngredientProducts(
            $boms->flatMap(fn (ManufacturingBom $bom) => $bom->lines->pluck('component_product_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );
        $ingredientMeta = $this->bomProductsMetaFrom($ingredientProducts);

        return view('manufacturing.boms.print-all', compact('boms', 'companyName', 'q', 'iq', 'ingredientMeta'));
    }

    public function updateLine(Request $request, ManufacturingBom $bom, ManufacturingBomLine $line): JsonResponse
    {
        abort_unless((int) $line->bom_id === (int) $bom->id, 404);

        $ingredientIds = IngredientsCategory::categoryIds();
        $data = $request->validate([
            'component_product_id' => [
                'nullable',
                'integer',
                'exists:tenant.inventory_products,id',
                Rule::exists('tenant.inventory_products', 'id')->where(function ($q) use ($ingredientIds) {
                    $q->whereIn('category_id', $ingredientIds)->where('active', true);
                }),
            ],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'uom' => ['required', 'string', 'max:30'],
        ], [
            'component_product_id.exists' => 'Sirf Ingredients category ke products allowed hain.',
        ]);

        $componentId = array_key_exists('component_product_id', $data) && $data['component_product_id'] !== null
            ? (int) $data['component_product_id']
            : (int) $line->component_product_id;

        if ($componentId === (int) $bom->finished_product_id) {
            return response()->json(['message' => 'Ingredient finished product nahi ho sakta.'], 422);
        }

        if ($componentId !== (int) $line->component_product_id
            && $bom->lines()->where('component_product_id', $componentId)->where('id', '!=', $line->id)->exists()) {
            return response()->json(['message' => 'Yeh ingredient pehle se recipe mein hai.'], 422);
        }

        $component = InventoryProduct::query()
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->findOrFail($componentId);
        $this->assertValidLineUom($component, (string) $data['uom']);

        DB::connection('tenant')->transaction(function () use ($line, $data, $bom, $componentId) {
            $line->update([
                'component_product_id' => $componentId,
                'qty' => $data['qty'],
                'uom' => trim((string) $data['uom']),
            ]);
            $bom->refresh()->load(['lines.component.uomConversions', 'finishedProduct']);
            $bom->syncFinishedProductStandardCost();
        });

        return response()->json($this->bomPrintSnapshot($bom->fresh(['lines.component.uomConversions', 'finishedProduct'])));
    }

    public function storeLine(Request $request, ManufacturingBom $bom): JsonResponse
    {
        $ingredientIds = IngredientsCategory::categoryIds();
        $data = $request->validate([
            'component_product_id' => [
                'required',
                'integer',
                'exists:tenant.inventory_products,id',
                Rule::exists('tenant.inventory_products', 'id')->where(function ($q) use ($ingredientIds) {
                    $q->whereIn('category_id', $ingredientIds)->where('active', true);
                }),
            ],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'uom' => ['required', 'string', 'max:30'],
        ], [
            'component_product_id.exists' => 'Sirf Ingredients category ke products allowed hain.',
        ]);

        $componentId = (int) $data['component_product_id'];
        if ($componentId === (int) $bom->finished_product_id) {
            return response()->json(['message' => 'Ingredient finished product nahi ho sakta.'], 422);
        }

        if ($bom->lines()->where('component_product_id', $componentId)->exists()) {
            return response()->json(['message' => 'Yeh ingredient pehle se recipe mein hai.'], 422);
        }

        $component = InventoryProduct::query()
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->findOrFail($componentId);
        $this->assertValidLineUom($component, (string) $data['uom']);

        $line = null;
        DB::connection('tenant')->transaction(function () use ($bom, $data, &$line) {
            $sort = (int) ($bom->lines()->max('sort_order') ?? -1) + 1;
            $line = ManufacturingBomLine::create([
                'company_id' => $bom->company_id,
                'bom_id' => $bom->id,
                'component_product_id' => (int) $data['component_product_id'],
                'qty' => $data['qty'],
                'uom' => trim((string) $data['uom']),
                'sort_order' => $sort,
            ]);
            $bom->refresh()->load(['lines.component.uomConversions', 'finishedProduct']);
            $bom->syncFinishedProductStandardCost();
        });

        $snapshot = $this->bomPrintSnapshot($bom->fresh(['lines.component.uomConversions', 'finishedProduct']));
        $snapshot['new_line_id'] = $line?->id;

        return response()->json($snapshot);
    }

    public function destroyLine(Request $request, ManufacturingBom $bom, ManufacturingBomLine $line): JsonResponse
    {
        abort_unless((int) $line->bom_id === (int) $bom->id, 404);

        DB::connection('tenant')->transaction(function () use ($line, $bom) {
            SyncAwareDelete::models([$line]);
            $bom->refresh()->load(['lines.component.uomConversions', 'finishedProduct']);
            $bom->syncFinishedProductStandardCost();
        });

        return response()->json($this->bomPrintSnapshot($bom->fresh(['lines.component.uomConversions', 'finishedProduct'])));
    }

    public function updateSalePrice(Request $request, ManufacturingBom $bom): JsonResponse
    {
        $data = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:0'],
        ]);

        $finished = InventoryProduct::query()->findOrFail((int) $bom->finished_product_id);
        $finished->price = round((float) $data['sale_price'], 2);
        $finished->save();

        return response()->json($this->bomPrintSnapshot($bom->fresh(['lines.component.uomConversions', 'finishedProduct'])));
    }

    private function assertValidLineUom(?InventoryProduct $comp, string $uom): void
    {
        if (! $comp) {
            abort(422, 'Ingredient not found.');
        }

        $allowed = $comp->allowedUomCodes();
        $u = trim($uom);
        foreach ($allowed as $code) {
            if (strcasecmp($code, $u) === 0) {
                return;
            }
        }

        abort(422, 'Invalid unit "'.$u.'" for '.$comp->sku.'. Allowed: '.implode(', ', $allowed));
    }

    /**
     * @return array{
     *     bom_id:int,
     *     total_cost:float,
     *     sale_price:float,
     *     profit:float,
     *     finished_uom:string,
     *     lines:list<array{id:int,component_product_id:int,component_name:string,qty:float,uom:string,rate:float,amount:float,uoms:list<string>}>
     * }
     */
    private function bomPrintSnapshot(ManufacturingBom $bom): array
    {
        $bom->loadMissing(['finishedProduct', 'lines.component.uomConversions']);
        $materialPerBatch = (float) $bom->materialCostPerBatch();
        $batchQty = (float) $bom->batch_qty;
        $totalCost = $batchQty > 0 ? ($materialPerBatch / $batchQty) : $materialPerBatch;
        $salePrice = (float) ($bom->finishedProduct?->price ?? 0);

        $lines = $bom->lines->map(function (ManufacturingBomLine $line) {
            $qty = (float) $line->qty;
            $uom = $line->effectiveUom();
            $amount = (float) $line->lineMaterialCostPerBatch();
            $rate = $qty > 0 ? ($amount / $qty) : (float) ($line->component?->cost ?? 0);
            $uoms = collect($line->component?->uomsForForms() ?? [])
                ->pluck('uom')
                ->map(fn ($u) => (string) $u)
                ->values()
                ->all();

            return [
                'id' => (int) $line->id,
                'component_product_id' => (int) $line->component_product_id,
                'component_name' => (string) ($line->component?->name ?? '—'),
                'qty' => $qty,
                'uom' => $uom,
                'rate' => round($rate, 6),
                'amount' => round($amount, 6),
                'uoms' => $uoms !== [] ? $uoms : array_filter([$uom]),
            ];
        })->values()->all();

        return [
            'bom_id' => (int) $bom->id,
            'total_cost' => round($totalCost, 6),
            'sale_price' => round($salePrice, 2),
            'profit' => round($salePrice - $totalCost, 6),
            'finished_uom' => (string) ($bom->finishedProduct?->uom ?? ''),
            'lines' => $lines,
        ];
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $boms = $this->filteredBomsForExport($request);
        $filename = 'recipes-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($boms) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Urdu/special chars correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Dish Name',
                'Ingredient',
                'Quantity',
                'Unit',
            ]);

            foreach ($boms as $bom) {
                /** @var ManufacturingBom $bom */
                $dishName = (string) ($bom->finishedProduct?->name ?? '—');

                if ($bom->lines->isEmpty()) {
                    fputcsv($out, [$dishName, '', '', '']);
                    continue;
                }

                foreach ($bom->lines as $line) {
                    $qty = (float) $line->qty;
                    fputcsv($out, [
                        $dishName,
                        (string) ($line->component?->name ?? '—'),
                        rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?: '0',
                        $line->effectiveUom(),
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * All matching BoMs (no pagination) for print / Excel — same filters as index.
     *
     * @return Collection<int, ManufacturingBom>
     */
    private function filteredBomsForExport(Request $request): Collection
    {
        $q = trim((string) $request->query('q', ''));
        $iq = trim((string) $request->query('iq', ''));
        $finishedProductId = $request->filled('finished_product') ? $request->integer('finished_product') : null;

        $boms = ManufacturingBom::query()
            ->with([
                'finishedProduct:id,sku,name,uom,price',
                'lines' => fn ($query) => $query->orderBy('sort_order'),
                'lines.component' => fn ($query) => $query->select([
                    'id', 'sku', 'name', 'uom', 'cost', 'qty_on_hand',
                    'package_contents_qty', 'package_contents_uom',
                ])->with([
                    'uomConversions' => fn ($c) => $c->where('active', true),
                ]),
            ])
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
            ->when($iq !== '', function ($query) use ($iq) {
                $query->whereHas('lines.component', function ($p) use ($iq) {
                    $p->where('sku', 'like', "%{$iq}%")
                        ->orWhere('name', 'like', "%{$iq}%");
                });
            })
            ->orderBy(
                InventoryProduct::query()
                    ->select('name')
                    ->whereColumn('inventory_products.id', 'manufacturing_boms.finished_product_id')
                    ->limit(1)
            )
            ->orderBy('name')
            ->get();

        return $boms;
    }

    public function create(Request $request): View
    {
        IngredientsCategory::assignWarehouseProducts();

        $finishedProducts = $this->bomFinishedProducts();
        $ingredientProducts = $this->bomIngredientProducts();
        $finishedProductsMeta = $this->bomProductsMetaFrom($finishedProducts);
        $ingredientProductsMeta = $this->bomProductsMetaFrom($ingredientProducts);
        // Line costing / UOM lookup: ingredients (+ finished for header search).
        $bomProductsMeta = collect($finishedProductsMeta)
            ->keyBy('id')
            ->union(collect($ingredientProductsMeta)->keyBy('id'))
            ->values()
            ->all();

        $products = $finishedProducts;
        $productOptions = $finishedProducts->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku.' — '.$p->name.' ('.$p->uom.')',
        ])->values();

        $prefillFinishedId = old('finished_product_id', $request->integer('finished_product_id')) ?: null;
        if ($prefillFinishedId && ! $finishedProducts->contains('id', (int) $prefillFinishedId)) {
            $prefillFinishedId = null;
        }

        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.create', compact(
            'products',
            'productOptions',
            'finishedProducts',
            'ingredientProducts',
            'bomProductsMeta',
            'finishedProductsMeta',
            'ingredientProductsMeta',
            'prefillFinishedId',
            'bomReturnPath'
        ));
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
        IngredientsCategory::assignWarehouseProducts();

        $bom->load(['lines.component']);
        $finishedProducts = $this->bomFinishedProducts();
        $ingredientProducts = $this->bomIngredientProducts(
            $bom->lines->pluck('component_product_id')->map(fn ($id) => (int) $id)->all()
        );
        $finishedProductsMeta = $this->bomProductsMetaFrom($finishedProducts);
        $ingredientProductsMeta = $this->bomProductsMetaFrom($ingredientProducts);
        $bomProductsMeta = collect($finishedProductsMeta)
            ->keyBy('id')
            ->union(collect($ingredientProductsMeta)->keyBy('id'))
            ->values()
            ->all();

        $products = $finishedProducts;
        $productOptions = $finishedProducts->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku.' — '.$p->name.' ('.$p->uom.')',
        ])->values();
        $bomReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('manufacturing.boms.edit', compact(
            'bom',
            'products',
            'productOptions',
            'finishedProducts',
            'ingredientProducts',
            'bomProductsMeta',
            'finishedProductsMeta',
            'ingredientProductsMeta',
            'bomReturnPath'
        ));
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

    public function destroy(Request $request, ManufacturingBom $bom): RedirectResponse
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
        $ingredientIds = IngredientsCategory::categoryIds();
        $finishedRule = ['required', 'integer', 'exists:tenant.inventory_products,id'];
        $componentRule = [
            'required',
            'integer',
            'exists:tenant.inventory_products,id',
            Rule::exists('tenant.inventory_products', 'id')->where(function ($q) use ($ingredientIds) {
                $q->whereIn('category_id', $ingredientIds)->where('active', true);
            }),
        ];
        $data = $request->validate([
            'finished_product_id' => $finishedRule,
            'name' => ['required', 'string', 'max:120'],
            'batch_qty' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_product_id' => $componentRule,
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
        ], [
            'lines.*.component_product_id.exists' => 'Recipe lines mein sirf Ingredients category ke products allowed hain.',
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
            if (! $ok) {
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
        if (! $product->active) {
            abort(422, 'Finished product must be active.');
        }

        return $data;
    }

    /**
     * @param  list<array{component_product_id: int, qty: float|int|string, uom?: string}>  $lines
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
     * Finished goods picker — active products (not limited to Ingredients).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryProduct>
     */
    private function bomFinishedProducts()
    {
        return InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom', 'cost', 'category_id']);
    }

    /**
     * Recipe components — Ingredients category only (+ optional legacy IDs for edit).
     *
     * @param  list<int>  $alsoIncludeIds
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryProduct>
     */
    private function bomIngredientProducts(array $alsoIncludeIds = [])
    {
        $categoryIds = IngredientsCategory::categoryIds();
        $alsoIncludeIds = array_values(array_unique(array_filter(array_map('intval', $alsoIncludeIds))));

        return InventoryProduct::query()
            ->where('active', true)
            ->where(function ($q) use ($categoryIds, $alsoIncludeIds) {
                $q->whereIn('category_id', $categoryIds);
                if ($alsoIncludeIds !== []) {
                    $q->orWhereIn('id', $alsoIncludeIds);
                }
            })
            ->with(['uomConversions' => fn ($c) => $c->where('active', true)])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom', 'cost', 'category_id', 'package_contents_qty', 'package_contents_uom']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InventoryProduct>  $products
     * @return list<array{id:int,label:string,base_uom:string,cost:float,uoms:list<array{uom:string,factor:float}>}>
     */
    private function bomProductsMetaFrom($products): array
    {
        return $products
            ->map(function (InventoryProduct $p) {
                $p->loadMissing(['uomConversions' => fn ($q) => $q->where('active', true)]);
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
                    'name' => (string) $p->name,
                    'sku' => (string) $p->sku,
                    'base_uom' => $p->uom,
                    'cost' => (float) $p->cost,
                    'uoms' => $uoms,
                ];
            })
            ->values()
            ->all();
    }
}
