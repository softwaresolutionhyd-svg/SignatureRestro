<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollEntry;
use App\Models\Setting;
use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncPayrollQueueService;
use App\Support\ActivityLogger;
use App\Support\EnsuresEmployeeAdvanceSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeAdvanceController extends Controller
{
    use EnsuresEmployeeAdvanceSchema;

    public function __construct(
        private readonly CloudSyncService $cloudSync,
        private readonly SyncPayrollQueueService $syncPayrollQueue,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $status = $request->query('status', 'active');
        $employeeNo = trim((string) $request->query('employee_no', ''));

        $advances = EmployeeAdvance::query()
            ->with(['employee:id,name,employee_no'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($employeeNo !== '', fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->matchingSearch($employeeNo)
            ))
            ->join('employees', 'employee_advances.employee_id', '=', 'employees.id')
            ->orderBy('employees.employee_no')
            ->select('employee_advances.*')
            ->paginate(Setting::pageSize('employees_per_page', 30))
            ->withQueryString();

        return view('employees.advances-index', compact('advances', 'status', 'employeeNo'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $employees = Employee::query()
            ->excludeAdminAccounts()
            ->where('active', true)
            ->orderBy('employee_no')
            ->get(['id', 'name', 'employee_no']);

        return view('employees.advances-form', [
            'advance' => new EmployeeAdvance(['start_date' => now()->toDateString(), 'status' => 'active']),
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $data = $request->validate([
            'employee_id' => ['required', 'exists:tenant.employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasActive = EmployeeAdvance::query()
            ->where('employee_id', $data['employee_id'])
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->exists();

        if ($hasActive) {
            return back()->withInput()->withErrors('Is employee ka pehle se active advance hai.');
        }

        $advance = EmployeeAdvance::create([
            ...$data,
            'balance' => $data['amount'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        $this->refreshDraftPayrollForEmployee((int) $advance->employee_id, $this->periodForAdvance($advance));

        ActivityLogger::log('employee_advance.created', 'Employee advance created', $advance);

        return redirect()->route('employees.advances.index')->with('status', 'Advance record created.');
    }

    public function edit(EmployeeAdvance $advance)
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $advance->load(['employee:id,name,employee_no', 'settledPayrollEntry:id,period,paid_at']);

        return view('employees.advances-form', [
            'advance' => $advance,
            'employees' => collect(),
        ]);
    }

    public function update(Request $request, EmployeeAdvance $advance)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $isSettled = $advance->status === 'settled' || (float) $advance->balance <= 0;

        $data = $request->validate([
            'amount' => $isSettled ? ['prohibited'] : ['required', 'numeric', 'min:0.01'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'settled', 'cancelled'])],
        ]);

        if (! $isSettled) {
            $advance->amount = $data['amount'];
            $advance->balance = $data['status'] === 'active' ? $data['amount'] : 0;
        }

        $advance->start_date = $data['start_date'] ?? null;
        $advance->notes = $data['notes'] ?? null;
        $advance->status = $data['status'];
        if ($advance->status !== 'active') {
            $advance->balance = 0;
        }
        if ($advance->status === 'settled' && $advance->settled_at === null) {
            $advance->settled_at = now();
        }
        $advance->save();

        $this->refreshDraftPayrollForEmployee((int) $advance->employee_id, $this->periodForAdvance($advance));

        ActivityLogger::log('employee_advance.updated', 'Employee advance updated', $advance);

        return redirect()->route('employees.advances.index')->with('status', 'Advance updated.');
    }

    public function destroy(EmployeeAdvance $advance)
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeAdvanceSchema();

        $employeeId = (int) $advance->employee_id;
        $period = $this->periodForAdvance($advance);
        $advance->delete();

        $drafts = PayrollEntry::query()
            ->where('employee_id', $employeeId)
            ->where('status', '!=', 'paid')
            ->where('advance', '>', 0)
            ->get();

        foreach ($drafts as $entry) {
            $entry->advance = 0;
            $entry->recalculateNet();
            $entry->save();
        }

        if ($this->cloudSync->isLocalRole()) {
            $this->syncPayrollQueue->queuePayrollData($period);
        }

        ActivityLogger::log('employee_advance.deleted', 'Employee advance deleted', null, [
            'employee_id' => $employeeId,
        ]);

        return redirect()->route('employees.advances.index')->with('status', 'Advance entry delete ho gayi.');
    }

    private function periodForAdvance(EmployeeAdvance $advance): string
    {
        return ($advance->start_date ?? $advance->created_at ?? now())->format('Y-m');
    }

    private function refreshDraftPayrollForEmployee(int $employeeId, string $period): void
    {
        $entry = PayrollEntry::query()
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->where('status', 'draft')
            ->first();

        if ($entry === null) {
            return;
        }

        $entry->loadMissing('employee');
        if ($entry->employee === null) {
            return;
        }

        app(\App\Services\PayrollSalaryService::class)->syncPayrollEntryForEmployee($entry->employee, $period, auth()->id());

        if ($this->cloudSync->isLocalRole()) {
            $this->syncPayrollQueue->queuePayrollData($period);
        }
    }
}
