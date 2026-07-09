<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDepartment;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\Setting;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;

class StockIssueController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    public function index()
    {
        $this->stockService->ensureWarehouse();

        $issues = InventoryMove::query()
            ->where('type', 'transfer')
            ->with([
                'product:id,sku,name,uom',
                'user:id,name',
            ])
            ->with(['fromDepartment:id,name', 'toDepartment:id,name'])
            ->latest()
            ->paginate(Setting::pageSize('inventory_issues_per_page', 25));

        $warehouse = InventoryDepartment::query()->where('is_warehouse', true)->first();

        return view('inventory.issues.index', compact('issues', 'warehouse'));
    }

    public function create()
    {
        $warehouse = $this->stockService->ensureWarehouse();

        $departments = InventoryDepartment::query()
            ->where('is_warehouse', false)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->orderBy('name')
            ->with([
                'stocks' => fn ($q) => $q->where('department_id', $warehouse->id),
                'uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']),
            ])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand']);

        $products->transform(function (InventoryProduct $product) use ($warehouse) {
            $product->warehouse_qty = $this->stockService->stockQty((int) $product->id, (int) $warehouse->id);

            return $product;
        });

        return view('inventory.issues.create', compact('warehouse', 'departments', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'to_department_id' => ['required', 'integer', 'exists:tenant.inventory_departments,id'],
            'qty_uom' => ['required', 'numeric', 'gt:0'],
            'uom' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $product = InventoryProduct::query()->findOrFail($data['product_id']);
        $toDepartment = InventoryDepartment::query()->findOrFail($data['to_department_id']);

        abort_if($toDepartment->is_warehouse, 422, 'Target department warehouse nahi ho sakta.');

        $factor = $product->factorToBaseForUom((string) $data['uom']);
        if ($factor === null || $factor <= 0) {
            return back()->withErrors(['uom' => 'Invalid UOM for this product.'])->withInput();
        }

        $qtyBase = round((float) $data['qty_uom'] * $factor, 3);

        $this->stockService->issueFromWarehouse(
            $product,
            $toDepartment,
            $qtyBase,
            (string) $data['uom'],
            (float) $data['qty_uom'],
            $factor,
            (int) $request->user()?->id,
            $data['note'] ?? null,
            $data['reference'] ?? null
        );

        return redirect()
            ->route('inventory.issues.index')
            ->with('status', sprintf('Stock %s ko %s mein issue ho gaya.', $product->name, $toDepartment->name));
    }
}
