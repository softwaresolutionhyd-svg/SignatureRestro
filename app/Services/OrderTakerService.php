<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosSession;
use App\Models\PosTable;
use App\Models\RoomBooking;
use App\Models\Setting;
use App\Support\PosServiceCharge;
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

        $this->assertPosSessionStarted();

        $guest = $this->resolveGuestMeta($meta);
        [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $lines] = $this->buildLines(
            $items,
            $guest['service_type'] ?? PosOrder::SERVICE_DINE_IN
        );
        $this->validateProductsForCustomerType($lines, $guest['customer_type']);
        if (($guest['service_type'] ?? PosOrder::SERVICE_DINE_IN) === PosOrder::SERVICE_DINE_IN) {
            $this->assertTableAvailable($guest['table_id'], null, true);
        }
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
            'service_type' => $guest['service_type'] ?? PosOrder::SERVICE_DINE_IN,
            'table_id' => $guest['table_id'],
            'guest_name' => $guest['guest_name'],
            'room_no' => $guest['room_no'],
            'waiter_name' => $guest['waiter_name'],
            'order_notes' => $guest['order_notes'] ?? null,
            'serve_time' => $guest['serve_time'] ?? null,
            'serve_date' => $guest['serve_date'] ?? null,
            'serve_meal' => $guest['serve_meal'] ?? null,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'service_charge_percent' => $serviceTotal > 0 ? PosServiceCharge::percent() : null,
            'service_charge_total' => $serviceTotal,
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
            ->when($this->sessionsHaveShiftStartedColumn(), function ($q) {
                $q->where('shift_started', true);
            })
            ->latest('id')
            ->first();
    }

    public function hasStartedPosSession(): bool
    {
        return $this->openPosSession() !== null;
    }

    public function assertPosSessionStarted(): void
    {
        if (! $this->hasStartedPosSession()) {
            throw new RuntimeException('POS session abhi open nahi hui. Pehle cashier se POS session open karwayein, phir order punch karein.');
        }
    }

    private function sessionsHaveShiftStartedColumn(): bool
    {
        return Schema::hasColumn('pos_sessions', 'shift_started');
    }

    /**
     * All currently open POS sessions (any business day) — table occupancy / shared bills.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function openPosSessionIdsForToday(): \Illuminate\Support\Collection
    {
        return PosSession::query()
            ->where('status', 'open')
            ->when($this->sessionsHaveShiftStartedColumn(), function ($q) {
                $q->where('shift_started', true);
            })
            ->pluck('id');
    }

    /**
     * Draft orders keyed by table_id for today's open table holds.
     *
     * Uses today's draft rows (not only "open session" IDs) so hosting stays
     * in sync even when pos_sessions state differs between local and cloud.
     *
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    public function draftOrdersByTableId(): \Illuminate\Support\Collection
    {
        $today = now()->toDateString();

        return PosOrder::query()
            ->where('status', 'draft')
            ->whereNotNull('table_id')
            ->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                    ->orWhereDate('updated_at', $today);
            })
            ->with(['table:id,name'])
            ->withCount('items')
            ->latest('id')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');
    }

    public function assertTableAvailable(?int $tableId, ?int $exceptOrderId = null, bool $lockForUpdate = false): void
    {
        if ($tableId === null || $tableId <= 0) {
            return;
        }

        if ((string) Setting::get('pos_enable_tables', '1') === '0') {
            return;
        }

        $today = now()->toDateString();
        $query = PosOrder::query()
            ->where('status', 'draft')
            ->where('table_id', $tableId)
            ->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                    ->orWhereDate('updated_at', $today);
            });

        if ($exceptOrderId !== null && $exceptOrderId > 0) {
            $query->where('id', '!=', $exceptOrderId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $existing = $query->orderByDesc('id')->first();

        if ($existing !== null) {
            $tableName = PosTable::query()->whereKey($tableId)->value('name') ?? 'Table';
            throw new RuntimeException(sprintf(
                '%s pehle se reserved hai (Order %s). Pehle wahi bill resume karein ya pay karein.',
                $tableName,
                $existing->order_no
            ));
        }
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
        $this->assertPosSessionStarted();
        $this->assertPendingAmendable($order);

        if ($items === []) {
            throw new RuntimeException('Kam az kam aik product rehna chahiye.');
        }

        [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $lines] = $this->buildLines(
            $items,
            $order->serviceTypeKey()
        );

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
            'service_charge_percent' => $serviceTotal > 0 ? PosServiceCharge::percent() : null,
            'service_charge_total' => $serviceTotal,
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

        $serviceType = $this->normalizeServiceType((string) ($meta['service_type'] ?? PosOrder::SERVICE_DINE_IN));
        $orderNotes = trim((string) ($meta['order_notes'] ?? ''));
        $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';

        if ($serviceType === PosOrder::SERVICE_DINE_IN) {
            if ($enableTables && $tableId === null && $guestName === '') {
                throw new RuntimeException('Table select karein.');
            }

            return [
                'customer_type' => $customerType,
                'service_type' => $serviceType,
                'guest_name' => $enableTables ? null : ($guestName !== '' ? $guestName : null),
                'room_no' => null,
                'waiter_name' => null,
                'order_notes' => null,
                'table_id' => $tableId,
                'serve_time' => null,
                'serve_date' => null,
                'serve_meal' => null,
            ];
        }

        if ($serviceType === PosOrder::SERVICE_DELIVERY) {
            if ($guestName === '') {
                throw new RuntimeException('Delivery ke liye customer name likhein.');
            }
            if ($roomNo === '') {
                throw new RuntimeException('Delivery ke liye phone number likhein.');
            }

            return [
                'customer_type' => $customerType,
                'service_type' => $serviceType,
                'guest_name' => $guestName,
                'room_no' => $roomNo,
                'waiter_name' => null,
                'order_notes' => $orderNotes !== '' ? $orderNotes : null,
                'table_id' => null,
                'serve_time' => null,
                'serve_date' => null,
                'serve_meal' => null,
            ];
        }

        return [
            'customer_type' => $customerType,
            'service_type' => PosOrder::SERVICE_TAKEAWAY,
            'guest_name' => null,
            'room_no' => null,
            'waiter_name' => null,
            'order_notes' => null,
            'table_id' => null,
            'serve_time' => null,
            'serve_date' => null,
            'serve_meal' => null,
        ];
    }

    private function normalizeServiceType(string $type): string
    {
        return in_array($type, [
            PosOrder::SERVICE_DINE_IN,
            PosOrder::SERVICE_TAKEAWAY,
            PosOrder::SERVICE_DELIVERY,
        ], true) ? $type : PosOrder::SERVICE_DINE_IN;
    }

    /**
     * @param  list<array{product_id:int,uom:string,qty:float,notes?:?string}>  $items
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: list<array<string, mixed>>}
     */
    public function buildLines(array $items, ?string $serviceType = null): array
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

        $net = round($subtotal - $discountTotal, 2);
        $serviceTotal = PosServiceCharge::amountOnNet($net, $serviceType);
        $grandTotal = round($net + $taxTotal + $serviceTotal, 2);

        return [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $lines];
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
