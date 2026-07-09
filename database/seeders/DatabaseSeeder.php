<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryProductUomConversion;
use App\Models\PurchaseVendor;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FixAdminEmployeeSeeder::class,
            UomLibrarySeeder::class,
            ExpenseCategorySeeder::class,
        ]);

        $company = Company::query()->firstOrFail();

        $cat = InventoryCategory::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'All Products',
                'parent_id' => null,
            ]
        );

        $product = InventoryProduct::query()->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'STAIR-001'],
            [
                'name' => 'Stair Sample Product',
                'category_id' => $cat->id,
                'uom' => 'tablet',
                'cost' => 10,
                'price' => 15,
                'qty_on_hand' => 0,
                'active' => true,
            ]
        );

        if ($product->uom !== 'tablet' && (float) $product->qty_on_hand === 0.0) {
            $product->update(['uom' => 'tablet']);
        }

        InventoryProductUomConversion::query()->updateOrCreate(
            ['product_id' => $product->id, 'uom' => 'pkt'],
            ['company_id' => $company->id, 'factor_to_base' => 10, 'active' => true]
        );
        InventoryProductUomConversion::query()->updateOrCreate(
            ['product_id' => $product->id, 'uom' => 'Box'],
            ['company_id' => $company->id, 'factor_to_base' => 100, 'active' => true]
        );

        PurchaseVendor::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Stair Supplies'],
            ['email' => 'vendor@example.com', 'phone' => '0300-0000000', 'active' => true]
        );

        // Demo POS / inventory / HR sample data — only for local testing:
        // php artisan db:seed --class=DummyDataSeeder
    }
}
