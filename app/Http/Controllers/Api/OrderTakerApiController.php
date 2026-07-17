<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\Setting;
use App\Support\LanServerUrl;
use App\Support\ServeMealSchedule;
use App\Services\OrderTakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTakerApiController extends Controller
{
    public function __construct(
        private readonly OrderTakerService $orderTaker
    ) {}

    public function bootstrap(): JsonResponse
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->where('for_pos', true)->orWhere('for_purchase', true);
            })
            ->orderBy('name')
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->get(['id', 'sku', 'name', 'uom', 'price', 'for_pos', 'for_purchase']);

        $tablesEnabled = (string) Setting::get('pos_enable_tables', '1') !== '0';
        $tables = $tablesEnabled
            ? PosTable::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $waiters = Employee::query()->where('active', true)->waiters()->orderBy('name')->get(['id', 'name']);

        return response()->json(array_merge([
            'currency' => Setting::get('currency_symbol', 'Rs.'),
            'tables_enabled' => $tablesEnabled,
            'products' => $products->map(fn (InventoryProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'base_uom' => $p->uom,
                'price' => (float) $p->price,
                'for_pos' => (bool) ($p->for_pos ?? false),
                'for_purchase' => (bool) ($p->for_purchase ?? false),
                'uoms' => $p->uomsForForms(),
            ])->values(),
            'tables' => $tables->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'waiters' => $waiters->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values(),
            'checked_in_rooms' => $this->orderTaker->checkedInRooms()->values(),
            'serve_meals' => ServeMealSchedule::optionsForUi(),
            'customer_types' => [
                ['key' => 'mess_use', 'label' => 'Walk-In'],
                ['key' => 'booking', 'label' => 'In-House'],
                ['key' => 'ast_offr', 'label' => PosOrder::MESS_BILL_LABEL],
            ],
        ], LanServerUrl::apiPayload()));
    }

    public function pending(): JsonResponse
    {
        $orders = $this->orderTaker->pendingPosOrders();

        return response()->json([
            'orders' => $orders->map(fn (PosOrder $o) => $this->orderSummary($o))->values(),
        ]);
    }

    public function show(PosOrder $order): JsonResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $order->load(['items.product', 'table']);

        return response()->json([
            'order' => $this->orderDetail($order),
            'pending_mode' => true,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $order = $this->orderTaker->createForPos($data['meta'], $data['items']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Order kitchen screen par bhej diya — POS pending bill bhi ready hai.',
            'order' => $this->orderDetail($order),
        ], 201);
    }

    public function update(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $data = $this->validated($request, $order);

        try {
            $order = $this->orderTaker->updatePendingBill($order, $data['items']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Bill update ho gayi — kitchen screen par naye items dikhenge.',
            'order' => $this->orderDetail($order),
        ]);
    }

    /**
     * @return array{meta: array<string, mixed>, items: list<array{product_id:int,uom:string,qty:float,notes?:?string}>}
     */
    private function validated(Request $request, ?PosOrder $order = null): array
    {
        $pendingMode = $order !== null && $this->orderTaker->isPendingAmendable($order);

        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'items.*.uom' => ['required', 'string', 'max:30'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];

        if (! $pendingMode) {
            $rules = array_merge($rules, [
                'customer_type' => ['required', 'in:mess_use,booking,ast_offr'],
                'guest_name' => ['nullable', 'string', 'max:120'],
                'room_no' => ['required_if:customer_type,booking', 'nullable', 'string', 'max:50'],
                'waiter_name' => ['required_if:customer_type,mess_use', 'nullable', 'string', 'max:120'],
                'serve_time' => ['nullable', 'string', 'max:10'],
                'serve_date' => ['nullable', 'date_format:Y-m-d'],
                'serve_meal' => ['nullable', 'string', 'in:'.implode(',', ServeMealSchedule::keys())],
                'table_id' => ['nullable', 'integer', 'exists:tenant.pos_tables,id'],
            ]);
        }

        $validated = $request->validate($rules);

        $items = [];
        foreach ($validated['items'] as $row) {
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'uom' => trim((string) $row['uom']),
                'qty' => (float) $row['qty'],
                'notes' => isset($row['notes']) ? trim((string) $row['notes']) : null,
            ];
        }

        if ($pendingMode) {
            return [
                'meta' => [
                    'customer_type' => $order->customerTypeKey(),
                    'guest_name' => $order->guest_name ?? '',
                    'room_no' => $order->room_no ?? '',
                    'waiter_name' => $order->waiter_name ?? '',
                    'table_id' => $order->table_id,
                ],
                'items' => $items,
            ];
        }

        return [
            'meta' => [
                'customer_type' => $validated['customer_type'],
                'guest_name' => $validated['guest_name'] ?? '',
                'room_no' => $validated['room_no'] ?? '',
                'waiter_name' => $validated['waiter_name'] ?? '',
                'serve_time' => $validated['serve_time'] ?? '',
                'serve_date' => $validated['serve_date'] ?? '',
                'serve_meal' => $validated['serve_meal'] ?? '',
                'table_id' => $validated['table_id'] ?? null,
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummary(PosOrder $o): array
    {
        $o->loadMissing(['table:id,name', 'items.product:id,name']);

        $tableRoom = [];
        if ($o->table) {
            $tableRoom[] = $o->table->name;
        }
        if ($o->room_no) {
            $tableRoom[] = $o->room_no;
        }

        $customerType = $o->customerTypeKey();
        $orderAt = $o->ready_for_pos_at ?? $o->created_at;
        $serveTime = trim((string) ($o->serve_time ?? ''));
        $serveAt = $o->serveAt();

        return [
            'id' => $o->id,
            'order_no' => $o->order_no,
            'from_order_taker' => $o->isFromOrderTaker(),
            'customer_type' => $customerType,
            'customer_type_label' => match ($customerType) {
                'ast_offr' => PosOrder::MESS_BILL_LABEL,
                'booking' => 'In-House',
                default => 'Walk-In',
            },
            'guest_name' => $o->guest_name,
            'table_room' => $tableRoom !== [] ? implode(' / ', $tableRoom) : null,
            'waiter_name' => $o->waiter_name,
            'serve_time' => $serveTime !== '' ? $serveTime : null,
            'serve_date' => $o->serve_date instanceof \Illuminate\Support\Carbon
                ? $o->serve_date->format('Y-m-d')
                : (trim((string) ($o->serve_date ?? '')) ?: null),
            'serve_meal' => ServeMealSchedule::isValid($o->serve_meal) ? $o->serve_meal : null,
            'serve_meal_label' => ServeMealSchedule::label($o->serve_meal),
            'serve_at_label' => $o->serveScheduleLabel(),
            'order_time' => $o->isFromOrderTaker() && $orderAt ? $orderAt->format('H:i') : null,
            'served_at' => $o->kitchen_completed_at?->format('H:i'),
            'kitchen_status_label' => $o->pendingKitchenStatusLabel(),
            'kitchen_status_badge' => $o->pendingKitchenStatusBadgeClass(),
            'grand_total' => (float) $o->grand_total,
            'items_count' => $o->items_count ?? $o->items->count(),
            'items' => $o->items->map(fn ($line) => [
                'name' => $line->product?->name ?? 'Item',
                'qty' => (float) $line->qty,
                'kitchen_served' => $line->isKitchenServed(),
                'kitchen_pending' => (bool) $line->kitchen_pending,
                'kitchen_served_at' => $line->kitchen_served_at?->format('H:i'),
            ])->values(),
            'editable' => $this->orderTaker->isPendingAmendable($o),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderDetail(PosOrder $order): array
    {
        $order->loadMissing(['items.product', 'table']);

        $summary = $this->orderSummary($order);

        $summary['serve_time'] = $order->serve_time;
        $summary['serve_date'] = $order->serve_date instanceof \Illuminate\Support\Carbon
            ? $order->serve_date->format('Y-m-d')
            : (trim((string) ($order->serve_date ?? '')) ?: null);
        $summary['serve_meal'] = ServeMealSchedule::isValid($order->serve_meal) ? $order->serve_meal : null;
        $summary['table_id'] = $order->table_id;
        $summary['room_no'] = $order->room_no;
        $summary['subtotal'] = (float) $order->subtotal;
        $summary['tax_total'] = (float) $order->tax_total;
        $summary['cart'] = $order->items->map(fn ($i) => [
            'product_id' => (int) $i->product_id,
            'name' => (string) ($i->product?->name ?? ''),
            'uom' => (string) $i->uom,
            'qty' => (float) $i->qty,
            'unit_price' => (float) $i->unit_price,
            'notes' => (string) ($i->notes ?? ''),
            'kitchen_served' => $i->isKitchenServed(),
            'kitchen_pending' => (bool) $i->kitchen_pending,
            'kitchen_served_at' => $i->kitchen_served_at?->format('H:i'),
        ])->values();

        return $summary;
    }
}
