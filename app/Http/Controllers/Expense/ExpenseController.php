<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Services\AutoJournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly AutoJournalService $autoJournal
    ) {}

    public function index(Request $request)
    {
        $query = Expense::with(['employee', 'category'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->to);
        }

        $expenses   = $query->paginate(Setting::pageSize('expenses_per_page', 25))->withQueryString();
        $employees  = Employee::orderBy('name')->get(['id', 'name']);
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get(['id', 'name']);
        $statusMap  = Expense::statusLabel();

        // KPI counts for the header ribbon
        $kpis = [
            'draft'     => Expense::where('status', Expense::STATUS_DRAFT)->count(),
            'submitted' => Expense::where('status', Expense::STATUS_SUBMITTED)->count(),
            'approved'  => Expense::where('status', Expense::STATUS_APPROVED)->count(),
            'paid'      => Expense::where('status', Expense::STATUS_PAID)->count(),
        ];

        return view('expenses.index', compact('expenses', 'employees', 'categories', 'statusMap', 'kpis'));
    }

    public function create()
    {
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get();
        $employees  = Employee::orderBy('name')->get(['id', 'name']);
        $myEmployee = $this->currentEmployee();

        return view('expenses.create', compact('categories', 'employees', 'myEmployee'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $expense = new Expense($data);
        $expense->recalculate();

        if ($request->hasFile('receipt')) {
            $expense->receipt_path = $request->file('receipt')->store('receipts', 'public');
        }

        $expense->save();

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense saved as draft.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['employee', 'category', 'approvedBy']);
        $statusMap = Expense::statusLabel();
        return view('expenses.show', compact('expense', 'statusMap'));
    }

    public function edit(Expense $expense)
    {
        if (!in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED])) {
            return back()->with('error', 'Only Draft or Refused expenses can be edited.');
        }
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get();
        $employees  = Employee::orderBy('name')->get(['id', 'name']);
        return view('expenses.edit', compact('expense', 'categories', 'employees'));
    }

    public function update(Request $request, Expense $expense)
    {
        if (!in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED])) {
            return back()->with('error', 'Only Draft or Refused expenses can be edited.');
        }

        $data = $this->validated($request, $expense->id);
        $expense->fill($data);
        $expense->recalculate();

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $expense->receipt_path = $request->file('receipt')->store('receipts', 'public');
        }

        $expense->save();

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        if (!in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED])) {
            return back()->with('error', 'Only Draft or Refused expenses can be deleted.');
        }
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    // ---- Workflow actions ----

    public function submit(Expense $expense)
    {
        if ($expense->status !== Expense::STATUS_DRAFT) {
            return back()->with('error', 'Only draft expenses can be submitted.');
        }
        if (Setting::get('expenses_require_receipt_on_submit', '0') === '1' && empty($expense->receipt_path)) {
            return back()->with('error', 'Attach a receipt before submitting for approval.');
        }
        $expense->update(['status' => Expense::STATUS_SUBMITTED, 'submitted_at' => now()]);
        return back()->with('success', 'Expense submitted for approval.');
    }

    public function approve(Expense $expense)
    {
        $this->assertCanApproveExpenses();

        if ($expense->status !== Expense::STATUS_SUBMITTED) {
            return back()->with('error', 'Only submitted expenses can be approved.');
        }
        $expense->update([
            'status'      => Expense::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);
        return back()->with('success', 'Expense approved.');
    }

    public function refuse(Request $request, Expense $expense)
    {
        $this->assertCanApproveExpenses();

        $request->validate(['refuse_reason' => 'required|string|max:500']);
        if (!in_array($expense->status, [Expense::STATUS_SUBMITTED, Expense::STATUS_APPROVED])) {
            return back()->with('error', 'Expense cannot be refused at this stage.');
        }
        $expense->update([
            'status'        => Expense::STATUS_REFUSED,
            'refuse_reason' => $request->refuse_reason,
        ]);
        return back()->with('success', 'Expense refused.');
    }

    public function markPaid(Expense $expense)
    {
        $this->assertCanApproveExpenses();

        if ($expense->status !== Expense::STATUS_APPROVED) {
            return back()->with('error', 'Only approved expenses can be marked as paid.');
        }
        $expense->update(['status' => Expense::STATUS_PAID, 'paid_at' => now()]);
        $this->autoJournal->postExpensePaid($expense);

        return back()->with('success', 'Expense marked as paid.');
    }

    // ---- Helpers ----

    private function assertCanApproveExpenses(): void
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->bypassesModulePermissions() || in_array($user->role ?? '', ['admin'], true)),
            403,
            'Only admin can approve or pay expenses.'
        );
    }
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'employee_id'  => 'required|exists:tenant.employees,id',
            'category_id'  => 'nullable|exists:tenant.expense_categories,id',
            'description'  => 'required|string|max:255',
            'expense_date' => 'required|date',
            'qty'          => 'required|numeric|min:0.001',
            'unit_amount'  => 'required|numeric|min:0',
            'tax_percent'  => 'nullable|numeric|min:0|max:100',
            'notes'        => 'nullable|string',
            'receipt'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
    }

    private function currentEmployee(): ?Employee
    {
        return Auth::user()->employee ?? null;
    }
}
