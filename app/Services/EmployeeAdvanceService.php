<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollEntry;
use App\Support\EnsuresEmployeeAdvanceSchema;

class EmployeeAdvanceService
{
    use EnsuresEmployeeAdvanceSchema;

    public function activeAdvanceForEmployee(int $employeeId): ?EmployeeAdvance
    {
        $this->ensureEmployeeAdvanceSchema();

        return EmployeeAdvance::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderByDesc('id')
            ->first();
    }

    public function amountForPayroll(Employee $employee, string $period): float
    {
        $advance = $this->activeAdvanceForEmployee((int) $employee->id);
        if ($advance === null || ! $advance->isActive()) {
            return 0.0;
        }

        $startPeriod = ($advance->start_date ?? $advance->created_at)?->format('Y-m');
        if ($startPeriod !== null && $period < $startPeriod) {
            return 0.0;
        }

        return round((float) $advance->balance, 2);
    }

    public function syncAdvanceDeductionForPayroll(PayrollEntry $entry, Employee $employee, string $period): void
    {
        $this->ensureEmployeeAdvanceSchema();

        if ($entry->status === 'paid') {
            return;
        }

        $entry->advance = $this->amountForPayroll($employee, $period);
    }

    public function settleOnPaid(PayrollEntry $entry, ?int $userId = null): void
    {
        $this->ensureEmployeeAdvanceSchema();

        $amount = round((float) ($entry->advance ?? 0), 2);
        if ($amount <= 0) {
            return;
        }

        $advance = $this->activeAdvanceForEmployee((int) $entry->employee_id);
        if ($advance === null) {
            return;
        }

        $balanceAfter = round(max(0, (float) $advance->balance - $amount), 2);
        $advance->balance = $balanceAfter;
        $advance->settled_payroll_entry_id = $entry->id;
        $advance->settled_period = $entry->period;
        $advance->settled_at = now();
        if ($balanceAfter <= 0) {
            $advance->status = 'settled';
        }
        $advance->save();
    }
}
