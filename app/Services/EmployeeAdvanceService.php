<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollEntry;
use App\Support\EnsuresEmployeeAdvanceSchema;
use Illuminate\Support\Collection;

class EmployeeAdvanceService
{
    use EnsuresEmployeeAdvanceSchema;

    public function activeAdvanceForEmployee(int $employeeId): ?EmployeeAdvance
    {
        return $this->activeAdvancesForEmployee($employeeId)->first();
    }

    /**
     * @return Collection<int, EmployeeAdvance>
     */
    public function activeAdvancesForEmployee(int $employeeId): Collection
    {
        $this->ensureEmployeeAdvanceSchema();

        return EmployeeAdvance::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderBy('id')
            ->get();
    }

    public function amountForPayroll(Employee $employee, string $period): float
    {
        $total = 0.0;

        foreach ($this->activeAdvancesForEmployee((int) $employee->id) as $advance) {
            if (! $advance->isActive()) {
                continue;
            }

            $startPeriod = ($advance->start_date ?? $advance->created_at)?->format('Y-m');
            if ($startPeriod !== null && $period < $startPeriod) {
                continue;
            }

            $total += (float) $advance->balance;
        }

        return round($total, 2);
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

        $remaining = round((float) ($entry->advance ?? 0), 2);
        if ($remaining <= 0) {
            return;
        }

        $advances = $this->activeAdvancesForEmployee((int) $entry->employee_id);
        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $startPeriod = ($advance->start_date ?? $advance->created_at)?->format('Y-m');
            if ($startPeriod !== null && $entry->period < $startPeriod) {
                continue;
            }

            $take = round(min((float) $advance->balance, $remaining), 2);
            if ($take <= 0) {
                continue;
            }

            $balanceAfter = round(max(0, (float) $advance->balance - $take), 2);
            $advance->balance = $balanceAfter;
            $advance->settled_payroll_entry_id = $entry->id;
            $advance->settled_period = $entry->period;
            $advance->settled_at = now();
            if ($balanceAfter <= 0) {
                $advance->status = 'settled';
            }
            $advance->save();

            $remaining = round($remaining - $take, 2);
        }
    }
}
