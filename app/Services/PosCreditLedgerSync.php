<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\PosOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class PosCreditLedgerSync
{
    /** Create credit_ledger rows for paid POS credit orders that never got a ledger line. */
    public function syncMissing(): void
    {
        if (! Schema::hasTable('credit_ledger') || ! Schema::hasTable('pos_orders')) {
            return;
        }

        $orders = PosOrder::query()
            ->where('is_credit', true)
            ->where('status', 'paid')
            ->whereNotNull('contact_id')
            ->whereDoesntHave('creditLedger')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get(['id', 'contact_id', 'order_no', 'grand_total', 'paid_at', 'created_at', 'user_id']);

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($orders->groupBy('contact_id') as $contactId => $group) {
            $contact = Contact::query()->find($contactId);
            if (! $contact) {
                continue;
            }

            $running = (float) $contact->creditLedger()->where('type', 'credit')->sum('amount')
                - (float) $contact->creditLedger()->where('type', 'payment')->sum('amount');

            foreach ($group as $order) {
                $running = round($running + (float) $order->grand_total, 2);
                $entryDate = $order->paid_at
                    ? $order->paid_at->toDateString()
                    : $order->created_at->toDateString();

                CreditLedger::updateOrCreate(
                    ['pos_order_id' => $order->id],
                    [
                        'contact_id' => (int) $contactId,
                        'type' => 'credit',
                        'description' => 'POS Credit Sale — '.$order->order_no.' (synced)',
                        'amount' => $order->grand_total,
                        'balance_after' => $running,
                        'entry_date' => $entryDate,
                        'created_by' => $order->user_id ?: Auth::id(),
                    ]
                );
            }
        }
    }
}
