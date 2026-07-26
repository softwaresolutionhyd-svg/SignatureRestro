<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Models\Setting;
use App\Services\DefaultChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountReportController extends Controller
{
    public function __construct(
        private readonly DefaultChartOfAccounts $defaultChart
    ) {}

    public function trialBalance(Request $request): View
    {
        $this->defaultChart->ensureForCompany(current_company_id());

        // Prefer from/to; keep as_of as legacy "To" if still used.
        $from = $request->input('from', $request->filled('as_of') ? null : now()->startOfMonth()->toDateString());
        $to = $request->input('to', $request->input('as_of', now()->toDateString()));

        if (! $from) {
            $from = now()->startOfMonth()->toDateString();
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $currency = Setting::get('currency_symbol', 'Rs.');
        $typeLabels = Account::typeLabels();

        $rows = Account::query()
            ->where('active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($from, $to) {
                $sums = JournalEntryLine::query()
                    ->where('account_id', $account->id)
                    ->whereHas('journalEntry', fn ($q) => $q
                        ->where('status', 'posted')
                        ->whereDate('entry_date', '>=', $from)
                        ->whereDate('entry_date', '<=', $to))
                    ->selectRaw('COALESCE(SUM(debit), 0) as debit_sum, COALESCE(SUM(credit), 0) as credit_sum')
                    ->first();

                $debitSum = round((float) ($sums->debit_sum ?? 0), 2);
                $creditSum = round((float) ($sums->credit_sum ?? 0), 2);
                $net = round($debitSum - $creditSum, 2);

                $debitBalance = 0.0;
                $creditBalance = 0.0;

                if ($account->isDebitNormal()) {
                    $debitBalance = $net >= 0 ? $net : 0;
                    $creditBalance = $net < 0 ? abs($net) : 0;
                } else {
                    $creditBalance = $net <= 0 ? abs($net) : 0;
                    $debitBalance = $net > 0 ? $net : 0;
                }

                return [
                    'account' => $account,
                    'debit' => $debitBalance,
                    'credit' => $creditBalance,
                ];
            })
            ->filter(fn (array $row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        $totalDebit = round((float) $rows->sum('debit'), 2);
        $totalCredit = round((float) $rows->sum('credit'), 2);

        return view('accounts.reports.trial-balance', compact(
            'rows',
            'from',
            'to',
            'currency',
            'typeLabels',
            'totalDebit',
            'totalCredit'
        ));
    }
}
