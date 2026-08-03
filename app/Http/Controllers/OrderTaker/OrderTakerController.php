<?php

namespace App\Http\Controllers\OrderTaker;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\Setting;
use App\Services\OrderTakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderTakerController extends Controller
{
    public function __construct(
        private readonly OrderTakerService $orderTaker
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('table_id') && ! $request->filled('order_id')) {
            $tableId = $request->integer('table_id');
            $occupied = $this->orderTaker->draftOrdersByTableId()->get($tableId);
            if ($occupied !== null) {
                if ($this->orderTaker->isPendingAmendable($occupied)) {
                    return redirect()->route('order-taker.index', ['order_id' => $occupied->id]);
                }

                $tableName = $occupied->table?->name ?? 'Table';

                return redirect()->route('order-taker.index')
                    ->with('error', sprintf(
                        '%s pehle se reserved hai (Order %s). Naya order yahan punch nahi ho sakta.',
                        $tableName,
                        $occupied->order_no
                    ));
            }
        }

        return view('order-taker.pos', $this->posViewData($request));
    }

    public function create(Request $request): RedirectResponse
    {
        $tableId = $request->integer('table_id');
        if ($tableId <= 0) {
            return redirect()->route('order-taker.index');
        }

        return redirect()->route('order-taker.index', ['table_id' => $tableId]);
    }

    public function edit(PosOrder $order): RedirectResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        return redirect()->route('order-taker.index', ['order_id' => $order->id]);
    }

    /** Lightweight board refresh for AJAX (no full product catalog). */
    public function board(): JsonResponse
    {
        $tableBoard = $this->orderTaker->tableBoard();

        return response()->json([
            'ok' => true,
            'table_board' => $tableBoard,
            'table_board_groups' => $this->orderTaker->tableBoardGrouped($tableBoard),
            'all_orders' => $this->orderTaker->allPendingOrdersForOrderTaker(),
            'has_session' => $this->orderTaker->openPosSession() !== null,
        ]);
    }

    /** Open pending bill without full page reload. */
    public function orderData(PosOrder $order): JsonResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $order->load(['items.product:id,name', 'table:id,name']);

        return response()->json([
            'ok' => true,
            'order' => [
                'id' => (int) $order->id,
                'order_no' => (string) $order->order_no,
                'table_id' => $order->table_id ? (int) $order->table_id : null,
                'table_name' => $order->table?->name,
                'service_type' => $order->serviceTypeKey(),
                'guest_name' => $order->guest_name,
                'room_no' => $order->room_no,
                'order_notes' => $order->order_notes,
                'kitchen_notes' => $order->kitchen_notes,
                'items' => $order->items->map(fn ($i) => [
                    'product_id' => (int) $i->product_id,
                    'name' => (string) ($i->product?->name ?? ''),
                    'uom' => (string) $i->uom,
                    'qty' => (float) $i->qty,
                    'unit_price' => (float) $i->unit_price,
                    'notes' => (string) ($i->notes ?? ''),
                    'kitchen_served' => $i->isKitchenServed(),
                    'kitchen_pending' => (bool) $i->kitchen_pending,
                    'kitchen_printed' => $i->kitchen_printed_at !== null,
                    'kitchen_locked_qty' => ($i->isKitchenServed() || $i->kitchen_pending || $i->kitchen_printed_at !== null)
                        ? (float) $i->qty
                        : 0,
                ])->values()->all(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);

        try {
            $order = $this->orderTaker->createForPos($data['meta'], $data['items']);
        } catch (\Throwable $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($this->wantsJson($request)) {
            return response()->json($this->mutationSuccessPayload(
                'Order kitchen printer + kitchen screen par bhej diya — POS pending bill bhi ready hai.',
                $order
            ));
        }

        return redirect()->route('order-taker.index')
            ->with('success', 'Order kitchen printer + kitchen screen par bhej diya — POS pending bill bhi ready hai.');
    }

    public function update(Request $request, PosOrder $order): RedirectResponse|JsonResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $data = $this->validated($request, $order);

        try {
            $order = $this->orderTaker->updatePendingBill($order, $data['items'], $data['meta']);
        } catch (\Throwable $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($this->wantsJson($request)) {
            return response()->json($this->mutationSuccessPayload(
                'Bill update ho gayi — kitchen printer + kitchen screen par update bhej diya.',
                $order
            ));
        }

        return redirect()->route('order-taker.index')
            ->with('success', 'Bill update ho gayi — kitchen printer + kitchen screen par update bhej diya.');
    }

    public function moveTable(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless($order->status === 'draft', 404);

        $data = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tenant.pos_tables,id'],
        ]);

        try {
            $result = DB::connection('tenant')->transaction(function () use ($order, $data) {
                $locked = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                return $this->orderTaker->moveOrderToTable($locked, (int) $data['table_id']);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Table move fail ho gayi.',
            ], 422);
        }

        /** @var PosOrder $moved */
        $moved = $result['order'];
        $tableBoard = $result['table_board'];

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Order %s: Table %s → %s',
                $moved->order_no,
                $result['from_table_name'],
                $result['to_table_name']
            ),
            'order_id' => (int) $moved->id,
            'order_no' => $moved->order_no,
            'from_table_id' => $result['from_table_id'],
            'from_table_name' => $result['from_table_name'],
            'to_table_id' => $result['to_table_id'],
            'to_table_name' => $result['to_table_name'],
            'table_board' => $tableBoard,
            'table_board_groups' => $this->orderTaker->tableBoardGrouped(
                is_array($tableBoard) ? $tableBoard : []
            ),
            'all_orders' => $this->orderTaker->allPendingOrdersForOrderTaker(),
            'print' => $result['print'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function posViewData(Request $request): array
    {
        $session = $this->orderTaker->openPosSession();
        $tableBoard = $this->orderTaker->tableBoard();
        $tableBoardGroups = $this->orderTaker->tableBoardGrouped($tableBoard);
        $allOrders = $this->orderTaker->allPendingOrdersForOrderTaker();

        $resumedOrder = null;
        $resumeProductIds = [];

        if ($request->filled('order_id')) {
            $candidate = PosOrder::query()->find($request->integer('order_id'));
            if ($candidate !== null && $this->orderTaker->isPendingAmendable($candidate)) {
                $resumedOrder = $candidate->load(['items.product', 'table']);
                $resumeProductIds = $resumedOrder->items->pluck('product_id')->unique()->values()->all();
            }
        }

        $products = $this->cachedPosProducts($resumeProductIds);

        $currency = Setting::get('currency_symbol', 'Rs.');
        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }

        $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
        $tables = $enableTables
            ? PosTable::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return compact('session', 'tableBoard', 'tableBoardGroups', 'allOrders', 'resumedOrder', 'products', 'currency', 'tables', 'enableTables') + [
            'taxMode' => $taxMode,
            'defaultTaxRate' => (float) Setting::get('tax_rate', 0),
            'serviceChargeEnabled' => Setting::get('pos_service_charge_enabled', '0') === '1',
            'serviceChargePercent' => (float) Setting::get('pos_service_charge_percent', 0),
            'startTableId' => $request->filled('order_id') ? null : ($request->filled('table_id') ? $request->integer('table_id') : null),
            'startServiceType' => $request->input('service_type'),
        ];
    }

    /**
     * @param  list<int|string>  $extraIds
     * @return \Illuminate\Support\Collection<int, InventoryProduct>
     */
    private function cachedPosProducts(array $extraIds = [])
    {
        $companyId = function_exists('current_company_id') ? (current_company_id() ?? 0) : 0;
        $cacheKey = 'order_taker:pos_products:c'.$companyId;

        /** @var \Illuminate\Support\Collection<int, InventoryProduct> $products */
        $products = Cache::remember($cacheKey, now()->addMinutes(15), function () {
            return InventoryProduct::query()
                ->where('active', true)
                ->where(function ($w) {
                    $w->where('for_pos', true)->orWhere('for_purchase', true);
                })
                ->orderBy('name')
                ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
                ->with(['category:id,name,parent_id', 'category.parent:id,name'])
                ->get(['id', 'sku', 'name', 'image_path', 'uom', 'price', 'for_pos', 'for_purchase', 'category_id']);
        });

        $extraIds = array_values(array_unique(array_filter(array_map('intval', $extraIds))));
        if ($extraIds === []) {
            return $products;
        }

        $have = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $missing = array_values(array_diff($extraIds, $have));
        if ($missing === []) {
            return $products;
        }

        $extra = InventoryProduct::query()
            ->whereIn('id', $missing)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->with(['category:id,name,parent_id', 'category.parent:id,name'])
            ->get(['id', 'sku', 'name', 'image_path', 'uom', 'price', 'for_pos', 'for_purchase', 'category_id']);

        return $products->concat($extra)->values();
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * @return array<string, mixed>
     */
    private function mutationSuccessPayload(string $message, PosOrder $order): array
    {
        $tableBoard = $this->orderTaker->tableBoard();

        return [
            'ok' => true,
            'message' => $message,
            'order_id' => (int) $order->id,
            'order_no' => $order->order_no,
            'table_board' => $tableBoard,
            'table_board_groups' => $this->orderTaker->tableBoardGrouped($tableBoard),
            'all_orders' => $this->orderTaker->allPendingOrdersForOrderTaker(),
        ];
    }

    /**
     * @return array{meta: array<string, mixed>, items: list<array{product_id:int,uom:string,qty:float,notes?:?string}>}
     */
    private function validated(Request $request, ?PosOrder $order = null): array
    {
        $itemsRaw = $request->input('items');
        if (is_string($itemsRaw)) {
            $decoded = json_decode($itemsRaw, true);
            $itemsRaw = is_array($decoded) ? $decoded : [];
            $request->merge(['items' => $itemsRaw]);
        }

        $pendingMode = $order !== null && $this->orderTaker->isPendingAmendable($order);

        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'items.*.uom' => ['required', 'string', 'max:30'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'kitchen_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $pendingMode) {
            $rules = array_merge($rules, [
                'customer_type' => ['required', 'in:mess_use,booking,ast_offr'],
                'service_type' => ['required', 'in:dine_in,takeaway,delivery'],
                'guest_name' => ['nullable', 'string', 'max:120'],
                'room_no' => ['nullable', 'string', 'max:50'],
                'order_notes' => ['nullable', 'string', 'max:1000'],
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
                    'kitchen_notes' => $validated['kitchen_notes'] ?? '',
                ],
                'items' => $items,
            ];
        }

        return [
            'meta' => [
                'customer_type' => $validated['customer_type'],
                'service_type' => $validated['service_type'],
                'guest_name' => $validated['guest_name'] ?? '',
                'room_no' => $validated['room_no'] ?? '',
                'order_notes' => $validated['order_notes'] ?? '',
                'table_id' => $validated['table_id'] ?? null,
                'kitchen_notes' => $validated['kitchen_notes'] ?? '',
            ],
            'items' => $items,
        ];
    }
}
