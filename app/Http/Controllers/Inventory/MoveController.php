<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\InventoryMoveStoreRequest;
use App\Notifications\StockUpdated;
use App\Services\InventoryStockService;
use App\Services\Sync\SyncAwareDelete;

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
            ->with(['product:id,sku,name,uom', 'user:id,name'])
            ->when(in_array($type, ['in', 'out', 'adjust', 'wastage', 'transfer'], true), fn ($q) => $q->where('type', $type))
            ->latest()
            ->paginate(Setting::pageSize('inventory_moves_per_page', 25))
            ->withQueryString();

        return view('inventory.moves.index', compact('moves', 'type'));
    }

    public function create()
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->orderBy('name')
            ->with(['uomConversions' => function ($q) {
                $q->where('active', true)->select(['id', 'product_id', 'uom', 'factor_to_base']);
            }])
            ->get(['id', 'sku', 'name', 'uom', 'qty_on_hand', 'package_contents_qty', 'package_contents_uom']);

        return view('inventory.moves.create', compact('products'));
    }

    public function store(InventoryMoveStoreRequest $request)
    {
        $data = $request->all();

        DB::connection('tenant')->transaction(function () use ($data, $request) {
            /** @var InventoryProduct $product */
            $product = InventoryProduct::query()->lockForUpdate()->findOrFail($data['product_id']);
            $before = (float) $product->qty_on_hand;
            $qtyUom = (float) $data['qty_uom'];
            $uom = (string) $data['uom'];

            $factor = $product->factorToBaseForUom($uom);

            if ($factor === null || $factor <= 0) {
                abort(422, 'Invalid UOM for this product.');
            }

            $qtyBase = $qtyUom * $factor;

            if (in_array($data['type'], ['out', 'wastage'], true) && $qtyBase > $before) {
                $hasActiveBom = $product->manufacturingBoms()->where('active', true)->exists();
                if ($data['type'] === 'wastage' || ! $hasActiveBom) {
                    abort(422, $data['type'] === 'wastage' ? 'Insufficient stock for WASTAGE.' : 'Insufficient stock for OUT.');
                }
            }

            $after = match ($data['type']) {
                'in' => $before + $qtyBase,
                'out' => $before - $qtyBase,
                'wastage' => $before - $qtyBase,
                'adjust' => $qtyBase,
            };

            $product->update(['qty_on_hand' => $after]);

            if ($data['type'] === 'in') {
                $this->inventoryStock->addToWarehouse($product, $qtyBase);
            }

            $unitCost = null;
            $totalCost = null;

            // FIFO costing behavior
            if ($data['type'] === 'in') {
                // create a new layer using current product cost (unless you later wire a UI field)
                $layerCost = (float) $product->cost;
                InventoryCostLayer::create([
                    'product_id' => $product->id,
                    'qty_remaining' => $qtyBase,
                    'unit_cost' => $layerCost,
                    'source' => 'adjust',
                    'reference' => $data['reference'] ?? null,
                    'received_at' => now(),
                ]);

                // keep product cost as current active layer cost
                $this->refreshProductCostFromLayers($product->id);
            } elseif (in_array($data['type'], ['out', 'wastage'], true)) {
                [$unitCost, $totalCost] = $this->consumeFifo($product->id, $qtyBase);
                $this->refreshProductCostFromLayers($product->id);
            } elseif ($data['type'] === 'adjust') {
                // reset layers to single layer at current product cost
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

            InventoryMove::create([
                'product_id' => $product->id,
                'user_id' => $request->user()?->id,
                'type' => $data['type'],
                'qty' => $qtyBase,
                'uom' => $uom,
                'qty_uom' => $qtyUom,
                'factor_to_base' => $factor,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'qty_before' => $before,
                'qty_after' => $after,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $title = match ($data['type']) {
                'in' => 'Stock increased',
                'out' => 'Stock decreased',
                'wastage' => 'Stock wastage recorded',
                default => 'Stock adjusted',
            };

            $body = "{$product->sku} — {$product->name}: {$before} → {$after} ({$qtyUom} {$uom})";

            User::query()->select(['id'])->each(function ($u) use ($title, $body, $product, $data) {
                $u->notify(new StockUpdated([
                    'title' => $title,
                    'body' => $body,
                    'product_id' => $product->id,
                    'type' => $data['type'],
                    'ts' => now()->toIso8601String(),
                ]));
            });
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
        $product = InventoryProduct::query()->find($productId);
        if (!$product) {
            return;
        }

        $layer = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', self::FIFO_EPSILON)
            ->orderByRaw('COALESCE(received_at, created_at) asc')
            ->first();

        if ($layer) {
            $product->cost = (float) $layer->unit_cost;
            $product->save();

            return;
        }

        $product->cost = 0;
        $product->save();
    }
}
