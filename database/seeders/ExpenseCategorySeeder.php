<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $cid = Company::query()->value('id');
        if (! $cid) {
            return;
        }

        $categories = [
            ['name' => 'Travel', 'description' => 'Flights, hotels, transport, fuel'],
            ['name' => 'Meals & Entertainment', 'description' => 'Client lunches, team dinners'],
            ['name' => 'Office Supplies', 'description' => 'Stationery, printing, consumables'],
            ['name' => 'Communication', 'description' => 'Phone, internet, courier'],
            ['name' => 'Training', 'description' => 'Courses, books, conferences'],
            ['name' => 'Software & Tools', 'description' => 'Licenses, subscriptions, SaaS tools'],
            ['name' => 'Medical', 'description' => 'Medical, health, insurance related'],
            ['name' => 'Miscellaneous', 'description' => 'Other business expenses'],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::query()->firstOrCreate(
                ['company_id' => $cid, 'name' => $cat['name']],
                array_merge($cat, ['company_id' => $cid, 'active' => true])
            );
        }
    }
}
