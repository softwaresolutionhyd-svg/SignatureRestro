<?php

namespace App\Http\Controllers\OrderTaker;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosTable;
use Illuminate\Http\RedirectResponse;
use App\Models\Setting;
use App\Services\OrderTakerService;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->orderTaker->createForPos($data['meta'], $data['items']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('order-taker.index')
            ->with('success', 'Order kitchen printer + kitchen screen par bhej diya — POS pending bill bhi ready hai.');
    }

    public function update(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $data = $this->validated($request, $order);

        try {
            $this->orderTaker->updatePendingBill($order, $data['items']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('order-taker.index')
            ->with('success', 'Bill update ho gayi — kitchen printer + kitchen screen par update bhej diya.');
    }

    /**
     * @return array<string, mixed>
     */
    private function posViewData(Request $request): array
    {
        $session = $this->orderTaker->openPosSession();
        $tableBoard = $this->orderTaker->tableBoard();
        $pendingBills = $this->orderTaker->pendingPosOrders();
        $myOrders = $this->orderTaker->myPunchedOrders();

        $resumedOrder = null;
        $resumeProductIds = [];

        if ($request->filled('order_id')) {
            $candidate = PosOrder::query()->find($request->integer('order_id'));
            if ($candidate !== null && $this->orderTaker->isPendingAmendable($candidate)) {
                $resumedOrder = $candidate->load(['items.product', 'table']);
                $resumeProductIds = $resumedOrder->items->pluck('product_id')->unique()->values()->all();
            }
        }

        $products = InventoryProduct::query()
            ->where(function ($q) use ($resumeProductIds) {
                $q->where(function ($w) {
                    $w->where('active', true)
                        ->where(function ($inner) {
                            $inner->where('for_pos', true)
                                ->orWhere('for_purchase', true);
                        });
                });
                if ($resumeProductIds !== []) {
                    $q->orWhereIn('id', $resumeProductIds);
                }
            })
            ->orderBy('name')
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->with(['category:id,name,parent_id', 'category.parent:id,name'])
            ->get(['id', 'sku', 'name', 'image_path', 'uom', 'price', 'for_pos', 'for_purchase', 'category_id']);

        $currency = Setting::get('currency_symbol', 'Rs.');
        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }

        $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
        $tables = $enableTables
            ? PosTable::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return compact('session', 'tableBoard', 'pendingBills', 'myOrders', 'resumedOrder', 'products', 'currency', 'tables', 'enableTables') + [
            'taxMode' => $taxMode,
            'defaultTaxRate' => (float) Setting::get('tax_rate', 0),
            'serviceChargeEnabled' => Setting::get('pos_service_charge_enabled', '0') === '1',
            'serviceChargePercent' => (float) Setting::get('pos_service_charge_percent', 0),
            'startTableId' => $request->filled('order_id') ? null : ($request->filled('table_id') ? $request->integer('table_id') : null),
            'startServiceType' => $request->input('service_type'),
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
            ],
            'items' => $items,
        ];
    }
}
