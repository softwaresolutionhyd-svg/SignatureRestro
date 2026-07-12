<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPayment;
use App\Models\PayrollEntry;
use App\Support\EnsuresEmployeeLoanSchema;
use Illuminate\Support\Facades\Auth;

class EmployeeLoanService
{
    use EnsuresEmployeeLoanSchema;

    public function activeLoanForEmployee(int $employeeId): ?EmployeeLoan
    {
        $this->ensureEmployeeLoanSchema();

        return EmployeeLoan::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderByDesc('id')
            ->first();
    }

    public function installmentForPeriod(EmployeeLoan $loan, string $period): float
    {
        if (! $loan->isActive()) {
            return 0.0;
        }

        if ($this->paymentExistsForPeriod($loan, $period)) {
            return (float) EmployeeLoanPayment::query()
                ->where('employee_loan_id', $loan->id)
                ->where('period', $period)
                ->value('amount');
        }

        return round(min((float) $loan->monthly_installment, (float) $loan->balance), 2);
    }

    public function syncLoanDeductionForPayroll(PayrollEntry $entry, Employee $employee, string $period): void
    {
        $this->ensureEmployeeLoanSchema();

        if ($entry->status === 'paid') {
            return;
        }

        $loan = $this->activeLoanForEmployee($employee->id);
        $entry->loan = $loan ? $this->installmentForPeriod($loan, $period) : 0.0;
    }

    public function recordPaymentOnPaid(PayrollEntry $entry, ?int $userId = null): void
    {
        $this->ensureEmployeeLoanSchema();

        $amount = round((float) ($entry->loan ?? 0), 2);
        if ($amount <= 0) {
            return;
        }

        $loan = $this->activeLoanForEmployee((int) $entry->employee_id);
        if ($loan === null) {
            return;
        }

        $existing = EmployeeLoanPayment::query()
            ->where('employee_loan_id', $loan->id)
            ->where('period', $entry->period)
            ->first();

        if ($existing !== null) {
            return;
        }

        $balanceAfter = round(max(0, (float) $loan->balance - $amount), 2);

        EmployeeLoanPayment::create([
            'employee_loan_id' => $loan->id,
            'payroll_entry_id' => $entry->id,
            'employee_id' => $entry->employee_id,
            'period' => $entry->period,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'created_by' => $userId ?? Auth::id(),
        ]);

        $loan->balance = $balanceAfter;
        if ($balanceAfter <= 0) {
            $loan->status = 'completed';
        }
        $loan->save();
    }

    private function paymentExistsForPeriod(EmployeeLoan $loan, string $period): bool
    {
        return EmployeeLoanPayment::query()
            ->where('employee_loan_id', $loan->id)
            ->where('period', $period)
            ->exists();
    }
}
