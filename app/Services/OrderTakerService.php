<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosSession;
use App\Models\PosTable;
use App\Models\RoomBooking;
use App\Models\Setting;
use App\Support\ServeMealSchedule;
use App\Support\DailyOrderNumber;
use App\Services\KitchenService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class OrderTakerService
{
    public const SOURCE_ORDER_TAKER = 'order_taker';

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{product_id:int,uom:string,qty:float,notes?:?string}>  $items
     */
    public function createForPos(array $meta, array $items): PosOrder
    {
        if ($items === []) {
            throw new RuntimeException('Kam az kam aik product add karein.');
        }

        [$subtotal, $discountTotal, $taxTotal, $grandTotal, $lines] = $this->buildLines($items);
        $guest = $this->resolveGuestMeta($meta);
        $this->validateProductsForCustomerType($lines, $guest['customer_type']);
        $session = $this->openPosSession();

        $order = PosOrder::create([
            'order_no' => DailyOrderNumber::next(),
            'session_id' => $session?->id,
            'user_id' => Auth::id(),
            'status' => 'draft',
            'order_source' => self::SOURCE_ORDER_TAKER,
            'type' => 'sale',
            'sale_mode' => $guest['customer_type'] === 'ast_offr' ? 'staff' : 'customer',
            'customer_type' => $guest['customer_type'],
            'table_id' => $guest['table_id'],
            'guest_name' => $guest['guest_name'],
            'room_no' => $guest['room_no'],
            'waiter_name' => $guest['waiter_name'],
            'serve_time' => $guest['serve_time'] ?? null,
            'serve_date' => $guest['serve_date'] ?? null,
            'serve_meal' => $guest['serve_meal'] ?? null,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'ready_for_pos_at' => now(),
        ]);

        $kitchen = app(KitchenService::class);
        $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags([], $lines);

        foreach ($itemsWithKitchenFlags as $line) {
            PosOrderItem::create(['order_id' => $order->id] + $line);
        }

        return $order->fresh(['items.product', 'table']);
    }

    public function openPosSession(): ?PosSession
    {
        return PosSession::query()
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    /**
     * Draft orders keyed by table_id (cashier hold + order taker) for the open session.
     *
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    public function draftOrdersByTableId(): \Illuminate\Support\Collection
    {
        $session = $this->openPosSession();
        if ($session === null) {
            return collect();
        }

        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

        return PosOrder::query()
            ->where('status', 'draft')
            ->whereNotNull('table_id')
            ->where(function ($outer) use ($session, $hasOrderTakerColumns) {
                $outer->where('session_id', $session->id);
                if ($hasOrderTakerColumns) {
                    $outer->orWhere(function ($w) {
                        $w->where('order_source', self::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at');
                    });
                }
            })
            ->with(['table:id,name'])
            ->withCount('items')
            ->latest('id')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   status: 'free'|'occupied',
     *   order_id: ?int,
     *   order_no: ?string,
     *   amendable: bool,
     *   items_count: int,
     *   grand_total: float
     * }>
     */
    public function tableBoard(): array
    {
        if ((string) Setting::get('pos_enable_tables', '1') === '0') {
            return [];
        }

        $occupied = $this->draftOrdersByTableId();

        return \App\Models\PosTable::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (\App\Models\PosTable $table) use ($occupied) {
                /** @var PosOrder|null $order */
                $order = $occupied->get($table->id);

                return [
                    'id' => (int) $table->id,
                    'name' => (string) $table->name,
                    'status' => $order !== null ? 'occupied' : 'free',
                    'order_id' => $order?->id,
                    'order_no' => $order?->order_no,
                    'amendable' => $order !== null ? $this->isPendingAmendable($order) : false,
                    'items_count' => $order ? (int) $order->items_count : 0,
                    'grand_total' => $order ? (float) $order->grand_total : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PosOrder>
     */
    public function pendingPosOrders(): \Illuminate\Database\Eloquent\Collection
    {
        $session = $this->openPosSession();
        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

        return PosOrder::query()
            ->where('status', 'draft')
            ->when($hasOrderTakerColumns, function ($q) use ($session) {
                $q->where(function ($outer) use ($session) {
                    if ($session) {
                        $outer->where(function ($w) use ($session) {
                            $w->where('session_id', $session->id)
                                ->where(function ($inner) {
                                    $inner->whereNull('order_source')
                                        ->orWhere('order_source', 'pos');
                                });
                        });
                    }
                    $outer->orWhere(function ($w) use ($session) {
                        $w->where('order_source', self::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at');
                        if ($session) {
                            $w->where(function ($inner) use ($session) {
                                $inner->whereNull('session_id')
                                    ->orWhere('session_id', $session->id);
                            });
                        }
                    });
                });
            }, function ($q) use ($session) {
                if ($session) {
                    $q->where('session_id', $session->id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with(['table:id,name', 'items.product:id,name'])
            ->withCount('items')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function isPendingAmendable(PosOrder $order): bool
    {
        if ($order->status !== 'draft') {
            return false;
        }

        if ($order->isReadyForPosPickup()) {
            return true;
        }

        if (! Schema::hasColumn($order->getTable(), 'order_source')) {
            return $order->session_id !== null;
        }

        $source = (string) ($order->order_source ?? 'pos');
        if ($source === self::SOURCE_ORDER_TAKER) {
            return false;
        }

        return $order->session_id !== null && in_array($source, ['pos', ''], true);
    }

    public function assertPendingAmendable(PosOrder $order): void
    {
        if (! $this->isPendingAmendable($order)) {
            throw new RuntimeException('Yeh pending bill ab edit nahi ho sakti.');
        }
    }

    /**
     * @param  list<array{product_id:int,uom:string,qty:float,notes?:?string}>  $items
     */
    public function updatePendingBill(PosOrder $order, array $items): PosOrder
    {
        $this->assertPendingAmendable($order);

        if ($items === []) {
            throw new RuntimeException('Kam az kam aik product rehna chahiye.');
        }

        [$subtotal, $discountTotal, $taxTotal, $grandTotal, $lines] = $this->buildLines($items);
        $this->validateProductsForCustomerType($lines, $order->customerTypeKey());

        $kitchen = app(KitchenService::class);
        $oldItems = $order->items()->get()->all();
        $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags($oldItems, $lines);

        $wasKitchenServed = $order->kitchen_completed_at !== null;
        $kitchenPayload = ['kitchen_completed_at' => null];
        if (Schema::hasColumn($order->getTable(), 'kitchen_preparing_at')) {
            $kitchenPayload['kitchen_preparing_at'] = null;
        }
        if (Schema::hasColumn($order->getTable(), 'kitchen_ready_at')) {
            $kitchenPayload['kitchen_ready_at'] = null;
        }
        if ($wasKitchenServed) {
            $kitchenPayload['kitchen_status'] = null;
        }
        if (Schema::hasColumn($order->getTable(), 'kitchen_status')) {
            $hasNewKitchenItems = collect($itemsWithKitchenFlags)
                ->contains(fn (array $item) => (bool) ($item['kitchen_pending'] ?? true));
            if ($hasNewKitchenItems && (
                $wasKitchenServed
                || $order->kitchenStatusKey() === PosOrder::KITCHEN_STATUS_READY
            )) {
                $kitchenPayload['kitchen_status'] = null;
            }
        }

        $order->fill([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
        ] + $kitchenPayload);
        $order->save();

        $order->items()->delete();
        foreach ($itemsWithKitchenFlags as $line) {
            PosOrderItem::create(['order_id' => $order->id] + $line);
        }

        return $order->fresh(['items.product', 'table']);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *   customer_type: string,
     *   guest_name: ?string,
     *   room_no: ?string,
     *   waiter_name: ?string,
     *   table_id: ?int,
     *   serve_time: ?string,
     *   serve_date: ?string,
     *   serve_meal: ?string
     * }
     */
    private function resolveGuestMeta(array $meta): array
    {
        $guestName = trim((string) ($meta['guest_name'] ?? ''));
        $roomNo = trim((string) ($meta['room_no'] ?? ''));
        $waiterName = trim((string) ($meta['waiter_name'] ?? ''));
        $serveTime = trim((string) ($meta['serve_time'] ?? ''));
        $serveDate = trim((string) ($meta['serve_date'] ?? ''));
        $serveMeal = trim((string) ($meta['serve_meal'] ?? ''));
        $customerType = $this->normalizeCustomerType((string) ($meta['customer_type'] ?? 'mess_use'));
        $tableId = isset($meta['table_id']) && (int) $meta['table_id'] > 0 ? (int) $meta['table_id'] : null;

        if ($customerType === 'booking') {
            $guestName = $this->resolveCheckedInGuestNameByRoomNo($roomNo) ?? '';
            if ($guestName === '') {
                throw new RuntimeException('Selected room abhi checked-in nahi hai.');
            }

            return [
                'customer_type' => $customerType,
                'guest_name' => $guestName,
                'room_no' => $roomNo !== '' ? $roomNo : null,
                'waiter_name' => null,
                'table_id' => null,
                'serve_time' => null,
                'serve_date' => null,
                'serve_meal' => null,
            ];
        }

        if ($customerType === 'ast_offr') {
            if ($guestName === '') {
                throw new RuntimeException(PosOrder::MESS_BILL_LABEL.' ke liye officer / guest name likhein.');
            }

            return [
                'customer_type' => $customerType,
                'guest_name' => $guestName,
                'room_no' => null,
                'waiter_name' => null,
                'table_id' => null,
                'serve_time' => null,
                'serve_date' => null,
                'serve_meal' => null,
            ];
        }

        if ($guestName === '') {
            throw new RuntimeException('Guest name zaroori hai.');
        }
        if ($waiterName === '') {
            throw new RuntimeException('Waiter select karein.');
        }

        if ($serveMeal !== '' && ServeMealSchedule::isValid($serveMeal)) {
            if ($serveDate === '' || $serveTime === '') {
                $resolved = ServeMealSchedule::resolveNext($serveMeal);
                $serveDate = $resolved['serve_date'];
                $serveTime = $resolved['serve_time'];
            }
        }

        return [
            'customer_type' => $customerType,
            'guest_name' => $guestName,
            'room_no' => null,
            'waiter_name' => $waiterName,
            'table_id' => $tableId,
            'serve_time' => $serveTime !== '' ? $serveTime : null,
            'serve_date' => $serveDate !== '' ? $serveDate : null,
            'serve_meal' => ServeMealSchedule::isValid($serveMeal) ? $serveMeal : null,
        ];
    }

    /**
     * @param  list<array{product_id:int,uom:string,qty:float,notes?:?string}>  $items
     * @return array{0: float, 1: float, 2: float, 3: float, 4: list<array<string, mixed>>}
     */
    public function buildLines(array $items): array
    {
        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }
        $billTaxPct = (float) Setting::get('tax_rate', 0);

        $ids = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $products = $ids === []
            ? collect()
            : InventoryProduct::query()
                ->whereIn('id', $ids)
                ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
                ->get()
                ->keyBy('id');

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $uom = trim((string) ($item['uom'] ?? ''));
            if ($pid <= 0 || $qty <= 0 || $uom === '') {
                continue;
            }

            $product = $products->get($pid);
            if (! $product) {
                throw new RuntimeException("Product #{$pid} not found.");
            }

            $factor = $product->factorToBaseForUom($uom);
            if ($factor === null || $factor <= 0) {
                throw new RuntimeException("Invalid UOM \"{$uom}\" for {$product->name}.");
            }

            $unitPrice = round((float) $product->price * $factor, 2);
            $lineSub = round($qty * $unitPrice, 2);
            $lineTax = $taxMode === 'line' ? round($lineSub * ($billTaxPct / 100), 2) : 0.0;
            $lineTotal = round($lineSub + $lineTax, 2);

            $subtotal += $lineSub;
            $taxTotal += $lineTax;

            $lines[] = [
                'product_id' => $pid,
                'uom' => $uom,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_percent' => 0.0,
                'tax_percent' => $taxMode === 'line' ? $billTaxPct : 0.0,
                'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
                'subtotal' => $lineSub,
                'discount_amount' => 0.0,
                'tax_amount' => $lineTax,
                'total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('Kam az kam aik valid product line add karein.');
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = 0.0;
        if ($taxMode === 'bill') {
            $taxTotal = round($subtotal * ($billTaxPct / 100), 2);
        } else {
            $taxTotal = round($taxTotal, 2);
        }
        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

        return [$subtotal, $discountTotal, $taxTotal, $grandTotal, $lines];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function validateProductsForCustomerType(array $lines, string $customerType): void
    {
        if (! in_array($customerType, ['mess_use', 'booking'], true) || $lines === []) {
            return;
        }

        $ids = collect($lines)->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $products = InventoryProduct::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'for_pos'])
            ->keyBy('id');

        foreach ($lines as $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            if ($product === null || $product->for_pos) {
                continue;
            }

            throw new RuntimeException($product->name.' Walk-In / In-House ke liye available nahi — sirf menu items choose karein.');
        }
    }

    private function normalizeCustomerType(string $type): string
    {
        return in_array($type, ['mess_use', 'booking', 'ast_offr'], true) ? $type : 'mess_use';
    }

    private function resolveCheckedInGuestNameByRoomNo(string $roomNo): ?string
    {
        $normalized = trim($roomNo);
        if ($normalized === '') {
            return null;
        }

        $row = $this->checkedInRooms()
            ->first(function (array $entry) use ($normalized) {
                if (strcasecmp((string) $entry['room_no'], $normalized) === 0) {
                    return true;
                }

                foreach (explode(',', (string) $entry['room_no']) as $assignedRoom) {
                    if (strcasecmp(trim($assignedRoom), $normalized) === 0) {
                        return true;
                    }
                }

                return false;
            });

        return is_array($row) ? ($row['guest_name'] ?? null) : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{room_no:string, guest_name:string}>
     */
    public function checkedInRooms(): \Illuminate\Support\Collection
    {
        return RoomBooking::query()
            ->where('status', RoomBooking::STATUS_CHECKED_IN)
            ->with([
                'activeAssignedRooms:id,room_number',
                'guestRoom:id,room_number',
            ])
            ->latest('actual_check_in')
            ->latest('id')
            ->get(['id', 'guest_name', 'person_type', 'care_of', 'pa_no', 'guest_rank', 'guest_room_id'])
            ->map(function (RoomBooking $booking) {
                $rooms = $booking->activeAssignedRooms
                    ->pluck('room_number')
                    ->filter()
                    ->values();

                if ($rooms->isEmpty() && $booking->guestRoom?->room_number) {
                    $rooms = collect([(string) $booking->guestRoom->room_number]);
                }

                if ($rooms->isEmpty()) {
                    return null;
                }

                $sortedRooms = $rooms
                    ->map(fn ($roomNo) => (string) $roomNo)
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'room_no' => $sortedRooms->implode(', '),
                    'guest_name' => $booking->guestDisplayName(),
                ];
            })
            ->filter()
            ->sortBy('room_no', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
