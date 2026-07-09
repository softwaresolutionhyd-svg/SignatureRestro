<?php

namespace App\Http\Controllers\OrderTaker;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\Setting;
use App\Support\ServeMealSchedule;
use App\Services\OrderTakerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTakerController extends Controller
{
    public function __construct(
        private readonly OrderTakerService $orderTaker
    ) {}

    public function index(): View
    {
        $pendingBills = $this->orderTaker->pendingPosOrders();

        return view('order-taker.index', compact('pendingBills'));
    }

    public function create(): View
    {
        return $this->formView(new PosOrder([
            'customer_type' => 'mess_use',
            'sale_mode' => 'customer',
        ]), false);
    }

    public function edit(PosOrder $order): View
    {
        abort_unless($this->orderTaker->isPendingAmendable($order), 404);

        $order->load(['items.product', 'table']);

        return $this->formView($order, true);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $order = $this->orderTaker->createForPos($data['meta'], $data['items']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('order-taker.index')
            ->with('success', 'Order kitchen screen par bhej diya — POS pending bill bhi ready hai.');
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
            ->with('success', 'Bill update ho gayi — kitchen screen par naye items dikhenge.');
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

    private function formView(PosOrder $order, bool $pendingPosMode = false): View
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->where('for_pos', true)->orWhere('for_purchase', true);
            })
            ->orderBy('name')
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->get(['id', 'sku', 'name', 'uom', 'price', 'for_pos', 'for_purchase']);

        $productsJson = $products->map(function (InventoryProduct $p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'base_uom' => $p->uom,
                'price' => (float) $p->price,
                'for_pos' => (bool) ($p->for_pos ?? false),
                'for_purchase' => (bool) ($p->for_purchase ?? false),
                'uoms' => $p->uomsForForms(),
            ];
        })->values();

        $cartJson = $order->exists
            ? $order->items->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'name' => (string) ($i->product?->name ?? ''),
                'uom' => (string) $i->uom,
                'qty' => (float) $i->qty,
                'unit_price' => (float) $i->unit_price,
                'notes' => (string) ($i->notes ?? ''),
                'kitchen_served' => $i->isKitchenServed(),
                'kitchen_pending' => (bool) $i->kitchen_pending,
            ])->values()
            : collect();

        $tables = (string) Setting::get('pos_enable_tables', '1') !== '0'
            ? PosTable::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $waiters = Employee::query()->where('active', true)->waiters()->orderBy('name')->get(['id', 'name']);
        $checkedInRooms = $this->orderTaker->checkedInRooms();
        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('order-taker.form', compact(
            'order',
            'productsJson',
            'cartJson',
            'tables',
            'waiters',
            'checkedInRooms',
            'currency',
            'pendingPosMode',
        ) + [
            'serveMealsJson' => ServeMealSchedule::optionsForUi(),
        ]);
    }
}
