<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLine;
use App\Models\Setting;
use App\Services\AutoJournalService;
use App\Support\EnsuresExpenseLinesSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    use EnsuresExpenseLinesSchema;

    public function __construct(
        private readonly AutoJournalService $autoJournal
    ) {}

    public function index(Request $request)
    {
        $this->ensureExpenseLinesSchema();

        $query = Expense::with(['category'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

        $expenses = $query->paginate(Setting::pageSize('expenses_per_page', 25))->withQueryString();
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get(['id', 'name']);
        $statusMap = Expense::statusLabel();

        $kpis = [
            'draft' => Expense::where('status', Expense::STATUS_DRAFT)->count(),
            'submitted' => Expense::where('status', Expense::STATUS_SUBMITTED)->count(),
            'approved' => Expense::where('status', Expense::STATUS_APPROVED)->count(),
            'paid' => Expense::where('status', Expense::STATUS_PAID)->count(),
        ];

        return view('expenses.index', compact('expenses', 'categories', 'statusMap', 'kpis'));
    }

    public function create()
    {
        $this->ensureExpenseLinesSchema();
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get();

        return view('expenses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->ensureExpenseLinesSchema();

        $employee = $this->currentEmployee();
        if (! $employee) {
            return back()->withInput()->with('error', 'Your user account is not linked to an employee. Contact admin.');
        }

        $data = $this->validatedHeader($request);
        $lines = $this->validatedLines($request);

        $expense = DB::connection('tenant')->transaction(function () use ($request, $data, $lines, $employee) {
            $expense = new Expense($data);
            $expense->employee_id = $employee->id;
            $expense->status = Expense::STATUS_SUBMITTED;
            $expense->submitted_at = now();
            $expense->qty = 1;
            $expense->unit_amount = 0;
            $expense->tax_percent = 0;
            $expense->tax_amount = 0;
            $expense->total_amount = 0;
            $expense->grand_total = 0;

            if ($request->hasFile('receipt')) {
                $expense->receipt_path = $request->file('receipt')->store('receipts', 'public');
            }

            $expense->save();
            $this->syncLines($expense, $lines);
            $expense->load('lines');
            $expense->recalculateFromLines();
            $expense->save();

            return $expense;
        });

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense sent for approval.');
    }

    public function show(Expense $expense)
    {
        $this->ensureExpenseLinesSchema();
        $expense->load(['category', 'approvedBy', 'lines']);
        $statusMap = Expense::statusLabel();

        return view('expenses.show', compact('expense', 'statusMap'));
    }

    public function print(Expense $expense)
    {
        $this->ensureExpenseLinesSchema();
        $expense->load(['category', 'approvedBy', 'employee:id,name,employee_no', 'lines']);
        $statusMap = Expense::statusLabel();
        $companyName = (string) Setting::get('company_name', config('app.name'));
        $companyLogo = company_logo_url(Setting::get('company_logo'));
        $currency = (string) Setting::get('currency_symbol', 'Rs.');

        return view('expenses.print', compact(
            'expense',
            'statusMap',
            'companyName',
            'companyLogo',
            'currency'
        ));
    }

    public function edit(Expense $expense)
    {
        $this->ensureExpenseLinesSchema();

        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED])) {
            return back()->with('error', 'Only Draft or Refused expenses can be edited.');
        }
        $expense->load('lines');
        $categories = ExpenseCategory::where('active', true)->orderBy('name')->get();

        return view('expenses.edit', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        $this->ensureExpenseLinesSchema();

        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED])) {
            return back()->with('error', 'Only Draft or Refused expenses can be edited.');
        }

        $data = $this->validatedHeader($request);
        $lines = $this->validatedLines($request);

        DB::connection('tenant')->transaction(function () use ($request, $expense, $data, $lines) {
            $expense->fill($data);

            if ($request->hasFile('receipt')) {
                if ($expense->receipt_path) {
                    Storage::disk('public')->delete($expense->receipt_path);
                }
                $expense->receipt_path = $request->file('receipt')->store('receipts', 'public');
            }

            $expense->save();
            $this->syncLines($expense, $lines);
            $expense->load('lines');
            $expense->recalculateFromLines();
            $expense->save();
        });

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $user = Auth::user();
        $isAdmin = $user && ($user->bypassesModulePermissions() || in_array($user->role ?? '', ['admin'], true));
        $isDraftOrRefused = in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED], true);

        if (! $isDraftOrRefused && ! $isAdmin) {
            return back()->with('error', 'Only Draft or Refused expenses can be deleted. Paid/approved expenses sirf admin delete kar sakta hai.');
        }

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        if ($expense->status === Expense::STATUS_PAID) {
            \App\Models\JournalEntry::query()
                ->where('source', 'expense')
                ->where('source_id', $expense->id)
                ->each(function (\App\Models\JournalEntry $entry) {
                    $entry->lines()->delete();
                    $entry->delete();
                });
        }

        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    public function submit(Expense $expense)
    {
        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED], true)) {
            return back()->with('error', 'Only draft or refused expenses can be sent for approval.');
        }
        if (Setting::get('expenses_require_receipt_on_submit', '0') === '1' && empty($expense->receipt_path)) {
            return back()->with('error', 'Attach a receipt before submitting for approval.');
        }
        $expense->update([
            'status' => Expense::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'refuse_reason' => null,
        ]);

        return back()->with('success', 'Expense sent for approval.');
    }

    public function approve(Expense $expense)
    {
        $this->assertCanManageExpenses();

        if ($expense->status !== Expense::STATUS_SUBMITTED) {
            return back()->with('error', 'Only submitted expenses can be approved.');
        }
        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Expense approved.');
    }

    public function refuse(Request $request, Expense $expense)
    {
        $this->assertCanManageExpenses();

        $request->validate(['refuse_reason' => 'required|string|max:500']);
        if (! in_array($expense->status, [Expense::STATUS_SUBMITTED, Expense::STATUS_APPROVED])) {
            return back()->with('error', 'Expense cannot be refused at this stage.');
        }
        $expense->update([
            'status' => Expense::STATUS_REFUSED,
            'refuse_reason' => $request->refuse_reason,
        ]);

        return back()->with('success', 'Expense refused.');
    }

    public function markPaid(Expense $expense)
    {
        $this->assertCanManageExpenses();

        if (! in_array($expense->status, [Expense::STATUS_SUBMITTED, Expense::STATUS_APPROVED], true)) {
            return back()->with('error', 'Only expenses sent for approval can be marked as paid.');
        }

        $payload = [
            'status' => Expense::STATUS_PAID,
            'paid_at' => now(),
        ];
        if (empty($expense->approved_at)) {
            $payload['approved_at'] = now();
            $payload['approved_by'] = Auth::id();
        }

        $expense->update($payload);
        $this->autoJournal->postExpensePaid($expense);

        return back()->with('success', 'Expense marked as paid.');
    }

    private function assertCanManageExpenses(): void
    {
        $user = Auth::user();
        abort_unless(
            $user && $user->canManageExpenses(),
            403,
            'Only manager or admin can mark expenses as paid.'
        );
    }

    private function validatedHeader(Request $request): array
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:tenant.expense_categories,id',
            'description' => 'required|string|max:255',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.qty' => 'required|numeric|min:0.001',
            'lines.*.unit_amount' => 'required|numeric|min:0',
            'lines.*.tax_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.line_total' => 'nullable|numeric|min:0',
        ]);

        return [
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'],
            'expense_date' => $validated['expense_date'],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @return list<array{description: string, qty: float, unit_amount: float, tax_percent: float}>
     */
    private function validatedLines(Request $request): array
    {
        $raw = $request->input('lines', []);
        $out = [];
        foreach ($raw as $row) {
            $qty = (float) ($row['qty'] ?? 0);
            $unit = (float) ($row['unit_amount'] ?? 0);
            $taxPct = (float) ($row['tax_percent'] ?? 0);
            $desc = trim((string) ($row['description'] ?? ''));
            if ($desc === '' || $qty <= 0) {
                continue;
            }
            $out[] = [
                'description' => $desc,
                'qty' => $qty,
                'unit_amount' => $unit,
                'tax_percent' => $taxPct,
            ];
        }

        if ($out === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => 'Kam az kam aik line add karein.',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<array{description: string, qty: float, unit_amount: float, tax_percent: float}>  $lines
     */
    private function syncLines(Expense $expense, array $lines): void
    {
        ExpenseLine::query()->where('expense_id', $expense->id)->delete();

        foreach ($lines as $i => $row) {
            $line = new ExpenseLine([
                'company_id' => $expense->company_id,
                'expense_id' => $expense->id,
                'description' => $row['description'],
                'qty' => $row['qty'],
                'unit_amount' => $row['unit_amount'],
                'tax_percent' => $row['tax_percent'],
                'sort_order' => $i,
            ]);
            $line->recalculate();
            $line->save();
        }
    }

    private function currentEmployee(): ?Employee
    {
        return Auth::user()->employee ?? null;
    }
}
