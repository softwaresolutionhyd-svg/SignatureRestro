<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Setting;
use App\Services\DefaultChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly DefaultChartOfAccounts $defaultChart
    ) {}

    public function index(Request $request): View
    {
        $this->defaultChart->ensureForCompany(current_company_id());

        $query = Account::query()->orderBy('code');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(fn ($w) => $w->where('code', 'like', $s)->orWhere('name', 'like', $s));
        }
        if ($request->get('active', '1') !== 'all') {
            $query->where('active', $request->get('active', '1') === '1');
        }

        $accounts = $query->paginate(Setting::pageSize('accounts_per_page', 25))->withQueryString();
        $typeLabels = Account::typeLabels();
        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('accounts.chart-of-accounts.index', compact('accounts', 'typeLabels', 'currency'));
    }

    public function create(): View
    {
        $typeLabels = Account::typeLabels();
        $parents = Account::where('active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('accounts.chart-of-accounts.create', compact('typeLabels', 'parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Account::create($data);

        return redirect()->route('accounts.chart-of-accounts.index')
            ->with('success', 'Account created.');
    }

    public function edit(Account $chartOfAccount): View
    {
        $account = $chartOfAccount;
        $typeLabels = Account::typeLabels();
        $parents = Account::where('active', true)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('accounts.chart-of-accounts.edit', compact('account', 'typeLabels', 'parents'));
    }

    public function update(Request $request, Account $chartOfAccount): RedirectResponse
    {
        $account = $chartOfAccount;

        if ($account->is_system && $request->input('code') !== $account->code) {
            return back()->with('error', 'System account code cannot be changed.');
        }

        $data = $this->validated($request, $account->id);

        if ($account->is_system) {
            unset($data['code']);
        }

        $account->update($data);

        return redirect()->route('accounts.chart-of-accounts.index')
            ->with('success', 'Account updated.');
    }

    public function destroy(Account $chartOfAccount): RedirectResponse
    {
        $account = $chartOfAccount;

        if ($account->is_system) {
            return back()->with('error', 'System accounts cannot be deleted.');
        }

        if ($account->journalLines()->exists()) {
            return back()->with('error', 'Account has journal entries and cannot be deleted.');
        }

        $account->delete();

        return redirect()->route('accounts.chart-of-accounts.index')
            ->with('success', 'Account deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:tenant.accounts,code';
        if ($ignoreId) {
            $uniqueRule .= ','.$ignoreId;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', $uniqueRule],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_keys(Account::typeLabels()))],
            'parent_id' => ['nullable', 'exists:tenant.accounts,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);

        return $data;
    }
}
