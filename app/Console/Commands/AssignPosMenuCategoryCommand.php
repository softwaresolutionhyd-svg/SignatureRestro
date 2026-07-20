<?php

namespace App\Console\Commands;

use App\Support\MenuCategory;
use Illuminate\Console\Command;

class AssignPosMenuCategoryCommand extends Command
{
    protected $signature = 'inventory:pos-to-menu {--reclassify : Re-guess all POS product sub-categories under Menu}';

    protected $description = 'Ensure Menu parent exists, keep POS sub-categories, restore products stuck on Menu root';

    public function handle(): int
    {
        $category = MenuCategory::ensure();
        $this->info('Menu category id='.$category->id);

        $adopted = MenuCategory::adoptLegacySubcategories($category);
        $this->info("Sub-categories moved under Menu: {$adopted}");

        if ($this->option('reclassify')) {
            $n = MenuCategory::reclassifyPosProducts($category, onlyMenuRoot: false, useHistory: false);
            $this->info("POS products reclassified: {$n}");
        } else {
            $n = MenuCategory::repairProductsOnMenuRoot($category);
            $this->info("POS products restored to sub-categories: {$n}");
        }

        return self::SUCCESS;
    }
}
