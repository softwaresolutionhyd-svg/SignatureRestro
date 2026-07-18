<?php

namespace App\Support;

use App\Models\InventoryCategory;
use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Services\InventoryStockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Recipe / BoM components category: “Ingredients”. */
final class IngredientsCategory
{
    public const NAME = 'Ingredients';

    public static function ensure(): InventoryCategory
    {
        $existing = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(self::NAME)])
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InventoryCategory::query()->create([
            'name' => self::NAME,
            'parent_id' => null,
        ]);
    }

    /**
     * Category ids that count as Ingredients (root + any child subcategories).
     *
     * @return list<int>
     */
    public static function categoryIds(): array
    {
        $root = self::ensure();

        return InventoryCategory::query()
            ->where('id', $root->id)
            ->orWhere('parent_id', $root->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function productIsIngredient(InventoryProduct $product): bool
    {
        $cid = (int) ($product->category_id ?? 0);
        if ($cid <= 0) {
            return false;
        }

        return in_array($cid, self::categoryIds(), true);
    }

    /**
     * Set category = Ingredients for every product assigned to the Warehouse department.
     *
     * @return int Number of products updated
     */
    public static function assignWarehouseProducts(): int
    {
        $category = self::ensure();
        $warehouse = app(InventoryStockService::class)->ensureWarehouse();

        $query = InventoryProduct::query()
            ->where(function ($q) use ($warehouse) {
                $q->whereHas(
                    'departments',
                    fn ($d) => $d->where('inventory_departments.id', $warehouse->id)
                );
                if (Schema::connection('tenant')->hasColumn('inventory_products', 'department_id')) {
                    $q->orWhere('department_id', $warehouse->id);
                }
            })
            ->where(function ($q) use ($category) {
                $q->whereNull('category_id')
                    ->orWhere('category_id', '!=', $category->id);
            });

        $updated = 0;
        $query->orderBy('id')->chunkById(100, function (Collection $products) use ($category, &$updated) {
            foreach ($products as $product) {
                /** @var InventoryProduct $product */
                $product->category_id = $category->id;
                $product->save();
                $updated++;
            }
        });

        return $updated;
    }
}
