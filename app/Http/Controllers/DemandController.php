<?php

namespace App\Http\Controllers;

use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Models\StockDemand;
use App\Models\StockDemandLine;
use App\Services\InventoryStockService;
use App\Support\IngredientsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemandController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    public function index(Request $request)
    {
        $this->ensureTables();
        $user = $request->user();
        abort_unless($user?->canAccessDemand(), 403);

        $canCreate = (bool) $user->canCreateStockDemand();
        $canViewToday = (bool) $user->canViewTodaysDemand();
        abort_unless($canCreate || $canViewToday, 403);

        $tab = (string) $request->query('tab', $canCreate ? 'create' : 'today');
        if (! in_array($tab, ['create', 'today'], true)) {
            $tab = $canCreate ? 'create' : 'today';
        }
        if ($tab === 'today' && ! $canViewToday) {
            $tab = 'create';
        }
        if ($tab === 'create' && ! $canCreate) {
            $tab = 'today';
        }

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->where('is_warehouse', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $ingredients = $canCreate ? $this->ingredientProducts() : collect();
        $warehouse = $this->stockService->ensureWarehouse();
        $warehouseId = (int) $warehouse->id;

        foreach ($ingredients as $product) {
            $product->setAttribute(
                'warehouse_qty',
                $this->stockService->stockQty((int) $product->id, $warehouseId)
            );
        }

        $today = now()->toDateString();
        $todaysDemands = collect();

        if ($canViewToday) {
            $todaysDemands = StockDemand::query()
                ->with([
                    'department:id,name',
                    'creator:id,name',
                    'lines.product:id,sku,name,uom',
                ])
                ->whereDate('demand_date', $today)
                ->whereIn('status', ['pending', 'partial', 'issued'])
                ->orderByDesc('id')
                ->get();

            foreach ($todaysDemands as $demand) {
                foreach ($demand->lines as $line) {
                    $whQty = $this->stockService->stockQty((int) $line->product_id, $warehouseId);
                    $remaining = $line->remainingQtyBase();
                    $line->setAttribute('warehouse_qty', $whQty);
                    $line->setAttribute('remaining_qty_base', $remaining);
                    $line->setAttribute(
                        'can_issue',
                        ! $line->isFullyIssued() && $whQty + 0.0005 >= $remaining && $remaining > 0
                    );
                    $line->setAttribute(
                        'out_of_stock',
                        ! $line->isFullyIssued() && $whQty + 0.0005 < $remaining
                    );
                }
            }
        }

        $ingredientsJson = $ingredients->map(function ($p) {
            $sku = trim((string) ($p->sku ?? ''));
            $name = trim((string) ($p->name ?? ''));

            return [
                'id' => (string) $p->id,
                'label' => trim(($sku !== '' ? $sku.' — ' : '').$name),
                'uom' => (string) $p->uom,
                'warehouse' => fmt_num((float) ($p->warehouse_qty ?? 0), 3),
            ];
        })->values()->all();

        return view('demand.index', [
            'tab' => $tab,
            'canCreate' => $canCreate,
            'canViewToday' => $canViewToday,
            'departments' => $departments,
            'ingredients' => $ingredients,
            'ingredientsJson' => $ingredientsJson,
            'todaysDemands' => $todaysDemands,
            'today' => $today,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTables();
        abort_unless($request->user()?->canCreateStockDemand(), 403);

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:tenant.inventory_departments,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.qty_uom' => ['required', 'numeric', 'min:0.001'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
        ]);

        $department = InventoryDepartment::query()->findOrFail((int) $data['department_id']);
        if ($department->is_warehouse) {
            return back()->withInput()->withErrors([
                'department_id' => 'Warehouse ke liye demand nahi bana sakte. Kitchen / department select karein.',
            ]);
        }

        $ingredientIds = collect($this->ingredientProducts())->pluck('id')->map(fn ($id) => (int) $id)->all();

        try {
            DB::connection('tenant')->transaction(function () use ($request, $data, $department, $ingredientIds) {
                $demand = StockDemand::query()->create([
                    'demand_no' => $this->nextDemandNo(),
                    'department_id' => $department->id,
                    'demand_date' => now()->toDateString(),
                    'status' => 'pending',
                    'note' => $data['note'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);

                foreach ((array) $data['lines'] as $row) {
                    $productId = (int) $row['product_id'];
                    if (! in_array($productId, $ingredientIds, true)) {
                        throw new RuntimeException('Sirf ingredients demand mein add ho sakte hain.');
                    }

                    $product = InventoryProduct::query()->findOrFail($productId);
                    $uom = trim((string) $row['uom']);
                    $qtyUom = (float) $row['qty_uom'];
                    $factor = (float) ($product->factorToBaseForUom($uom) ?? 0);
                    if ($factor <= 0) {
                        $factor = 1.0;
                        $uom = (string) $product->uom;
                    }
                    $qtyBase = round($qtyUom * $factor, 3);

                    StockDemandLine::query()->create([
                        'demand_id' => $demand->id,
                        'product_id' => $product->id,
                        'qty_uom' => $qtyUom,
                        'uom' => $uom,
                        'qty_base' => $qtyBase,
                        'issued_qty_base' => 0,
                        'status' => 'pending',
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('demand.index', ['tab' => 'create'])
            ->with('success', 'Demand create ho gayi. Storekeeper Today\'s Demand mein issue karega.');
    }

    public function issueLine(Request $request, StockDemandLine $line)
    {
        $this->ensureTables();
        abort_unless($request->user()?->canIssueStockDemand(), 403);

        $line->load(['demand.department', 'product']);
        $demand = $line->demand;
        if (! $demand || ! $demand->demand_date?->isToday()) {
            return back()->with('warning', 'Sirf aaj ki demand issue ho sakti hai.');
        }

        if ($line->isFullyIssued()) {
            return back()->with('warning', 'Ye item pehle hi issue ho chuka hai.');
        }

        $department = $demand->department;
        if (! $department || $department->is_warehouse) {
            return back()->with('error', 'Demand department invalid hai.');
        }

        $remaining = $line->remainingQtyBase();
        $warehouse = $this->stockService->ensureWarehouse();
        $whQty = $this->stockService->stockQty((int) $line->product_id, (int) $warehouse->id);

        if ($whQty + 0.0005 < $remaining) {
            return back()->with('warning', 'Warehouse mein stock kam hai — Out of Stock.');
        }

        $product = $line->product;
        $uom = (string) $line->uom;
        $factor = (float) ($product->factorToBaseForUom($uom) ?? 1);
        if ($factor <= 0) {
            $factor = 1.0;
        }
        $qtyUom = round($remaining / $factor, 3);

        try {
            DB::connection('tenant')->transaction(function () use ($line, $product, $department, $remaining, $uom, $qtyUom, $factor, $request, $demand) {
                $locked = StockDemandLine::query()->lockForUpdate()->findOrFail($line->id);
                if ($locked->isFullyIssued()) {
                    throw new RuntimeException('Ye item pehle hi issue ho chuka hai.');
                }

                $this->stockService->issueFromWarehouse(
                    $product,
                    $department,
                    $remaining,
                    $uom,
                    $qtyUom,
                    $factor,
                    $request->user()?->id,
                    'Stock demand issue',
                    'demand-'.$demand->demand_no
                );

                $locked->update([
                    'issued_qty_base' => round((float) $locked->issued_qty_base + $remaining, 3),
                    'status' => 'issued',
                    'issued_at' => now(),
                    'issued_by' => $request->user()?->id,
                ]);

                $this->refreshDemandStatus($demand->id);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $deptName = $department->name ?? 'Department';

        return back()->with('success', "Issued to {$deptName}.");
    }

    private function refreshDemandStatus(int $demandId): void
    {
        $demand = StockDemand::query()->with('lines')->find($demandId);
        if (! $demand) {
            return;
        }

        $lines = $demand->lines;
        if ($lines->isEmpty()) {
            return;
        }

        $allIssued = $lines->every(fn (StockDemandLine $l) => $l->isFullyIssued());
        $anyIssued = $lines->contains(fn (StockDemandLine $l) => (float) $l->issued_qty_base > 0);

        $demand->update([
            'status' => $allIssued ? 'issued' : ($anyIssued ? 'partial' : 'pending'),
        ]);
    }

    private function nextDemandNo(): string
    {
        $prefix = 'DEM-'.now()->format('ymd').'-';
        $last = StockDemand::query()
            ->where('demand_no', 'like', $prefix.'%')
            ->orderByDesc('demand_no')
            ->value('demand_no');

        $seq = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.sprintf('%03d', $seq);
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryProduct>
     */
    private function ingredientProducts()
    {
        $categoryIds = IngredientsCategory::categoryIds();

        $query = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name');

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        } else {
            $query->where('for_purchase', true);
        }

        return $query
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base'])])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand']);
    }

    private function ensureTables(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('stock_demands')) {
            $schema->create('stock_demands', function ($table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('demand_no', 40)->index();
                $table->unsignedBigInteger('department_id')->index();
                $table->date('demand_date')->index();
                $table->string('status', 20)->default('pending')->index();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('stock_demand_lines')) {
            $schema->create('stock_demand_lines', function ($table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('demand_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('qty_uom', 18, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 18, 3);
                $table->decimal('issued_qty_base', 18, 3)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('issued_at')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }
}
