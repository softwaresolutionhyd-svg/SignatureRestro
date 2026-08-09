<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Setting;
use App\Services\AttendancePayrollService;
use App\Services\PayrollSalaryService;
use App\Services\Sync\SyncPayrollQueueService;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        $staffQuery = Employee::query()
            ->excludeAdminAccounts()
            ->with(['staffCategory:id,name,sort_order'])
            ->orderBy('employee_no');
        if ($activeOnly) {
            $staffQuery->where('active', true);
        }
        if ($employeeNo !== '') {
            $staffQuery->matchingSearch($employeeNo);
        }
        $employees = $staffQuery->get(['id', 'name', 'employee_no', 'active', 'salary', 'staff_category_id']);
        $categoryGroups = $this->groupEmployeesByStaffCategory($employees);

        $grid = [];
        $summaries = [];
        foreach ($employees as $employee) {
            $grid[$employee->id] = [];
            $summaries[$employee->id] = [
                'present' => 0,
                'absent' => 0,
                'holiday' => 0,
                'earned' => 0.0,
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
            $workingDays = (int) $counts['present'] + (int) $counts['holiday'];
            $summaries[$employee->id]['earned'] = $this->attendancePayroll->earnedSalary(
                (float) $employee->salary,
                $workingDays
            );
        }

        return view('employees.attendance-index', compact(
            'employees',
            'categoryGroups',
            'month',
            'activeOnly',
            'employeeNo',
            'dates',
            'grid',
            'summaries',
        ));
    }

    public function printToday(Request $request): View
    {
        abort_unless($request->user()?->canManageTeamAttendance(), 403);

        $dateInput = trim((string) $request->query('date', ''));
        if ($dateInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
            $date = Carbon::createFromFormat('Y-m-d', $dateInput)->startOfDay();
        } else {
            $date = now()->timezone(config('app.timezone'))->startOfDay();
        }
        $dateKey = $date->format('Y-m-d');
        $activeOnly = $request->boolean('active_only', true);

        $staffQuery = Employee::query()
            ->excludeAdminAccounts()
            ->with(['staffCategory:id,name,sort_order'])
            ->orderBy('employee_no');
        if ($activeOnly) {
            $staffQuery->where('active', true);
        }
        $employees = $staffQuery->get(['id', 'name', 'employee_no', 'active', 'staff_category_id']);

        $byEmp = EmployeeAttendance::query()
            ->whereDate('attendance_date', $dateKey)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'status'])
            ->keyBy('employee_id');

        $totals = [
            'present' => 0,
            'absent' => 0,
            'holiday' => 0,
            'unmarked' => 0,
            'all' => $employees->count(),
        ];

        $categoryGroups = [];
        foreach ($this->groupEmployeesByStaffCategory($employees) as $group) {
            $rows = [];
            foreach ($group['employees'] as $employee) {
                $rec = $byEmp->get($employee->id);
                $code = $rec
                    ? AttendancePayrollService::codeFromStatus((string) $rec->status)
                    : '';

                $statusLabel = match ($code) {
                    'P' => 'Present',
                    'A' => 'Absent',
                    'H' => 'Holiday',
                    default => 'Not marked',
                };

                match ($code) {
                    'P' => $totals['present']++,
                    'A' => $totals['absent']++,
                    'H' => $totals['holiday']++,
                    default => $totals['unmarked']++,
                };

                $rows[] = [
                    'employee_no' => (string) ($employee->employee_no ?? ''),
                    'name' => (string) $employee->name,
                    'code' => $code !== '' ? $code : '—',
                    'status' => $statusLabel,
                ];
            }

            $categoryGroups[] = [
                'name' => $group['name'],
                'rows' => $rows,
            ];
        }

        $companyName = Setting::get('company_name', config('app.name'));
        $dateLabel = $date->timezone(config('app.timezone'))->format('d M Y');

        return view('employees.attendance-print-today', [
            'companyName' => $companyName,
            'dateKey' => $dateKey,
            'dateLabel' => $dateLabel,
            'activeOnly' => $activeOnly,
            'categoryGroups' => $categoryGroups,
            'totals' => $totals,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return list<array{name: string, employees: \Illuminate\Support\Collection<int, Employee>}>
     */
    private function groupEmployeesByStaffCategory($employees): array
    {
        $employees->loadMissing('staffCategory');

        $categories = \App\Models\EmployeeStaffCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        $groups = [];
        foreach ($categories as $category) {
            $rows = $employees
                ->where('staff_category_id', $category->id)
                ->sortBy('employee_no', SORT_NATURAL)
                ->values();
            if ($rows->isEmpty()) {
                continue;
            }
            $groups[] = ['name' => (string) $category->name, 'employees' => $rows];
        }

        $unassigned = $employees
            ->filter(fn (Employee $employee) => empty($employee->staff_category_id))
            ->sortBy('employee_no', SORT_NATURAL)
            ->values();
        if ($unassigned->isNotEmpty()) {
            $groups[] = ['name' => 'Unassigned', 'employees' => $unassigned];
        }

        if ($groups === [] && $employees->isNotEmpty()) {
            $groups[] = [
                'name' => 'All Employees',
                'employees' => $employees->sortBy('employee_no', SORT_NATURAL)->values(),
            ];
        }

        return $groups;
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

        if (config('sync.enabled') && config('sync.role') === 'local') {
            app(SyncPayrollQueueService::class)->queuePayrollData($month);
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
            ->with('status', 'Attendance save ho gayi — payroll net salary working days (P+H) ke hisab se update ho gayi.');
    }
}
