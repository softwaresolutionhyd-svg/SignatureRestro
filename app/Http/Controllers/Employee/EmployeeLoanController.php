<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\EnsuresEmployeeLoanSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeLoanController extends Controller
{
    use EnsuresEmployeeLoanSchema;

    public function index(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        $status = $request->query('status', 'active');
        $employeeNo = trim((string) $request->query('employee_no', ''));

        $loans = EmployeeLoan::query()
            ->with(['employee:id,name,employee_no'])
            ->withCount('payments')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($employeeNo !== '', fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->where('employee_no', 'like', '%'.$employeeNo.'%')
            ))
            ->join('employees', 'employee_loans.employee_id', '=', 'employees.id')
            ->orderBy('employees.employee_no')
            ->select('employee_loans.*')
            ->paginate(Setting::pageSize('employees_per_page', 30))
            ->withQueryString();

        return view('employees.loans-index', compact('loans', 'status', 'employeeNo'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        $employees = Employee::query()
            ->where('active', true)
            ->orderBy('employee_no')
            ->get(['id', 'name', 'employee_no']);

        return view('employees.loans-form', [
            'loan' => new EmployeeLoan(['start_date' => now()->toDateString(), 'status' => 'active']),
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        $data = $request->validate([
            'employee_id' => ['required', 'exists:tenant.employees,id'],
            'loan_amount' => ['required', 'numeric', 'min:0.01'],
            'monthly_installment' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['monthly_installment'] > $data['loan_amount']) {
            return back()->withInput()->withErrors('Monthly installment loan amount se zyada nahi ho sakti.');
        }

        $hasActive = EmployeeLoan::query()
            ->where('employee_id', $data['employee_id'])
            ->where('status', 'active')
            ->exists();

        if ($hasActive) {
            return back()->withInput()->withErrors('Is employee ka pehle se active loan hai.');
        }

        $loan = EmployeeLoan::create([
            ...$data,
            'balance' => $data['loan_amount'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        ActivityLogger::log('employee_loan.created', 'Employee loan created', $loan);

        return redirect()->route('employees.loans.index')->with('status', 'Loan record created.');
    }

    public function edit(EmployeeLoan $loan)
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        $loan->load(['employee:id,name,employee_no', 'payments' => fn ($q) => $q->orderByDesc('period')]);

        return view('employees.loans-form', [
            'loan' => $loan,
            'employees' => collect(),
        ]);
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        abort_unless($request->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        $hasPayments = $loan->payments()->exists();

        $data = $request->validate([
            'monthly_installment' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'loan_amount' => $hasPayments ? ['prohibited'] : ['required', 'numeric', 'min:0.01'],
        ]);

        if (! $hasPayments) {
            if ($data['monthly_installment'] > $data['loan_amount']) {
                return back()->withInput()->withErrors('Monthly installment loan amount se zyada nahi ho sakti.');
            }
            $loan->loan_amount = $data['loan_amount'];
            $loan->balance = $data['loan_amount'];
        } elseif ($data['monthly_installment'] > (float) $loan->balance && $loan->status === 'active') {
            return back()->withInput()->withErrors('Installment remaining balance se zyada nahi ho sakti.');
        }

        $loan->monthly_installment = $data['monthly_installment'];
        $loan->start_date = $data['start_date'] ?? null;
        $loan->notes = $data['notes'] ?? null;
        $loan->status = $data['status'];
        if ($loan->status === 'completed' || (float) $loan->balance <= 0) {
            $loan->balance = max(0, (float) $loan->balance);
            if ((float) $loan->balance <= 0) {
                $loan->status = 'completed';
            }
        }
        $loan->save();

        ActivityLogger::log('employee_loan.updated', 'Employee loan updated', $loan);

        return redirect()->route('employees.loans.index')->with('status', 'Loan updated.');
    }

    public function destroy(EmployeeLoan $loan)
    {
        abort_unless(auth()->user()?->canManagePayroll(), 403);
        $this->ensureEmployeeLoanSchema();

        if ($loan->payments()->exists()) {
            return back()->withErrors('Loan delete nahi ho sakta — payment history maujood hai.');
        }

        $loan->delete();

        return redirect()->route('employees.loans.index')->with('status', 'Loan deleted.');
    }
}
