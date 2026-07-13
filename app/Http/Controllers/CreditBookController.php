<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\PosOrder;
use App\Models\Setting;
use App\Services\PosCreditLedgerSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CreditBookController extends Controller
{
    public function __construct(
        private readonly PosCreditLedgerSync $posCreditLedgerSync
    ) {}

    /** Master credit book — all contacts with outstanding balance */
    public function index(Request $request)
    {
        $this->posCreditLedgerSync->syncMissing();

        $query = Contact::query()
            ->withSum(['creditLedger as total_credit' => fn ($q) => $q->where('type', 'credit')], 'amount')
            ->withSum(['creditLedger as total_paid' => fn ($q) => $q->where('type', 'payment')], 'amount')
            ->orderBy('name');

        $filter = $request->get('filter', 'outstanding');
        if ($filter === 'outstanding') {
            $query->whereHas('creditLedger');
        } else {
            $query->where('active', true);
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('phone', 'like', $s));
        }

        $contacts = $query
            ->paginate(Setting::pageSize('credit_book_per_page', 20))
            ->withQueryString();

        $totalOutstanding = (float) (CreditLedger::query()
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END) as bal', ['credit', 'payment'])
            ->value('bal') ?? 0);

        $totalContacts = Contact::query()->whereHas('creditLedger')->count();

        return view('credit-book.index', compact('contacts', 'totalOutstanding', 'totalContacts', 'filter'));
    }

    /** View items purchased on a POS credit sale linked to the credit book. */
    public function showPosSale(Request $request, PosOrder $order): View
    {
        abort_unless($order->status === 'paid', 404);
        abort_unless($order->is_credit && $order->creditLedger()->exists(), 404);

        $order->load(['items.product:id,name,sku', 'contact:id,name,phone', 'user:id,name', 'table:id,name']);

        $settings = array_merge([
            'currency_symbol' => 'Rs.',
            'pos_enable_tables' => '1',
        ], Setting::all_map());

        return view('credit-book.partials.pos-sale-detail', compact('order', 'settings'));
    }

    /** Add a manual credit or payment entry for a contact */
    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id'  => 'required|exists:tenant.contacts,id',
            'type'        => 'required|in:credit,payment',
            'description' => 'required|string|max:300',
            'amount'      => 'required|numeric|min:0.01',
            'entry_date'  => 'required|date',
            'notes'       => 'nullable|string',
        ]);

        $contact = Contact::findOrFail($data['contact_id']);
        $runningBalance = $contact->balance;

        $balAfter = $data['type'] === 'credit'
            ? $runningBalance + (float) $data['amount']
            : $runningBalance - (float) $data['amount'];

        CreditLedger::create([
            ...$data,
            'balance_after' => $balAfter,
            'created_by'    => Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'balance' => $balAfter]);
        }

        return redirect()->route('contacts.show', $data['contact_id'])
            ->with('success', ucfirst($data['type']) . ' entry added.');
    }

    /** Delete a manual ledger entry (not POS-linked) */
    public function destroy(CreditLedger $entry)
    {
        if ($entry->pos_order_id) {
            return back()->with('error', 'Cannot delete a POS-linked credit entry.');
        }
        if ($entry->purchase_order_id) {
            return back()->with('error', 'Cannot delete a purchase-linked credit entry.');
        }
        if ($entry->payroll_entry_id) {
            return back()->with('error', 'Cannot delete a payroll salary-deduction entry.');
        }
        $entry->delete();
        return back()->with('success', 'Entry deleted.');
    }
}
