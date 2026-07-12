<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollEntry;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Services\AutoJournalService;
use App\Services\PayrollSalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct(
        private readonly AutoJournalService $autoJournal,
        private readonly PayrollSalaryService $payrollSalary,
    ) {}

    public function index(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $this->payrollSalary->syncPayrollPeriod($period, $request->user()?->id, true);

        $entries = PayrollEntry::query()
            ->with(['employee:id,name,employee_no,salary,designation_id', 'employee.designation:id,name'])
            ->where('period', $period)
            ->orderBy('employee_id')
            ->paginate(Setting::pageSize('employees_per_page', 30))
            ->withQueryString();

        $totalNet = (float) PayrollEntry::query()->where('period', $period)->sum('net_pay');
        $paidNet = (float) PayrollEntry::query()->where('period', $period)->where('status', 'paid')->sum('net_pay');

        return view('employees.payroll-index', compact('entries', 'period', 'totalNet', 'paidNet'));
    }

    public function generate(Request $request)
    {
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
        $period = $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $rows = $this->payrollSalary->salaryRowsForPeriod($period, true);
        $periodLabel = $this->payrollSalary->periodLabel($period);
        $companyName = config('app.name');

        return view('employees.payroll-print', compact('rows', 'period', 'periodLabel', 'companyName'));
    }

    public function update(Request $request, PayrollEntry $payrollEntry)
    {
        if ($payrollEntry->status === 'paid') {
            return redirect()->back()->withErrors('Paid payroll cannot be edited.');
        }

        $data = $request->validate([
            'bonus' => ['required', 'numeric', 'min:0'],
            'deduction' => ['required', 'numeric', 'min:0'],
            'food_bill' => ['required', 'numeric', 'min:0'],
            'loan' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payrollEntry->bonus = $data['bonus'];
        $payrollEntry->deduction = $data['deduction'];
        $payrollEntry->food_bill = $data['food_bill'];
        $payrollEntry->loan = $data['loan'];
        $payrollEntry->notes = $data['notes'] ?? null;
        $payrollEntry->recalculateNet();
        $payrollEntry->save();

        ActivityLogger::log('payroll.updated', 'Payroll entry updated', $payrollEntry);

        return redirect()->back()->with('status', 'Payroll updated.');
    }

    public function markPaid(PayrollEntry $payrollEntry)
    {
        if ($payrollEntry->status === 'paid') {
            return redirect()->back()->withErrors('Already marked paid.');
        }

        $payrollEntry->status = 'paid';
        $payrollEntry->paid_at = now();
        $payrollEntry->save();

        $this->autoJournal->postPayrollPaid($payrollEntry);

        ActivityLogger::log('payroll.paid', 'Payroll marked paid', $payrollEntry);

        return redirect()->back()->with('status', 'Marked as paid.');
    }
}
