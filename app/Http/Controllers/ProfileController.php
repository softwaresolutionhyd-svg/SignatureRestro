<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Support\AppPasswordRules;
use App\Support\LoginUsername;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $employee = Employee::query()
            ->with(['department:id,name', 'designation:id,name'])
            ->where('user_id', $user->id)
            ->first();

        $month = now()->format('Y-m');
        $monthLabel = now()->format('F Y');
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $attendanceRows = collect();
        $attendanceStats = [
            'present' => 0,
            'absent' => 0,
            'leave' => 0,
            'half_day' => 0,
        ];
        $leaveRows = collect();
        $leaveStats = [
            'pending' => 0,
            'approved' => 0,
            'days' => 0,
        ];

        if ($employee !== null) {
            $attendanceRows = EmployeeAttendance::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$start, $end])
                ->orderByDesc('attendance_date')
                ->get();

            foreach ($attendanceRows as $row) {
                $key = $row->status;
                if (isset($attendanceStats[$key])) {
                    $attendanceStats[$key]++;
                }
            }

            if (Schema::hasTable('leave_requests')) {
                $leaveRows = LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('start_date', [$start, $end])
                            ->orWhereBetween('end_date', [$start, $end])
                            ->orWhere(function ($inner) use ($start, $end) {
                                $inner->where('start_date', '<=', $start)
                                    ->where('end_date', '>=', $end);
                            });
                    })
                    ->orderByDesc('start_date')
                    ->limit(10)
                    ->get();

                $leaveStats['pending'] = $leaveRows->where('status', LeaveRequest::STATUS_PENDING)->count();
                $leaveStats['approved'] = $leaveRows->where('status', LeaveRequest::STATUS_APPROVED)->count();
                $leaveStats['days'] = (int) $leaveRows
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->sum('days');
            }
        }

        $loginLogs = collect();
        if (Schema::hasTable('activity_logs')) {
            $loginLogs = ActivityLog::query()
                ->where('user_id', $user->id)
                ->whereIn('action', ['auth.login', 'auth.logout'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'action', 'description', 'ip_address', 'created_at']);
        }

        $companyName = Setting::get('company_name', config('app.name'));
        $companyLogo = Setting::get('company_logo', '');

        return view('profile.edit', [
            'user' => $user,
            'mustChangePassword' => (bool) ($user->must_change_password ?? false),
            'employee' => $employee,
            'month' => $month,
            'monthLabel' => $monthLabel,
            'attendanceRows' => $attendanceRows,
            'attendanceStats' => $attendanceStats,
            'leaveRows' => $leaveRows,
            'leaveStats' => $leaveStats,
            'leaveStatusLabels' => LeaveRequest::statusLabels(),
            'leaveTypeLabels' => LeaveRequest::typeLabels(),
            'loginLogs' => $loginLogs,
            'companyName' => $companyName,
            'companyLogo' => $companyLogo,
            'username' => LoginUsername::display($user->email),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $mustChange = (bool) ($user->must_change_password ?? false);

        $rules = [
            'name' => ['required', 'string', 'max:150'],
        ];

        if ($mustChange) {
            $rules['password'] = AppPasswordRules::requiredConfirmed();
        } elseif ($request->filled('password')) {
            $rules['current_password'] = ['required', 'current_password:web'];
            $rules['password'] = AppPasswordRules::requiredConfirmed();
        }

        $data = $request->validate($rules);

        $user->name = $data['name'];

        if (! empty($data['password'] ?? null)) {
            $user->password = $data['password'];
            $user->must_change_password = false;
        }

        $user->save();

        $message = $mustChange
            ? 'Naya password set ho gaya. Ab aap software use kar sakte hain.'
            : 'Profile updated.';

        return redirect()->route('profile.edit')->with('status', $message);
    }
}
