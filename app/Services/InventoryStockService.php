<?php

namespace App\Services;

use App\Models\InventoryDepartment;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryProductStock;
use Illuminate\Support\Facades\DB;

final class InventoryStockService
{
    public function ensureWarehouse(): InventoryDepartment
    {
        $existing = InventoryDepartment::query()->where('is_warehouse', true)->first();
        if ($existing) {
            return $existing;
        }

        $byName = InventoryDepartment::query()
            ->whereRaw('LOWER(name) = ?', ['warehouse'])
            ->first();

        if ($byName) {
            $byName->update([
                'is_warehouse' => true,
                'active' => true,
                'name' => 'Warehouse',
            ]);

            return $byName->fresh();
        }

        return InventoryDepartment::create([
            'name' => 'Warehouse',
            'active' => true,
            'is_warehouse' => true,
        ]);
    }

    public function warehouse(): InventoryDepartment
    {
        return $this->ensureWarehouse();
    }

    public function stockQty(int $productId, int $departmentId): float
    {
        $row = InventoryProductStock::query()
            ->where('product_id', $productId)
            ->where('department_id', $departmentId)
            ->first();

        return $row ? (float) $row->qty_on_hand : 0.0;
    }

    public function addToWarehouse(InventoryProduct $product, float $qtyBase): void
    {
        if ($qtyBase <= 0) {
            return;
        }

        $warehouse = $this->ensureWarehouse();
        $this->addStock((int) $product->id, (int) $warehouse->id, $qtyBase);
    }

    public function addStock(int $productId, int $departmentId, float $qtyBase): InventoryProductStock
    {
        $row = InventoryProductStock::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'department_id' => $departmentId,
            ],
            ['qty_on_hand' => 0]
        );

        $row->update([
            'qty_on_hand' => round((float) $row->qty_on_hand + $qtyBase, 3),
        ]);

        return $row->fresh();
    }

    /**
     * Move stock from warehouse to another department. Total product qty_on_hand stays the same.
     */
    public function issueFromWarehouse(
        InventoryProduct $product,
        InventoryDepartment $toDepartment,
        float $qtyBase,
        string $uom,
        float $qtyUom,
        float $factor,
        ?int $userId = null,
        ?string $note = null,
        ?string $reference = null
    ): void {
        if ($toDepartment->is_warehouse) {
            abort(422, 'Warehouse se warehouse ko issue nahi kar sakte.');
        }

        if ($qtyBase <= 0) {
            abort(422, 'Quantity must be greater than zero.');
        }

        DB::connection('tenant')->transaction(function () use ($product, $toDepartment, $qtyBase, $uom, $qtyUom, $factor, $userId, $note, $reference) {
            $warehouse = $this->warehouse();
            $product = InventoryProduct::query()->lockForUpdate()->findOrFail($product->id);

            $warehouseRow = InventoryProductStock::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'department_id' => $warehouse->id,
                    ],
                    ['qty_on_hand' => 0]
                );

            $available = (float) $warehouseRow->qty_on_hand;
            if ($available + 0.0005 < $qtyBase) {
                abort(422, sprintf(
                    'Warehouse me sirf %s %s maujood hai.',
                    fmt_num($available, 3),
                    $product->uom
                ));
            }

            $warehouseRow->update(['qty_on_hand' => round($available - $qtyBase, 3)]);
            $this->addStock((int) $product->id, (int) $toDepartment->id, $qtyBase);

            InventoryMove::create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'type' => 'transfer',
                'from_department_id' => $warehouse->id,
                'to_department_id' => $toDepartment->id,
                'qty' => $qtyBase,
                'uom' => $uom,
                'qty_uom' => $qtyUom,
                'factor_to_base' => $factor,
                'qty_before' => (float) $product->qty_on_hand,
                'qty_after' => (float) $product->qty_on_hand,
                'reference' => $reference,
                'note' => $note ?: sprintf('Issued to %s', $toDepartment->name),
            ]);
        });
    }

    /** @return array<int, float> department_id => qty */
    public function stockByDepartment(int $productId): array
    {
        return InventoryProductStock::query()
            ->where('product_id', $productId)
            ->where('qty_on_hand', '>', 0)
            ->pluck('qty_on_hand', 'department_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }
}
