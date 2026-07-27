<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollEntry;

/**
 * When payroll is marked paid, mirror net pay in Expenses (Salaries Expense category).
 * GL stays on payroll journal only — do not call AutoJournalService::postExpensePaid here.
 */
final class PayrollPaidExpenseService
{
    public const CATEGORY_NAME = 'Salaries Expense';

    public const PAYROLL_NOTE_PREFIX = 'payroll_entry:';

    public function syncFromPaidPayroll(PayrollEntry $entry, ?int $approvedByUserId = null): ?Expense
    {
        $entry->loadMissing('employee:id,name,company_id');

        $noteKey = self::PAYROLL_NOTE_PREFIX.$entry->id;
        $existing = Expense::query()
            ->where('notes', $noteKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $netPay = round((float) $entry->net_pay, 2);
        if ($netPay <= 0) {
            return null;
        }

        $employee = $entry->employee;
        if ($employee === null) {
            return null;
        }

        $companyId = (int) ($entry->company_id ?? $employee->company_id ?? current_company_id() ?? 0);
        $category = $this->salariesCategory($companyId);

        $name = trim((string) $employee->name);
        if ($name === '') {
            $name = 'Employee';
        }

        $paidAt = $entry->paid_at ?? now();
        $now = now();

        $expense = new Expense([
            'company_id' => $companyId > 0 ? $companyId : null,
            'employee_id' => (int) $employee->id,
            'category_id' => $category->id,
            'description' => $name.' salary paid',
            'expense_date' => $paidAt->toDateString(),
            'qty' => 1,
            'unit_amount' => $netPay,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total_amount' => $netPay,
            'grand_total' => $netPay,
            'notes' => $noteKey,
            'status' => Expense::STATUS_PAID,
            'submitted_at' => $now,
            'approved_at' => $now,
            'approved_by' => $approvedByUserId,
            'paid_at' => $paidAt,
        ]);

        $expense->save();

        return $expense;
    }

    private function salariesCategory(int $companyId): ExpenseCategory
    {
        if ($companyId <= 0) {
            $companyId = (int) (current_company_id() ?? 0);
        }

        return ExpenseCategory::query()->firstOrCreate(
            [
                'company_id' => $companyId > 0 ? $companyId : null,
                'name' => self::CATEGORY_NAME,
            ],
            [
                'description' => 'Employee salary payments from payroll',
                'active' => true,
            ]
        );
    }
}
