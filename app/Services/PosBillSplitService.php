<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Setting;
use App\Support\DailyOrderNumber;
use App\Support\PosServiceCharge;
use App\Services\Sync\SyncAwareDelete;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PosBillSplitService
{
    /**
     * Move selected items onto a new pending draft bill.
     *
     * @param  list<int>  $itemIds
     * @return array{original: PosOrder, created: list<PosOrder>}
     */
    public function splitItemWise(PosOrder $order, array $itemIds): array
    {
        $this->assertDraftSale($order);

        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            throw new RuntimeException('Kam az kam aik item select karein.');
        }

        $order->load(['items', 'table:id,name']);
        $allItems = $order->items;
        if ($allItems->count() < 2) {
            throw new RuntimeException('Split ke liye bill me kam az kam 2 items hone chahiye.');
        }

        $moving = $allItems->whereIn('id', $itemIds)->values();
        if ($moving->count() !== count($itemIds)) {
            throw new RuntimeException('Kuch selected items is bill me nahi mile.');
        }
        if ($moving->count() >= $allItems->count()) {
            throw new RuntimeException('Poori bill move nahi ho sakti — kuch items original bill me rehni chahiye.');
        }

        $newOrder = $this->cloneOrderHeader($order, $this->splitGuestLabel($order, 'Items'));
        foreach ($moving as $item) {
            $item->update(['order_id' => $newOrder->id]);
        }

        $this->recalculateTotals($order->fresh(['items']));
        $this->recalculateTotals($newOrder->fresh(['items']));
        $this->markOrderAsSplitParent($order->fresh(['table:id,name']));

        return [
            'original' => $order->fresh(['items.product', 'table', 'user:id,name']),
            'created' => [$newOrder->fresh(['items.product', 'table', 'user:id,name'])],
        ];
    }

    /**
     * Divide one pending bill into N equal pending bills (by qty / amounts).
     *
     * @return array{original: PosOrder, created: list<PosOrder>}
     */
    public function splitMemberWise(PosOrder $order, int $members): array
    {
        $this->assertDraftSale($order);

        if ($members < 2) {
            throw new RuntimeException('Members kam az kam 2 hone chahiye.');
        }
        if ($members > 20) {
            throw new RuntimeException('Maximum 20 members tak split ho sakta hai.');
        }

        $order->load(['items', 'table:id,name']);
        $items = $order->items;
        if ($items->isEmpty()) {
            throw new RuntimeException('Bill me koi item nahi.');
        }

        // Build N baskets of line payloads (qty split; last share gets remainder).
        $baskets = array_fill(0, $members, []);
        foreach ($items as $item) {
            $shares = $this->splitQty((float) $item->qty, $members);
            $moneyShares = $this->splitMoney((float) $item->subtotal, $members);
            $discShares = $this->splitMoney((float) $item->discount_amount, $members);
            $taxShares = $this->splitMoney((float) $item->tax_amount, $members);
            $totalShares = $this->splitMoney((float) $item->total, $members);

            for ($i = 0; $i < $members; $i++) {
                $qty = $shares[$i];
                if ($qty <= 0.0000001 && $totalShares[$i] <= 0.0000001) {
                    continue;
                }
                $unitPrice = $qty > 0.0000001
                    ? round($moneyShares[$i] / $qty, 2)
                    : (float) $item->unit_price;

                $baskets[$i][] = [
                    'product_id' => (int) $item->product_id,
                    'item_name' => $item->item_name,
                    'is_custom' => (bool) $item->is_custom,
                    'uom' => (string) $item->uom,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_percent' => (float) $item->discount_percent,
                    'tax_percent' => (float) $item->tax_percent,
                    'notes' => $this->appendSplitNote($item->notes, $i + 1, $members),
                    'subtotal' => $moneyShares[$i],
                    'discount_amount' => $discShares[$i],
                    'tax_amount' => $taxShares[$i],
                    'total' => $totalShares[$i],
                    'kitchen_pending' => false,
                    'kitchen_served_at' => $item->kitchen_served_at,
                    'kitchen_printed_at' => $item->kitchen_printed_at ?? now(),
                    'company_id' => $item->company_id,
                ];
            }
        }

        foreach ($baskets as $idx => $lines) {
            if ($lines === []) {
                throw new RuntimeException('Member '.($idx + 1).' ke liye koi share nahi bana — qty / members check karein.');
            }
        }

        // Member 1 reuses original order; create N-1 new drafts.
        SyncAwareDelete::query(PosOrderItem::query()->where('order_id', $order->id));

        $created = [];
        for ($i = 0; $i < $members; $i++) {
            $target = $i === 0
                ? $order
                : $this->cloneOrderHeader($order, $this->splitGuestLabel($order, 'M'.($i + 1).'/'.$members), $i > 0);

            foreach ($baskets[$i] as $line) {
                PosOrderItem::create(['order_id' => $target->id] + $line);
            }

            if ($i === 0) {
                $guest = $this->splitGuestLabel($order, 'M1/'.$members);
                $order->update([
                    'guest_name' => $guest,
                    // Keep table on first share only so occupancy stays valid.
                    'table_id' => $order->table_id,
                ]);
            }

            $this->recalculateTotals($target->fresh(['items']));
            if ($i > 0) {
                $created[] = $target->fresh(['items.product', 'table', 'user:id,name']);
            }
        }

        return [
            'original' => $order->fresh(['items.product', 'table', 'user:id,name']),
            'created' => $created,
        ];
    }

    private function assertDraftSale(PosOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new RuntimeException('Sirf pending bill split ho sakti hai.');
        }
        if ($order->type !== 'sale') {
            throw new RuntimeException('Refund bill split nahi ho sakti.');
        }
    }

    private function splitGuestLabel(PosOrder $order, string $tag): string
    {
        $base = trim((string) ($order->table?->name ?: $order->guest_name ?: $order->order_no));
        $label = trim($base.' · Split '.$tag);

        return mb_substr($label, 0, 120);
    }

    /** Original bill jis se items alag hui — pending list me split icon ke liye. */
    private function markOrderAsSplitParent(PosOrder $order): void
    {
        $guest = trim((string) ($order->guest_name ?? ''));
        if ($guest !== '' && str_contains($guest, ' · Split ')) {
            return;
        }

        $order->update([
            'guest_name' => $this->splitGuestLabel($order, 'Source'),
        ]);
    }

    private function appendSplitNote(?string $notes, int $part, int $total): ?string
    {
        $mark = 'Split '.$part.'/'.$total;
        $notes = trim((string) $notes);
        if ($notes === '') {
            return $mark;
        }
        if (str_contains($notes, 'Split ')) {
            return $notes;
        }

        return mb_substr($notes.' · '.$mark, 0, 200);
    }

    /**
     * Clone draft header. Child splits do not take table_id (one table → one occupant).
     */
    private function cloneOrderHeader(PosOrder $source, string $guestName, bool $clearTable = true): PosOrder
    {
        $payload = [
            'order_no' => DailyOrderNumber::next(),
            'session_id' => $source->session_id,
            'user_id' => Auth::id() ?: $source->user_id,
            'status' => 'draft',
            'type' => 'sale',
            'sale_mode' => $source->sale_mode,
            'customer_type' => $source->customer_type,
            'service_type' => $source->service_type,
            'table_id' => $clearTable ? null : $source->table_id,
            'guest_name' => $guestName,
            'room_no' => $source->room_no,
            'waiter_name' => $source->waiter_name,
            'order_notes' => $source->order_notes,
            'serve_time' => $source->serve_time,
            'serve_date' => $source->serve_date,
            'serve_meal' => $source->serve_meal,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'service_charge_percent' => $source->service_charge_percent,
            'service_charge_total' => 0,
            'bill_tax_percent' => $source->bill_tax_percent,
            'bill_discount_percent' => $source->bill_discount_percent,
            'is_owner_discount' => (bool) $source->is_owner_discount,
            'grand_total' => 0,
            'ready_for_pos_at' => now(),
        ];

        if (Schema::hasColumn('pos_orders', 'order_source')) {
            $payload['order_source'] = $source->order_source;
        }
        if (Schema::hasColumn('pos_orders', 'kitchen_notes')) {
            $payload['kitchen_notes'] = $source->kitchen_notes;
        }
        if (Schema::hasColumn('pos_orders', 'company_id') && $source->company_id) {
            $payload['company_id'] = $source->company_id;
        }

        return PosOrder::create($payload);
    }

    public function recalculateTotals(PosOrder $order): void
    {
        $items = $order->items()->get();
        $subtotal = round((float) $items->sum('subtotal'), 2);
        $discountTotal = round((float) $items->sum('discount_amount'), 2);
        $taxFromLines = round((float) $items->sum('tax_amount'), 2);

        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }

        $net = round($subtotal - $discountTotal, 2);
        if ($taxMode === 'bill') {
            $billTax = (float) ($order->bill_tax_percent ?? Setting::get('tax_rate', 0));
            $taxTotal = round($net * ($billTax / 100), 2);
        } elseif ($taxMode === 'off') {
            $taxTotal = 0.0;
        } else {
            $taxTotal = $taxFromLines;
        }

        $serviceTotal = PosServiceCharge::amountOnNet($net, $order->serviceTypeKey());
        $grandTotal = round($net + $taxTotal + $serviceTotal, 2);

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'service_charge_percent' => $serviceTotal > 0 ? PosServiceCharge::percent() : null,
            'service_charge_total' => $serviceTotal,
            'grand_total' => $grandTotal,
        ]);
    }

    /**
     * @return list<float>
     */
    private function splitQty(float $qty, int $parts): array
    {
        $qty = round($qty, 3);
        if ($parts <= 1) {
            return [$qty];
        }

        $base = floor(($qty / $parts) * 1000) / 1000;
        $shares = array_fill(0, $parts, $base);
        $used = round($base * $parts, 3);
        $shares[$parts - 1] = round($qty - ($used - $base), 3);

        return $shares;
    }

    /**
     * @return list<float>
     */
    private function splitMoney(float $amount, int $parts): array
    {
        $amount = round($amount, 2);
        if ($parts <= 1) {
            return [$amount];
        }

        $base = round($amount / $parts, 2);
        $shares = array_fill(0, $parts - 1, $base);
        $shares[] = round($amount - array_sum($shares), 2);

        return $shares;
    }
}
