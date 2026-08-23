<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryMoveStoreRequest;
use App\Models\InventoryCostLayer;
use App\Models\InventoryDepartment;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryUnit;
use App\Models\Setting;
use App\Notifications\StockUpdated;
use App\Services\InventoryStockService;
use App\Services\Sync\SyncAwareDelete;
use App\Support\StaffNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoveController extends Controller
{
    private const FIFO_EPSILON = 0.000001;

    public function __construct(
        private readonly InventoryStockService $inventoryStock
    ) {}
    public function index(Request $request)
    {
        $type = $request->query('type');

        $moves = InventoryMove::query()
            ->excludingPosSales()
            ->with(['product:id,sku,name,uom', 'user:id,name'])
            ->when(in_array($type, ['in', 'out', 'adjust', 'wastage', 'transfer'], true), fn ($q) => $q->where('type', $type))
            ->latest()
            ->paginate(Setting::pageSize('inventory_moves_per_page', 25))
            ->withQueryString();

        return view('inventory.moves.index', compact('moves', 'type'));
    }

    public function create()
    {
        $warehouse = $this->inventoryStock->ensureWarehouse();

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->orderByDesc('is_warehouse')
            ->orderBy('name')
            ->get(['id', 'name', 'is_warehouse']);

        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->orderBy('name')
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'cost', 'package_contents_qty', 'package_contents_uom']);

        return view('inventory.moves.create', compact('products', 'departments', 'warehouse'));
    }

    public function productStock(InventoryProduct $product)
    {
        return response()->json([
            'base_uom' => (string) $product->uom,
            'unit_cost_base' => round((float) $product->cost, 6),
            'departments' => $this->inventoryStock->departmentStockBreakdown((int) $product->id),
        ]);
    }

    public function updateProductCost(Request $request, InventoryProduct $product)
    {
        $data = $request->validate([
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'uom' => ['required', 'string', 'max:30'],
        ]);

        $factor = $product->factorToBaseForUom((string) $data['uom']);
        if ($factor === null || $factor <= 0) {
            return back()->withErrors([
                'unit_cost' => 'Selected UOM is not configured for this product.',
            ]);
        }

        $unitCostBase = round((float) $data['unit_cost'] / (float) $factor, 6);

        DB::connection('tenant')->transaction(function () use ($product, $unitCostBase) {
            /** @var InventoryProduct $locked */
            $locked = InventoryProduct::query()->lockForUpdate()->findOrFail($product->id);

            $layers = InventoryCostLayer::query()
                ->where('product_id', $locked->id)
                ->where('qty_remaining', '>', self::FIFO_EPSILON)
                ->lockForUpdate()
                ->get();

            if ($layers->isNotEmpty()) {
                foreach ($layers as $layer) {
                    $layer->update(['unit_cost' => $unitCostBase]);
                }
            } elseif ((float) $locked->qty_on_hand > self::FIFO_EPSILON) {
                InventoryCostLayer::create([
                    'product_id' => $locked->id,
                    'qty_remaining' => (float) $locked->qty_on_hand,
                    'unit_cost' => $unitCostBase,
                    'source' => 'manual_cost',
                    'reference' => 'manual-cost-update',
                    'received_at' => now(),
                ]);
            }

            $locked->cost = $unitCostBase;
            $price = round((float) $locked->price, 2);
            if ($price > 0) {
                $locked->profit = round($price - $unitCostBase, 2);
            }
            $locked->save();

            InventoryCostLayer::refreshProductUnitCost((int) $locked->id, self::FIFO_EPSILON);
        });

        return redirect()
            ->route('inventory.moves.create', array_filter([
                'product_id' => $product->id,
                'uom' => $data['uom'],
            ]))
            ->with('status', 'Product cost updated.');
    }

    public function store(InventoryMoveStoreRequest $request)
    {
        $data = $request->validated();

        DB::connection('tenant')->transaction(function () use ($data, $request) {
            /** @var InventoryProduct $product */
            $product = InventoryProduct::query()->lockForUpdate()->findOrFail($data['product_id']);
            $department = InventoryDepartment::query()->findOrFail($data['department_id']);
            $isWarehouse = (bool) $department->is_warehouse;

            $qtyUom = (float) $data['qty_uom'];
            $uom = (string) $data['uom'];

            $factor = $product->factorToBaseForUom($uom);

            if ($factor === null || $factor <= 0) {
                abort(422, 'Invalid UOM for this product.');
            }

            $qtyBase = $qtyUom * $factor;
            $deptBefore = $this->inventoryStock->stockQty((int) $product->id, (int) $department->id);
            $productBefore = (float) $product->qty_on_hand;

            $deptAfter = match ($data['type']) {
                'in' => $deptBefore + $qtyBase,
                'out', 'wastage' => $deptBefore - $qtyBase,
                'adjust' => $qtyBase,
            };

            if (in_array($data['type'], ['out', 'wastage'], true) && $qtyBase > $deptBefore) {
                $hasActiveBom = $product->manufacturingBoms()->where('active', true)->exists();
                if ($data['type'] === 'wastage' || ! $hasActiveBom) {
                    abort(422, $data['type'] === 'wastage'
                        ? sprintf('Insufficient stock in %s for WASTAGE.', $department->name)
                        : sprintf('Insufficient stock in %s for OUT.', $department->name));
                }
            }

            [$deptBeforeActual, $deptAfterActual] = $this->inventoryStock->setDepartmentQuantity(
                (int) $product->id,
                (int) $department->id,
                $deptAfter
            );

            $after = $deptAfterActual;
            $unitCost = null;
            $totalCost = null;

            if ($isWarehouse) {
                $after = match ($data['type']) {
                    'in' => $productBefore + $qtyBase,
                    'out', 'wastage' => $productBefore - $qtyBase,
                    'adjust' => $qtyBase,
                };

                $product->update(['qty_on_hand' => $after]);

                if ($data['type'] === 'in') {
                    $layerCost = (float) $product->cost;
                    InventoryCostLayer::create([
                        'product_id' => $product->id,
                        'qty_remaining' => $qtyBase,
                        'unit_cost' => $layerCost,
                        'source' => 'adjust',
                        'reference' => $data['reference'] ?? null,
                        'received_at' => now(),
                    ]);
                    $this->refreshProductCostFromLayers($product->id);
                } elseif (in_array($data['type'], ['out', 'wastage'], true)) {
                    [$unitCost, $totalCost] = $this->consumeFifo($product->id, $qtyBase);
                    $this->refreshProductCostFromLayers($product->id);
                } elseif ($data['type'] === 'adjust') {
                    SyncAwareDelete::query(
                        InventoryCostLayer::query()->where('product_id', $product->id)
                    );
                    if ($qtyBase > 0) {
                        InventoryCostLayer::create([
                            'product_id' => $product->id,
                            'qty_remaining' => $qtyBase,
                            'unit_cost' => (float) $product->cost,
                            'source' => 'adjust',
                            'reference' => $data['reference'] ?? null,
                            'received_at' => now(),
                        ]);
                    }
                    $this->refreshProductCostFromLayers($product->id);
                }
            }

            $moveNote = $data['note'] ?? null;
            if ($moveNote === null || $moveNote === '') {
                $moveNote = sprintf('%s — %s', ucfirst($data['type']), $department->name);
            }

            InventoryMove::create([
                'product_id' => $product->id,
                'user_id' => $request->user()?->id,
                'from_department_id' => in_array($data['type'], ['out', 'wastage'], true) ? $department->id : null,
                'to_department_id' => in_array($data['type'], ['in', 'adjust'], true) ? $department->id : null,
                'type' => $data['type'],
                'qty' => $qtyBase,
                'uom' => $uom,
                'qty_uom' => $qtyUom,
                'factor_to_base' => $factor,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'qty_before' => $isWarehouse ? $productBefore : $deptBeforeActual,
                'qty_after' => $isWarehouse ? $after : $deptAfterActual,
                'reference' => $data['reference'] ?? null,
                'note' => $moveNote,
            ]);

            $title = match ($data['type']) {
                'in' => 'Stock increased',
                'out' => 'Stock decreased',
                'wastage' => 'Stock wastage recorded',
                default => 'Stock adjusted',
            };

            $scopeLabel = $isWarehouse ? 'total' : $department->name;
            $body = "{$product->sku} — {$product->name} ({$scopeLabel}): "
                .fmt_num($isWarehouse ? $productBefore : $deptBeforeActual, 3)
                .' → '
                .fmt_num($isWarehouse ? $after : $deptAfterActual, 3)
                ." ({$qtyUom} {$uom})";

            StaffNotifier::notifyManagement(new StockUpdated([
                'title' => $title,
                'body' => $body,
                'product_id' => $product->id,
                'type' => $data['type'],
                'ts' => now()->toIso8601String(),
            ]), function_exists('current_company_id') ? current_company_id() : null);
        });

        return redirect()->route('inventory.moves.index')->with('status', 'Stock updated.');
    }

    private function consumeFifo(int $productId, float $qtyBase): array
    {
        // Ensure we have layers; if none but stock exists, create an opening layer at current product cost.
        $product = InventoryProduct::query()->findOrFail($productId);
        if (InventoryCostLayer::query()->where('product_id', $productId)->doesntExist() && (float) $product->qty_on_hand > 0) {
            InventoryCostLayer::create([
                'product_id' => $productId,
                'qty_remaining' => (float) $product->qty_on_hand,
                'unit_cost' => (float) $product->cost,
                'source' => 'opening',
                'reference' => 'opening',
                'received_at' => now()->subSecond(),
            ]);
        }

        $remaining = $qtyBase;
        $costTotal = 0.0;
        $unitCostWeighted = null;

        $layers = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('COALESCE(received_at, created_at) asc')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) break;
            $take = min($remaining, (float) $layer->qty_remaining);
            $costTotal += $take * (float) $layer->unit_cost;
            $newRemaining = (float) $layer->qty_remaining - $take;
            if (abs($newRemaining) < self::FIFO_EPSILON) {
                $newRemaining = 0.0;
            }
            $layer->update(['qty_remaining' => $newRemaining]);
            $remaining -= $take;
        }

        if ($remaining > 0.0000001) {
            abort(422, 'FIFO layers insufficient for OUT.');
        }

        $unitCostWeighted = $qtyBase > 0 ? ($costTotal / $qtyBase) : null;
        return [$unitCostWeighted, $costTotal];
    }

    private function refreshProductCostFromLayers(int $productId): void
    {
        InventoryCostLayer::refreshProductUnitCost($productId, self::FIFO_EPSILON);
    }
}
