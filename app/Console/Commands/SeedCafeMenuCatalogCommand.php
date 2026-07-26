<?php

namespace App\Console\Commands;

use App\Models\InventoryCategory;
use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Support\MenuCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedCafeMenuCatalogCommand extends Command
{
    protected $signature = 'inventory:seed-cafe-menu {--company=2 : company_id}';

    protected $description = 'Create Menu subcategories (Smoothie, Shakes, …) and POS-only cafe products';

    public function handle(): int
    {
        $companyId = (int) ($this->option('company') ?: 2);
        session(['active_company_id' => $companyId]);

        $menu = MenuCategory::ensure();
        if ((int) $menu->company_id !== $companyId) {
            $menu->company_id = $companyId;
            $menu->save();
        }

        $departmentId = (int) (InventoryDepartment::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['bar'])
            ->value('id')
            ?: 0);

        if ($departmentId <= 0) {
            $this->error('Bar department nahi mila.');

            return self::FAILURE;
        }
        $catalog = $this->catalog();
        $createdCats = 0;
        $createdProducts = 0;
        $updatedProducts = 0;
        $skippedProducts = 0;

        DB::connection('tenant')->transaction(function () use (
            $catalog,
            $menu,
            $companyId,
            $departmentId,
            &$createdCats,
            &$createdProducts,
            &$updatedProducts,
            &$skippedProducts
        ) {
            foreach ($catalog as $subName => $items) {
                $sub = InventoryCategory::query()
                    ->where('company_id', $companyId)
                    ->where('parent_id', $menu->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($subName)])
                    ->first();

                if (! $sub) {
                    $sub = InventoryCategory::query()->create([
                        'company_id' => $companyId,
                        'name' => $subName,
                        'parent_id' => $menu->id,
                    ]);
                    $createdCats++;
                    $this->line("Category: {$subName}");
                }

                foreach ($items as [$name, $price]) {
                    $existing = InventoryProduct::query()
                        ->where('company_id', $companyId)
                        ->where('category_id', $sub->id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                        ->first();

                    if ($existing) {
                        $existing->fill([
                            'price' => $price,
                            'active' => true,
                            'for_pos' => true,
                            'for_purchase' => false,
                            'department_id' => $departmentId,
                        ]);
                        if ($existing->isDirty()) {
                            $existing->save();
                            $updatedProducts++;
                        } else {
                            $skippedProducts++;
                        }
                        $this->assignBarOnly($existing, $companyId, $departmentId);
                        continue;
                    }

                    $product = InventoryProduct::query()->create([
                        'company_id' => $companyId,
                        'category_id' => $sub->id,
                        'department_id' => $departmentId,
                        'sku' => InventoryProduct::generateNextSku('CAF'),
                        'barcode' => null,
                        'name' => $name,
                        'uom' => 'nos',
                        'cost' => 0,
                        'gas_charges' => 0,
                        'profit' => 0,
                        'service_charges' => 0,
                        'extra_costs' => null,
                        'price' => $price,
                        'qty_on_hand' => 0,
                        'reorder_level' => 0,
                        'active' => true,
                        'for_pos' => true,
                        'for_purchase' => false,
                    ]);
                    $this->assignBarOnly($product, $companyId, $departmentId);
                    $createdProducts++;
                }
            }
        });

        $this->info("Done. Categories created: {$createdCats}. Products created: {$createdProducts}, updated: {$updatedProducts}, unchanged: {$skippedProducts}.");

        return self::SUCCESS;
    }

    private function assignBarOnly(InventoryProduct $product, int $companyId, int $departmentId): void
    {
        $product->departments()->sync([
            $departmentId => ['company_id' => $companyId],
        ]);
    }

    /**
     * @return array<string, list<array{0: string, 1: float}>>
     */
    private function catalog(): array
    {
        return [
            'Smoothie' => [
                ['Berry Passion Smoothie (Small)', 795],
                ['Berry Passion Smoothie (Medium)', 895],
                ['Berry Passion Smoothie (Large)', 1095],
                ['Strawberry Banana Smoothie (Small)', 795],
                ['Strawberry Banana Smoothie (Medium)', 895],
                ['Strawberry Banana Smoothie (Large)', 1095],
                ['Clementine Mango (Small)', 795],
                ['Clementine Mango (Medium)', 895],
                ['Clementine Mango (Large)', 1095],
            ],
            'Shakes' => [
                ['Mixed Berry (Small)', 795],
                ['Mixed Berry (Medium)', 895],
                ['Mixed Berry (Large)', 1095],
                ['Strawberry Banana (Small)', 795],
                ['Strawberry Banana (Medium)', 895],
                ['Strawberry Banana (Large)', 1095],
                ['Granola & Honey (Small)', 795],
                ['Granola & Honey (Medium)', 895],
                ['Granola & Honey (Large)', 1095],
            ],
            'Parfaits' => [
                ['Mocha Mania (Regular)', 795],
                ['Mocha Mania (Large)', 1095],
                ['Cookies & Crumble (Regular)', 795],
                ['Cookies & Crumble (Large)', 1095],
                ['Berried In Caramel (Regular)', 795],
                ['Berried In Caramel (Large)', 1095],
                ['Dreamsicle Life (Regular)', 795],
                ['Dreamsicle Life (Large)', 1095],
            ],
            'Chilled Favorites' => [
                ['Icepresso (Regular)', 695],
                ['Icepresso (Large)', 795],
                ['Flavored Icepresso (Mocha, Caramel, Vanilla, Hazelnut) (Regular)', 695],
                ['Flavored Icepresso (Mocha, Caramel, Vanilla, Hazelnut) (Large)', 795],
                ['Chillatte (Regular)', 695],
                ['Chillatte (Large)', 795],
                ['Frozen Hot Chocolate (Creamy, Dark, White, Vanilla) (Regular)', 695],
                ['Frozen Hot Chocolate (Creamy, Dark, White, Vanilla) (Large)', 795],
                ['Cookies & Cream (Regular)', 695],
                ['Cookies & Cream (Large)', 795],
                ['Chai Chiller (Regular)', 695],
                ['Chai Chiller (Large)', 795],
                ['Green Tea Chiller (Regular)', 695],
                ['Green Tea Chiller (Large)', 795],
                ['Fruit Chiller (Mango, Strawberry) (Regular)', 695],
                ['Fruit Chiller (Mango, Strawberry) (Large)', 795],
            ],
            'Hot Handcrafted' => [
                ['Signature Lattes (Vanilla Beans, Hazelnut, Butter Nut) (Regular)', 695],
                ['Signature Lattes (Vanilla Beans, Hazelnut, Butter Nut) (Large)', 895],
                ['Espressos (Regular)', 495],
                ['Espressos (Large)', 550],
                ['Signature Hot Chocolates (Creamy, Dark, White, Cookies & Cream) (Regular)', 695],
                ['Signature Hot Chocolates (Creamy, Dark, White, Cookies & Cream) (Large)', 795],
                ['Caramel Crafted (Regular)', 695],
                ['Caramel Crafted (Large)', 795],
                ['Cafe Latte (Regular)', 695],
                ['Cafe Latte (Large)', 795],
                ['Flat White (Regular)', 695],
                ['Flat White (Large)', 795],
                ['Cappuccino (Regular)', 695],
                ['Cappuccino (Large)', 795],
                ['Americano (Regular)', 695],
                ['Americano (Large)', 795],
                ['Matcha (Regular)', 695],
                ['Matcha (Large)', 795],
                ['Cardamom Tea', 695],
                ['Karak Tea', 250],
                ['Kashmiri Tea', 250],
            ],
            'Desserts' => [
                ['Molten Lava Cake', 595],
                ['Molten Lava Cake With Ice Cream', 750],
                ['Matilda Cake', 725],
                ['Tera Missu Cake', 725],
                ['Kunafa', 999],
                ['Basbusa', 495],
                ['Arabic Delight', 595],
                ['Cheese Cake Slice', 995],
                ['Red Velvet Slice', 625],
                ['Banana Bread Slice', 350],
                ['Brownies (Special Nutella)', 450],
                ['Tarte (Chocolate, Lemon, Walnut)', 595],
                ['Sundae (Lotus, Red Velvet, Vanilla)', 550],
                ['Muffins (Chocolate Chip, Double Chocolate)', 495],
                ['Croissant (Cheese, Chocolate, Butter)', 495],
                ['Stuffed Croissant (Nutella, Caramel, Lotus)', 995],
                ['Almond Croissant', 450],
                ['Ice Cream (2 Scoop)', 350],
                ['Cookies Chocolate Chip', 350],
                ['Cookies (Double Chocolate)', 250],
                ['Chocolate Pop', 550],
            ],
            'Whole Cakes' => [
                ['Lotus Cake', 2499],
                ['Chocolate Fudge Cake', 2499],
                ['Nutella Cake', 2499],
                ['Chocolate Cake', 2499],
                ['Red Velvet Cake', 2499],
                ['Three Milk Cake', 2499],
                ['Cheese Cake', 2499],
            ],
        ];
    }
}
