<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosCashMovementRequest;
use App\Http\Requests\PosCheckoutRequest;
use App\Http\Requests\PosCloseSessionRequest;
use App\Http\Requests\PosOpenSessionRequest;
use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryUnit;
use App\Models\ManufacturingBom;
use App\Models\PosCashMovement;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Models\PosTable;
use App\Models\Employee;
use App\Models\RoomBooking;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\StockUpdated;
use App\Support\DailyOrderNumber;
use App\Support\ActivityLogger;
use App\Services\KitchenService;
use App\Services\ManufacturingStockService;
use App\Services\AutoJournalService;
use App\Services\OrderTakerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    private const FIFO_EPSILON = 0.000001;

    public function __construct(
        private readonly ManufacturingStockService $manufacturingStock,
        private readonly AutoJournalService $autoJournal,
        private readonly OrderTakerService $orderTaker,
    ) {}

    public function restaurant(Request $request): View|RedirectResponse
    {
        if ($request->filled('resume_order')) {
            $session = $this->ensureOpenSessionForUser(Auth::user());
            $draft = $this->findDraftOrderForSession($session, $request->integer('resume_order'));
            if ($draft === null) {
                return redirect()
                    ->route('restaurant-pos.index')
                    ->with('warning', 'Pending order maujood nahi ya pehle se band ho chuki hai.');
            }
        }

        return view('pos.restaurant', $this->posIndexViewData($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function posIndexViewData(Request $request): array
    {
        $this->ensurePosTablesSchema();
        $this->ensurePosOrderSchemaForCheckout();
        $this->ensurePosOrderItemsSchema();
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();
        $session = $this->ensureOpenSessionForUser($user);

        $heldOrders = collect();
        $paidOrders = collect();
        $paidBillsDetail = collect();
        $pendingBillsDetail = collect();
        $resumedOrder = null;
        $resumeProductIds = [];
        $tableBoard = [];
        $tables = collect();
        $rawEnableTables = Setting::get('pos_enable_tables', '1');
        $enableTables = (string) $rawEnableTables !== '0';

        $heldOrders = $this->heldOrdersForSession($session);

        $paidOrders = PosOrder::query()
            ->where('session_id', $session->id)
            ->where('status', 'paid')
            ->with(['table:id,name', 'payments:id,order_id,method,amount', 'items.product:id,name'])
            ->withCount('items')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        $paidBillsDetail = $paidOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order))
            ->values();

        $pendingBillsDetail = $heldOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order))
            ->values();

        if ($request->filled('resume_order')) {
            $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
                && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

            $resumedOrder = $this->findDraftOrderForSession($session, $request->integer('resume_order'));
            if ($resumedOrder !== null) {
                $resumedOrder->load('items');
            }
            if ($resumedOrder && $hasOrderTakerColumns && $resumedOrder->isReadyForPosPickup()) {
                $resumedOrder->update(['session_id' => $session->id]);
                $resumedOrder->refresh();
            }
            if ($resumedOrder) {
                $resumeProductIds = $resumedOrder->items->pluck('product_id')->unique()->values()->all();
            }
        }

        if ($enableTables) {
            $tableBoard = $this->orderTaker->tableBoard();
            $tables = PosTable::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $waiters = Employee::query()
            ->where('active', true)
            ->waiters()
            ->orderBy('name')
            ->get(['id', 'name']);

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
            ->withExists(['manufacturingBoms' => fn ($q) => $q->where('active', true)])
            ->get(['id', 'sku', 'barcode', 'name', 'image_path', 'uom', 'price', 'cost', 'gas_charges', 'extra_costs', 'qty_on_hand', 'reorder_level', 'active', 'for_pos', 'for_purchase', 'category_id']);

        // Recent contacts for quick credit selection
        $contacts = Contact::where('active', true)->orderBy('name')->get(['id','name','phone']);

        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }
        $defaultTaxRate = (float) Setting::get('tax_rate', 0);

        $posSettings = [
            'show_cash_movements' => Setting::get('pos_show_cash_movements', '1') === '1',
            'show_held_orders' => Setting::get('pos_show_held_orders', '1') === '1',
            'show_customer_section' => Setting::get('pos_show_customer_section', '1') === '1',
            'show_hold_button' => Setting::get('pos_show_hold_button', '1') === '1',
            'hold_only' => Setting::get('pos_hold_only', '0') === '1',
            'show_refund_toggle' => Setting::get('pos_show_refund_toggle', '1') === '1',
            'show_discount' => Setting::get('pos_show_discount', '1') === '1',
            'allow_bill_print' => Setting::get('pos_allow_bill_print', '1') === '1',
            'enable_tables' => $enableTables,
            'tax_mode' => $taxMode,
            'default_tax_rate' => $defaultTaxRate,
            'resume_bill_tax_percent' => null,
            'resume_bill_discount_percent' => null,
            'resume_table_id' => $resumedOrder?->table_id ? (int) $resumedOrder->table_id : null,
            'resume_guest_name' => $resumedOrder?->guest_name ?? null,
            'resume_room_no' => $resumedOrder?->room_no ?? null,
            'resume_waiter_name' => $resumedOrder?->waiter_name ?? null,
            'resume_order_notes' => $resumedOrder?->order_notes ?? null,
            'resume_serve_time' => $resumedOrder?->serve_time ?? null,
            'resume_serve_date' => $resumedOrder?->serve_date?->format('Y-m-d') ?? null,
            'resume_customer_type' => $resumedOrder?->customerTypeKey() ?? null,
            'resume_service_type' => $resumedOrder?->serviceTypeKey() ?? 'dine_in',
            'resume_is_credit' => (bool) ($resumedOrder?->is_credit ?? false),
            'resume_contact_id' => $resumedOrder?->contact_id ? (int) $resumedOrder->contact_id : null,
            'resume_sale_mode' => $resumedOrder
                ? ($resumedOrder->sale_mode === 'staff' ? 'staff' : 'customer')
                : null,
        ];

        if ($resumedOrder !== null && $resumedOrder->bill_tax_percent !== null) {
            $posSettings['resume_bill_tax_percent'] = (float) $resumedOrder->bill_tax_percent;
        }

        if ($resumedOrder !== null) {
            if ($resumedOrder->bill_discount_percent !== null) {
                $posSettings['resume_bill_discount_percent'] = (float) $resumedOrder->bill_discount_percent;
            } elseif ((float) $resumedOrder->subtotal > 0 && (float) $resumedOrder->discount_total > 0) {
                $posSettings['resume_bill_discount_percent'] = round(
                    (float) $resumedOrder->discount_total / (float) $resumedOrder->subtotal * 100,
                    3
                );
            }
        }

        $sessionCashExpected = $this->sessionCashBreakdown($session);
        $sessionPosStats = $this->sessionPosStats($session);
        $checkedInRooms = $this->checkedInRoomsForPos();
        $recentDailyClosings = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'closed')
            ->orderByDesc('closed_at')
            ->limit(14)
            ->get([
                'id',
                'business_date',
                'closed_at',
                'closing_cash',
                'closing_bank',
                'closing_card',
                'amount_to_collect',
                'note',
            ]);

        return compact('session', 'products', 'heldOrders', 'paidOrders', 'paidBillsDetail', 'pendingBillsDetail', 'resumedOrder', 'contacts', 'posSettings', 'sessionCashExpected', 'sessionPosStats', 'tables', 'tableBoard', 'checkedInRooms', 'waiters', 'recentDailyClosings');
    }

    public function sync(Request $request): JsonResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $session = $this->ensureOpenSessionForUser(Auth::user());

        $heldOrders = $this->heldOrdersForSession($session);
        $pending = $heldOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order))
            ->values();

        $resumed = null;
        if ($request->filled('resume_order_id')) {
            $resumedOrder = $this->findDraftOrderForSession(
                $session,
                $request->integer('resume_order_id')
            );

            if ($resumedOrder !== null) {
                $resumedOrder->loadMissing(['items.product:id,name']);
                $resumed = [
                    'id' => $resumedOrder->id,
                    'items' => $resumedOrder->items->map(fn (PosOrderItem $item) => [
                        'product_id' => (int) $item->product_id,
                        'uom' => (string) $item->uom,
                        'qty' => (float) $item->qty,
                        'unit_price' => (float) $item->unit_price,
                        'tax_percent' => (float) $item->tax_percent,
                        'notes' => (string) ($item->notes ?? ''),
                        'kitchen_served' => $item->isKitchenServed(),
                        'kitchen_pending' => (bool) $item->kitchen_pending,
                    ])->values()->all(),
                ];
            }
        }

        return response()->json([
            'pending' => $pending,
            'count' => $pending->count(),
            'resumed' => $resumed,
            'table_board' => $this->orderTaker->tableBoard(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    private function heldOrdersForSession(PosSession $session): \Illuminate\Support\Collection
    {
        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

        $heldOrders = PosOrder::query()
            ->where('status', 'draft')
            ->when($hasOrderTakerColumns, function ($q) use ($session) {
                $q->where(function ($outer) use ($session) {
                    $outer->where(function ($w) use ($session) {
                        $w->where('session_id', $session->id)
                            ->where(function ($inner) {
                                $inner->whereNull('order_source')
                                    ->orWhere('order_source', 'pos');
                            });
                    })->orWhere(function ($w) use ($session) {
                        $w->where('order_source', OrderTakerService::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at')
                            ->where(function ($inner) use ($session) {
                                $inner->whereNull('session_id')
                                    ->orWhere('session_id', $session->id);
                            });
                    });
                });
            }, function ($q) use ($session) {
                $q->where('session_id', $session->id);
            })
            ->with(['items.product:id,name', 'table:id,name'])
            ->withCount('items')
            ->latest('id')
            ->get();

        foreach ($heldOrders as $draft) {
            if ($this->repairDraftOrderIfNeeded($draft)) {
                $draft->refresh();
                $draft->loadMissing(['items.product:id,name', 'table:id,name']);
            }
        }

        return $heldOrders
            ->filter(fn (PosOrder $order) => $order->isDueForServeDay())
            ->values();
    }

    private function findDraftOrderForSession(PosSession $session, int $orderId): ?PosOrder
    {
        if ($orderId <= 0) {
            return null;
        }

        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

        return PosOrder::query()
            ->where('id', $orderId)
            ->where('status', 'draft')
            ->where(function ($q) use ($session, $hasOrderTakerColumns) {
                $q->where('session_id', $session->id);
                if ($hasOrderTakerColumns) {
                    $q->orWhere(function ($w) {
                        $w->where('order_source', OrderTakerService::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at');
                    });
                }
            })
            ->first();
    }

    /**
     * @return array{
     *   held_count:int,
     *   can_close_session:bool,
     *   sales_count:int,
     *   sales_total:float,
     *   refunds_count:int,
     *   refunds_total:float,
     *   credit_sales_count:int,
     *   credit_sales_total:float,
     *   payments_cash:float,
     *   payments_card:float,
     *   payments_bank:float
     * }
     */
    private function sessionPosStats(PosSession $session): array
    {
        $heldCount = $this->heldOrdersForSession($session)->count();

        $sessionId = $session->id;
        $paid = PosOrder::query()->where('session_id', $sessionId)->where('status', 'paid');

        $salesCount = (int) (clone $paid)->where('type', 'sale')->count();
        $salesTotal = (float) (clone $paid)->where('type', 'sale')->sum('grand_total');

        $refundsCount = (int) (clone $paid)->where('type', 'refund')->count();
        $refundsTotal = (float) (clone $paid)->where('type', 'refund')->sum('grand_total');

        $creditCount = (int) (clone $paid)->where('type', 'sale')->where('is_credit', true)->count();
        $creditTotal = (float) (clone $paid)->where('type', 'sale')->where('is_credit', true)->sum('grand_total');

        $salePayTotals = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $sessionId)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->selectRaw('pos_payments.method as payment_method, SUM(pos_payments.amount) as total')
            ->groupBy('pos_payments.method')
            ->pluck('total', 'payment_method');

        $refundPayTotals = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $sessionId)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'refund')
            ->selectRaw('pos_payments.method as payment_method, SUM(pos_payments.amount) as total')
            ->groupBy('pos_payments.method')
            ->pluck('total', 'payment_method');

        $net = static function (string $m) use ($salePayTotals, $refundPayTotals): float {
            return (float) (($salePayTotals[$m] ?? 0) - ($refundPayTotals[$m] ?? 0));
        };

        return [
            'held_count' => $heldCount,
            'can_close_session' => $heldCount === 0,
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'refunds_count' => $refundsCount,
            'refunds_total' => $refundsTotal,
            'credit_sales_count' => $creditCount,
            'credit_sales_total' => $creditTotal,
            'payments_cash' => $net('cash'),
            'payments_card' => $net('card'),
            'payments_bank' => $net('bank'),
        ];
    }

    /**
     * @return array{opening_cash: float, cash_from_sales: float, cash_refunds_paid: float, cash_in: float, cash_out: float, expected_closing: float}
     */
    private function sessionCashBreakdown(PosSession $session): array
    {
        $cashFromSales = (float) PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashRefundsPaid = (float) PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'refund')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashIn = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'in')->sum('amount');
        $cashOut = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'out')->sum('amount');

        $opening = (float) $session->opening_cash;
        $expected = round($opening + $cashFromSales - $cashRefundsPaid + $cashIn - $cashOut, 2);

        return [
            'opening_cash' => $opening,
            'cash_from_sales' => $cashFromSales,
            'cash_refunds_paid' => $cashRefundsPaid,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_closing' => $expected,
        ];
    }

    public function openSession(PosOpenSessionRequest $request): RedirectResponse
    {
        $this->ensureOpenSessionForUser(Auth::user());

        return redirect()->route('restaurant-pos.index')->with('success', 'POS ready.');
    }

    public function closeSession(PosCloseSessionRequest $request): RedirectResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();
        $session = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->firstOrFail();

        $heldDraft = (int) PosOrder::query()
            ->where('session_id', $session->id)
            ->where('status', 'draft')
            ->count();
        if ($heldDraft > 0) {
            return back()->with(
                'error',
                "Day close nahi ho sakta: {$heldDraft} pending bill(s) abhi bhi maujood hain. Pehle Resume kar ke complete karein ya Discard karein."
            );
        }

        $this->finalizeSessionClose($session, $request->note);

        return redirect()->route('restaurant-pos.index')->with('success', 'Aaj ki daily closing save ho gayi.');
    }

    private function finalizeSessionClose(PosSession $session, ?string $note = null): void
    {
        $stats = $this->sessionPosStats($session);
        $cashBreakdown = $this->sessionCashBreakdown($session);
        $amountToCollect = round(
            $stats['payments_cash'] + $cashBreakdown['cash_in'] - $cashBreakdown['cash_out'],
            2
        );

        $session->update([
            'status' => 'closed',
            'closing_cash' => $stats['payments_cash'],
            'closing_bank' => $stats['payments_bank'],
            'closing_card' => $stats['payments_card'],
            'amount_to_collect' => $amountToCollect,
            'expected_cash' => $amountToCollect,
            'cash_difference' => 0.0,
            'closed_at' => now(),
            'note' => $note ?: $session->note,
            'business_date' => $session->business_date ?? now()->toDateString(),
        ]);
    }

    private function ensureOpenSessionForUser(User $user): PosSession
    {
        $today = now()->toDateString();

        $session = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->where(function ($q) use ($today) {
                $q->whereDate('opened_at', $today)
                    ->orWhere('business_date', $today);
            })
            ->latest('id')
            ->first();

        if ($session !== null) {
            return $session;
        }

        $staleOpen = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->whereDate('opened_at', '<', $today)
            ->orderBy('id')
            ->get();

        $newSession = null;
        foreach ($staleOpen as $stale) {
            if ($newSession === null) {
                $newSession = $this->createDailySession($user);
            }

            PosOrder::query()
                ->where('session_id', $stale->id)
                ->where('status', 'draft')
                ->update(['session_id' => $newSession->id]);

            $this->finalizeSessionClose($stale, 'Auto day rollover');
        }

        if ($newSession !== null) {
            return $newSession;
        }

        return $this->createDailySession($user);
    }

    private function createDailySession(User $user): PosSession
    {
        return PosSession::create([
            'session_no' => 'DAY-' . now()->format('dmy') . '-' . $user->id,
            'business_date' => now()->toDateString(),
            'user_id' => $user->id,
            'status' => 'open',
            'opening_cash' => 0,
            'opened_at' => now(),
        ]);
    }

    private function ensurePosSessionDailyClosingSchema(): void
    {
        if (! Schema::hasTable('pos_sessions')) {
            return;
        }

        if (Schema::hasColumn('pos_sessions', 'closing_bank')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sessions', 'business_date')) {
                $table->date('business_date')->nullable()->after('session_no');
            }
            if (! Schema::hasColumn('pos_sessions', 'closing_bank')) {
                $table->decimal('closing_bank', 14, 2)->nullable()->after('closing_cash');
            }
            if (! Schema::hasColumn('pos_sessions', 'closing_card')) {
                $table->decimal('closing_card', 14, 2)->nullable()->after('closing_bank');
            }
            if (! Schema::hasColumn('pos_sessions', 'amount_to_collect')) {
                $table->decimal('amount_to_collect', 14, 2)->nullable()->after('closing_card');
            }
        });
    }

    public function addCashMovement(PosCashMovementRequest $request): RedirectResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $session = $this->ensureOpenSessionForUser(Auth::user());

        PosCashMovement::create([
            'session_id' => $session->id,
            'user_id' => Auth::id(),
            'type' => $request->type,
            'amount' => (float) $request->amount,
            'reason' => $request->reason,
        ]);

        return back()->with('success', 'Cash movement saved.');
    }

    public function checkout(PosCheckoutRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensurePosTablesSchema();
        $this->ensurePosOrderSchemaForCheckout();
        $this->ensurePosOrderItemsSchema();
        $this->ensurePosSessionDailyClosingSchema();

        $session = $this->ensureOpenSessionForUser(Auth::user());
        $wantsJson = $request->expectsJson() && $this->isRestaurantPosRequest($request);

        $serviceType = null;
        $restaurantTableId = null;

        if ($this->isRestaurantPosRequest($request)) {
            $restaurantMeta = $this->restaurantPosOrderMeta($request);
            $customerType = $restaurantMeta['customer_type'];
            $serviceType = $restaurantMeta['service_type'];
            $isCredit = $restaurantMeta['is_credit'];
            $contactId = $restaurantMeta['contact_id'];
            if ($isCredit && ! $contactId) {
                if ($wantsJson) {
                    return response()->json(['message' => 'Credit sale ke liye contact select karein.'], 422);
                }

                return back()->with('error', 'Credit sale ke liye contact select karein.');
            }
            $saleMode = $restaurantMeta['sale_mode'];
            $guestName = $restaurantMeta['guest_name'];
            $roomNo = $restaurantMeta['room_no'];
            $waiterName = $restaurantMeta['waiter_name'];
            $serveTime = $restaurantMeta['serve_time'];
            $serveDate = $restaurantMeta['serve_date'];
            $orderNotes = $restaurantMeta['order_notes'];
            $restaurantTableId = $restaurantMeta['table_id'];
        } else {
            // Detect credit sale
            $customerType = $this->normalizeCustomerType($request->input('customer_type'));
            $isCredit = $request->boolean('is_credit');
            $contactId = $isCredit ? $request->integer('contact_id') : null;

            if ($customerType === 'ast_offr') {
                $isCredit = true;
                $contactId = $request->integer('contact_id') ?: null;
                if (! $contactId) {
                    return back()->with('error', PosOrder::MESS_BILL_LABEL.' ke liye officer select karein.');
                }
            } elseif ($isCredit && ! $contactId) {
                return back()->with('error', 'Please select a contact for credit sale.');
            }

            $saleMode = $request->input('sale_mode') === 'staff' ? 'staff' : 'customer';
            if ($customerType === 'ast_offr') {
                $saleMode = 'staff';
            }
        }

        $resumeOrderId = $request->integer('resume_order_id') ?: null;
        $itemsNormalized = $this->normalizePosCheckoutItems(
            $request->items,
            $customerType,
            $saleMode,
            (string) $request->type,
            $request->boolean('staff_include_gas')
        );
        if ($request->type === 'sale') {
            $this->validatePosProductsForCustomerType($itemsNormalized, $customerType);
            $this->validatePosStockForSale($itemsNormalized);
        }

        $this->assertKitchenVoidPermission($request);

        if ($resumeOrderId) {
            $resumeDraft = PosOrder::query()
                ->where('id', $resumeOrderId)
                ->where('status', 'draft')
                ->where('session_id', $session->id)
                ->first();
            if ($resumeDraft) {
                $this->assertKitchenLockedQuantitiesPreserved(
                    $resumeDraft->items()->get()->all(),
                    $itemsNormalized,
                    $this->normalizedKitchenVoids($request)
                );
            }
        }

        if (! $this->isRestaurantPosRequest($request)) {
            $guestName = $this->nullableText($request->input('guest_name'));
            $roomNo = $this->nullableText($request->input('room_no'));
            $waiterName = $this->nullableText($request->input('waiter_name'));
            $serveTime = $this->nullableText($request->input('serve_time'));
            $serveDate = $this->resolveServeDate($request->input('serve_date'), $customerType);
            $orderNotes = $this->nullableText($request->input('order_notes'));

            if ($customerType === 'booking') {
                $bookingGuestName = $roomNo ? $this->resolveCheckedInGuestNameByRoomNo($roomNo) : null;
                if (! $bookingGuestName) {
                    return back()->with('error', 'Selected room is not checked-in right now.');
                }
                $guestName = $bookingGuestName;
                $waiterName = null;
                $serveTime = null;
                $serveDate = null;
            } else {
                $roomNo = null;
            }

            if ($customerType === 'ast_offr' && $contactId) {
                $guestName = Contact::query()->find($contactId)?->name ?? $guestName;
            }

            $pendingDraft = $this->findGuestPendingDraftOrder(
                (int) $session->id,
                $customerType,
                $guestName,
                $roomNo,
                $resumeOrderId
            );
            if ($pendingDraft) {
                return back()->with('error', sprintf(
                    'Is guest ki pending bill pehle se maujood hai (%s). Pehle Resume kar ke pay karein ya Discard karein.',
                    $pendingDraft->order_no
                ));
            }
        }

        $order = DB::connection('tenant')->transaction(function () use ($request, $session, $isCredit, $contactId, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $resumeOrderId, $customerType, $saleMode, $serviceType, $restaurantTableId) {
            $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
            if ($this->isRestaurantPosRequest($request)) {
                $tableId = $restaurantTableId;
            } else {
                $tableId = ($enableTables && $customerType !== 'booking') ? $request->integer('table_id') : null;
            }
            $pricing = $this->posPricingOptions();
            $billTax = $pricing['tax_mode'] === 'bill'
                ? round((float) $request->input('bill_tax_percent', $pricing['default_tax_rate']), 3)
                : 0.0;
            $billDiscount = $this->resolveBillDiscountPercent($request, $pricing['allow_discount'], $saleMode);
            [$subtotal, $discountTotal, $taxTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $billTax,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
            ]);

            if (!$isCredit) {
                $paymentsTotal = (float) collect($request->payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
                if (abs(round($paymentsTotal, 2) - round($grandTotal, 2)) > 0.01) {
                    abort(422, 'Payments total must match order total.');
                }
            }

            $cashTendered = !$isCredit && $request->filled('cash_tendered')
                ? round((float) $request->input('cash_tendered'), 2)
                : null;
            $cashChange = !$isCredit && $request->filled('cash_change')
                ? round((float) $request->input('cash_change'), 2)
                : null;

            $order = PosOrder::create([
                'order_no'           => DailyOrderNumber::next(),
                'session_id'         => $session->id,
                'table_id'           => $tableId ?: null,
                'user_id'            => Auth::id(),
                'contact_id'         => $contactId,
                'customer_type'      => $customerType,
                'service_type'       => $serviceType,
                'sale_mode'          => $saleMode,
                'guest_name'         => $guestName,
                'room_no'            => $roomNo,
                'waiter_name'        => $waiterName,
                'order_notes'        => $orderNotes,
                'serve_time'         => $serveTime,
                'serve_date'         => $serveDate,
                'is_credit'          => $isCredit,
                'refund_of_order_id' => $request->refund_of_order_id,
                'type'               => $request->type,
                'status'             => 'paid',
                'subtotal'           => $subtotal,
                'discount_total'     => $discountTotal,
                'tax_total'          => $taxTotal,
                'bill_tax_percent'   => $pricing['tax_mode'] === 'bill' ? $billTax : null,
                'bill_discount_percent' => $pricing['allow_discount'] ? $billDiscount : null,
                'grand_total'        => $grandTotal,
                'cash_tendered'      => $cashTendered,
                'cash_change'        => $cashChange,
                'paid_at'            => now(),
            ]);

            $kitchen = app(KitchenService::class);
            $oldKitchenItems = [];
            if ($resumeOrderId) {
                $draftForKitchen = PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->where('session_id', $session->id)
                    ->first();

                if ($draftForKitchen) {
                    $oldKitchenItems = $draftForKitchen->items()->get()->all();
                }
            }

            $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags($oldKitchenItems, $itemsData);

            foreach ($itemsWithKitchenFlags as $item) {
                PosOrderItem::create(['order_id' => $order->id] + $item);
                $this->applyInventoryForPos($order, $item);
            }

            if ($isCredit) {
                // Create credit ledger entry — no cash payment recorded
                $contact        = Contact::findOrFail($contactId);
                $runningBalance = $contact->balance;
                $balAfter       = round($runningBalance + (float) $grandTotal, 2);

                CreditLedger::updateOrCreate(
                    ['pos_order_id' => $order->id],
                    [
                        'contact_id'    => $contactId,
                        'type'          => 'credit',
                        'description'   => 'POS Credit Sale — '.$order->order_no,
                        'amount'        => $grandTotal,
                        'balance_after' => $balAfter,
                        'entry_date'    => now()->toDateString(),
                        'created_by'    => Auth::id(),
                    ]
                );
            } else {
                foreach ($request->payments as $payment) {
                    PosPayment::create([
                        'order_id'  => $order->id,
                        'method'    => $payment['method'],
                        'amount'    => (float) $payment['amount'],
                        'reference' => $payment['reference'] ?? null,
                    ]);
                }
            }

            // If this checkout came from a resumed held bill, clear the original draft.
            if ($resumeOrderId) {
                PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->where('session_id', $session->id)
                    ->where(function ($q) {
                        $q->where('user_id', Auth::id())
                            ->orWhere('order_source', 'order_taker');
                    })
                    ->delete();
            }

            return $order;
        });

        $this->logKitchenVoids($order, $this->normalizedKitchenVoids($request));
        $this->autoJournal->postPosSale($order);

        $openReceipt = Setting::get('pos_open_receipt_after_sale', '1') === '1';
        $msg = $isCredit ? 'Credit sale recorded successfully.' : 'Order paid successfully.';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'receipt_url' => $openReceipt ? route('restaurant-pos.receipt', $order) : null,
                'redirect_url' => $openReceipt
                    ? route('restaurant-pos.receipt', $order)
                    : route('restaurant-pos.index'),
            ]);
        }

        if ($customerType === 'mess_use' && ! $isCredit) {
            if ($openReceipt) {
                return redirect()->route('restaurant-pos.receipt', $order)->with('success', $msg);
            }

            return redirect()->route('restaurant-pos.index')->with('success', $msg)->with('last_pos_order_id', $order->id)->with('pos_active_tab', 'paid');
        }

        if ($openReceipt) {
            return redirect()->route('restaurant-pos.receipt', $order)->with('success', $msg);
        }

        return redirect()->route('restaurant-pos.index')->with('success', $msg)->with('last_pos_order_id', $order->id)->with('pos_active_tab', 'paid');
    }

    public function hold(PosCheckoutRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensurePosTablesSchema();
        $this->ensurePosOrderSchemaForCheckout();
        $this->ensurePosOrderItemsSchema();
        $this->ensurePosSessionDailyClosingSchema();

        $customerType = $this->normalizeCustomerType($request->input('customer_type'));

        $session = $this->ensureOpenSessionForUser(Auth::user());

        $serviceType = null;
        $restaurantTableId = null;

        if ($this->isRestaurantPosRequest($request)) {
            $restaurantMeta = $this->restaurantPosOrderMeta($request);
            $customerType = $restaurantMeta['customer_type'];
            $serviceType = $restaurantMeta['service_type'];
            $saleMode = $restaurantMeta['sale_mode'];
            $guestName = $restaurantMeta['guest_name'];
            $roomNo = $restaurantMeta['room_no'];
            $waiterName = $restaurantMeta['waiter_name'];
            $serveTime = $restaurantMeta['serve_time'];
            $serveDate = $restaurantMeta['serve_date'];
            $orderNotes = $restaurantMeta['order_notes'];
            $restaurantTableId = $restaurantMeta['table_id'];
        } else {
            $saleMode = $request->input('sale_mode') === 'staff' ? 'staff' : 'customer';
            if ($customerType === 'ast_offr') {
                $saleMode = 'staff';
            }
        }

        $resumeOrderId = $request->integer('resume_order_id') ?: null;
        $itemsNormalized = $this->normalizePosCheckoutItems(
            $request->items,
            $customerType,
            $saleMode,
            (string) $request->type,
            $request->boolean('staff_include_gas'),
            false
        );
        if ($request->type === 'sale') {
            $this->validatePosProductsForCustomerType($itemsNormalized, $customerType);
            $this->validatePosStockForSale($itemsNormalized);
        }

        $this->assertKitchenVoidPermission($request);

        if ($resumeOrderId) {
            $resumeDraft = PosOrder::query()
                ->where('id', $resumeOrderId)
                ->where('status', 'draft')
                ->where('session_id', $session->id)
                ->first();
            if ($resumeDraft) {
                $this->assertKitchenLockedQuantitiesPreserved(
                    $resumeDraft->items()->get()->all(),
                    $itemsNormalized,
                    $this->normalizedKitchenVoids($request)
                );
            }
        }

        if (! $this->isRestaurantPosRequest($request)) {
            $guestName = $this->nullableText($request->input('guest_name'));
            $roomNo = $this->nullableText($request->input('room_no'));
            $waiterName = $this->nullableText($request->input('waiter_name'));
            $serveTime = $this->nullableText($request->input('serve_time'));
            $serveDate = $this->resolveServeDate($request->input('serve_date'), $customerType);
            $orderNotes = $this->nullableText($request->input('order_notes'));

            if ($customerType === 'booking') {
                $bookingGuestName = $roomNo ? $this->resolveCheckedInGuestNameByRoomNo($roomNo) : null;
                if (! $bookingGuestName) {
                    $message = 'Selected room is not checked-in right now.';
                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 422);
                    }

                    return back()->with('error', $message);
                }
                $guestName = $bookingGuestName;
                $waiterName = null;
                $serveTime = null;
                $serveDate = null;
            } else {
                $roomNo = null;
            }

            $pendingDraft = $this->findGuestPendingDraftOrder(
                (int) $session->id,
                $customerType,
                $guestName,
                $roomNo,
                $resumeOrderId
            );
            if ($pendingDraft) {
                $message = sprintf(
                    'Is guest ki pending bill pehle se maujood hai (%s). Pehle Resume kar ke pay karein ya Discard karein.',
                    $pendingDraft->order_no
                );
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }
        }

        $updatedExisting = false;
        $clientTotals = $this->clientHoldTotalsFromRequest($request);
        $sendToKitchen = $this->isRestaurantPosRequest($request)
            ? $request->boolean('send_to_kitchen')
            : true;
        $order = DB::connection('tenant')->transaction(function () use ($request, $session, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $customerType, $saleMode, $serviceType, $restaurantTableId, $resumeOrderId, $clientTotals, $sendToKitchen, &$updatedExisting) {
            $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
            if ($this->isRestaurantPosRequest($request)) {
                $tableId = $restaurantTableId;
            } else {
                $tableId = ($enableTables && $customerType !== 'booking') ? $request->integer('table_id') : null;
            }
            $pricing = $this->posPricingOptions();
            $billTax = $pricing['tax_mode'] === 'bill'
                ? round((float) $request->input('bill_tax_percent', $pricing['default_tax_rate']), 3)
                : 0.0;
            $billDiscount = $this->resolveBillDiscountPercent($request, $pricing['allow_discount'], $saleMode);
            [$subtotal, $discountTotal, $taxTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $billTax,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
            ]);
            if ($clientTotals !== null) {
                if ($clientTotals['subtotal'] !== null) {
                    $subtotal = $clientTotals['subtotal'];
                }
                if ($clientTotals['discount'] !== null) {
                    $discountTotal = $clientTotals['discount'];
                }
                if ($clientTotals['tax'] !== null) {
                    $taxTotal = $clientTotals['tax'];
                }
                if ($clientTotals['grand'] !== null) {
                    $grandTotal = $clientTotals['grand'];
                }
            }

            $orderPayload = [
                'customer_type' => $customerType,
                'service_type' => $serviceType,
                'sale_mode' => $saleMode,
                'table_id' => $tableId ?: null,
                'guest_name' => $guestName,
                'room_no' => $roomNo,
                'waiter_name' => $waiterName,
                'order_notes' => $orderNotes,
                'serve_time' => $serveTime,
                'serve_date' => $serveDate,
                'type' => $request->type,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'bill_tax_percent' => $pricing['tax_mode'] === 'bill' ? $billTax : null,
                'bill_discount_percent' => $pricing['allow_discount'] ? $billDiscount : null,
                'grand_total' => $grandTotal,
            ];

            if ($resumeOrderId) {
                $existing = PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->where('session_id', $session->id)
                    ->where('user_id', Auth::id())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $kitchen = app(KitchenService::class);
                    $oldItems = $existing->items()->get()->all();
                    $wasKitchenServed = $existing->kitchen_completed_at !== null;
                    $kitchenPayload = [];

                    if ($wasKitchenServed) {
                        $kitchenPayload['kitchen_completed_at'] = null;
                        $kitchenPayload['kitchen_status'] = null;
                        if (Schema::hasColumn($existing->getTable(), 'kitchen_preparing_at')) {
                            $kitchenPayload['kitchen_preparing_at'] = null;
                        }
                        if (Schema::hasColumn($existing->getTable(), 'kitchen_ready_at')) {
                            $kitchenPayload['kitchen_ready_at'] = null;
                        }
                    }

                    $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags($oldItems, $itemsData, $sendToKitchen);
                    $hasNewKitchenItems = collect($itemsWithKitchenFlags)
                        ->contains(fn (array $item) => (bool) ($item['kitchen_pending'] ?? true));

                    if ($hasNewKitchenItems && (
                        $wasKitchenServed
                        || $existing->kitchenStatusKey() === PosOrder::KITCHEN_STATUS_READY
                    )) {
                        $kitchenPayload['kitchen_status'] = null;
                    }

                    $existing->update($orderPayload + $kitchenPayload);
                    $existing->items()->delete();
                    foreach ($itemsWithKitchenFlags as $item) {
                        PosOrderItem::create(['order_id' => $existing->id] + $item);
                    }
                    $updatedExisting = true;

                    return $existing->fresh(['table', 'items']);
                }
            }

            $kitchen = app(KitchenService::class);
            $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags([], $itemsData, $sendToKitchen);

            $order = PosOrder::create([
                'order_no' => DailyOrderNumber::next(),
                'session_id' => $session->id,
                'user_id' => Auth::id(),
                'status' => 'draft',
            ] + $orderPayload);

            foreach ($itemsWithKitchenFlags as $item) {
                PosOrderItem::create(['order_id' => $order->id] + $item);
            }

            return $order->fresh(['table']);
        });

        if ($this->repairDraftOrderIfNeeded($order)) {
            $order->refresh();
            $order->loadMissing('table');
        }

        $this->logKitchenVoids($order, $this->normalizedKitchenVoids($request));

        $message = $updatedExisting ? 'Held order updated.' : 'Order held successfully.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updatedExisting,
                'order' => $this->posOrderDetailsPayload($order),
                'held_count' => $this->heldOrdersForSession($session)->count(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function resume(Request $request, PosOrder $order): RedirectResponse
    {
        if ($order->status !== 'draft') {
            abort(403);
        }

        $uiRoute = 'restaurant-pos.index';

        if ($order->isReadyForPosPickup()) {
            $session = $this->ensureOpenSessionForUser(Auth::user());
            $order->update(['session_id' => $session->id]);

            return redirect()->route($uiRoute, ['resume_order' => $order->id]);
        }

        if ($order->session === null || (int) $order->session->user_id !== (int) Auth::id()) {
            abort(403);
        }

        return redirect()->route($uiRoute, ['resume_order' => $order->id]);
    }

    /** Super admin: permanently delete a paid POS bill and reverse its stock impact. */
    public function destroyPaidBill(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403);
        abort_unless($order->status === 'paid', 404);

        $orderNo = $order->order_no;

        try {
            $this->deletePaidOrder($order);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Bill delete nahi ho saki.');
        }

        return back()->with('success', "Bill {$orderNo} deleted.");
    }

    /** Delete a draft held order for the current open register session (items cascade). */
    public function discardHeld(int $orderId): RedirectResponse|JsonResponse
    {
        $session = $this->ensureOpenSessionForUser(Auth::user());
        $order = PosOrder::query()->find($orderId);

        if ($order === null) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'already_discarded' => true,
                    'message' => 'Order pehle se khatam ho chuki hai.',
                ]);
            }

            return back()->with('warning', 'Order pehle se khatam ho chuki hai.');
        }

        if ($order->status !== 'draft' || (int) $order->session_id !== (int) $session->id) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'Is order ko discard nahi kar sakte.',
                ], 403);
            }

            abort(403);
        }

        if ($order->session->user_id !== Auth::id()) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'Is order ko discard nahi kar sakte.',
                ], 403);
            }

            abort(403);
        }

        $order->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Held order discarded.',
            ]);
        }

        return back()->with('success', 'Held order discarded.');
    }

    public function settleDraftOrderForCheckoutCounter(PosOrder $order, string $paymentMethod): PosOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'order' => 'Yeh cafe bill pehle se settle ho chuki hai.',
            ]);
        }

        if (! in_array($paymentMethod, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Payment method cash ya bank honi chahiye.',
            ]);
        }

        $settled = DB::connection('tenant')->transaction(function () use ($order, $paymentMethod) {
            $locked = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'order' => 'Yeh cafe bill pehle se settle ho chuki hai.',
                ]);
            }

            $locked->load('items');

            $payload = [
                'status' => 'paid',
                'paid_at' => now(),
            ];

            if (Schema::hasColumn($locked->getTable(), 'kitchen_completed_at') && $locked->kitchen_completed_at === null) {
                $payload['kitchen_completed_at'] = now();
            }

            $locked->update($payload);

            $locked->payments()->delete();
            PosPayment::create([
                'order_id' => $locked->id,
                'method' => $paymentMethod,
                'amount' => (float) $locked->grand_total,
                'reference' => 'Checkout Counter',
            ]);

            if ($locked->type === 'sale') {
                foreach ($locked->items as $item) {
                    $this->applyInventoryForPos($locked, [
                        'product_id' => $item->product_id,
                        'uom' => (string) $item->uom,
                        'qty' => (float) $item->qty,
                    ]);
                }
            }

            return $locked->fresh();
        });

        $this->autoJournal->postPosSale($settled);

        return $settled;
    }

    public function receipt(Request $request, PosOrder $order): View
    {
        return $this->renderReceipt($request, $order, paidOnly: true);
    }

    public function unpaidReceipt(Request $request, PosOrder $order): View
    {
        abort_unless(Setting::get('pos_allow_bill_print', '1') === '1', 403);

        return $this->renderReceipt($request, $order, paidOnly: false);
    }

    public function kitchenSlip(Request $request, PosOrder $order): View
    {
        abort_unless($order->status === 'draft', 404);
        $this->assertDraftReceiptAccess($order);

        $order->load(['items.product:id,name,sku', 'user:id,name', 'table:id,name']);

        $kitchenItems = $order->items->filter(
            fn (PosOrderItem $item) => (bool) $item->kitchen_pending && ! $item->isKitchenServed()
        )->values();

        abort_unless($kitchenItems->isNotEmpty(), 404);

        $settings = $this->receiptSettingsMap();
        $autoPrint = ! $request->boolean('noprint', false) && $request->boolean('autoprint', true);
        $backUrl = route('restaurant-pos.index', ['resume_order' => $order->id]);
        $backLabel = '← Back to order';

        return view('pos.kitchen-slip', compact('order', 'kitchenItems', 'settings', 'autoPrint', 'backUrl', 'backLabel'));
    }

    private function renderReceipt(Request $request, PosOrder $order, bool $paidOnly): View
    {
        if ($paidOnly) {
            abort_unless($order->status === 'paid', 404);
            abort_unless((int) $order->user_id === (int) Auth::id(), 403);
        } else {
            abort_unless($order->status === 'draft', 404);
            $this->assertDraftReceiptAccess($order);
        }

        $order->load(['items.product:id,name,sku', 'payments', 'contact:id,name,phone', 'user:id,name', 'table:id,name']);

        $settings = $this->receiptSettingsMap();
        $isUnpaid = ! $paidOnly;
        $allowBillPrint = (($settings['pos_allow_bill_print'] ?? '1') === '1');
        $autoPrint = ! $request->boolean('noprint', false) && (
            $paidOnly
                ? Setting::get('pos_auto_print_receipt', '1') === '1'
                : $request->boolean('autoprint', true)
        );
        $backUrl = route('restaurant-pos.index');
        $backLabel = '← Back to Restaurant POS';

        return view('pos.receipt', compact('order', 'settings', 'autoPrint', 'allowBillPrint', 'backUrl', 'backLabel', 'isUnpaid'));
    }

    private function assertDraftReceiptAccess(PosOrder $order): void
    {
        $user = Auth::user();
        if ((int) $order->user_id === (int) $user->id) {
            return;
        }

        $session = $this->openPosSessionForUser($user);
        if ($session !== null && (int) $order->session_id === (int) $session->id) {
            return;
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptSettingsMap(): array
    {
        $settings = array_merge([
            'company_name' => config('app.name'),
            'company_address' => '',
            'company_phone' => '',
            'company_logo' => '',
            'currency_symbol' => 'Rs.',
            'pos_allow_bill_print' => '1',
            'pos_enable_tables' => '1',
        ], Setting::all_map());

        $settings['company_logo_url'] = company_logo_url($settings['company_logo'] ?? '') ?? '';

        return $settings;
    }

    private function openPosSessionForUser(User $user): ?PosSession
    {
        $today = now()->toDateString();

        return PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->where(function ($q) use ($today) {
                $q->whereDate('opened_at', $today)
                    ->orWhereDate('business_date', $today);
            })
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function canonicalizePosLineUoms(array $items): array
    {
        if ($items === []) {
            return $items;
        }

        $ids = collect($items)->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return $items;
        }

        $products = InventoryProduct::query()
            ->whereIn('id', $ids)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->get()
            ->keyBy('id');

        foreach ($items as $k => $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $product = $products->get($pid);
            if (!$product) {
                continue;
            }
            $uom = trim((string) ($item['uom'] ?? ''));
            if ($uom === '') {
                continue;
            }
            if ($product->factorToBaseForUom($uom) !== null) {
                continue;
            }
            foreach ($product->uomsForForms() as $row) {
                $code = (string) ($row['uom'] ?? '');
                if ($code === '') {
                    continue;
                }
                if (strcasecmp($code, $uom) === 0) {
                    $items[$k]['uom'] = $code;
                    break;
                }
                if (InventoryUnit::normalizeCode($code) === InventoryUnit::normalizeCode($uom)) {
                    $items[$k]['uom'] = $code;
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Walk-In / In-House may only sell POS menu items, not purchase inventory SKUs.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validatePosProductsForCustomerType(array $items, string $customerType): void
    {
        if (! in_array($customerType, ['mess_use', 'booking'], true) || $items === []) {
            return;
        }

        $ids = collect($items)->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return;
        }

        $products = InventoryProduct::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'for_pos'])
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            if ($product === null || $product->for_pos) {
                continue;
            }

            throw ValidationException::withMessages([
                'items' => [
                    $product->name.' Walk-In / In-House ke liye available nahi — sirf menu items choose karein.',
                ],
            ]);
        }
    }

    /**
     * Block POS sales when simple SKU stock is insufficient.
     * BoM component stock is allowed to go negative during POS sale.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validatePosStockForSale(array $items): void
    {
        if ($items === []) {
            return;
        }

        $ids = collect($items)->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return;
        }

        $products = InventoryProduct::query()
            ->whereIn('id', $ids)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->withExists(['manufacturingBoms' => fn ($q) => $q->where('active', true)])
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $product = $products->get($pid);
            if ($product === null) {
                continue;
            }

            $factor = $product->factorToBaseForUom((string) ($item['uom'] ?? ''));
            if ($factor === null || $factor <= 0) {
                continue;
            }

            $qtyBase = (float) ($item['qty'] ?? 0) * $factor;
            if ($qtyBase <= 0) {
                continue;
            }

            if ($product->manufacturing_boms_exists) {
                $bom = ManufacturingBom::query()
                    ->where('finished_product_id', $product->id)
                    ->where('active', true)
                    ->with(['lines.component'])
                    ->orderBy('id')
                    ->first();
                if ($bom === null) {
                    continue;
                }

                $batch = (float) $bom->batch_qty;
                if ($batch <= self::FIFO_EPSILON) {
                    throw ValidationException::withMessages([
                        'items' => ['Invalid batch quantity for manufactured product '.$product->name.'.'],
                    ]);
                }

                $mult = $qtyBase / $batch;

                // BoM component shortage is allowed for POS sale;
                // inventory will move component stock into negative and later purchases can offset it.
                continue;
            }

            if (! $product->for_purchase) {
                continue;
            }

            $avail = (float) $product->qty_on_hand;
            if ($qtyBase > $avail + self::FIFO_EPSILON) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Stock nahi: '.$product->name.' — chahiye '.fmt_num($qtyBase, 3).' '.$product->uom.' (base), maujood '.fmt_num($avail, 3).'.',
                    ],
                ]);
            }
        }
    }

    /**
     * @return array{tax_mode: 'off'|'line'|'bill', allow_discount: bool, default_tax_rate: float}
     */
    private function posPricingOptions(): array
    {
        $taxMode = Setting::get('pos_tax_mode', 'line');
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }

        return [
            'tax_mode' => $taxMode,
            'allow_discount' => Setting::get('pos_show_discount', '1') === '1',
            'default_tax_rate' => (float) Setting::get('tax_rate', 0),
        ];
    }

    /**
     * @param  array{
     *   tax_mode?: 'off'|'line'|'bill',
     *   bill_tax_percent?: float,
     *   bill_discount_percent?: float,
     *   allow_discount?: bool
     * }  $opts
     * @return array{0: float, 1: float, 2: float, 3: float, 4: list<array<string, mixed>>}
     */
    private function buildLines(array $items, array $opts = []): array
    {
        $taxMode = $opts['tax_mode'] ?? 'line';
        if (! in_array($taxMode, ['off', 'line', 'bill'], true)) {
            $taxMode = 'line';
        }
        $billTaxPct = (float) ($opts['bill_tax_percent'] ?? 0);
        $allowDiscount = (bool) ($opts['allow_discount'] ?? true);
        $billDiscountPct = $allowDiscount
            ? max(0.0, min(100.0, (float) ($opts['bill_discount_percent'] ?? 0)))
            : 0.0;

        $subtotal = 0.0;
        $lines = [];
        $rawLines = [];

        $ids = collect($items)->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        $products = $ids === []
            ? collect()
            : InventoryProduct::query()
                ->whereIn('id', $ids)
                ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
                ->get()
                ->keyBy('id');

        foreach ($items as $item) {
            $qty = (float) $item['qty'];
            $price = (float) $item['unit_price'];
            $taxPct = (float) ($item['tax_percent'] ?? 0);
            $pid = (int) $item['product_id'];

            $lineSub = $qty * $price;

            $product = $products->get($pid);
            $factor = $product ? $product->factorToBaseForUom((string) $item['uom']) : null;
            if ($product === null || $factor === null || $factor <= 0) {
                abort(422, 'Invalid UOM or product for a cart line.');
            }

            $subtotal += $lineSub;
            $rawLines[] = [
                'product_id' => $pid,
                'uom' => $item['uom'],
                'qty' => $qty,
                'unit_price' => $price,
                'line_sub' => $lineSub,
                'tax_pct' => $taxPct,
                'notes' => $this->nullableText($item['notes'] ?? null),
            ];
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = $allowDiscount ? round($subtotal * ($billDiscountPct / 100), 2) : 0.0;

        $taxTotal = 0.0;
        $allocatedDisc = 0.0;
        $lineCount = count($rawLines);

        foreach ($rawLines as $idx => $raw) {
            $lineSub = round($raw['line_sub'], 2);
            $taxPct = $raw['tax_pct'];

            if ($idx === $lineCount - 1) {
                $lineDisc = round($discountTotal - $allocatedDisc, 2);
            } else {
                $lineDisc = $subtotal > 0 ? round($discountTotal * ($lineSub / $subtotal), 2) : 0.0;
                $allocatedDisc += $lineDisc;
            }

            $lineNet = $lineSub - $lineDisc;

            if ($taxMode === 'line') {
                $lineTax = round($lineNet * ($taxPct / 100), 2);
                $taxPctStored = $taxPct;
            } else {
                $lineTax = 0.0;
                $taxPctStored = 0.0;
            }

            $lineTotal = round($lineNet + $lineTax, 2);
            if (! empty($opts['trust_line_totals'])) {
                $sourceItem = $items[$idx] ?? null;
                if (is_array($sourceItem) && array_key_exists('line_total', $sourceItem)) {
                    $lineTotal = round((float) $sourceItem['line_total'], 2);
                    if ($taxMode === 'line') {
                        $lineTax = round($lineTotal - $lineNet, 2);
                    }
                }
            }

            $taxTotal += $lineTax;

            $lines[] = [
                'product_id' => $raw['product_id'],
                'uom' => $raw['uom'],
                'qty' => $raw['qty'],
                'unit_price' => $raw['unit_price'],
                'discount_percent' => 0.0,
                'tax_percent' => $taxPctStored,
                'notes' => $raw['notes'],
                'subtotal' => $lineSub,
                'discount_amount' => $lineDisc,
                'tax_amount' => $lineTax,
                'total' => $lineTotal,
            ];
        }

        if ($taxMode === 'bill') {
            $net = round($subtotal - $discountTotal, 2);
            $taxTotal = round($net * ($billTaxPct / 100), 2);
        } else {
            $taxTotal = round($taxTotal, 2);
        }

        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

        return [$subtotal, $discountTotal, $taxTotal, $grandTotal, $lines];
    }

    private function resolveBillDiscountPercent(PosCheckoutRequest $request, bool $allowDiscount, string $saleMode): float
    {
        if (! $allowDiscount || $saleMode === 'staff') {
            return 0.0;
        }

        return max(0.0, min(100.0, round((float) $request->input('bill_discount_percent', 0), 3)));
    }

    private function deletePaidOrder(PosOrder $order): void
    {
        if ($order->type === 'sale' && PosOrder::query()->where('refund_of_order_id', $order->id)->exists()) {
            throw new \RuntimeException('Is bill ki refund entries maujood hain — pehle unhe delete karein.');
        }

        DB::connection('tenant')->transaction(function () use ($order) {
            $order->load(['items.product']);

            $reverseType = $order->type === 'sale' ? 'refund' : 'sale';
            $order->type = $reverseType;

            foreach ($order->items as $line) {
                $this->applyInventoryForPos($order, [
                    'product_id' => (int) $line->product_id,
                    'uom' => (string) $line->uom,
                    'qty' => (float) $line->qty,
                ]);
            }

            InventoryMove::query()
                ->where('reference', $order->order_no)
                ->delete();

            CreditLedger::query()->where('pos_order_id', $order->id)->delete();
            $order->payments()->delete();
            $order->items()->delete();
            $order->delete();
        });
    }

    private function applyInventoryForPos(PosOrder $order, array $item): void
    {
        $product = InventoryProduct::query()
            ->with('uomConversions')
            ->withExists(['manufacturingBoms' => fn ($q) => $q->where('active', true)])
            ->findOrFail($item['product_id']);
        $factor = $product->factorToBaseForUom((string) $item['uom']);
        if ($factor === null || $factor <= 0) {
            abort(422, 'Invalid UOM for '.$product->name);
        }

        $qtyBase = (float) $item['qty'] * $factor;
        $isSale = $order->type === 'sale';

        if ($product->manufacturing_boms_exists) {
            $bom = ManufacturingBom::query()
                ->where('finished_product_id', $product->id)
                ->where('active', true)
                ->with(['lines.component.uomConversions'])
                ->orderBy('id')
                ->first();
            if ($bom !== null) {
                $this->applyPosInventoryThroughBom($order, $item, $product, $factor, $qtyBase, $isSale, $bom);

                return;
            }
        }

        if (! $product->for_purchase) {
            return;
        }

        $moveType = $isSale ? 'out' : 'in';
        $qtyBefore = (float) $product->qty_on_hand;
        $qtyAfter = $isSale ? ($qtyBefore - $qtyBase) : ($qtyBefore + $qtyBase);

        $unitCost = 0.0;
        if ($isSale) {
            $unitCost = $this->consumeFifo($product, $qtyBase);
        } else {
            $unitCost = (float) $product->cost;
            InventoryCostLayer::create([
                'product_id' => $product->id,
                'qty_remaining' => $qtyBase,
                'unit_cost' => $unitCost,
                'source' => 'pos_refund',
                'reference' => $order->order_no,
                'received_at' => now(),
            ]);
        }

        $product->update([
            'qty_on_hand' => $qtyAfter,
        ]);

        InventoryMove::create([
            'product_id' => $product->id,
            'user_id' => Auth::id(),
            'type' => $moveType,
            'qty' => $qtyBase,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'reference' => $order->order_no,
            'note' => 'POS ' . $order->type,
            'uom' => $item['uom'],
            'qty_uom' => (float) $item['qty'],
            'factor_to_base' => $factor,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost * $qtyBase,
        ]);

        $this->refreshProductCostFromLayers($product);
        $this->notifyStockUpdate($product, $moveType, $qtyBase, $order->order_no);
    }

    /**
     * POS sale/refund of a finished good with an active BoM: move component stock (same math as manufacturing complete), not the finished SKU.
     */
    private function applyPosInventoryThroughBom(
        PosOrder $order,
        array $item,
        InventoryProduct $finished,
        float $factor,
        float $qtyFinishedBase,
        bool $isSale,
        ManufacturingBom $bom
    ): void {
        $batch = (float) $bom->batch_qty;
        if ($batch <= 0) {
            abort(422, 'Invalid BoM batch quantity for '.$finished->name);
        }

        $mult = $qtyFinishedBase / $batch;

        $productIds = $bom->lines->pluck('component_product_id')->unique()->sort()->values()->all();
        $locked = [];
        foreach ($productIds as $pid) {
            $locked[$pid] = InventoryProduct::query()->lockForUpdate()->findOrFail($pid);
        }

        $ref = $order->order_no;
        $notePrefix = $isSale ? 'POS sale' : 'POS refund';

        foreach ($bom->lines as $line) {
            $component = $locked[$line->component_product_id];
            $component->loadMissing('uomConversions');
            $lineUom = $line->effectiveUom();
            $qtyInLineUom = (float) $line->qty * $mult;
            $needBase = $component->convertQtyToBaseUom($qtyInLineUom, $lineUom);
            if ($needBase <= 0) {
                continue;
            }

            try {
                if ($isSale) {
                    $this->manufacturingStock->stockOut(
                        $component,
                        $needBase,
                        Auth::id(),
                        $ref,
                        $notePrefix.' — '.$finished->name.' (BoM)',
                        true
                    );
                    $this->notifyStockUpdate($component, 'out', $needBase, $order->order_no);
                } else {
                    $this->manufacturingStock->stockIn(
                        $component,
                        $needBase,
                        Auth::id(),
                        $ref,
                        $notePrefix.' — '.$finished->name.' (BoM)',
                        (float) $component->cost
                    );
                    $this->notifyStockUpdate($component, 'in', $needBase, $order->order_no);
                }
            } catch (\RuntimeException $e) {
                abort(422, $e->getMessage());
            }
        }

        InventoryMove::create([
            'product_id' => $finished->id,
            'user_id' => Auth::id(),
            'type' => $isSale ? 'out' : 'in',
            'qty' => $qtyFinishedBase,
            'qty_before' => (float) $finished->qty_on_hand,
            'qty_after' => (float) $finished->qty_on_hand,
            'reference' => $order->order_no,
            'note' => $notePrefix.' — '.$finished->name.' (recipe; stock via components)',
            'uom' => $item['uom'],
            'qty_uom' => (float) $item['qty'],
            'factor_to_base' => $factor,
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);
    }

    private function consumeFifo(InventoryProduct $product, float $qtyBase): float
    {
        $remaining = $qtyBase;
        $totalCost = 0.0;

        $layers = InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->where('qty_remaining', '>', self::FIFO_EPSILON)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $layer->qty_remaining, $remaining);
            $layer->qty_remaining = (float) $layer->qty_remaining - $take;
            if (abs((float) $layer->qty_remaining) < self::FIFO_EPSILON) {
                $layer->qty_remaining = 0.0;
            }
            $layer->save();

            $totalCost += $take * (float) $layer->unit_cost;
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $fallback = (float) $product->cost;
            $totalCost += $remaining * $fallback;
        }

        return $qtyBase > 0 ? ($totalCost / $qtyBase) : 0.0;
    }

    private function refreshProductCostFromLayers(InventoryProduct $product): void
    {
        $layer = InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->where('qty_remaining', '>', self::FIFO_EPSILON)
            ->orderBy('received_at')
            ->orderBy('id')
            ->first();

        if ($layer) {
            $product->update(['cost' => (float) $layer->unit_cost]);
            return;
        }

        $product->update(['cost' => 0]);
    }

    /**
     * Paid checkout uses cash_tendered / cash_change and credit fields; add if migrations were not run.
     */
    private function ensurePosOrderSchemaForCheckout(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        try {
            if (! Schema::hasColumn('pos_orders', 'cash_tendered')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->decimal('cash_tendered', 12, 2)->nullable();
                });
            }
            if (! Schema::hasColumn('pos_orders', 'cash_change')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->decimal('cash_change', 12, 2)->nullable();
                });
            }
            if (! Schema::hasColumn('pos_orders', 'contact_id') && Schema::hasTable('contacts')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                });
            }
            if (! Schema::hasColumn('pos_orders', 'is_credit')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->boolean('is_credit')->default(false);
                });
            }
            if (! Schema::hasColumn('pos_orders', 'bill_tax_percent')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->decimal('bill_tax_percent', 8, 3)->nullable()->after('tax_total');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'bill_discount_percent')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->decimal('bill_discount_percent', 8, 3)->nullable()->after('bill_tax_percent');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'table_id') && Schema::hasTable('pos_tables')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->foreignId('table_id')->nullable()->after('session_id')->constrained('pos_tables')->nullOnDelete();
                });
            }
            if (! Schema::hasColumn('pos_orders', 'guest_name')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('guest_name', 120)->nullable()->after('contact_id');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'room_no')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('room_no', 50)->nullable()->after('guest_name');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'waiter_name')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('waiter_name', 120)->nullable()->after('room_no');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'order_notes')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->text('order_notes')->nullable()->after('waiter_name');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'serve_time')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('serve_time', 10)->nullable()->after('waiter_name');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'serve_date')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->date('serve_date')->nullable()->after('serve_time');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'serve_meal')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('serve_meal', 20)->nullable()->after('serve_date');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'kitchen_preparing_at')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->timestamp('kitchen_preparing_at')->nullable()->after('kitchen_completed_at');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'kitchen_ready_at')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->timestamp('kitchen_ready_at')->nullable()->after('kitchen_preparing_at');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'customer_type')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('customer_type', 20)->default('mess_use')->after('contact_id');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'sale_mode')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('sale_mode', 20)->default('customer')->after('customer_type');
                });
            }
            if (! Schema::hasColumn('pos_orders', 'service_type')) {
                Schema::table('pos_orders', function (Blueprint $table) {
                    $table->string('service_type', 20)->nullable()->after('customer_type');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensurePosOrderItemsSchema(): void
    {
        if (! Schema::hasTable('pos_order_items')) {
            return;
        }

        try {
            if (! Schema::hasColumn('pos_order_items', 'notes')) {
                Schema::table('pos_order_items', function (Blueprint $table) {
                    $table->string('notes', 255)->nullable()->after('tax_percent');
                });
            }
            if (! Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
                Schema::table('pos_order_items', function (Blueprint $table) {
                    $table->boolean('kitchen_pending')->default(true)->after('notes');
                });
            }
            if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
                Schema::table('pos_order_items', function (Blueprint $table) {
                    $table->timestamp('kitchen_served_at')->nullable()->after('kitchen_pending');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function resolveServeDate(mixed $value, string $customerType): ?string
    {
        if ($customerType === 'booking') {
            return null;
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }

        return now()->timezone(config('app.timezone'))->toDateString();
    }

    /**
     * One entry per checked-in booking; multiple assigned rooms are combined in room_no.
     *
     * @return \Illuminate\Support\Collection<int, array{room_no:string, guest_name:string}>
     */
    private function checkedInRoomsForPos(): \Illuminate\Support\Collection
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

    private function resolveCheckedInGuestNameByRoomNo(string $roomNo): ?string
    {
        $normalized = trim($roomNo);
        if ($normalized === '') {
            return null;
        }

        $row = $this->checkedInRoomsForPos()
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

    /** @return array<string, mixed> */
    private function posOrderDetailsPayload(PosOrder $order): array
    {
        $order->loadMissing(['table', 'items.product', 'payments']);

        $tableRoomParts = [];
        if ($order->table) {
            $tableRoomParts[] = $order->table->name;
        }
        if ($order->room_no) {
            $tableRoomParts[] = $order->room_no;
        }

        $payMethods = $order->payments
            ->pluck('method')
            ->map(fn ($m) => ucfirst((string) $m))
            ->unique()
            ->values();

        $orderAt = $order->ready_for_pos_at ?? $order->created_at;
        $serveTime = trim((string) ($order->serve_time ?? ''));
        $serveDate = $order->serve_date instanceof \Illuminate\Support\Carbon
            ? $order->serve_date->format('Y-m-d')
            : trim((string) ($order->serve_date ?? ''));

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'is_pending' => $order->status === 'draft',
            'customer_type' => $order->customerTypeKey(),
            'service_type' => $order->serviceTypeKey(),
            'service_type_label' => $order->serviceTypeLabel(),
            'guest_name' => $order->guest_name,
            'waiter_name' => $order->waiter_name,
            'order_notes' => trim((string) ($order->order_notes ?? '')),
            'room_no' => $order->room_no,
            'table_id' => $order->table_id ? (int) $order->table_id : null,
            'table_name' => $order->table?->name,
            'table_room' => $tableRoomParts !== [] ? implode(' / ', $tableRoomParts) : null,
            'from_order_taker' => $order->isFromOrderTaker(),
            'is_credit' => (bool) $order->is_credit,
            'is_refund' => $order->type === 'refund',
            'payment_label' => $order->customerTypeKey() === 'ast_offr'
                ? PosOrder::MESS_BILL_LABEL
                : ($order->is_credit
                    ? 'Credit'
                    : ($payMethods->isNotEmpty() ? $payMethods->implode(', ') : '—')),
            'grand_total' => (float) $order->grand_total,
            'items_count' => $order->items->count(),
            'paid_at' => $order->paid_at?->format('H:i'),
            'paid_at_full' => $order->paid_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            'serve_time' => $serveTime !== '' ? $serveTime : null,
            'serve_date' => $serveDate !== '' ? $serveDate : null,
            'order_time' => $order->isFromOrderTaker() && $orderAt ? $orderAt->format('H:i') : null,
            'served_at' => $order->kitchen_completed_at?->format('H:i'),
            'kitchen_status_label' => $order->pendingKitchenStatusLabel(),
            'kitchen_status_badge' => $order->pendingKitchenStatusBadgeClass(),
            'created_at' => $order->created_at?->format('Y-m-d H:i'),
            'served_count' => $order->items->filter(fn (PosOrderItem $item) => $item->isKitchenServed())->count(),
            'pending_count' => $order->items->filter(fn (PosOrderItem $item) => ! $item->isKitchenServed() && (bool) $item->kitchen_pending)->count(),
            'timeline' => $order->orderTimelineSteps(),
            'items' => $order->items->map(fn (PosOrderItem $item) => [
                'product_id' => (int) $item->product_id,
                'name' => $item->product->name ?? 'Item',
                'qty' => fmt_num((float) $item->qty, 3),
                'uom' => $item->uom,
                'unit_price' => (float) $item->unit_price,
                'tax_percent' => (float) $item->tax_percent,
                'total' => (float) $item->total,
                'notes' => trim((string) ($item->notes ?? '')),
                'kitchen_served' => $item->isKitchenServed(),
                'kitchen_pending' => (bool) $item->kitchen_pending,
                'kitchen_served_at' => $item->kitchen_served_at?->format('H:i'),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function paidOrderPayloadForJson(PosOrder $order): array
    {
        return $this->posOrderDetailsPayload($order);
    }

    /** @return array<string, mixed> */
    private function heldOrderPayloadForJson(PosOrder $order): array
    {
        return $this->posOrderDetailsPayload($order);
    }

    /**
     * @return array{subtotal: ?float, discount: ?float, tax: ?float, grand: ?float}|null
     */
    private function clientHoldTotalsFromRequest(PosCheckoutRequest $request): ?array
    {
        if (! $request->filled('client_grand_total')) {
            return null;
        }

        return [
            'subtotal' => $request->filled('client_subtotal')
                ? round((float) $request->input('client_subtotal'), 2)
                : null,
            'discount' => $request->filled('client_discount_total')
                ? round((float) $request->input('client_discount_total'), 2)
                : null,
            'tax' => $request->filled('client_tax_total')
                ? round((float) $request->input('client_tax_total'), 2)
                : null,
            'grand' => round((float) $request->input('client_grand_total'), 2),
        ];
    }

    /**
     * Fix draft bills saved with base cost on alternate UOM lines (e.g. 200 g priced per kg).
     */
    private function repairDraftOrderIfNeeded(PosOrder $order): bool
    {
        if ($order->status !== 'draft') {
            return false;
        }

        $order->loadMissing('items');
        if ($order->items->isEmpty()) {
            return false;
        }

        $customerType = $order->customerTypeKey();
        $pricing = $this->posPricingOptions();
        $billTax = (float) ($order->bill_tax_percent ?? 0);
        $billDiscount = (float) ($order->bill_discount_percent ?? 0);

        $baseItems = $order->items->map(static fn ($i) => [
            'product_id' => $i->product_id,
            'uom' => $i->uom,
            'qty' => (float) $i->qty,
            'unit_price' => (float) $i->unit_price,
            'discount_percent' => (float) $i->discount_percent,
            'tax_percent' => (float) $i->tax_percent,
            'notes' => $i->notes,
        ])->values()->all();

        $brokenStaffUom = $this->draftHasBrokenStaffUnitPricing($baseItems);
        $storedGrand = round((float) $order->grand_total, 2);

        $attempts = [];
        if ($customerType === 'ast_offr') {
            $attempts[] = ['sale' => 'staff', 'gas' => false];
        } elseif ($brokenStaffUom || $order->sale_mode === 'staff') {
            $attempts[] = ['sale' => 'staff', 'gas' => true];
            $attempts[] = ['sale' => 'staff', 'gas' => false];
        } else {
            $attempts[] = ['sale' => 'customer', 'gas' => false];
        }

        $best = null;
        foreach ($attempts as $attempt) {
            $itemsNormalized = $this->normalizePosCheckoutItems(
                $baseItems,
                $customerType,
                $attempt['sale'],
                (string) ($order->type ?? 'sale'),
                $attempt['gas'],
                false
            );

            [$subtotal, $discountTotal, $taxTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $pricing['tax_mode'] === 'bill' ? $billTax : 0.0,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
            ]);

            $delta = abs($storedGrand - $grandTotal);
            if ($best === null || $delta < $best['delta']) {
                $best = [
                    'delta' => $delta,
                    'sale' => $attempt['sale'],
                    'subtotal' => $subtotal,
                    'discount' => $discountTotal,
                    'tax' => $taxTotal,
                    'grand' => $grandTotal,
                    'itemsData' => $itemsData,
                ];
            }
        }

        if ($best === null || $best['delta'] < 0.02) {
            return false;
        }

        DB::connection('tenant')->transaction(function () use ($order, $best) {
            $order->update([
                'sale_mode' => $best['sale'],
                'subtotal' => $best['subtotal'],
                'discount_total' => $best['discount'],
                'tax_total' => $best['tax'],
                'grand_total' => $best['grand'],
            ]);
            $order->items()->delete();
            foreach ($best['itemsData'] as $item) {
                PosOrderItem::create(['order_id' => $order->id] + $item);
            }
        });

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function draftHasBrokenStaffUnitPricing(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return false;
        }

        $products = InventoryProduct::query()
            ->whereIn('id', $productIds)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->get(['id', 'cost', 'uom'])
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            if (! $product) {
                continue;
            }

            $factor = $product->factorToBaseForUom((string) ($item['uom'] ?? ''));
            if ($factor === null || $factor <= 0 || $factor >= 1) {
                continue;
            }

            $expectedStaff = round((float) $product->cost * $factor, 2);
            $stored = round((float) ($item['unit_price'] ?? 0), 2);
            $baseCost = round((float) $product->cost, 2);
            if ($expectedStaff <= 0 || $stored <= 0) {
                continue;
            }

            if (abs($stored - $baseCost) < 0.05 && abs($stored - $expectedStaff) > 0.05) {
                return true;
            }
        }

        return false;
    }

    private function findGuestPendingDraftOrder(
        int $sessionId,
        string $customerType,
        ?string $guestName,
        ?string $roomNo,
        ?int $excludeOrderId = null
    ): ?PosOrder {
        $query = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'draft');

        if ($excludeOrderId) {
            $query->where('id', '!=', $excludeOrderId);
        }

        foreach ($query->get(['id', 'order_no', 'customer_type', 'guest_name', 'room_no']) as $draft) {
            if (! $draft instanceof PosOrder) {
                continue;
            }

            $draftType = $draft->customerTypeKey();

            if ($customerType === 'booking' && $draftType === 'booking' && PosOrder::roomNumbersOverlap($roomNo, $draft->room_no)) {
                return $draft;
            }

            if ($customerType === 'mess_use' && $draftType === 'mess_use') {
                $guest = trim((string) $guestName);
                $draftGuest = trim((string) $draft->guest_name);
                if ($guest !== '' && strcasecmp($guest, $draftGuest) === 0) {
                    return $draft;
                }
            }

            if ($customerType === 'ast_offr' && $draftType === 'ast_offr') {
                $guest = trim((string) $guestName);
                $draftGuest = trim((string) $draft->guest_name);
                if ($guest !== '' && strcasecmp($guest, $draftGuest) === 0) {
                    return $draft;
                }
            }
        }

        return null;
    }

    private function normalizeCustomerType(mixed $value): string
    {
        $type = (string) $value;

        return in_array($type, ['booking', 'ast_offr', 'mess_use'], true) ? $type : 'mess_use';
    }

    private function isRestaurantPosRequest(Request $request): bool
    {
        return $request->routeIs('restaurant-pos.checkout') || $request->routeIs('restaurant-pos.hold');
    }

    private function normalizeServiceType(mixed $value): string
    {
        $type = (string) $value;

        return in_array($type, [
            PosOrder::SERVICE_DINE_IN,
            PosOrder::SERVICE_TAKEAWAY,
            PosOrder::SERVICE_DELIVERY,
        ], true) ? $type : PosOrder::SERVICE_DINE_IN;
    }

    /**
     * @return array{
     *     customer_type: string,
     *     service_type: string,
     *     guest_name: ?string,
     *     room_no: ?string,
     *     waiter_name: ?string,
     *     order_notes: ?string,
     *     serve_time: ?string,
     *     serve_date: ?string,
     *     is_credit: bool,
     *     contact_id: ?int,
     *     sale_mode: string,
     *     table_id: ?int
     * }
     */
    private function restaurantPosOrderMeta(Request $request): array
    {
        $serviceType = $this->normalizeServiceType($request->input('service_type'));
        $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';

        $guestName = null;
        $roomNo = null;
        $orderNotes = null;
        $tableId = null;

        if ($serviceType === PosOrder::SERVICE_DINE_IN) {
            if ($enableTables) {
                $tableId = $request->integer('table_id') ?: null;
            } else {
                $guestName = $this->nullableText($request->input('guest_name'));
            }
        } elseif ($serviceType === PosOrder::SERVICE_DELIVERY) {
            $guestName = $this->nullableText($request->input('guest_name'));
            $roomNo = $this->nullableText($request->input('room_no'));
            $orderNotes = $this->nullableText($request->input('order_notes'));
        }

        return [
            'customer_type' => 'mess_use',
            'service_type' => $serviceType,
            'guest_name' => $guestName,
            'room_no' => $roomNo,
            'waiter_name' => null,
            'order_notes' => $orderNotes,
            'serve_time' => null,
            'serve_date' => null,
            'is_credit' => $request->boolean('is_credit'),
            'contact_id' => $request->boolean('is_credit') ? ($request->integer('contact_id') ?: null) : null,
            'sale_mode' => 'customer',
            'table_id' => $tableId,
        ];
    }

    private function ensurePosTablesSchema(): void
    {
        try {
            if (! Schema::hasTable('pos_tables')) {
                Schema::create('pos_tables', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 60)->unique();
                    $table->boolean('active')->default(true);
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizePosCheckoutItems(
        array $items,
        string $customerType,
        string $saleMode,
        string $orderType,
        bool $staffIncludeGas,
        bool $trustClientPrices = false,
    ): array {
        $itemsNormalized = $this->canonicalizePosLineUoms($items);

        if ($customerType === 'ast_offr') {
            return $this->applySaleModePricing($itemsNormalized, 'staff', $orderType, false);
        }

        if ($trustClientPrices) {
            return $itemsNormalized;
        }

        $includeGas = $saleMode === 'staff';

        return $this->applySaleModePricing($itemsNormalized, $saleMode, $orderType, $includeGas);
    }

    /**
     * For staff sales, enforce line price = cost (+ optional gas) and no discount.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function applySaleModePricing(array $items, string $saleMode, string $orderType, bool $includeGas = false): array
    {
        if ($saleMode !== 'staff' || $orderType !== 'sale' || $items === []) {
            return $items;
        }

        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return $items;
        }

        $pricingByProduct = InventoryProduct::query()
            ->whereIn('id', $productIds)
            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
            ->get(['id', 'cost', 'gas_charges', 'extra_costs', 'uom'])
            ->keyBy('id');

        foreach ($items as &$item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $pricingByProduct->get($productId);
            if (! $product) {
                continue;
            }

            $factor = $product->factorToBaseForUom((string) ($item['uom'] ?? ''));
            if ($factor === null || $factor <= 0) {
                abort(422, "Invalid UOM '{$item['uom']}' for staff pricing on product #{$productId}.");
            }

            $gas = $includeGas ? $product->gasChargesAmount() : 0.0;
            if ($includeGas && $gas <= 0) {
                $rate = InventoryProduct::gasChargesRatePercent();
                if ($rate > 0) {
                    $gas = round((float) $product->cost * ($rate / 100), 2);
                }
            }
            $item['unit_price'] = round(((float) $product->cost + $gas) * $factor, 2);
            $item['discount_percent'] = 0;
        }
        unset($item);

        return $items;
    }

    /**
     * Kitchen-sent qty (served or pending) cannot be reduced or removed on hold/checkout
     * unless a matching kitchen void reason is supplied by an admin.
     *
     * @param  array<int, PosOrderItem>  $existingItems
     * @param  array<int, array<string, mixed>>  $incomingItems
     * @param  array<int, array<string, mixed>>  $kitchenVoids
     */
    private function assertKitchenLockedQuantitiesPreserved(array $existingItems, array $incomingItems, array $kitchenVoids = []): void
    {
        $kitchen = app(KitchenService::class);
        $lockedByFingerprint = [];
        foreach ($existingItems as $existing) {
            $qty = (float) $existing->qty;
            if ($qty <= 0) {
                continue;
            }
            $isServed = $existing->kitchen_served_at !== null;
            $isPending = (bool) $existing->kitchen_pending && ! $isServed;
            if (! $isServed && ! $isPending) {
                continue;
            }
            $fp = $kitchen->baseItemFingerprint($existing);
            $lockedByFingerprint[$fp] = ($lockedByFingerprint[$fp] ?? 0) + $qty;
        }

        if ($lockedByFingerprint === []) {
            return;
        }

        $voidByFingerprint = [];
        foreach ($kitchenVoids as $void) {
            $fp = $kitchen->baseItemFingerprint($void);
            $voidByFingerprint[$fp] = ($voidByFingerprint[$fp] ?? 0) + (float) ($void['qty'] ?? 0);
        }

        $incomingByFingerprint = [];
        foreach ($incomingItems as $incoming) {
            $fp = $kitchen->baseItemFingerprint($incoming);
            $incomingByFingerprint[$fp] = ($incomingByFingerprint[$fp] ?? 0) + (float) ($incoming['qty'] ?? 0);
        }

        foreach ($lockedByFingerprint as $fp => $lockedQty) {
            $newQty = $incomingByFingerprint[$fp] ?? 0;
            $allowedVoid = $voidByFingerprint[$fp] ?? 0;
            $minimumQty = max(0.0, $lockedQty - $allowedVoid);
            if ($newQty + 0.00001 < $minimumQty) {
                throw ValidationException::withMessages([
                    'items' => 'Kitchen me bheji hui items hataane ke liye reason dena zaroori hai.',
                ]);
            }
        }
    }

    private function assertKitchenVoidPermission(PosCheckoutRequest $request): void
    {
        if ($this->normalizedKitchenVoids($request) === []) {
            return;
        }

        $user = Auth::user();
        if (! $user || ! $user->bypassesModulePermissions()) {
            throw ValidationException::withMessages([
                'kitchen_voids' => 'Kitchen items sirf admin remove kar sakta hai.',
            ]);
        }
    }

    /**
     * @return list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string}>
     */
    private function normalizedKitchenVoids(PosCheckoutRequest $request): array
    {
        $voids = [];
        foreach ((array) $request->input('kitchen_voids', []) as $void) {
            if (! is_array($void)) {
                continue;
            }
            $reason = trim((string) ($void['reason'] ?? ''));
            $qty = (float) ($void['qty'] ?? 0);
            if ($reason === '' || $qty <= 0) {
                continue;
            }
            $voids[] = [
                'product_id' => (int) ($void['product_id'] ?? 0),
                'uom' => (string) ($void['uom'] ?? ''),
                'qty' => $qty,
                'reason' => $reason,
                'notes' => trim((string) ($void['notes'] ?? '')),
            ];
        }

        return $voids;
    }

    /**
     * @param  list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string}>  $kitchenVoids
     */
    private function logKitchenVoids(PosOrder $order, array $kitchenVoids): void
    {
        if ($kitchenVoids === []) {
            return;
        }

        foreach ($kitchenVoids as $void) {
            $label = trim((string) ($void['name'] ?? ''));
            if ($label === '') {
                $label = 'Product #'.(int) ($void['product_id'] ?? 0);
            }
            ActivityLogger::log(
                'pos.kitchen_void',
                sprintf(
                    'Kitchen item removed from %s: %s × %s — %s',
                    $order->order_no,
                    $label,
                    (float) ($void['qty'] ?? 0),
                    (string) ($void['reason'] ?? '')
                ),
                $order,
                ['void' => $void]
            );
        }
    }

    private function notifyStockUpdate(InventoryProduct $product, string $type, float $qtyBase, string $reference): void
    {
        $payload = [
            'title' => 'Stock Updated',
            'message' => "{$product->name} stock {$type} by {$qtyBase} {$product->uom}",
            'reference' => $reference,
        ];

        User::query()->chunkById(200, function ($users) use ($payload) {
            foreach ($users as $user) {
                $user->notify(new StockUpdated($payload));
            }
        });
    }
}
