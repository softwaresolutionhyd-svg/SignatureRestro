<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\PurchaseOrder;
use App\Support\EnsuresVendorCreditSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class PurchaseCreditLedgerService
{
    use EnsuresVendorCreditSchema;

    public function __construct(
        private readonly VendorContactSyncService $vendorContacts
    ) {}

    /**
     * Keep the credit-book ledger in sync with a purchase order's credit state.
     * Called on create / update / confirm.
     */
    public function syncForOrder(PurchaseOrder $order): void
    {
        if (! $this->ready()) {
            return;
        }

        $order->loadMissing('vendor');
        $vendor = $order->vendor;
        if ($vendor === null) {
            return;
        }

        $shouldOwe = $order->purchase_type === 'credit'
            && $order->payment_status !== 'paid'
            && $order->status !== 'cancelled'
            && (float) $order->grand_total > 0;

        if (! $shouldOwe) {
            // Not a live credit anymore (debit, cancelled, or zero) and not yet paid.
            if ($order->payment_status !== 'paid') {
                $this->removeEntriesForOrder($order->id);
            }

            return;
        }

        $contact = $this->vendorContacts->ensureContactForVendor($vendor);
        if (! $contact) {
            return;
        }

        $this->writeEntry(
            $order->id,
            (int) $contact->id,
            'credit',
            (float) $order->grand_total,
            'Purchase Credit — '.$order->number.' · '.$vendor->name,
            $this->orderDate($order),
            (int) ($order->created_by ?: Auth::id())
        );
    }

    /** Record the payment side when a credit purchase is marked paid. */
    public function registerPayment(PurchaseOrder $order): void
    {
        if (! $this->ready()) {
            return;
        }

        $order->loadMissing('vendor');
        $vendor = $order->vendor;
        if ($vendor === null || (float) $order->grand_total <= 0) {
            return;
        }

        $contact = $this->vendorContacts->ensureContactForVendor($vendor);
        if (! $contact) {
            return;
        }

        // Make sure the credit side exists (in case it was a credit created before this feature).
        $this->writeEntry(
            $order->id,
            (int) $contact->id,
            'credit',
            (float) $order->grand_total,
            'Purchase Credit — '.$order->number.' · '.$vendor->name,
            $this->orderDate($order),
            (int) ($order->created_by ?: Auth::id())
        );

        $paidDate = $order->paid_at
            ? $order->paid_at->toDateString()
            : now()->toDateString();

        $this->writeEntry(
            $order->id,
            (int) $contact->id,
            'payment',
            (float) $order->grand_total,
            'Purchase Payment — '.$order->number.' · '.$vendor->name,
            $paidDate,
            (int) Auth::id()
        );
    }

    private function writeEntry(int $orderId, int $contactId, string $type, float $amount, string $description, string $entryDate, int $createdBy): void
    {
        $existing = CreditLedger::query()
            ->where('purchase_order_id', $orderId)
            ->where('type', $type)
            ->first();

        $balanceAfter = $this->balanceExcluding($contactId, $existing?->id);
        $balanceAfter = $type === 'credit'
            ? round($balanceAfter + $amount, 2)
            : round($balanceAfter - $amount, 2);

        CreditLedger::updateOrCreate(
            ['purchase_order_id' => $orderId, 'type' => $type],
            [
                'contact_id' => $contactId,
                'description' => $description,
                'amount' => round($amount, 2),
                'balance_after' => $balanceAfter,
                'entry_date' => $entryDate,
                'created_by' => $createdBy ?: null,
            ]
        );
    }

    private function removeEntriesForOrder(int $orderId): void
    {
        \App\Services\Sync\SyncAwareDelete::query(
            CreditLedger::query()->where('purchase_order_id', $orderId)
        );
    }

    private function balanceExcluding(int $contactId, ?int $excludeLedgerId): float
    {
        $credit = CreditLedger::query()
            ->where('contact_id', $contactId)
            ->where('type', 'credit')
            ->when($excludeLedgerId, fn ($q) => $q->where('id', '!=', $excludeLedgerId))
            ->sum('amount');

        $payment = CreditLedger::query()
            ->where('contact_id', $contactId)
            ->where('type', 'payment')
            ->when($excludeLedgerId, fn ($q) => $q->where('id', '!=', $excludeLedgerId))
            ->sum('amount');

        return round((float) $credit - (float) $payment, 2);
    }

    private function orderDate(PurchaseOrder $order): string
    {
        if ($order->order_date) {
            return $order->order_date instanceof \Illuminate\Support\Carbon
                ? $order->order_date->toDateString()
                : (string) $order->order_date;
        }

        return $order->created_at?->toDateString() ?? now()->toDateString();
    }

    private function ready(): bool
    {
        $this->ensureVendorCreditSchema();

        return Schema::connection('tenant')->hasTable('credit_ledger')
            && Schema::connection('tenant')->hasColumn('credit_ledger', 'purchase_order_id');
    }
}
