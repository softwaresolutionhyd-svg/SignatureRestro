<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Support\EnsuresPayrollSchema;
use Carbon\Carbon;

class PayrollSalaryService
{
    use EnsuresPayrollSchema;

    public function __construct(
        private readonly AttendancePayrollService $attendancePayroll,
        private readonly EmployeeContactSyncService $contactSync,
        private readonly EmployeeLoanService $loanService,
    ) {}

    /**
     * @return array{0: string, 1: string}
     */
    public function periodBounds(string $period): array
    {
        return $this->attendancePayroll->monthBounds($period);
    }

    public function workingDaysForEmployee(int $employeeId, string $period): int
    {
        $counts = $this->attendancePayroll->monthCountsForEmployee($employeeId, $period);

        return $counts['present'] + $counts['holiday'];
    }

    public function foodBillForEmployee(Employee $employee, string $period): float
    {
        $this->contactSync->ensureContactForEmployee($employee);
        $employee->refresh();

        if (! $employee->contact_id) {
            return 0.0;
        }

        [$start, $end] = $this->periodBounds($period);

        return round((float) CreditLedger::query()
            ->where('contact_id', $employee->contact_id)
            ->where('type', 'credit')
            ->whereBetween('entry_date', [$start, $end])
            ->sum('amount'), 2);
    }

    public function syncPayrollEntryForEmployee(Employee $employee, string $period, ?int $createdBy = null): PayrollEntry
    {
        $base = (float) ($employee->salary ?? 0);
        $absentDays = $this->attendancePayroll->countAbsentDays($employee->id, $period);
        $deduction = $this->attendancePayroll->absentDeductionAmount($base, $absentDays);
        $foodBill = $this->foodBillForEmployee($employee, $period);

        $entry = PayrollEntry::query()
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        if ($entry !== null && $entry->status === 'paid') {
            app(PayrollFoodBillSettlementService::class)->settle($entry, $createdBy);

            return $entry;
        }

        if ($entry === null) {
            $entry = new PayrollEntry([
                'employee_id' => $employee->id,
                'period' => $period,
                'base_salary' => $base,
                'bonus' => 0,
                'loan' => 0,
                'status' => 'draft',
                'created_by' => $createdBy,
            ]);
        }

        $entry->base_salary = $base;
        $entry->deduction = $deduction;
        $entry->food_bill = $foodBill;
        $this->loanService->syncLoanDeductionForPayroll($entry, $employee, $period);
        $entry->bonus = (float) ($entry->bonus ?? 0);
        $entry->recalculateNet();
        $entry->save();

        app(PayrollFoodBillSettlementService::class)->settle($entry, $createdBy);

        return $entry;
    }

    public function syncPayrollPeriod(string $period, ?int $createdBy = null, bool $activeOnly = true): void
    {
        $this->ensurePayrollSchema();
        $this->contactSync->syncAllEmployees();

        $query = Employee::query()->orderBy('name');
        if ($activeOnly) {
            $query->where('active', true);
        }

        $query->get()->each(function (Employee $employee) use ($period, $createdBy) {
            $this->syncPayrollEntryForEmployee($employee, $period, $createdBy);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salaryRowsForPeriod(string $period, bool $activeOnly = true): array
    {
        $this->syncPayrollPeriod($period, null, $activeOnly);

        $query = Employee::query()
            ->with([
                'designation:id,name',
                'staffCategory:id,name,sort_order',
                'payrollEntries' => fn ($q) => $q->where('period', $period),
            ])
            ->orderBy('employee_no');
        if ($activeOnly) {
            $query->where('active', true);
        }

        $rows = [];
        foreach ($query->get() as $employee) {
            $entry = $employee->payrollEntries->first();
            if ($entry === null) {
                $entry = $this->syncPayrollEntryForEmployee($employee, $period);
            }

            $rows[] = $this->rowFromEntry($employee, $entry, $period);
        }

        return $rows;
    }

    public function brandName(): string
    {
        return 'Signature Restro';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, rows: list<array<string, mixed>>}>
     */
    public function groupRowsByStaffCategory(array $rows): array
    {
        $categories = \App\Models\EmployeeStaffCategory::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        $groups = [];

        foreach ($categories as $category) {
            $catRows = array_values(array_filter(
                $rows,
                fn (array $row) => (int) ($row['staff_category_id'] ?? 0) === (int) $category->id
            ));
            if ($catRows === []) {
                continue;
            }
            $groups[] = ['name' => $category->name, 'rows' => $catRows];
        }

        $unassigned = array_values(array_filter(
            $rows,
            fn (array $row) => empty($row['staff_category_id'])
        ));
        if ($unassigned !== []) {
            $groups[] = ['name' => 'UNASSIGNED', 'rows' => $unassigned];
        }

        if ($groups === [] && $rows !== []) {
            $groups[] = ['name' => 'ALL EMPLOYEES', 'rows' => $rows];
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    public function rowFromEntry(Employee $employee, PayrollEntry $entry, string $period): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->name,
            'designation' => $employee->designation?->name ?? '—',
            'staff_category_id' => $employee->staff_category_id,
            'staff_category' => $employee->staffCategory?->name,
            'staff_category_sort' => (int) ($employee->staffCategory?->sort_order ?? 999),
            'basic_salary' => (float) $entry->base_salary,
            'working_days' => $this->workingDaysForEmployee($employee->id, $period),
            'deduction' => (float) $entry->deduction,
            'food_bill' => (float) $entry->food_bill,
            'loan' => (float) $entry->loan,
            'bonus' => (float) $entry->bonus,
            'final_salary' => (float) $entry->net_pay,
            'status' => $entry->status === 'paid' ? 'Paid' : 'Unpaid',
            'status_key' => $entry->status,
            'payroll_entry_id' => $entry->id,
            'paid_at' => $entry->paid_at,
        ];
    }

    public function periodLabel(string $period): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $period.'-01')->format('F Y');
        } catch (\Throwable) {
            return $period;
        }
    }
}
