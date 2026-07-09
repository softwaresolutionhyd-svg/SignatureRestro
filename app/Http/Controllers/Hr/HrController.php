<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\LeaveRequest;
use App\Models\PayrollEntry;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    public function index()
    {
        $currency = Setting::get('currency_symbol', 'Rs.');
        $today = now()->toDateString();
        $period = now()->format('Y-m');

        $presentToday = EmployeeAttendance::query()
            ->whereDate('attendance_date', $today)
            ->where('status', 'present')
            ->count();

        $pendingLeave = 0;
        $recentLeave = collect();

        if (Schema::hasTable('leave_requests')) {
            $pendingLeave = LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count();
            $recentLeave = LeaveRequest::with(['employee:id,name,employee_no'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();
        }

        $kpis = [
            'employees' => Employee::where('active', true)->count(),
            'present_today' => $presentToday,
            'pending_leave' => $pendingLeave,
            'payroll_draft' => PayrollEntry::where('period', $period)
                ->where('status', 'draft')
                ->count(),
        ];

        $recentEmployees = Employee::with(['department:id,name', 'designation:id,name'])
            ->where('active', true)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'employee_no', 'name', 'department_id', 'designation_id', 'join_date']);

        return view('hr.index', compact('currency', 'kpis', 'recentLeave', 'recentEmployees'));
    }
}
