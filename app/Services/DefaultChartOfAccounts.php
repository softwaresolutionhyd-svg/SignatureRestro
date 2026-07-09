<?php

namespace App\Services;

use App\Models\Account;

class DefaultChartOfAccounts
{
    /** @var list<array{code: string, name: string, type: string, description: string}> */
    private const ACCOUNTS = [
        ['code' => '1000', 'name' => 'Cash', 'type' => Account::TYPE_ASSET, 'description' => 'Cash on hand'],
        ['code' => '1100', 'name' => 'Bank', 'type' => Account::TYPE_ASSET, 'description' => 'Bank accounts'],
        ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET, 'description' => 'Customer credit / receivables'],
        ['code' => '1300', 'name' => 'Inventory', 'type' => Account::TYPE_ASSET, 'description' => 'Stock on hand'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => Account::TYPE_LIABILITY, 'description' => 'Vendor payables'],
        ['code' => '3000', 'name' => "Owner's Equity", 'type' => Account::TYPE_EQUITY, 'description' => 'Capital / equity'],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => Account::TYPE_INCOME, 'description' => 'POS and sales income'],
        ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => Account::TYPE_EXPENSE, 'description' => 'Inventory cost of sales'],
        ['code' => '5100', 'name' => 'Operating Expenses', 'type' => Account::TYPE_EXPENSE, 'description' => 'General operating expenses'],
        ['code' => '5200', 'name' => 'Payroll Expense', 'type' => Account::TYPE_EXPENSE, 'description' => 'Salaries and wages'],
    ];

    public function ensureForCompany(?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        if (Account::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::ACCOUNTS as $row) {
            Account::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'description' => $row['description'],
                'active' => true,
                'is_system' => true,
            ]);
        }
    }
}
