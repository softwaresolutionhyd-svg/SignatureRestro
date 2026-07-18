<?php

namespace App\Console\Commands;

use App\Support\IngredientsCategory;
use Illuminate\Console\Command;

class AssignWarehouseIngredientsCommand extends Command
{
    protected $signature = 'inventory:warehouse-to-ingredients';

    protected $description = 'Ensure Ingredients category exists and assign it to all Warehouse department products';

    public function handle(): int
    {
        $category = IngredientsCategory::ensure();
        $this->info('Ingredients category id='.$category->id);

        $n = IngredientsCategory::assignWarehouseProducts();
        $this->info("Updated {$n} warehouse product(s) → Ingredients.");

        return self::SUCCESS;
    }
}
