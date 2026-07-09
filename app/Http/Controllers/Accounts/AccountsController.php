<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\DefaultChartOfAccounts;
use Illuminate\View\View;

class AccountsController extends Controller
{
    public function __construct(
        private readonly DefaultChartOfAccounts $defaultChart
    ) {}

    public function index(): View
    {
        $this->defaultChart->ensureForCompany(current_company_id());

        $currency = Setting::get('currency_symbol', 'Rs.');

        $accountCounts = Account::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $kpis = [
            'accounts' => Account::where('active', true)->count(),
            'draft' => JournalEntry::where('status', JournalEntry::STATUS_DRAFT)->count(),
            'posted' => JournalEntry::where('status', JournalEntry::STATUS_POSTED)->count(),
            'posted_total' => (float) JournalEntry::where('status', JournalEntry::STATUS_POSTED)->sum('total_debit'),
        ];

        $recentEntries = JournalEntry::with(['lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $typeLabels = Account::typeLabels();

        return view('accounts.index', compact('currency', 'accountCounts', 'kpis', 'recentEntries', 'typeLabels'));
    }
}
