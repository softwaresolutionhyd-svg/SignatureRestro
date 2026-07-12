<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canManageTeamAttendance(), 403);

        $month = $request->query('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $employeeId = $request->query('employee_id');
        $activeOnly = $request->boolean('active_only');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        $staffQuery = Employee::query()->orderBy('name');
        if ($activeOnly) {
            $staffQuery->where('active', true);
        }
        $employees = $staffQuery->get(['id', 'name', 'active']);

        $statsByEmployee = [];
        foreach ($employees as $emp) {
            $statsByEmployee[$emp->id] = [
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'half_day' => 0,
                'total' => 0,
            ];
        }

        $monthRowsForStats = EmployeeAttendance::query()
            ->whereBetween('attendance_date', [$startStr, $endStr])
            ->get(['employee_id', 'status']);

        foreach ($monthRowsForStats as $r) {
            if (!isset($statsByEmployee[$r->employee_id])) {
                continue;
            }
            $key = $r->status;
            if (isset($statsByEmployee[$r->employee_id][$key])) {
                $statsByEmployee[$r->employee_id][$key]++;
            }
            $statsByEmployee[$r->employee_id]['total']++;
        }

        $rows = EmployeeAttendance::query()
            ->with(['employee:id,name,active'])
            ->when($employeeId, fn ($q) => $q->where('employee_id', (int) $employeeId))
            ->whereBetween('attendance_date', [$startStr, $endStr])
            ->orderByDesc('attendance_date')
            ->orderBy('employee_id')
            ->paginate(Setting::pageSize('employees_per_page', 30))
            ->withQueryString();

        return view('employees.attendance-index', compact(
            'rows',
            'employees',
            'month',
            'employeeId',
            'activeOnly',
            'statsByEmployee',
        ));
    }

    public function store(Request $request)
    {
        if (!$request->user()->canManageTeamAttendance()) {
            abort(403);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:tenant.employees,id'],
            'attendance_date' => ['required', 'date'],
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['required', Rule::in(['present', 'absent', 'leave', 'half_day'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = EmployeeAttendance::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->exists();
        if ($exists) {
            return redirect()->back()->withInput()->withErrors('Attendance for this employee on that date already exists.');
        }

        $row = EmployeeAttendance::create([
            'employee_id' => (int) $data['employee_id'],
            'user_id' => $request->user()->id,
            'attendance_date' => $data['attendance_date'],
            'clock_in' => $data['clock_in'] ?? null,
            'clock_out' => $data['clock_out'] ?? null,
            'status' => $data['status'],
            'source' => 'manual',
            'notes' => $data['notes'] ?? null,
        ]);

        ActivityLogger::log('attendance.manual_create', 'Attendance recorded (manual)', $row);

        return redirect()->route('employees.attendance.index', ['month' => substr($data['attendance_date'], 0, 7)])
            ->with('status', 'Attendance saved.');
    }

    public function update(Request $request, EmployeeAttendance $attendance)
    {
        if (!$request->user()->canManageTeamAttendance()) {
            abort(403);
        }

        $data = $request->validate([
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['required', Rule::in(['present', 'absent', 'leave', 'half_day'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance->update([
            'clock_in' => $data['clock_in'] ?? null,
            'clock_out' => $data['clock_out'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        ActivityLogger::log('attendance.updated', 'Attendance updated', $attendance);

        return redirect()->back()->with('status', 'Attendance updated.');
    }

    public function destroy(Request $request, EmployeeAttendance $attendance)
    {
        if (!$request->user()->canManageTeamAttendance()) {
            abort(403);
        }

        $month = $attendance->attendance_date?->format('Y-m');
        $id = $attendance->id;
        ActivityLogger::log('attendance.deleted', 'Attendance deleted', null, ['attendance_id' => $id]);
        $attendance->delete();

        return redirect()->route('employees.attendance.index', array_filter(['month' => $month]))
            ->with('status', 'Attendance removed.');
    }
}
