<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\PosOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVendor;
use App\Models\Setting;
use App\Services\AutoJournalService;
use App\Services\PosCreditLedgerSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditBookController extends Controller
{
    public function __construct(
        private readonly PosCreditLedgerSync $posCreditLedgerSync,
        private readonly AutoJournalService $autoJournal,
    ) {}

    /** Master credit book — all contacts with outstanding balance */
    public function index(Request $request)
    {
        $this->posCreditLedgerSync->syncMissing();
        $this->purgeOrphanLedgerEntries();

        $query = Contact::query()
            ->withSum(['creditLedger as total_credit' => fn ($q) => $q->where('type', 'credit')], 'amount')
            ->withSum(['creditLedger as total_paid' => fn ($q) => $q->where('type', 'payment')], 'amount')
            ->orderBy('name');

        $filter = $request->get('filter', 'outstanding');
        if ($filter === 'outstanding') {
            // Only contacts with balance due > 0 (settled contacts hide)
            $query->whereRaw(
                '(SELECT COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END), 0)
                  FROM credit_ledger WHERE contact_id = contacts.id) > 0.009',
                ['credit', 'payment']
            );
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

        // Sum only positive balances of existing contacts (ignore deleted-contact orphans)
        $balanceRows = CreditLedger::query()
            ->whereIn('contact_id', Contact::query()->select('id'))
            ->select('contact_id')
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END) as bal', ['credit', 'payment'])
            ->groupBy('contact_id')
            ->havingRaw('SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END) > 0.009', ['credit', 'payment'])
            ->get();

        $totalOutstanding = round((float) $balanceRows->sum('bal'), 2);
        $totalContacts = $balanceRows->count();

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

    /** View items purchased on a credit purchase order linked to the credit book. */
    public function showPurchase(Request $request, PurchaseOrder $order): View
    {
        abort_unless($order->purchase_type === 'credit', 404);

        $order->load(['lines.product:id,name,sku', 'vendor:id,name,phone', 'creator:id,name']);

        $settings = array_merge([
            'currency_symbol' => 'Rs.',
        ], Setting::all_map());

        return view('credit-book.partials.purchase-detail', compact('order', 'settings'));
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

        $entry = DB::connection('tenant')->transaction(function () use ($data, $balAfter, $contact) {
            $payload = [
                ...$data,
                'balance_after' => $balAfter,
                'created_by' => Auth::id(),
            ];

            // Payment against a vendor contact: link matching unpaid credit PO (same amount)
            if ($data['type'] === 'payment') {
                $vendorIds = PurchaseVendor::query()->where('contact_id', $contact->id)->pluck('id');
                if ($vendorIds->isNotEmpty()) {
                    $po = PurchaseOrder::query()
                        ->whereIn('vendor_id', $vendorIds)
                        ->where('purchase_type', 'credit')
                        ->where('status', 'received')
                        ->where(function ($q) {
                            $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
                        })
                        ->whereRaw('ABS(grand_total - ?) < 0.02', [(float) $data['amount']])
                        ->orderBy('id')
                        ->first();

                    if ($po) {
                        $payload['purchase_order_id'] = $po->id;
                        $po->payment_status = 'paid';
                        $po->paid_at = $data['entry_date'];
                        $po->save();
                    }
                }
            }

            return CreditLedger::create($payload);
        });

        if ($entry->type === 'payment') {
            $this->autoJournal->postCreditBookPayment($entry->fresh());
            if ($entry->purchase_order_id) {
                $po = PurchaseOrder::query()->find($entry->purchase_order_id);
                if ($po) {
                    $this->autoJournal->postPurchasePaid($po);
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'balance' => $balAfter]);
        }

        return redirect()->route('contacts.show', $data['contact_id'])
            ->with('success', ucfirst($data['type']) . ' entry added.');
    }

    /** Remove ledger rows whose contact was deleted (ghost balances inflate totals). */
    private function purgeOrphanLedgerEntries(): void
    {
        CreditLedger::query()
            ->whereNotNull('contact_id')
            ->whereNotIn('contact_id', Contact::query()->select('id'))
            ->delete();
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
