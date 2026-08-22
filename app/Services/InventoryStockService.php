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
        $product = InventoryProduct::query()->withoutGlobalScopes()->find($productId);
        $companyId = $product?->company_id ?? current_company_id();

        $row = InventoryProductStock::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'department_id' => $departmentId,
            ],
            [
                'qty_on_hand' => 0,
                'company_id' => $companyId,
            ]
        );

        if ($row->company_id === null && $companyId !== null) {
            $row->company_id = $companyId;
        }

        $row->update([
            'qty_on_hand' => round((float) $row->qty_on_hand + $qtyBase, 3),
        ]);

        return $row->fresh();
    }

    /**
     * Deduct from a department stock row (recipes / POS BoM consume here — not warehouse by default).
     */
    public function removeStock(int $productId, int $departmentId, float $qtyBase, bool $allowNegative = false): InventoryProductStock
    {
        if ($qtyBase <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        $row = InventoryProductStock::query()
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'product_id' => $productId,
                    'department_id' => $departmentId,
                ],
                [
                    'qty_on_hand' => 0,
                    'company_id' => InventoryProduct::query()->withoutGlobalScopes()->find($productId)?->company_id
                        ?? current_company_id(),
                ]
            );

        $available = (float) $row->qty_on_hand;
        if (! $allowNegative && $available + 0.0005 < $qtyBase) {
            $dept = InventoryDepartment::query()->find($departmentId);
            $product = InventoryProduct::query()->find($productId);
            throw new \RuntimeException(sprintf(
                '%s me sirf %s %s maujood hai.',
                $dept?->name ?? 'Department',
                fmt_num($available, 3),
                $product?->uom ?? ''
            ));
        }

        $row->update([
            'qty_on_hand' => round($available - $qtyBase, 3),
        ]);

        return $row->fresh();
    }

    /**
     * Department where recipe/BoM components should be consumed (POS sale, manufacturing, etc.).
     * Uses the finished/POS item's tagged department — never warehouse.
     */
    public function consumptionDepartmentIdForProduct(InventoryProduct $product): int
    {
        $product->loadMissing(['department', 'departments']);

        $candidates = collect();

        if ($product->department_id && $product->department) {
            $candidates->push($product->department);
        }

        foreach ($product->departments as $dept) {
            if (! $candidates->contains('id', (int) $dept->id)) {
                $candidates->push($dept);
            }
        }

        $operating = $candidates->first(fn ($dept) => ! $dept->is_warehouse);
        if ($operating !== null) {
            return (int) $operating->id;
        }

        throw new \RuntimeException(sprintf(
            '%s ke liye kitchen/department set karein — recipe ingredients warehouse se cut nahi hoti.',
            $product->name
        ));
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

            $qtyAfterWarehouse = round($available - $qtyBase, 3);
            $warehouseRow->update(['qty_on_hand' => $qtyAfterWarehouse]);
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
                // Show warehouse balance change (company total qty_on_hand is unchanged on transfer).
                'qty_before' => $available,
                'qty_after' => $qtyAfterWarehouse,
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

    /**
     * Physical stock check: counted qty lives in Warehouse; other dept rows reset to 0
     * so Issue Stock / warehouse reports match the count (kitchen negatives are not "real" stock).
     */
    public function applyStockCheckQuantity(InventoryProduct $product, float $targetQty): void
    {
        $warehouse = $this->ensureWarehouseForCompany($product->company_id ? (int) $product->company_id : null);
        $targetQty = round($targetQty, 3);

        $rows = InventoryProductStock::query()
            ->withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->get();

        $warehouseRow = null;
        foreach ($rows as $row) {
            if ((int) $row->department_id === (int) $warehouse->id) {
                $warehouseRow = $row;
                continue;
            }
            if (abs((float) $row->qty_on_hand) > 0.000001) {
                $row->update(['qty_on_hand' => 0]);
            }
        }

        if (! $warehouseRow) {
            $warehouseRow = InventoryProductStock::query()->withoutGlobalScopes()->create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'department_id' => $warehouse->id,
                'qty_on_hand' => $targetQty,
            ]);

            return;
        }

        if ($warehouseRow->company_id === null && $product->company_id !== null) {
            $warehouseRow->company_id = $product->company_id;
        }

        $warehouseRow->update(['qty_on_hand' => $targetQty]);
    }

    /**
     * Keep department rows in sync with product.qty_on_hand by putting the gap on Warehouse.
     * Prefer applyStockCheckQuantity() after a physical count.
     */
    public function reconcileWarehouseToMatchProduct(InventoryProduct $product): void
    {
        $this->applyStockCheckQuantity($product, (float) $product->qty_on_hand);
    }

    public function ensureWarehouseForCompany(?int $companyId = null): InventoryDepartment
    {
        if ($companyId === null) {
            return $this->ensureWarehouse();
        }

        $existing = InventoryDepartment::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_warehouse', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        $byName = InventoryDepartment::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
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

        return InventoryDepartment::query()->create([
            'company_id' => $companyId,
            'name' => 'Warehouse',
            'active' => true,
            'is_warehouse' => true,
        ]);
    }
}
