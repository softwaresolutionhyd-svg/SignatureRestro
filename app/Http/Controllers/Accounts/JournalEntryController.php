<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Setting;
use App\Services\DefaultChartOfAccounts;
use App\Services\Sync\SyncAwareDelete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly DefaultChartOfAccounts $defaultChart
    ) {}

    public function index(Request $request): View
    {
        $this->defaultChart->ensureForCompany(current_company_id());

        $query = JournalEntry::query()
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(fn ($w) => $w
                ->where('entry_number', 'like', $s)
                ->orWhere('reference', 'like', $s)
                ->orWhere('description', 'like', $s));
        }

        $entries = $query->paginate(Setting::pageSize('accounts_journal_per_page', 25))->withQueryString();
        $statusMap = JournalEntry::statusLabel();
        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('accounts.journal-entries.index', compact('entries', 'statusMap', 'currency'));
    }

    public function create(): View
    {
        $this->defaultChart->ensureForCompany(current_company_id());

        $accounts = Account::where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']);
        $entryNumber = $this->nextEntryNumber();
        $typeLabels = Account::typeLabels();

        return view('accounts.journal-entries.create', compact('accounts', 'entryNumber', 'typeLabels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedEntry($request);
        $lines = $this->validatedLines($request);

        $entry = DB::connection('tenant')->transaction(function () use ($data, $lines) {
            $entry = new JournalEntry($data);
            $entry->entry_number = $this->nextEntryNumber();
            $entry->status = JournalEntry::STATUS_DRAFT;
            $entry->source = 'manual';
            $entry->save();

            $this->syncLines($entry, $lines);
            $entry->recalculateTotals();
            $entry->save();

            return $entry;
        });

        return redirect()->route('accounts.journal-entries.show', $entry)
            ->with('success', 'Journal entry saved as draft.');
    }

    public function show(JournalEntry $journalEntry): View
    {
        $entry = $journalEntry->load(['lines.account', 'postedByUser']);
        $statusMap = JournalEntry::statusLabel();
        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('accounts.journal-entries.show', compact('entry', 'statusMap', 'currency'));
    }

    public function edit(JournalEntry $journalEntry): View|RedirectResponse
    {
        $entry = $journalEntry;

        if (! $entry->isDraft()) {
            return redirect()->route('accounts.journal-entries.show', $entry)
                ->with('error', 'Only draft entries can be edited.');
        }

        $entry->load('lines.account');
        $accounts = Account::where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']);
        $typeLabels = Account::typeLabels();

        return view('accounts.journal-entries.edit', compact('entry', 'accounts', 'typeLabels'));
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $entry = $journalEntry;

        if (! $entry->isDraft()) {
            return back()->with('error', 'Only draft entries can be edited.');
        }

        $data = $this->validatedEntry($request);
        $lines = $this->validatedLines($request);

        DB::connection('tenant')->transaction(function () use ($entry, $data, $lines) {
            $entry->fill($data);
            $entry->save();

            SyncAwareDelete::relation($entry->lines());
            $this->syncLines($entry, $lines);
            $entry->recalculateTotals();
            $entry->save();
        });

        return redirect()->route('accounts.journal-entries.show', $entry)
            ->with('success', 'Journal entry updated.');
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $entry = $journalEntry;

        if (! $entry->isDraft()) {
            return back()->with('error', 'Only draft entries can be deleted.');
        }

        $entry->delete();

        return redirect()->route('accounts.journal-entries.index')
            ->with('success', 'Journal entry deleted.');
    }

    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        $entry = $journalEntry;

        if (! $entry->isDraft()) {
            return back()->with('error', 'Entry is already posted.');
        }

        $entry->load('lines');
        $entry->recalculateTotals();

        if ($entry->lines->count() < 2) {
            return back()->with('error', 'At least two journal lines are required.');
        }

        if (! $entry->isBalanced()) {
            return back()->with('error', 'Debits and credits must be equal and greater than zero.');
        }

        $entry->status = JournalEntry::STATUS_POSTED;
        $entry->posted_at = now();
        $entry->posted_by = Auth::id();
        $entry->save();

        return redirect()->route('accounts.journal-entries.show', $entry)
            ->with('success', 'Journal entry posted to the ledger.');
    }

    private function nextEntryNumber(): string
    {
        $last = JournalEntry::query()
            ->where('entry_number', 'like', 'JE-%')
            ->orderByDesc('id')
            ->value('entry_number');

        $seq = 1;
        if ($last && preg_match('/JE-(\d+)/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'JE-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function validatedEntry(Request $request): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** @return list<array{account_id: int, description: ?string, debit: float, credit: float}> */
    private function validatedLines(Request $request): array
    {
        $raw = $request->input('lines', []);
        if (! is_array($raw) || count($raw) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => ['At least two journal lines are required.'],
            ]);
        }

        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($raw as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $debit = round(max(0, (float) ($row['debit'] ?? 0)), 2);
            $credit = round(max(0, (float) ($row['credit'] ?? 0)), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$i}.debit" => ['A line cannot have both debit and credit.'],
                ]);
            }

            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$i}.account_id" => ['Account is required.'],
                ]);
            }

            if (! Account::where('id', $accountId)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$i}.account_id" => ['Invalid account selected.'],
                ]);
            }

            $lines[] = [
                'account_id' => $accountId,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($lines) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => ['At least two journal lines with amounts are required.'],
            ]);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => ['Total debits must equal total credits.'],
            ]);
        }

        return $lines;
    }

    /** @param list<array{account_id: int, description: ?string, debit: float, credit: float}> $lines */
    private function syncLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $i => $row) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $row['account_id'],
                'description' => $row['description'] ?: null,
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'sort_order' => $i,
            ]);
        }
    }
}
