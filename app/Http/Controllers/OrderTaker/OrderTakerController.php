<?php

namespace App\Http\Controllers\OrderTaker;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use Illuminate\Http\RedirectResponse;
use App\Models\Setting;
use App\Support\ServeMealSchedule;
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
            if ($occupied !== null && $this->orderTaker->isPendingAmendable($occupied)) {
                return redirect()->route('order-taker.index', ['order_id' => $occupied->id]);
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
     * @return array<string, mixed>
     */
    private function posViewData(Request $request): array
    {
        $session = $this->orderTaker->openPosSession();
        $tableBoard = $this->orderTaker->tableBoard();
        $pendingBills = $this->orderTaker->pendingPosOrders();

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

        $waiters = Employee::query()->where('active', true)->waiters()->orderBy('name')->get(['id', 'name']);
        $currency = Setting::get('currency_symbol', 'Rs.');
        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }

        return compact('session', 'tableBoard', 'pendingBills', 'resumedOrder', 'products', 'waiters', 'currency') + [
            'taxMode' => $taxMode,
            'defaultTaxRate' => (float) Setting::get('tax_rate', 0),
            'startTableId' => $request->filled('order_id') ? null : ($request->filled('table_id') ? $request->integer('table_id') : null),
            'serveMealsJson' => ServeMealSchedule::optionsForUi(),
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
}
