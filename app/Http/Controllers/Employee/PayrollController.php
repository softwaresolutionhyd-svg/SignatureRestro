<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollEntry;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\EnsuresPayrollSchema;
use App\Services\AutoJournalService;
use App\Services\EmployeeLoanService;
use App\Services\PayrollFoodBillSettlementService;
use App\Services\PayrollSalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    use EnsuresPayrollSchema;

    public function __construct(
        private readonly AutoJournalService $autoJournal,
        private readonly PayrollSalaryService $payrollSalary,
        private readonly PayrollFoodBillSettlementService $foodBillSettlement,
        private readonly EmployeeLoanService $loanService,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensurePayrollSchema();

        $period = $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }
        $employeeNo = trim((string) $request->query('employee_no', ''));

        $this->payrollSalary->syncPayrollPeriod($period, $request->user()?->id, true);
        $this->foodBillSettlement->syncUnsettledForPeriod($period, $request->user()?->id);

        $entries = PayrollEntry::query()
            ->with(['employee:id,name,employee_no,salary,designation_id', 'employee.designation:id,name'])
            ->join('employees', 'payroll_entries.employee_id', '=', 'employees.id')
            ->where('payroll_entries.period', $period)
            ->when($employeeNo !== '', fn ($q) => $q->where('employees.employee_no', 'like', '%'.$employeeNo.'%'))
            ->orderBy('employees.employee_no')
            ->select('payroll_entries.*')
            ->paginate(Setting::pageSize('employees_per_page', 30))
            ->withQueryString();

        $totalNet = (float) PayrollEntry::query()->where('period', $period)->sum('net_pay');
        $paidNet = (float) PayrollEntry::query()->where('period', $period)->where('status', 'paid')->sum('net_pay');

        return view('employees.payroll-index', compact('entries', 'period', 'employeeNo', 'totalNet', 'paidNet'));
    }

    public function generate(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensurePayrollSchema();

        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $period = $data['period'];

        $before = PayrollEntry::query()->where('period', $period)->count();
        $this->payrollSalary->syncPayrollPeriod($period, $request->user()->id, true);
        $after = PayrollEntry::query()->where('period', $period)->count();
        $created = max(0, $after - $before);

        ActivityLogger::log('payroll.generated', 'Payroll draft rows generated', null, [
            'period' => $period,
            'created' => $created,
        ]);

        return redirect()->route('employees.payroll.index', ['period' => $period])
            ->with('status', $created > 0 ? "{$created} payroll row(s) created." : 'Payroll rows updated / already exist for active employees.');
    }

    public function printSalaryRecord(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensurePayrollSchema();

        $period = $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }
        $employeeNo = trim((string) $request->query('employee_no', ''));

        $rows = $this->payrollSalary->salaryRowsForPeriod($period, true);
        if ($employeeNo !== '') {
            $needle = mb_strtolower($employeeNo, 'UTF-8');
            $rows = array_values(array_filter($rows, fn ($row) => str_contains(mb_strtolower((string) ($row['employee_no'] ?? ''), 'UTF-8'), $needle)));
        }
        $periodLabel = $this->payrollSalary->periodLabel($period);
        $companyName = config('app.name');

        return view('employees.payroll-print', compact('rows', 'period', 'periodLabel', 'companyName', 'employeeNo'));
    }

    public function printSlip(Request $request, PayrollEntry $payrollEntry)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensurePayrollSchema();

        $payrollEntry->load(['employee.designation:id,name']);
        $employee = $payrollEntry->employee;
        if ($employee === null) {
            abort(404);
        }

        $period = $payrollEntry->period;
        $row = $this->payrollSalary->rowFromEntry($employee, $payrollEntry, $period);
        $periodLabel = $this->payrollSalary->periodLabel($period);
        $companyName = config('app.name');

        return view('employees.payroll-slip', compact('row', 'period', 'periodLabel', 'companyName'));
    }

    public function update(Request $request, PayrollEntry $payrollEntry)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        if ($payrollEntry->status === 'paid') {
            return redirect()->back()->withErrors('Paid payroll cannot be edited.');
        }

        $data = $request->validate([
            'bonus' => ['required', 'numeric', 'min:0'],
            'deduction' => ['required', 'numeric', 'min:0'],
            'food_bill' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payrollEntry->bonus = $data['bonus'];
        $payrollEntry->deduction = $data['deduction'];
        $payrollEntry->food_bill = $data['food_bill'];
        $payrollEntry->loadMissing('employee');
        if ($payrollEntry->employee) {
            $this->loanService->syncLoanDeductionForPayroll($payrollEntry, $payrollEntry->employee, $payrollEntry->period);
        }
        $payrollEntry->notes = $data['notes'] ?? null;
        $payrollEntry->recalculateNet();
        $payrollEntry->save();

        $this->foodBillSettlement->settle($payrollEntry, $request->user()->id);

        ActivityLogger::log('payroll.updated', 'Payroll entry updated', $payrollEntry);

        return redirect()->back()->with('status', 'Payroll updated.');
    }

    public function markPaid(PayrollEntry $payrollEntry)
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        if ($payrollEntry->status === 'paid') {
            return redirect()->back()->withErrors('Already marked paid.');
        }

        $payrollEntry->status = 'paid';
        $payrollEntry->paid_at = now();
        $payrollEntry->save();

        $this->foodBillSettlement->settle($payrollEntry, auth()->id());
        $this->loanService->recordPaymentOnPaid($payrollEntry, auth()->id());
        $this->autoJournal->postPayrollPaid($payrollEntry);

        ActivityLogger::log('payroll.paid', 'Payroll marked paid', $payrollEntry);

        return redirect()->back()->with('status', 'Marked as paid.');
    }
}
