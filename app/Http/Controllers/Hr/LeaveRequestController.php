<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('leave_requests')) {
            return redirect()->route('hr.index')
                ->withErrors('Leave module tables are not ready. Run migrate on server: php artisan migrate --force');
        }

        $status = trim((string) $request->query('status', ''));
        $employeeId = (int) $request->query('employee_id', 0);
        $month = trim((string) $request->query('month', ''));

        $query = LeaveRequest::query()
            ->with(['employee:id,name,employee_no', 'submittedBy:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
            });
        }

        if (! $this->canViewAllLeave($request)) {
            $linked = $this->linkedEmployee($request);
            if ($linked === null) {
                abort(403, 'No employee profile linked to your account.');
            }
            $query->where('employee_id', $linked->id);
        }

        $requests = $query->paginate(Setting::pageSize('hr_leave_per_page', 20))->withQueryString();

        $employees = $this->canViewAllLeave($request)
            ? Employee::orderBy('name')->get(['id', 'name', 'employee_no'])
            : collect();

        $statusLabels = LeaveRequest::statusLabels();
        $typeLabels = LeaveRequest::typeLabels();

        $kpis = [
            'pending' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count(),
            'approved' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => LeaveRequest::where('status', LeaveRequest::STATUS_REJECTED)->count(),
        ];

        return view('hr.leave.index', compact(
            'requests',
            'employees',
            'status',
            'employeeId',
            'month',
            'statusLabels',
            'typeLabels',
            'kpis',
        ));
    }

    public function create(Request $request)
    {
        $employees = collect();
        $myEmployee = $this->linkedEmployee($request);

        if ($this->canViewAllLeave($request)) {
            $employees = Employee::where('active', true)->orderBy('name')->get(['id', 'name', 'employee_no']);
        } elseif ($myEmployee === null) {
            return redirect()->route('hr.leave.index')
                ->withErrors('No active employee profile is linked to your account.');
        }

        $typeLabels = LeaveRequest::typeLabels();

        return view('hr.leave.create', compact('employees', 'myEmployee', 'typeLabels'));
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('leave_requests')) {
            return redirect()->route('hr.index')
                ->withErrors('Leave module tables are not ready. Run migrate on server.');
        }

        $canPickEmployee = $this->canViewAllLeave($request);
        $myEmployee = $this->linkedEmployee($request);

        $rules = [
            'leave_type' => ['required', 'string', Rule::in(array_keys(LeaveRequest::typeLabels()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];

        if ($canPickEmployee) {
            $rules['employee_id'] = ['required', 'integer', 'exists:tenant.employees,id'];
        }

        $data = $request->validate($rules);

        $employeeId = $canPickEmployee
            ? (int) $data['employee_id']
            : ($myEmployee?->id ?? 0);

        if ($employeeId <= 0) {
            throw ValidationException::withMessages([
                'employee_id' => ['Select an employee or link your account to an employee profile.'],
            ]);
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = LeaveRequest::countWeekdays($start, $end);

        $annualLimit = (int) Setting::get('hr_annual_leave_days', 14);
        if ($data['leave_type'] === LeaveRequest::TYPE_ANNUAL && $annualLimit > 0) {
            $used = $this->approvedAnnualDaysForEmployee($employeeId, (int) $start->year);
            if (($used + $days) > $annualLimit) {
                throw ValidationException::withMessages([
                    'start_date' => ["Annual leave limit exceeded. Used {$used} of {$annualLimit} days this year."],
                ]);
            }
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employeeId,
            'user_id' => $request->user()->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        ActivityLogger::log('hr.leave.requested', 'Leave request submitted', $leave, [
            'employee_id' => $employeeId,
            'days' => $days,
        ]);

        return redirect()->route('hr.leave.show', $leave)->with('status', 'Leave request submitted.');
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $this->authorizeLeaveAccess($leaveRequest);

        $leaveRequest->load(['employee.department', 'employee.designation', 'submittedBy:id,name', 'reviewer:id,name']);

        $statusLabels = LeaveRequest::statusLabels();
        $typeLabels = LeaveRequest::typeLabels();

        return view('hr.leave.show', compact('leaveRequest', 'statusLabels', 'typeLabels'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->ensureCanReview($request);

        if (! $leaveRequest->isPending()) {
            return back()->withErrors('Only pending requests can be approved.');
        }

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        $this->syncApprovedLeaveToAttendance($leaveRequest);

        ActivityLogger::log('hr.leave.approved', 'Leave request approved', $leaveRequest);

        return redirect()->route('hr.leave.show', $leaveRequest)->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->ensureCanReview($request);

        if (! $leaveRequest->isPending()) {
            return back()->withErrors('Only pending requests can be rejected.');
        }

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        ActivityLogger::log('hr.leave.rejected', 'Leave request rejected', $leaveRequest);

        return redirect()->route('hr.leave.show', $leaveRequest)->with('status', 'Leave request rejected.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        if (! $leaveRequest->isPending()) {
            return back()->withErrors('Only pending requests can be cancelled.');
        }

        $user = $request->user();
        $isOwner = (int) $leaveRequest->user_id === (int) $user->id;
        $canManage = $user->moduleAllows('hr', 'delete') || $user->moduleAllows('hr', 'edit');

        if (! $isOwner && ! $canManage && ! $user->bypassesModulePermissions()) {
            abort(403);
        }

        $leaveRequest->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        ActivityLogger::log('hr.leave.cancelled', 'Leave request cancelled', $leaveRequest);

        return redirect()->route('hr.leave.index')->with('status', 'Leave request cancelled.');
    }

    private function canViewAllLeave(Request $request): bool
    {
        $user = $request->user();
        if ($user?->bypassesModulePermissions()) {
            return true;
        }

        return $user?->moduleAllows('hr', 'edit') ?? false;
    }

    private function linkedEmployee(Request $request): ?Employee
    {
        return Employee::query()
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->first();
    }

    private function authorizeLeaveAccess(LeaveRequest $leaveRequest): void
    {
        if ($this->canViewAllLeave(request())) {
            return;
        }

        $linked = $this->linkedEmployee(request());
        if ($linked !== null && (int) $leaveRequest->employee_id === (int) $linked->id) {
            return;
        }

        abort(403);
    }

    private function ensureCanReview(Request $request): void
    {
        $user = $request->user();
        if ($user?->bypassesModulePermissions() || $user?->moduleAllows('hr', 'edit')) {
            return;
        }

        abort(403, 'You do not have permission to approve or reject leave.');
    }

    private function approvedAnnualDaysForEmployee(int $employeeId, int $year): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type', LeaveRequest::TYPE_ANNUAL)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    private function syncApprovedLeaveToAttendance(LeaveRequest $leaveRequest): void
    {
        $start = Carbon::parse($leaveRequest->start_date)->startOfDay();
        $end = Carbon::parse($leaveRequest->end_date)->startOfDay();

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            EmployeeAttendance::query()->updateOrCreate(
                [
                    'employee_id' => $leaveRequest->employee_id,
                    'attendance_date' => $date->toDateString(),
                ],
                [
                    'user_id' => $leaveRequest->user_id,
                    'status' => 'leave',
                    'source' => 'manual',
                    'notes' => 'Approved leave #'.$leaveRequest->id,
                ],
            );
        }
    }
}
