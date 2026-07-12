<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\AttendancePayrollService;
use App\Services\PayrollSalaryService;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendancePayrollService $attendancePayroll
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->canManageTeamAttendance(), 403);

        $month = $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $activeOnly = $request->boolean('active_only', true);
        $employeeNo = trim((string) $request->query('employee_no', ''));
        $dates = $this->attendancePayroll->datesInMonth($month);
        [$startStr, $endStr] = $this->attendancePayroll->monthBounds($month);

        $staffQuery = Employee::query()->orderBy('employee_no');
        if ($activeOnly) {
            $staffQuery->where('active', true);
        }
        if ($employeeNo !== '') {
            $staffQuery->where('employee_no', 'like', '%'.$employeeNo.'%');
        }
        $employees = $staffQuery->get(['id', 'name', 'employee_no', 'active', 'salary']);

        $grid = [];
        $summaries = [];
        foreach ($employees as $employee) {
            $grid[$employee->id] = [];
            $summaries[$employee->id] = [
                'present' => 0,
                'absent' => 0,
                'holiday' => 0,
                'deduction' => 0.0,
                'per_day' => $this->attendancePayroll->perDaySalary((float) $employee->salary),
            ];
        }

        $records = EmployeeAttendance::query()
            ->whereBetween('attendance_date', [$startStr, $endStr])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'attendance_date', 'status']);

        foreach ($records as $record) {
            $empId = (int) $record->employee_id;
            $dateKey = $record->attendance_date->format('Y-m-d');
            $code = AttendancePayrollService::codeFromStatus($record->status);
            if ($code === '' || ! isset($grid[$empId])) {
                continue;
            }
            $grid[$empId][$dateKey] = $code;
        }

        foreach ($employees as $employee) {
            $counts = $this->attendancePayroll->monthCountsForEmployee($employee->id, $month);
            $summaries[$employee->id]['present'] = $counts['present'];
            $summaries[$employee->id]['absent'] = $counts['absent'];
            $summaries[$employee->id]['holiday'] = $counts['holiday'];
            $summaries[$employee->id]['deduction'] = $this->attendancePayroll->absentDeductionAmount(
                (float) $employee->salary,
                $counts['absent']
            );
        }

        return view('employees.attendance-index', compact(
            'employees',
            'month',
            'activeOnly',
            'employeeNo',
            'dates',
            'grid',
            'summaries',
        ));
    }

    public function saveGrid(Request $request)
    {
        abort_unless($request->user()->canManageTeamAttendance(), 403);

        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'active_only' => ['nullable', 'boolean'],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'attendance_json' => ['nullable', 'string'],
        ]);

        $month = $data['month'];
        [$startStr, $endStr] = $this->attendancePayroll->monthBounds($month);
        $start = Carbon::parse($startStr);
        $end = Carbon::parse($endStr);
        $payload = json_decode($data['attendance_json'] ?? '{}', true);
        if (! is_array($payload)) {
            return redirect()->back()->withErrors('Attendance data invalid.');
        }
        $touchedEmployeeIds = [];

        DB::connection('tenant')->transaction(function () use ($request, $payload, $start, $end, &$touchedEmployeeIds) {
            foreach ($payload as $employeeId => $days) {
                $employeeId = (int) $employeeId;
                if ($employeeId <= 0 || ! is_array($days)) {
                    continue;
                }

                $touchedEmployeeIds[] = $employeeId;

                foreach ($days as $date => $code) {
                    $date = (string) $date;
                    $day = Carbon::parse($date);
                    if ($day->lt($start) || $day->gt($end)) {
                        continue;
                    }

                    $status = AttendancePayrollService::statusFromCode(is_string($code) ? $code : null);
                    $existing = EmployeeAttendance::query()
                        ->where('employee_id', $employeeId)
                        ->whereDate('attendance_date', $date)
                        ->first();

                    if ($status === null) {
                        if ($existing) {
                            $existing->delete();
                        }

                        continue;
                    }

                    EmployeeAttendance::query()->updateOrCreate(
                        [
                            'employee_id' => $employeeId,
                            'attendance_date' => $date,
                        ],
                        [
                            'user_id' => $request->user()->id,
                            'status' => $status,
                            'source' => 'manual',
                            'clock_in' => null,
                            'clock_out' => null,
                            'notes' => null,
                        ]
                    );
                }
            }
        });

        foreach (array_unique($touchedEmployeeIds) as $employeeId) {
            $employee = Employee::query()->find($employeeId);
            if ($employee) {
                app(PayrollSalaryService::class)->syncPayrollEntryForEmployee($employee, $month, $request->user()->id);
            }
        }

        ActivityLogger::log('attendance.grid_saved', 'Monthly attendance grid saved', null, [
            'month' => $month,
            'employees' => count(array_unique($touchedEmployeeIds)),
        ]);

        return redirect()
            ->route('employees.attendance.index', array_filter([
                'month' => $month,
                'active_only' => $request->boolean('active_only', true) ? 1 : 0,
                'employee_no' => trim((string) ($data['employee_no'] ?? '')),
            ], fn ($v) => $v !== '' && $v !== null))
            ->with('status', 'Attendance save ho gayi — absent days ki salary payroll deduction mein update ho gayi.');
    }
}
