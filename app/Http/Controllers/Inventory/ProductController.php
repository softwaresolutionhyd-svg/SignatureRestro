<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Support\SafeInternalReturnPath;
use App\Models\InventoryCategory;
use App\Models\InventoryDepartment;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryProductFavorite;
use App\Models\InventoryProductUomConversion;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Models\ManufacturingBom;
use App\Support\ProductCosting;
use App\Support\MenuCategory;
use App\Services\ProductImageService;
use App\Services\InventoryStockService;
use App\Services\Sync\SyncAwareDelete;
use App\Services\Sync\SyncOutboxRecorder;
use App\Models\ManufacturingBomLine;
use App\Models\PosOrderItem;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductImageService $productImages,
        private readonly InventoryStockService $stockService,
    ) {}

    /**
     * Allow redirects only to same-app paths or URLs under config('app.url').
     */
    private function safeInternalReturnUrl(mixed $value): ?string
    {
        return SafeInternalReturnPath::normalize($value);
    }

    private function redirectAfterProduct(Request $request, string $status): \Illuminate\Http\RedirectResponse
    {
        $safe = $this->safeInternalReturnUrl($request->input('return'));
        if ($safe !== null) {
            return redirect()->to($safe)->with('status', $status);
        }

        return redirect()->route('inventory.products.index')->with('status', $status);
    }

    public function index(Request $request)
    {
        MenuCategory::assignPosProducts();

        $q            = trim((string) $request->query('q', ''));
        $stockFilter  = $request->query('stock_filter', '');
        $purchaseFilter = $request->query('for_purchase', '');
        $posFilter = $request->query('for_pos', '');
        $categoryId   = (int) $request->query('category_id', 0);
        $categoryId   = $categoryId > 0 ? $categoryId : null;
        $departmentId = (int) $request->query('department_id', 0);
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $userId       = (int) Auth::id();
        $categories   = InventoryCategory::query()
            ->with('parent:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
        $departments  = InventoryDepartment::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        // Low stock / out-of-stock counts for the alert banner
        $lowStockCount    = InventoryProduct::where('active', true)
            ->where('for_purchase', true)
            ->where('reorder_level', '>', 0)
            ->whereRaw('qty_on_hand <= reorder_level')
            ->count();
        $outOfStockCount  = InventoryProduct::where('active', true)
            ->where('for_purchase', true)
            ->where('qty_on_hand', '<=', 0)
            ->count();

        $products = InventoryProduct::query()
            ->leftJoin('inventory_product_favorites as ipf', function ($join) use ($userId) {
                $join->on('ipf.product_id', '=', 'inventory_products.id')
                    ->where('ipf.user_id', '=', $userId);
            })
            ->select('inventory_products.*')
            ->selectRaw('CASE WHEN ipf.id IS NULL THEN 0 ELSE 1 END as is_starred_sort')
            ->with(['category:id,name,parent_id', 'category.parent:id,name', 'department:id,name', 'departments:id,name'])
            ->withCount(['manufacturingBoms as active_manufacturing_boms_count' => fn ($q) => $q->where('active', true)])
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhereHas('category', fn ($cat) => $cat->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('department', fn ($dep) => $dep->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('departments', fn ($dep) => $dep->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($categoryId !== null, fn ($query) => $query->where('inventory_products.category_id', $categoryId))
            ->when($departmentId !== null, fn ($query) => $query->whereHas(
                'departments',
                fn ($dep) => $dep->where('inventory_departments.id', $departmentId)
            ))
            ->when($stockFilter === 'low', fn ($q) => $q->where('for_purchase', true)->where('reorder_level', '>', 0)->whereRaw('qty_on_hand <= reorder_level')->excludingActiveBomFinishedProducts())
            ->when($stockFilter === 'zero', fn ($q) => $q->where('for_purchase', true)->where('qty_on_hand', '<=', 0))
            ->when($stockFilter === 'ok', fn ($q) => $q->where('for_purchase', true)->where(fn ($sub) => $sub->where('reorder_level', 0)->orWhereRaw('qty_on_hand > reorder_level')))
            ->when($purchaseFilter === '1', fn ($q) => $q->where('for_purchase', true))
            ->when($purchaseFilter === '0', fn ($q) => $q->where('for_purchase', false))
            ->when($posFilter === '1', fn ($q) => $q->where('for_pos', true))
            ->when($posFilter === '0', fn ($q) => $q->where('for_pos', false))
            ->orderByDesc('is_starred_sort')
            ->orderBy('name')
            ->paginate(Setting::pageSize('inventory_products_per_page', 20))
            ->withQueryString();

        $productIds = $products->getCollection()->pluck('id')->all();
        $starredIds = InventoryProductFavorite::query()
            ->where('user_id', $userId)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
        $starredLookup = array_flip($starredIds);

        $bomCountByProduct = collect();
        if ($productIds !== []) {
            $bomCountByProduct = ManufacturingBom::query()
                ->whereIn('finished_product_id', $productIds)
                ->selectRaw('finished_product_id, COUNT(*) as c')
                ->groupBy('finished_product_id')
                ->pluck('c', 'finished_product_id');
        }

        $heldRows = $productIds === [] ? collect() : PosOrderItem::query()
            ->select(['pos_order_items.product_id', 'pos_order_items.uom'])
            ->selectRaw('SUM(pos_order_items.qty) as qty_sum')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->whereIn('pos_order_items.product_id', $productIds)
            ->where('pos_orders.status', 'draft')
            ->where('pos_orders.type', 'sale')
            ->groupBy('pos_order_items.product_id', 'pos_order_items.uom')
            ->get();

        $holdRows = $productIds === [] ? collect() : PosOrderItem::query()
            ->select(['pos_order_items.product_id', 'pos_order_items.uom'])
            ->selectRaw('SUM(pos_order_items.qty) as qty_sum')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->whereIn('pos_order_items.product_id', $productIds)
            ->where('pos_orders.status', 'draft')
            ->where('pos_orders.type', 'sale')
            ->where('pos_orders.user_id', $userId)
            ->groupBy('pos_order_items.product_id', 'pos_order_items.uom')
            ->get();

        $saleQtyRows = $productIds === [] ? collect() : PosOrderItem::query()
            ->select(['pos_order_items.product_id', 'pos_order_items.uom'])
            ->selectRaw('SUM(pos_order_items.qty) as qty_sum')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->whereIn('pos_order_items.product_id', $productIds)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->groupBy('pos_order_items.product_id', 'pos_order_items.uom')
            ->get();

        $saleAmountRows = $productIds === [] ? collect() : PosOrderItem::query()
            ->select(['pos_order_items.product_id'])
            ->selectRaw('SUM(pos_order_items.total) as amount_sum')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->whereIn('pos_order_items.product_id', $productIds)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->groupBy('pos_order_items.product_id')
            ->get();

        $heldByProductId = $heldRows->groupBy('product_id');
        $holdByProductId = $holdRows->groupBy('product_id');
        $saleQtyByProductId = $saleQtyRows->groupBy('product_id');
        $saleAmountByProductId = $saleAmountRows->keyBy('product_id');

        $heldByProduct = [];
        $holdByProduct = [];
        $saleQtyByProduct = [];
        $saleAmountByProduct = [];
        foreach ($products as $p) {
            $conversionMap = $p->uomConversions
                ->pluck('factor_to_base', 'uom')
                ->map(fn ($v) => (float) $v)
                ->toArray();
            $conversionMap[$p->uom] = 1.0;

            $heldBase = 0.0;
            foreach ($heldByProductId->get($p->id, collect()) as $r) {
                $factor = (float) ($conversionMap[$r->uom] ?? 0);
                $factor = $factor > 0 ? $factor : (($r->uom === $p->uom) ? 1.0 : 0.0);
                $heldBase += (float) $r->qty_sum * $factor;
            }
            $heldByProduct[$p->id] = $heldBase;

            $holdBase = 0.0;
            foreach ($holdByProductId->get($p->id, collect()) as $r) {
                $factor = (float) ($conversionMap[$r->uom] ?? 0);
                $factor = $factor > 0 ? $factor : (($r->uom === $p->uom) ? 1.0 : 0.0);
                $holdBase += (float) $r->qty_sum * $factor;
            }
            $holdByProduct[$p->id] = $holdBase;

            $saleQtyBase = 0.0;
            foreach ($saleQtyByProductId->get($p->id, collect()) as $r) {
                $factor = (float) ($conversionMap[$r->uom] ?? 0);
                $factor = $factor > 0 ? $factor : (($r->uom === $p->uom) ? 1.0 : 0.0);
                $saleQtyBase += (float) $r->qty_sum * $factor;
            }
            $saleQtyByProduct[$p->id] = $saleQtyBase;
            $saleAmountRow = $saleAmountByProductId->get($p->id);
            $saleAmountByProduct[$p->id] = (float) ($saleAmountRow ? $saleAmountRow->amount_sum : 0);
        }

        $products->getCollection()->transform(function ($p) use ($heldByProduct, $holdByProduct, $saleQtyByProduct, $saleAmountByProduct, $starredLookup, $bomCountByProduct) {
            $p->held_qty = (float) ($heldByProduct[$p->id] ?? 0);
            $p->hold_qty = (float) ($holdByProduct[$p->id] ?? 0);
            $p->total_sale_qty = (float) ($saleQtyByProduct[$p->id] ?? 0);
            $p->total_sale_amount = (float) ($saleAmountByProduct[$p->id] ?? 0);
            $p->is_starred = isset($starredLookup[$p->id]);
            $p->bom_count = (int) ($bomCountByProduct[$p->id] ?? 0);

            return $p;
        });

        $canManufacturing = in_array($request->user()?->role, ['super_admin', 'company_admin', 'admin'], true)
            || (bool) data_get($request->user()?->permissions, 'manufacturing.view');

        $showLowStockBanner = Setting::get('inventory_show_low_stock_banner', '1') === '1';

        return view('inventory.products.index', compact(
            'products',
            'q',
            'categories',
            'categoryId',
            'departments',
            'departmentId',
            'purchaseFilter',
            'posFilter',
            'lowStockCount',
            'outOfStockCount',
            'canManufacturing',
            'showLowStockBanner'
        ));
    }

    public function toggleStar(InventoryProduct $product)
    {
        $favorite = InventoryProductFavorite::query()
            ->where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
        } else {
            InventoryProductFavorite::query()->create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
            ]);
        }

        return back();
    }

    public function create()
    {
        [$uomLibraryUnits, $uomLibraryRules] = $this->uomLibraryForProductForm();

        return view('inventory.products.create', array_merge(
            compact('uomLibraryUnits', 'uomLibraryRules'),
            $this->categoryFormDataForProduct(null),
            $this->departmentFormDataForProduct(null)
        ));
    }

    public function store(Request $request)
    {
        $this->normalizeRequestUomBlanks($request);

        $allowedUoms = $this->allowedUomCodesForProductForm(null);
        $uomRules = ['required', 'string', 'max:30'];
        $pkgUomRules = ['nullable', 'required_with:package_contents_qty', 'string', 'max:30'];
        $convUomRules = ['nullable', 'string', 'max:30'];
        if ($allowedUoms !== []) {
            $uomRules[] = Rule::in($allowedUoms);
            $pkgUomRules[] = Rule::in($allowedUoms);
            $convUomRules[] = Rule::in($allowedUoms);
        }

        $data = $request->validate([
            'sku'           => ['nullable', 'string', 'max:80', 'unique:tenant.inventory_products,sku'],
            'barcode'       => ['nullable', 'string', 'max:120', 'unique:tenant.inventory_products,barcode'],
            'name'          => ['required', 'string', 'max:200'],
            'parent_category_id' => ['nullable', 'integer', 'exists:tenant.inventory_categories,id'],
            'sub_category_id'    => ['nullable', 'integer', 'exists:tenant.inventory_categories,id'],
            'department_ids'     => ['nullable', 'array'],
            'department_ids.*'   => ['integer', 'exists:tenant.inventory_departments,id'],
            'uom'           => $uomRules,
            'package_contents_qty' => ['nullable', 'required_with:package_contents_uom', 'numeric', 'min:0.000001'],
            'package_contents_uom' => $pkgUomRules,
            'conversions'   => ['nullable', 'array'],
            'conversions.*.uom'             => $convUomRules,
            'conversions.*.factor_to_base'  => ['nullable', 'numeric', 'min:0.000001'],
            'cost'          => ['nullable', 'numeric', 'min:0'],
            'extra_costs'   => ['nullable', 'array'],
            'extra_costs.*' => ['nullable', 'numeric'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'active'        => ['nullable', 'boolean'],
            'for_pos'       => ['nullable', 'boolean'],
            'for_purchase'  => ['nullable', 'boolean'],
            'image'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);
        $this->assertUniqueConversionUnits($data);

        $data['category_id'] = $this->resolveProductCategoryId($request);
        unset($data['department_ids']);

        $this->applyPackageContentsFields($request, $data);
        $this->applyProductCostingFields($request, $data);
        $this->normalizeStoredUomFields($data);

        if (! isset($data['sku']) || trim((string) $data['sku']) === '') {
            $data['sku'] = InventoryProduct::generateNextSku('PRD');
        }

        $data['for_pos']       = $request->boolean('for_pos');
        $data['for_purchase']  = $request->boolean('for_purchase');
        $data['active']        = (bool) ($data['active'] ?? false);
        $data['cost']          = isset($data['cost']) ? (float) $data['cost'] : 0.0;
        $data['gas_charges']   = (float) ($data['gas_charges'] ?? 0);
        $data['service_charges'] = 0;
        $data['extra_costs']   = $data['extra_costs'] ?? [];
        $data['price']         = isset($data['price']) ? (float) $data['price'] : 0.0;
        $effectiveCost         = (float) $data['cost'] + (float) collect((array) ($data['extra_costs'] ?? []))->sum();
        $data['profit']        = isset($data['profit']) ? (float) $data['profit'] : round((float) $data['price'] - $effectiveCost, 2);
        $data['reorder_level'] = $data['for_purchase'] ? ($data['reorder_level'] ?? 0) : 0;
        $data['qty_on_hand']   = 0;
        $data['department_id'] = null;

        $product = InventoryProduct::create($data);
        $this->syncProductDepartments($product, $this->validatedDepartmentIds($request));

        if ($request->hasFile('image')) {
            $product->update([
                'image_path' => $this->productImages->storeSquare($request->file('image')),
            ]);
        }

        $conversions = $request->input('conversions', []);
        foreach ($conversions as $c) {
            $uom = isset($c['uom']) ? InventoryUnit::normalizeCode((string) $c['uom']) : '';
            $factor = isset($c['factor_to_base']) ? (float)$c['factor_to_base'] : 0;

            if ($uom === '' || $factor <= 0) continue;
            if ($uom === $product->uom) continue; // base is implicit

            InventoryProductUomConversion::query()->updateOrCreate(
                ['product_id' => $product->id, 'uom' => $uom],
                ['factor_to_base' => $factor, 'active' => true]
            );
        }

        if ($this->inventoryProductsHavePackageColumns()) {
            $this->syncPackageContentsConversion($product);
        }

        return redirect()->route('inventory.products.index')->with('status', 'Product created.');
    }

    public function edit(Request $request, InventoryProduct $product)
    {
        $product->loadMissing(['category.parent', 'department', 'departments']);

        $conversionMap = $product->uomConversions()
            ->where('active', true)
            ->pluck('factor_to_base', 'uom')
            ->map(fn ($v) => (float) $v)
            ->toArray();
        $conversionMap[$product->uom] = 1.0;

        $history = PurchaseOrderLine::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('status', 'received'))
            ->with(['order.vendor:id,name', 'order:id,number,vendor_id,status,received_at,order_date'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function ($line) use ($conversionMap, $product) {
                $factor = (float) ($conversionMap[$line->uom] ?? 0);
                $factor = $factor > 0 ? $factor : 1.0;
                $qtyBase = (float) $line->qty * $factor;
                $pricePerBase = (float) $line->unit_price / $factor;
                $date = optional($line->order->received_at)->format('Y-m-d') ?: optional($line->order->order_date)->format('Y-m-d');

                return [
                    'line_id' => $line->id,
                    'order_id' => $line->purchase_order_id,
                    'date' => $date,
                    'po_number' => $line->order->number ?? '-',
                    'vendor' => $line->order->vendor->name ?? '-',
                    'qty' => (float) $line->qty,
                    'uom' => $line->uom,
                    'qty_base' => $qtyBase,
                    'base_uom' => $product->uom,
                    'rate' => (float) $line->unit_price,
                    'rate_base' => $pricePerBase,
                    'line_total' => (float) $line->total,
                ];
            });

        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');
        $monthRows = $history->filter(fn ($r) => $r['date'] >= $monthStart && $r['date'] <= $monthEnd);

        $monthQtyBase = (float) $monthRows->sum('qty_base');
        $monthCostBase = (float) $monthRows->sum(fn ($r) => $r['qty_base'] * $r['rate_base']);
        $monthAvgRateBase = $monthQtyBase > 0 ? ($monthCostBase / $monthQtyBase) : 0.0;
        $lastRateBase = (float) ($history->first()['rate_base'] ?? 0);

        $salesMonthRows = PosOrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('status', 'paid')->where('type', 'sale')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]))
            ->get(['uom', 'qty', 'total']);

        $salesMonthQtyBase = 0.0;
        $salesMonthAmount = 0.0;
        foreach ($salesMonthRows as $row) {
            $factor = (float) ($conversionMap[$row->uom] ?? 0);
            $factor = $factor > 0 ? $factor : (($row->uom === $product->uom) ? 1.0 : 0.0);
            $salesMonthQtyBase += ((float) $row->qty * $factor);
            $salesMonthAmount += (float) $row->total;
        }

        $purchaseSummary = [
            'month_qty_base' => $monthQtyBase,
            'month_avg_rate_base' => $monthAvgRateBase,
            'last_rate_base' => $lastRateBase,
            'base_uom' => $product->uom,
            'rows_count_this_month' => $monthRows->count(),
            'sale_month_qty_base' => $salesMonthQtyBase,
            'sale_month_amount' => $salesMonthAmount,
        ];

        $productBoms = ManufacturingBom::query()
            ->where('finished_product_id', $product->id)
            ->withCount('lines')
            ->with(['lines' => fn ($q) => $q->orderBy('sort_order')->with(['component' => fn ($c) => $c->with(['uomConversions' => fn ($u) => $u->where('active', true)])])])
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
        $bomLineCosts = [];
        foreach ($productBoms as $bom) {
            $bomLineCosts[$bom->id] = (float) $bom->materialCostPerBatch();
        }

        $usedInBomIds = ManufacturingBomLine::query()
            ->where('component_product_id', $product->id)
            ->pluck('bom_id')
            ->unique()
            ->values();
        $componentUsedInBoms = ManufacturingBom::query()
            ->whereIn('id', $usedInBomIds)
            ->withCount('lines')
            ->with('finishedProduct:id,sku,name,uom')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $canManufacturing = in_array(request()->user()?->role, ['super_admin', 'company_admin', 'admin'], true)
            || (bool) data_get(request()->user()?->permissions, 'manufacturing.view');

        $activeBom = $productBoms->first(fn ($b) => $b->active);
        if ($activeBom) {
            $activeBom->syncFinishedProductStandardCost();
            $product->refresh();
        }
        $bomStandardCost = $activeBom ? (float) $activeBom->standardCostPerFinishedUnit() : null;

        [$uomLibraryUnits, $uomLibraryRules] = $this->uomLibraryForProductForm();
        $productReturnPath = $this->safeInternalReturnUrl($request->query('return'));

        return view('inventory.products.edit', array_merge(
            compact('product', 'purchaseSummary', 'history', 'productBoms', 'bomLineCosts', 'componentUsedInBoms', 'canManufacturing', 'uomLibraryUnits', 'uomLibraryRules', 'productReturnPath', 'bomStandardCost'),
            $this->categoryFormDataForProduct($product),
            $this->departmentFormDataForProduct($product)
        ));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, \App\Models\InventoryUnit>, 1: list<array{from: string, to: string, factor: float}>}
     */
    private function uomLibraryForProductForm(): array
    {
        if (! Schema::connection('tenant')->hasTable('inventory_units')) {
            return [collect(), []];
        }

        $units = InventoryUnit::query()->orderBy('code')->get(['id', 'code', 'name'])
            ->filter(function (InventoryUnit $unit) {
                $code = InventoryUnit::normalizeCode((string) $unit->code);
                $preferred = InventoryProduct::preferredUomCode($code);

                // Hide alias spellings (gm/gram/grams) when preferred code (g) is the family key
                // and this row is not itself the preferred spelling.
                return $code === $preferred;
            })
            ->values();

        if (! Schema::connection('tenant')->hasTable('inventory_unit_conversions')) {
            return [$units, []];
        }

        $rules = InventoryUnitConversion::query()
            ->with(['fromUnit:id,code', 'toUnit:id,code'])
            ->get()
            ->map(fn (InventoryUnitConversion $c) => [
                'from' => InventoryProduct::preferredUomCode((string) ($c->fromUnit->code ?? '')),
                'to' => InventoryProduct::preferredUomCode((string) ($c->toUnit->code ?? '')),
                'factor' => (float) $c->factor,
            ])
            ->filter(fn (array $r) => $r['from'] !== '' && $r['to'] !== '')
            ->unique(fn (array $r) => $r['from'].'|'.$r['to'])
            ->values()
            ->all();

        return [$units, $rules];
    }

    /** Lowercase codes from Inventory → Units (empty if table missing or no rows). */
    private function libraryUnitCodesStrict(): array
    {
        if (! Schema::connection('tenant')->hasTable('inventory_units')) {
            return [];
        }

        return InventoryUnit::query()
            ->orderBy('code')
            ->pluck('code')
            ->map(fn ($c) => InventoryUnit::normalizeCode((string) $c))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Allowed UOM codes for validation: library only on create; on edit, library plus this product’s
     * current codes so legacy rows still save until you migrate them into the library.
     *
     * @return list<string>
     */
    private function allowedUomCodesForProductForm(?InventoryProduct $existing): array
    {
        $lib = $this->libraryUnitCodesStrict();
        if ($lib === []) {
            return [];
        }
        if (!$existing) {
            return $lib;
        }

        $existing->loadMissing('uomConversions');
        $extra = [InventoryUnit::normalizeCode($existing->uom)];
        if ($existing->package_contents_uom) {
            $extra[] = InventoryUnit::normalizeCode((string) $existing->package_contents_uom);
        }
        foreach ($existing->uomConversions as $c) {
            $extra[] = InventoryUnit::normalizeCode((string) $c->uom);
        }

        return array_values(array_unique(array_merge($lib, array_filter($extra))));
    }

    /** @param  array<string, mixed>  $data */
    private function normalizeStoredUomFields(array &$data): void
    {
        $data['uom'] = InventoryUnit::normalizeCode((string) ($data['uom'] ?? ''));
        if (!empty($data['package_contents_uom'])) {
            $data['package_contents_uom'] = InventoryUnit::normalizeCode((string) $data['package_contents_uom']);
        }
    }

    /** Ensure Other Units contains each UOM only once. */
    private function assertUniqueConversionUnits(array $data): void
    {
        $rows = $data['conversions'] ?? [];
        if (!is_array($rows)) {
            return;
        }

        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $uom = InventoryUnit::normalizeCode((string) ($row['uom'] ?? ''));
            if ($uom === '') {
                continue;
            }
            if (isset($seen[$uom])) {
                throw ValidationException::withMessages([
                    'conversions' => ["Other Units mein unit \"{$uom}\" sirf ek dafa add ho sakti hai."],
                ]);
            }

            $seen[$uom] = true;
        }
    }

    /** So optional selects (empty option) do not fail Rule::in with "". */
    private function normalizeRequestUomBlanks(Request $request): void
    {
        if ($request->has('uom')) {
            $base = InventoryUnit::normalizeCode((string) $request->input('uom', ''));
            $request->merge(['uom' => $base]);
        }

        $pkg = $request->input('package_contents_uom');
        if ($pkg === null || trim((string) $pkg) === '') {
            $request->merge(['package_contents_uom' => null]);
        } else {
            $request->merge(['package_contents_uom' => InventoryUnit::normalizeCode((string) $pkg)]);
        }

        $conv = $request->input('conversions', []);
        if (!is_array($conv)) {
            return;
        }

        foreach ($conv as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $u = $row['uom'] ?? null;
            if ($u === null || trim((string) $u) === '') {
                $conv[$i]['uom'] = null;
            } else {
                $conv[$i]['uom'] = InventoryUnit::normalizeCode((string) $u);
            }
        }

        $request->merge(['conversions' => $conv]);
    }

    private function inventoryProductsHavePackageColumns(): bool
    {
        return Schema::hasTable('inventory_products')
            && Schema::hasColumn('inventory_products', 'package_contents_qty')
            && Schema::hasColumn('inventory_products', 'package_contents_uom');
    }

    private function inventoryProductsHaveCostingColumns(): bool
    {
        return Schema::hasTable('inventory_products')
            && Schema::hasColumn('inventory_products', 'extra_costs');
    }

    /**
     * Add packet-size columns at runtime if missing (e.g. migrate not run). Only runs when the form sends those fields.
     */
    private function ensureInventoryProductsPackageColumns(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        try {
            if (! Schema::hasColumn('inventory_products', 'package_contents_qty')) {
                Schema::table('inventory_products', function (Blueprint $table) {
                    $table->decimal('package_contents_qty', 14, 6)->nullable();
                });
            }
            if (! Schema::hasColumn('inventory_products', 'package_contents_uom')) {
                Schema::table('inventory_products', function (Blueprint $table) {
                    $table->string('package_contents_uom', 30)->nullable();
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensureInventoryProductsCostingColumns(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        try {
            if (! Schema::hasColumn('inventory_products', 'extra_costs')) {
                Schema::table('inventory_products', function (Blueprint $table) {
                    $table->json('extra_costs')->nullable();
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * When DB columns exist: normalize packet-size fields. Otherwise strip them so save does not 500.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyPackageContentsFields(Request $request, array &$data): void
    {
        if ($request->filled('package_contents_qty') || $request->filled('package_contents_uom')) {
            $this->ensureInventoryProductsPackageColumns();
        }

        if (! $this->inventoryProductsHavePackageColumns()) {
            unset($data['package_contents_qty'], $data['package_contents_uom']);

            if ($request->filled('package_contents_qty') || $request->filled('package_contents_uom')) {
                session()->flash(
                    'warning',
                    'Packet size could not be saved (database columns missing or ALTER denied). Ask your host to run: php artisan migrate'
                );
            }

            return;
        }

        $this->mergePackageContentsIntoData($request, $data);
    }

    /**
     * Ensure costing columns exist before save and normalize values.
     * Extra fields are driven from Settings (base cost or another line; % add/subtract or × / ÷).
     *
     * @param  array<string, mixed>  $data
     */
    private function applyProductCostingFields(Request $request, array &$data): void
    {
        if (
            $request->has('extra_costs')
        ) {
            $this->ensureInventoryProductsCostingColumns();
        }

        if (! $this->inventoryProductsHaveCostingColumns()) {
            unset($data['extra_costs']);
            if (
                $request->has('extra_costs')
            ) {
                session()->flash(
                    'warning',
                    'Custom pricing fields could not be saved (database column missing or ALTER denied). Run: php artisan migrate'
                );
            }

            return;
        }

        $cost = isset($data['cost']) ? (float) $data['cost'] : 0.0;
        $submittedPrice = isset($data['price']) ? (float) $data['price'] : 0.0;
        $costing = ProductCosting::computeFromCost($cost, $submittedPrice, recipeDriven: false);

        $data['service_charges'] = 0.0;
        $data['extra_costs'] = $costing['extra_costs'];
        $data['gas_charges'] = $costing['gas_charges'];
        $data['price'] = round(max($submittedPrice, 0), 2);
        $data['profit'] = round($data['price'] - $costing['effective_cost'], 2);
    }

    /**
     * When an active recipe exists, cost + sale price follow recipe rollup and settings rules.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyActiveRecipeStandardCost(InventoryProduct $product, array &$data): bool
    {
        $activeBom = ManufacturingBom::query()
            ->where('finished_product_id', $product->id)
            ->where('active', true)
            ->with(['lines.component.uomConversions'])
            ->first();

        if ($activeBom === null || $activeBom->lines->isEmpty()) {
            return false;
        }

        $recipeCost = round($activeBom->standardCostPerFinishedUnit(), 2);
        $submittedPrice = isset($data['price']) ? (float) $data['price'] : (float) $product->price;
        $costing = ProductCosting::computeFromCost(
            $recipeCost,
            $submittedPrice,
            recipeDriven: true,
            previousEffectiveCost: (float) $product->total,
        );

        $data['cost'] = $recipeCost;
        $data['extra_costs'] = $costing['extra_costs'];
        $data['gas_charges'] = $costing['gas_charges'];
        $data['price'] = round(max($submittedPrice, 0), 2);
        $data['profit'] = round($data['price'] - $costing['effective_cost'], 2);
        $data['service_charges'] = 0.0;

        return true;
    }

    /**
     * Normalize package-contents fields into $data and ensure inner UOM ≠ base.
     *
     * @param  array<string, mixed>  $data
     */
    private function mergePackageContentsIntoData(Request $request, array &$data): void
    {
        $q = $request->input('package_contents_qty');
        $u = InventoryUnit::normalizeCode((string) $request->input('package_contents_uom', ''));
        if ($q === null || $q === '' || $u === '') {
            $data['package_contents_qty'] = null;
            $data['package_contents_uom'] = null;

            return;
        }

        $base = InventoryUnit::normalizeCode((string) ($data['uom'] ?? $request->input('uom', '')));
        if ($base !== '' && $u === $base) {
            throw ValidationException::withMessages([
                'package_contents_uom' => ['Inner unit must differ from base stock unit (e.g. base pkt, inner g).'],
            ]);
        }

        $data['package_contents_qty'] = (float) $q;
        $data['package_contents_uom'] = $u;
    }

    /** Upsert conversion: 1 inner UOM = (1/package_contents_qty) base UOM, e.g. 1 g = 0.04 pkt when 1 pkt = 25 g. */
    private function syncPackageContentsConversion(InventoryProduct $product): void
    {
        $product->refresh();

        if (!$product->hasPackageContents()) {
            return;
        }

        $inner = InventoryUnit::normalizeCode((string) $product->package_contents_uom);
        $per = (float) $product->package_contents_qty;
        if ($per <= 0 || $inner === InventoryUnit::normalizeCode((string) $product->uom)) {
            return;
        }

        $factor = 1 / $per;

        InventoryProductUomConversion::query()->updateOrCreate(
            ['product_id' => $product->id, 'uom' => $inner],
            ['factor_to_base' => $factor, 'active' => true]
        );
    }

    public function update(Request $request, InventoryProduct $product)
    {
        $this->normalizeRequestUomBlanks($request);

        $allowedUoms = $this->allowedUomCodesForProductForm($product);
        $uomRules = ['required', 'string', 'max:30'];
        $pkgUomRules = ['nullable', 'required_with:package_contents_qty', 'string', 'max:30'];
        $convUomRules = ['nullable', 'string', 'max:30'];
        if ($allowedUoms !== []) {
            $uomRules[] = Rule::in($allowedUoms);
            $pkgUomRules[] = Rule::in($allowedUoms);
            $convUomRules[] = Rule::in($allowedUoms);
        }

        $data = $request->validate([
            'sku'           => ['required', 'string', 'max:80', Rule::unique('tenant.inventory_products', 'sku')->ignore($product->id)],
            'barcode'       => ['nullable', 'string', 'max:120', Rule::unique('tenant.inventory_products', 'barcode')->ignore($product->id)],
            'name'          => ['required', 'string', 'max:200'],
            'parent_category_id' => ['nullable', 'integer', 'exists:tenant.inventory_categories,id'],
            'sub_category_id'    => ['nullable', 'integer', 'exists:tenant.inventory_categories,id'],
            'department_ids'     => ['nullable', 'array'],
            'department_ids.*'   => ['integer', 'exists:tenant.inventory_departments,id'],
            'uom'           => $uomRules,
            'package_contents_qty' => ['nullable', 'required_with:package_contents_uom', 'numeric', 'min:0.000001'],
            'package_contents_uom' => $pkgUomRules,
            'conversions'   => ['nullable', 'array'],
            'conversions.*.uom'             => $convUomRules,
            'conversions.*.factor_to_base'  => ['nullable', 'numeric', 'min:0.000001'],
            'cost'          => ['nullable', 'numeric', 'min:0'],
            'extra_costs'   => ['nullable', 'array'],
            'extra_costs.*' => ['nullable', 'numeric'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'active'        => ['nullable', 'boolean'],
            'for_pos'       => ['nullable', 'boolean'],
            'for_purchase'  => ['nullable', 'boolean'],
            'image'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_image'  => ['nullable', 'boolean'],
        ]);
        $this->assertUniqueConversionUnits($data);

        $data['category_id'] = $this->resolveProductCategoryId($request);
        unset($data['department_ids']);

        $this->applyPackageContentsFields($request, $data);
        $recipeApplied = $this->applyActiveRecipeStandardCost($product, $data);
        if (! $recipeApplied) {
            $this->applyProductCostingFields($request, $data);
        }
        $this->normalizeStoredUomFields($data);

        $data['for_pos']       = $request->boolean('for_pos');
        $data['for_purchase']  = $request->boolean('for_purchase');
        $data['active']        = (bool) ($data['active'] ?? false);
        $data['cost']          = isset($data['cost']) ? (float) $data['cost'] : 0.0;
        $data['gas_charges']   = (float) ($data['gas_charges'] ?? 0);
        $data['service_charges'] = 0;
        $data['extra_costs']   = $data['extra_costs'] ?? [];
        $data['price']         = isset($data['price']) ? (float) $data['price'] : 0.0;
        $effectiveCost         = (float) $data['cost'] + (float) collect((array) ($data['extra_costs'] ?? []))->sum();
        $data['profit']        = isset($data['profit']) ? (float) $data['profit'] : round((float) $data['price'] - $effectiveCost, 2);
        $data['reorder_level'] = $data['for_purchase'] ? ($data['reorder_level'] ?? 0) : 0;

        if ($request->boolean('remove_image')) {
            $this->productImages->delete($product->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $this->productImages->delete($product->image_path);
            $data['image_path'] = $this->productImages->storeSquare($request->file('image'));
        }

        $departmentIds = $this->validatedDepartmentIds($request);

        DB::connection('tenant')->transaction(function () use ($request, $product, $data, $departmentIds) {
            $data['department_id'] = $departmentIds[0] ?? null;
            $product->update($data);
            $this->syncProductDepartments($product, $departmentIds);
            $product->refresh();

            if ($request->has('conversions')) {
                SyncAwareDelete::query(
                    InventoryProductUomConversion::query()->where('product_id', $product->id)
                );

                $conversions = $request->input('conversions', []);
                foreach ($conversions as $c) {
                    $uom = isset($c['uom']) ? InventoryUnit::normalizeCode((string) $c['uom']) : '';
                    $factor = isset($c['factor_to_base']) ? (float)$c['factor_to_base'] : 0;
                    if ($uom === '' || $factor <= 0) continue;
                    if ($uom === $product->uom) continue;

                    InventoryProductUomConversion::query()->create([
                        'product_id' => $product->id,
                        'uom' => $uom,
                        'factor_to_base' => $factor,
                        'active' => true,
                    ]);
                }
            }

            if ($this->inventoryProductsHavePackageColumns()) {
                $this->syncPackageContentsConversion($product);
            }
        });

        return $this->redirectAfterProduct($request, 'Product updated.');
    }

    public function destroy(InventoryProduct $product)
    {
        $imagePath = $product->image_path;

        try {
            $product->delete();
        } catch (QueryException $e) {
            $isForeignKeyViolation = (string) $e->getCode() === '23000'
                || str_contains(strtolower($e->getMessage()), 'foreign key constraint');

            if ($isForeignKeyViolation) {
                return redirect()
                    ->route('inventory.products.index')
                    ->with('warning', 'Product cannot be deleted because it is already used in transactions (purchase, POS, or stock checks). Please mark it inactive instead.');
            }

            throw $e;
        }

        $this->productImages->delete($imagePath);

        return redirect()->route('inventory.products.index')->with('status', 'Product deleted.');
    }

    public function updatePurchaseHistoryLine(Request $request, InventoryProduct $product, PurchaseOrderLine $line)
    {
        if (! $request->user()?->isPlatformSuperAdmin()) {
            abort(403);
        }

        if ((int) $line->product_id !== (int) $product->id) {
            abort(404);
        }

        $order = PurchaseOrder::query()->find($line->purchase_order_id);
        if (! $order || $order->status !== 'received') {
            return back()->with('warning', 'Only received purchase lines can be edited from product history.');
        }

        $data = $request->validateWithBag('purchaseHistoryEdit', [
            'qty' => ['required', 'numeric', 'min:0.001'],
            'uom' => ['required', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $factor = $product->factorToBaseForUom((string) $data['uom']);
        if ($factor === null || $factor <= 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'uom' => 'Selected UOM is not configured for this product.',
                ], 'purchaseHistoryEdit');
        }

        $oldFactor = $product->factorToBaseForUom((string) $line->uom);
        $oldFactor = ($oldFactor !== null && $oldFactor > 0) ? $oldFactor : 1.0;
        $oldUnitCostBase = (float) $line->unit_price / $oldFactor;
        $newUnitCostBase = (float) $data['unit_price'] / (float) $factor;

        DB::connection('tenant')->transaction(function () use ($product, $line, $order, $data, $oldUnitCostBase, $newUnitCostBase) {
            $qty = (float) $data['qty'];
            $unitPrice = (float) $data['unit_price'];
            $taxPercent = (float) ($line->tax_percent ?? 0);
            $subtotal = $qty * $unitPrice;
            $taxAmount = $subtotal * ($taxPercent / 100.0);
            $total = $subtotal + $taxAmount;

            $line->update([
                'description' => $data['description'] ?? $line->description,
                'uom' => (string) $data['uom'],
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            $orderLines = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->get(['subtotal', 'tax_amount', 'total']);
            $order->update([
                'subtotal' => (float) $orderLines->sum('subtotal'),
                'tax_total' => (float) $orderLines->sum('tax_amount'),
                'grand_total' => (float) $orderLines->sum('total'),
            ]);

            $closestMove = InventoryMove::query()
                ->where('product_id', $product->id)
                ->where('type', 'in')
                ->where('reference', $order->number)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->sortBy(fn ($m) => abs((float) ($m->unit_cost ?? 0) - $oldUnitCostBase))
                ->first();
            if ($closestMove) {
                $closestMove->unit_cost = $newUnitCostBase;
                $closestMove->total_cost = (float) $closestMove->qty * $newUnitCostBase;
                $closestMove->save();
            }

            $closestLayer = InventoryCostLayer::query()
                ->where('product_id', $product->id)
                ->where('source', 'purchase')
                ->where('reference', $order->number)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->sortBy(fn ($l) => abs((float) $l->unit_cost - $oldUnitCostBase))
                ->first();
            if ($closestLayer) {
                $closestLayer->unit_cost = $newUnitCostBase;
                $closestLayer->save();
            }

            $activeLayer = InventoryCostLayer::query()
                ->where('product_id', $product->id)
                ->where('qty_remaining', '>', 0.000001)
                ->orderByRaw('COALESCE(received_at, created_at) asc')
                ->first();
            $lockedProduct = InventoryProduct::query()->lockForUpdate()->find($product->id);
            if ($lockedProduct) {
                $lockedProduct->cost = $activeLayer ? (float) $activeLayer->unit_cost : $newUnitCostBase;
                $lockedProduct->save();
            }
        });

        $safeReturn = $this->safeInternalReturnUrl($request->input('return'));

        return redirect()
            ->route('inventory.products.edit', array_filter([
                'product' => $product,
                'return' => $safeReturn,
            ], fn ($v) => $v !== null && $v !== ''))
            ->with('status', 'Purchase history line updated.');
    }

    /**
     * @return array{
     *     departments: \Illuminate\Support\Collection<int, InventoryDepartment>,
     *     selectedDepartmentIds: list<string>,
     *     defaultWarehouseId: int
     * }
     */
    private function departmentFormDataForProduct(?InventoryProduct $product = null): array
    {
        $warehouse = $this->stockService->ensureWarehouse();
        $warehouseId = (int) $warehouse->id;

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->orderByDesc('is_warehouse')
            ->orderBy('name')
            ->get(['id', 'name', 'is_warehouse']);

        $selectedDepartmentIds = old('department_ids');
        $usedOldInput = is_array($selectedDepartmentIds);

        if (! $usedOldInput) {
            if ($product) {
                $product->loadMissing('departments');
                $selectedDepartmentIds = $product->departments->pluck('id')->all();
                if ($selectedDepartmentIds === [] && $product->department_id) {
                    $selectedDepartmentIds = [(int) $product->department_id];
                }
            } else {
                $selectedDepartmentIds = [];
            }
        }

        $selectedDepartmentIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $selectedDepartmentIds),
            fn (int $id) => $id > 0
        )));

        if ((! $usedOldInput || $selectedDepartmentIds === []) && ! in_array($warehouseId, $selectedDepartmentIds, true)) {
            array_unshift($selectedDepartmentIds, $warehouseId);
        }

        return [
            'departments' => $departments,
            'selectedDepartmentIds' => array_map('strval', $selectedDepartmentIds),
            'defaultWarehouseId' => $warehouseId,
        ];
    }

    /** @return list<int> */
    private function validatedDepartmentIds(Request $request): array
    {
        $ids = $request->input('department_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0
        )));

        if ($ids === []) {
            $warehouse = $this->stockService->ensureWarehouse();
            $ids = [(int) $warehouse->id];
        }

        return $ids;
    }

    /** @param  list<int>  $departmentIds */
    private function syncProductDepartments(InventoryProduct $product, array $departmentIds): void
    {
        $companyId = $product->company_id ?? current_company_id();
        $syncData = [];

        foreach ($departmentIds as $departmentId) {
            $syncData[$departmentId] = ['company_id' => $companyId];
        }

        $oldPivotIds = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('inventory_product_department')) {
            $oldPivotIds = \Illuminate\Support\Facades\DB::table('inventory_product_department')
                ->where('product_id', $product->id)
                ->pluck('id')
                ->all();
        }

        $product->departments()->sync($syncData);
        $product->update(['department_id' => $departmentIds[0] ?? null]);

        app(SyncOutboxRecorder::class)->resyncPivotTable(
            'inventory_product_department',
            'product_id',
            $product->id,
            $oldPivotIds
        );
    }

    /**
     * @return array{
     *     parentCategories: \Illuminate\Support\Collection<int, InventoryCategory>,
     *     subcategoriesByParent: array<int, list<array{id: int, name: string}>>,
     *     categorySelection: array{parent_id: int|null, sub_category_id: int|null}
     * }
     */
    private function categoryFormDataForProduct(?InventoryProduct $product): array
    {
        return [
            'parentCategories' => InventoryCategory::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name']),
            'subcategoriesByParent' => InventoryCategory::subcategoriesGroupedByParent(),
            'categorySelection' => InventoryCategory::selectionForProduct($product),
        ];
    }

    private function resolveProductCategoryId(Request $request): ?int
    {
        $parentId = $request->integer('parent_category_id') ?: null;
        $subId = $request->integer('sub_category_id') ?: null;

        if ($subId) {
            $sub = InventoryCategory::query()->find($subId);
            if (! $sub || ! $sub->parent_id) {
                throw ValidationException::withMessages([
                    'sub_category_id' => 'Valid sub-category choose karein.',
                ]);
            }
            if ($parentId && (int) $sub->parent_id !== $parentId) {
                throw ValidationException::withMessages([
                    'sub_category_id' => 'Sub-category is category se match nahi karti.',
                ]);
            }

            return $subId;
        }

        if ($parentId) {
            $parent = InventoryCategory::query()->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_category_id' => 'Valid category choose karein.',
                ]);
            }
            if ($parent->parent_id) {
                throw ValidationException::withMessages([
                    'parent_category_id' => 'Parent category choose karein, sub-category nahi.',
                ]);
            }

            return $parentId;
        }

        return null;
    }
}
