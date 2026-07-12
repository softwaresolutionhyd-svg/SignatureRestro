<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\PayrollEntry;
use Carbon\Carbon;

class AttendancePayrollService
{
    public const WORKING_DAYS_PER_MONTH = 30;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_HOLIDAY = 'holiday';

    public static function statusFromCode(?string $code): ?string
    {
        return match (strtoupper(trim((string) $code))) {
            'P' => self::STATUS_PRESENT,
            'A' => self::STATUS_ABSENT,
            'H' => self::STATUS_HOLIDAY,
            default => null,
        };
    }

    public static function codeFromStatus(?string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => 'P',
            self::STATUS_ABSENT => 'A',
            self::STATUS_HOLIDAY, 'leave', 'half_day' => 'H',
            default => '',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function monthBounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    /**
     * @return list<\Carbon\CarbonInterface>
     */
    public function datesInMonth(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->copy();
        }

        return $dates;
    }

    public function countAbsentDays(int $employeeId, string $month): int
    {
        [$start, $end] = $this->monthBounds($month);

        return EmployeeAttendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$start, $end])
            ->where('status', self::STATUS_ABSENT)
            ->count();
    }

    /**
     * @return array{present: int, absent: int, holiday: int}
     */
    public function monthCountsForEmployee(int $employeeId, string $month): array
    {
        [$start, $end] = $this->monthBounds($month);

        $rows = EmployeeAttendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$start, $end])
            ->get(['status']);

        $counts = ['present' => 0, 'absent' => 0, 'holiday' => 0];
        foreach ($rows as $row) {
            $code = self::codeFromStatus($row->status);
            if ($code === 'P') {
                $counts['present']++;
            } elseif ($code === 'A') {
                $counts['absent']++;
            } elseif ($code === 'H') {
                $counts['holiday']++;
            }
        }

        return $counts;
    }

    public function perDaySalary(float $basicSalary): float
    {
        if ($basicSalary <= 0) {
            return 0.0;
        }

        return round($basicSalary / self::WORKING_DAYS_PER_MONTH, 4);
    }

    public function absentDeductionAmount(float $basicSalary, int $absentDays): float
    {
        if ($absentDays <= 0 || $basicSalary <= 0) {
            return 0.0;
        }

        return round($this->perDaySalary($basicSalary) * $absentDays, 2);
    }

    public function syncPayrollDeductionForEmployee(int $employeeId, string $period): void
    {
        $employee = Employee::query()->find($employeeId);
        if ($employee === null) {
            return;
        }

        $entry = PayrollEntry::query()
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->first();

        if ($entry === null || $entry->status === 'paid') {
            return;
        }

        $absentDays = $this->countAbsentDays($employeeId, $period);
        $entry->deduction = $this->absentDeductionAmount((float) $employee->salary, $absentDays);
        $entry->recalculateNet();
        $entry->save();
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function syncPayrollDeductionsForPeriod(string $period, array $employeeIds): void
    {
        foreach (array_unique($employeeIds) as $employeeId) {
            $this->syncPayrollDeductionForEmployee((int) $employeeId, $period);
        }
    }
}
