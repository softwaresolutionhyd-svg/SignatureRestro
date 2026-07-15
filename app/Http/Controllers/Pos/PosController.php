<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosCashMovementRequest;
use App\Http\Requests\PosCheckoutRequest;
use App\Http\Requests\PosCloseSessionRequest;
use App\Http\Requests\PosOpenSessionRequest;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryUnit;
use App\Models\JournalEntry;
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
use App\Support\EnsuresKitchenAgentSchema;
use App\Support\PosServiceCharge;
use App\Support\PosRuntimeSchema;
use App\Support\ActivityLogger;
use App\Services\KitchenService;
use App\Services\ManufacturingStockService;
use App\Services\AutoJournalService;
use App\Services\NetworkPrinterService;
use App\Services\OrderTakerService;
use App\Services\PosPendingBillsService;
use App\Services\PosSessionSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
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
    use EnsuresKitchenAgentSchema;

    private const FIFO_EPSILON = 0.000001;

    public function __construct(
        private readonly ManufacturingStockService $manufacturingStock,
        private readonly AutoJournalService $autoJournal,
        private readonly OrderTakerService $orderTaker,
    ) {}

    public function restaurant(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $this->ensurePosSessionDailyClosingSchema();

        $session = $this->getOpenPosSessionForUser($user);

        if ($request->filled('resume_order')) {
            if ($session === null) {
                return redirect()
                    ->route('restaurant-pos.index')
                    ->with('warning', 'Pehle POS session open karein.');
            }
            $draft = $this->findDraftOrderForSession($session, $request->integer('resume_order'));
            if ($draft === null) {
                return redirect()
                    ->route('restaurant-pos.index')
                    ->with('warning', 'Pending order maujood nahi ya pehle se band ho chuki hai.');
            }
        }

        if ($session === null) {
            return view('pos.open-session', [
                'canOpen' => $this->userCanOpenPosSession($user),
            ]);
        }

        return view('pos.restaurant', $this->posIndexViewData($request, $session));
    }

    /**
     * @return array<string, mixed>
     */
    private function posIndexViewData(Request $request, PosSession $session): array
    {
        $this->ensurePosTablesSchema();
        $this->ensurePosOrderSchemaForCheckout();
        $this->ensurePosOrderItemsSchema();
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();

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

        $heldOrders = $this->heldOrdersForSession($session, $user);

        $paidOrders = PosOrder::query()
            ->where('session_id', $session->id)
            ->where('status', 'paid')
            ->when($session->opened_at, function ($q) use ($session) {
                $q->where(function ($sub) use ($session) {
                    $sub->where('paid_at', '>=', $session->opened_at)
                        ->orWhereNull('paid_at');
                });
            })
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
            if ($resumedOrder !== null && (int) $resumedOrder->session_id !== (int) $session->id) {
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
            'service_charge_enabled' => Setting::get('pos_service_charge_enabled', '0') === '1',
            'service_charge_percent' => (float) Setting::get('pos_service_charge_percent', 0),
            'resume_bill_tax_percent' => null,
            'resume_bill_discount_percent' => null,
            'resume_table_id' => $resumedOrder?->table_id ? (int) $resumedOrder->table_id : null,
            'resume_guest_name' => $resumedOrder?->guest_name ?? null,
            'resume_room_no' => $resumedOrder?->room_no ?? null,
            'resume_waiter_name' => $resumedOrder?->waiter_name ?? null,
            'resume_order_notes' => $resumedOrder?->order_notes ?? null,
            'resume_kitchen_notes' => $resumedOrder?->kitchen_notes ?? null,
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
            $posSettings['resume_is_owner_discount'] = (bool) ($resumedOrder->is_owner_discount ?? false);
        }

        $sessionCashExpected = $this->sessionCashBreakdown($session);
        $sessionPosStats = $this->sessionPosStats($session);
        $checkedInRooms = $this->checkedInRoomsForPos();
        $canReopenPaidBill = $this->userCanReopenPaidPosBill($user);
        $posStaffCaps = $this->posStaffCapabilities($user);
        $canPosPay = $posStaffCaps['can_pay'];
        $canPosDiscount = $posStaffCaps['can_discount'];
        $canPosDiscountCredit = $posStaffCaps['can_discount_credit'];
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

        return compact('session', 'products', 'heldOrders', 'paidOrders', 'paidBillsDetail', 'pendingBillsDetail', 'resumedOrder', 'contacts', 'posSettings', 'sessionCashExpected', 'sessionPosStats', 'tables', 'tableBoard', 'checkedInRooms', 'waiters', 'recentDailyClosings', 'canReopenPaidBill', 'canPosPay', 'canPosDiscount', 'canPosDiscountCredit');
    }

    private function userCanReopenPaidPosBill(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->bypassesModulePermissions()) {
            return true;
        }

        $employee = $this->resolvePosEmployee($user);
        if ($employee === null) {
            return false;
        }

        return $this->employeeIsPosManager($employee);
    }

    /**
     * @return array{
     *   can_pay: bool,
     *   can_discount: bool,
     *   can_discount_credit: bool,
     *   is_manager: bool,
     *   is_cashier: bool
     * }
     */
    private function posStaffCapabilities(?User $user): array
    {
        if ($user === null) {
            return [
                'can_pay' => false,
                'can_discount' => false,
                'can_discount_credit' => false,
                'is_manager' => false,
                'is_cashier' => false,
            ];
        }

        if ($user->bypassesModulePermissions()) {
            return [
                'can_pay' => true,
                'can_discount' => true,
                'can_discount_credit' => true,
                'is_manager' => true,
                'is_cashier' => true,
            ];
        }

        $employee = $this->resolvePosEmployee($user);
        if ($employee === null) {
            return [
                'can_pay' => false,
                'can_discount' => false,
                'can_discount_credit' => false,
                'is_manager' => false,
                'is_cashier' => false,
            ];
        }

        $isManager = $this->employeeIsPosManager($employee);
        $isCashier = $this->employeeIsPosCashier($employee);

        return [
            'can_pay' => $isCashier,
            'can_discount' => $isManager || $isCashier,
            'can_discount_credit' => $isManager,
            'is_manager' => $isManager,
            'is_cashier' => $isCashier,
        ];
    }

    /** @var array<int, Employee|null> Per-request cache keyed by user id. */
    private array $posEmployeeCache = [];

    private function resolvePosEmployee(?User $user): ?Employee
    {
        if ($user === null) {
            return null;
        }

        if (array_key_exists($user->id, $this->posEmployeeCache)) {
            return $this->posEmployeeCache[$user->id];
        }

        $employee = $user->relationLoaded('employee') ? $user->getRelation('employee') : null;
        if ($employee !== null) {
            return $this->posEmployeeCache[$user->id] = ($employee->active ? $employee : null);
        }

        $query = Employee::withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->where('active', true);

        if ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $this->posEmployeeCache[$user->id] = $query->first();
    }

    private function employeeDesignationText(Employee $employee): string
    {
        $employee->loadMissing('designation:id,name');

        $name = trim((string) ($employee->designation?->name ?? ''));
        if ($name !== '') {
            return mb_strtolower($name, 'UTF-8');
        }

        if (Schema::connection('tenant')->hasColumn('employees', 'designation')) {
            return mb_strtolower(trim((string) $employee->getAttribute('designation')), 'UTF-8');
        }

        return '';
    }

    private function employeeStaffCategoryText(Employee $employee): string
    {
        $employee->loadMissing('staffCategory:id,name');

        return mb_strtolower(trim((string) ($employee->staffCategory?->name ?? '')), 'UTF-8');
    }

    private function labelMatchesCashier(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return $value === 'cashier' || str_contains($value, 'cashier');
    }

    private function employeeIsPosCashier(Employee $employee): bool
    {
        if ($this->labelMatchesCashier($this->employeeDesignationText($employee))) {
            return true;
        }

        return $this->labelMatchesCashier($this->employeeStaffCategoryText($employee));
    }

    private function employeeIsPosManager(Employee $employee): bool
    {
        $designation = $this->employeeDesignationText($employee);

        return $designation !== ''
            && (
                str_contains($designation, 'manager')
                || str_contains($designation, 'owner')
                || str_contains($designation, 'admin')
            );
    }

    private function posUsesSharedBills(?User $user): bool
    {
        if ($user?->bypassesModulePermissions()) {
            return true;
        }

        $caps = $this->posStaffCapabilities($user);

        return $caps['is_manager'] || $caps['is_cashier'];
    }

    private function todayBusinessDate(): string
    {
        return now()->toDateString();
    }

    /**
     * @return list<int>
     */
    private function sessionIdsForBusinessDate(?string $date = null, bool $openOnly = false): array
    {
        $date = $date ?? $this->todayBusinessDate();

        $query = PosSession::query()
            ->where(function ($q) use ($date) {
                $q->where('business_date', $date)
                    ->orWhereDate('opened_at', $date);
            });

        if ($openOnly) {
            $query->where('status', 'open');
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function resolvePosBillSessionIds(PosSession $session, ?User $user): array
    {
        if ($this->posUsesSharedBills($user)) {
            $date = $session->business_date instanceof \Illuminate\Support\Carbon
                ? $session->business_date->toDateString()
                : (string) ($session->business_date ?: $this->todayBusinessDate());

            return $this->sessionIdsForBusinessDate($date);
        }

        return [(int) $session->id];
    }

    private function assertPosCheckoutPermissions(User $user, PosCheckoutRequest $request, bool $isCredit, bool $isCheckout): void
    {
        $caps = $this->posStaffCapabilities($user);

        if ($isCheckout) {
            if ($isCredit) {
                abort_unless($caps['can_discount_credit'], 403, 'Credit sirf manager de sakta hai.');
            } else {
                abort_unless($caps['can_pay'], 403, 'Pay sirf cashier kar sakta hai.');
            }
        }

        if ($request->boolean('is_owner_discount')) {
            if (! $caps['can_discount_credit']) {
                abort_unless(
                    $this->resumeOrderPreservesOwnerDiscount($user, $request),
                    403,
                    'Owner discount sirf manager de sakta hai.'
                );
            }
        }

        $billDisc = round((float) $request->input('bill_discount_percent', 0), 3);
        if ($billDisc > 0 && ! $request->boolean('is_owner_discount')) {
            abort_unless($caps['can_discount'], 403, 'Discount sirf cashier ya manager de sakta hai.');
        }
    }

    private function resumeOrderPreservesOwnerDiscount(User $user, PosCheckoutRequest $request): bool
    {
        $resumeOrderId = $request->integer('resume_order_id') ?: null;
        if (! $resumeOrderId) {
            return false;
        }

        try {
            $session = $this->requireOpenSessionForUser($user);
        } catch (\Throwable) {
            return false;
        }

        $draft = $this->findDraftOrderForSession($session, $resumeOrderId, $user);

        return $draft !== null && (bool) ($draft->is_owner_discount ?? false);
    }

    public function sync(Request $request): JsonResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $session = $this->requireOpenSessionForUser(Auth::user());

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
     * Kitchen print ke baad bill se hataaye gaye items (reasons ke sath) — sirf manager/admin.
     */
    public function sessionKitchenVoids(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->userCanKitchenVoid($user), 403);

        $session = $this->requireOpenSessionForUser($user);
        $billSessionIds = $this->resolvePosBillSessionIds($session, $user);

        if ($billSessionIds === []) {
            return response()->json(['items' => [], 'count' => 0]);
        }

        $orderIds = PosOrder::query()
            ->whereIn('session_id', $billSessionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($orderIds === []) {
            return response()->json(['items' => [], 'count' => 0]);
        }

        $logs = ActivityLog::query()
            ->where('action', 'pos.kitchen_void')
            ->where('subject_type', PosOrder::class)
            ->whereIn('subject_id', $orderIds)
            ->with(['user:id,name', 'subject:id,order_no,table_id,guest_name,room_no'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $missingProductIds = $logs->map(function (ActivityLog $log) {
            $void = is_array($log->properties) ? ($log->properties['void'] ?? []) : [];
            if (! is_array($void)) {
                return 0;
            }
            $name = trim((string) ($void['name'] ?? ''));
            $productId = (int) ($void['product_id'] ?? 0);

            return ($name === '' || str_starts_with($name, 'Product #')) && $productId > 0
                ? $productId
                : 0;
        })->filter()->unique()->values()->all();

        $productNames = $missingProductIds === []
            ? collect()
            : InventoryProduct::query()
                ->whereIn('id', $missingProductIds)
                ->pluck('name', 'id');

        $items = $logs->map(function (ActivityLog $log) use ($productNames) {
            /** @var PosOrder|null $order */
            $order = $log->subject;
            $void = is_array($log->properties) ? ($log->properties['void'] ?? []) : [];
            if (! is_array($void)) {
                $void = [];
            }

            $productId = (int) ($void['product_id'] ?? 0);
            $name = trim((string) ($void['name'] ?? ''));
            if ($name === '' || str_starts_with($name, 'Product #')) {
                $resolved = trim((string) ($productNames[$productId] ?? ''));
                if ($resolved !== '') {
                    $name = $resolved;
                }
            }
            if ($name === '') {
                $name = $productId > 0 ? 'Product #'.$productId : 'Item';
            }

            return [
                'id' => (int) $log->id,
                'order_id' => (int) $log->subject_id,
                'order_no' => (string) ($order?->order_no ?? ('#'.$log->subject_id)),
                'product' => $name,
                'qty' => round((float) ($void['qty'] ?? 0), 3),
                'uom' => (string) ($void['uom'] ?? ''),
                'reason' => (string) ($void['reason'] ?? ''),
                'notes' => (string) ($void['notes'] ?? ''),
                'cancelled_at' => $log->created_at?->format('d M Y, h:i A') ?? '',
                'cancelled_by' => (string) ($log->user?->name ?? '—'),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'count' => $items->count(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    private function heldOrdersForSession(PosSession $session, ?User $user = null): \Illuminate\Support\Collection
    {
        $user = $user ?? Auth::user();
        $billSessionIds = $this->resolvePosBillSessionIds($session, $user);
        $heldOrders = app(PosPendingBillsService::class)->queryHeldDrafts($billSessionIds, false);

        // Batch eager-load once for the whole collection (avoids N+1 per draft).
        if ($heldOrders->isNotEmpty()) {
            $heldOrders->load(['items.product:id,name', 'table:id,name']);
            $heldOrders->loadCount('items');
        }

        foreach ($heldOrders as $draft) {
            if ($this->repairDraftOrderIfNeeded($draft)) {
                $draft->refresh();
                $draft->loadMissing(['items.product:id,name', 'table:id,name']);
                $draft->loadCount('items');
            }
        }

        return $heldOrders
            ->filter(fn (PosOrder $order) => $order->isDueForServeDay())
            ->sortByDesc('id')
            ->values();
    }

    private function findDraftOrderForSession(PosSession $session, int $orderId, ?User $user = null): ?PosOrder
    {
        if ($orderId <= 0) {
            return null;
        }

        $user = $user ?? Auth::user();
        $billSessionIds = $this->resolvePosBillSessionIds($session, $user);
        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');

        return PosOrder::query()
            ->where('id', $orderId)
            ->where('status', 'draft')
            ->where(function ($q) use ($billSessionIds, $hasOrderTakerColumns) {
                $q->whereIn('session_id', $billSessionIds);
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
        return app(PosSessionSummaryService::class)->stats($session);
    }

    /**
     * @return array{cash_from_sales: float, cash_refunds_paid: float, cash_in: float, cash_out: float, expected_closing: float}
     */
    private function sessionCashBreakdown(PosSession $session): array
    {
        return app(PosSessionSummaryService::class)->cashBreakdown($session);
    }

    public function closing(): \Illuminate\View\View
    {
        PosRuntimeSchema::ensureForSessionSummary();
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();
        $session = $this->getOpenPosSessionForUser($user);
        if ($session !== null) {
            $session->loadMissing('user:id,name');
        }

        $currency = Setting::get('currency_symbol', 'Rs.');
        $companyName = Setting::get('company_name', config('app.name'));

        if ($session === null) {
            return view('pos.closing.index', [
                'session' => null,
                'stats' => null,
                'cash' => null,
                'amountToCollect' => 0,
                'currency' => $currency,
                'companyName' => $companyName,
                'canClose' => false,
                'noOpenSession' => true,
            ]);
        }

        $summary = app(PosSessionSummaryService::class)->summaryPayload($session);
        $pendingCount = $this->heldOrdersForSession($session, $user)->count();
        $stats = array_merge($summary['stats'], [
            'held_count' => $pendingCount,
            'can_close_session' => $pendingCount === 0,
        ]);

        return view('pos.closing.index', [
            'session' => $session,
            'stats' => $stats,
            'cash' => $summary['cash'],
            'amountToCollect' => $summary['amount_to_collect'],
            'currency' => $currency,
            'companyName' => $companyName,
            'canClose' => $pendingCount === 0,
            'noOpenSession' => false,
        ]);
    }

    public function closingPrint(): \Illuminate\View\View
    {
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();
        $session = $this->getOpenPosSessionForUser($user);
        abort_if($session === null, 404, 'Koi open POS session nahi hai.');

        return $this->closingPrintView($session);
    }

    public function closingPrintSession(PosSession $session): \Illuminate\View\View
    {
        $this->ensurePosSessionDailyClosingSchema();
        abort_unless($session->status === 'closed', 404);

        return $this->closingPrintView($session);
    }

    private function closingPrintView(PosSession $session): \Illuminate\View\View
    {
        $summary = app(PosSessionSummaryService::class)->summaryPayload($session);

        return view('pos.closing.print', [
            'session' => $session->loadMissing('user:id,name'),
            'stats' => $summary['stats'],
            'cash' => $summary['cash'],
            'amountToCollect' => $summary['amount_to_collect'],
            'currency' => Setting::get('currency_symbol', 'Rs.'),
            'companyName' => Setting::get('company_name', config('app.name')),
            'printedBy' => Auth::user()?->name,
            'autoPrint' => request()->boolean('auto'),
        ]);
    }

    public function openSession(PosOpenSessionRequest $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($this->userCanOpenPosSession($user), 403, 'POS session sirf cashier open kar sakta hai.');

        $this->ensurePosSessionDailyClosingSchema();

        if ($this->getOpenPosSessionForUser($user) !== null) {
            return redirect()->route('restaurant-pos.index')->with('success', 'POS session pehle se open hai.');
        }

        // Reuse any still-open session (even from previous days) — never auto-close overnight.
        $pending = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($pending !== null) {
            $pending->update([
                'shift_started' => true,
                'opening_cash' => 0,
                'note' => $request->input('note') ?: $pending->note,
            ]);

            return redirect()->route('restaurant-pos.index')->with('success', 'POS session open ho gayi.');
        }

        $this->createDailySession($user, $request->input('note'));

        return redirect()->route('restaurant-pos.index')->with('success', 'POS session open ho gayi.');
    }

    public function closeSession(PosCloseSessionRequest $request): RedirectResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $user = Auth::user();
        abort_unless(
            $user !== null && ($user->bypassesModulePermissions() || $user->canAccessPosClosing()),
            403,
            'POS session sirf manager ya admin close kar sakta hai.'
        );

        $session = $this->getOpenPosSessionForUser($user);
        abort_if($session === null, 404, 'Koi open POS session nahi hai.');

        $heldDraft = $this->heldOrdersForSession($session, $user)->count();
        if ($heldDraft > 0) {
            return back()->with(
                'error',
                "Session close nahi ho sakti: {$heldDraft} pending bill(s) abhi bhi maujood hain. Pehle Restaurant POS par ja kar pay ya discard karein."
            );
        }

        $this->finalizeSessionClose(
            $session,
            $request->note,
            $request->filled('counted_cash') ? round((float) $request->input('counted_cash'), 2) : null
        );

        return redirect()->route('reports.pos-sessions')->with('success', 'POS session close ho gayi aur save ho gayi.');
    }

    private function finalizeSessionClose(PosSession $session, ?string $note = null, ?float $countedCash = null): void
    {
        $stats = $this->sessionPosStats($session);
        $cashBreakdown = $this->sessionCashBreakdown($session);
        $amountToCollect = round(
            $stats['payments_cash'] + $cashBreakdown['cash_in'] - $cashBreakdown['cash_out'],
            2
        );
        $counted = $countedCash ?? $amountToCollect;

        $session->update([
            'status' => 'closed',
            'closing_cash' => $stats['payments_cash'],
            'closing_bank' => $stats['payments_bank'],
            'closing_card' => $stats['payments_card'],
            'amount_to_collect' => $amountToCollect,
            'expected_cash' => $amountToCollect,
            'cash_difference' => round($counted - $amountToCollect, 2),
            'closed_at' => now(),
            'note' => $note ?: $session->note,
            'business_date' => $session->business_date ?? now()->toDateString(),
        ]);
    }

    private function getOpenPosSessionForUser(User $user): ?PosSession
    {
        $own = PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($own !== null) {
            if ($this->userIsPosCashier($user) && ! $this->posSessionShiftStarted($own)) {
                return null;
            }

            return $own;
        }

        if ($this->posUsesSharedBills($user) && ! $this->userIsPosCashier($user)) {
            $query = PosSession::query()->where('status', 'open');

            if ($this->posSessionsHaveShiftStartedColumn()) {
                $query->where('shift_started', true);
            }

            return $query->latest('id')->first();
        }

        return null;
    }

    private function posSessionsHaveShiftStartedColumn(): bool
    {
        return \Illuminate\Support\Facades\Schema::connection('tenant')
            ->hasColumn('pos_sessions', 'shift_started');
    }

    private function posSessionShiftStarted(PosSession $session): bool
    {
        if (! $this->posSessionsHaveShiftStartedColumn()) {
            return false;
        }

        return (bool) $session->shift_started;
    }

    private function requireOpenSessionForUser(User $user): PosSession
    {
        $session = $this->getOpenPosSessionForUser($user);
        if ($session === null) {
            throw new HttpResponseException(
                redirect()->route('restaurant-pos.index')
                    ->with('warning', 'Pehle POS session open karein.')
            );
        }

        return $session;
    }

    private function userIsPosCashier(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $employee = $this->resolvePosEmployee($user);

        return $employee !== null && $this->employeeIsPosCashier($employee);
    }

    private function userCanOpenPosSession(?User $user): bool
    {
        return $this->userIsPosCashier($user);
    }

    /**
     * Previously auto-closed previous-day open sessions. Disabled — sessions stay
     * open until manager/admin closes them from POS Closing.
     */
    private function rolloverStaleOpenSessionsForUser(User $user, PosSession $newSession): void
    {
        // no-op
    }

    private function createDailySession(User $user, ?string $note = null): PosSession
    {
        return PosSession::create([
            'session_no' => $this->nextDailySessionNo($user),
            'business_date' => now()->toDateString(),
            'user_id' => $user->id,
            'status' => 'open',
            'shift_started' => true,
            'opening_cash' => 0,
            'opened_at' => now(),
            'note' => $note,
        ]);
    }

    private function nextDailySessionNo(User $user): string
    {
        $prefix = 'DAY-'.now()->format('dmy').'-'.$user->id;

        if (! PosSession::query()->where('session_no', $prefix)->exists()) {
            return $prefix;
        }

        for ($suffix = 2; $suffix <= 99; $suffix++) {
            $candidate = $prefix.'-'.$suffix;
            if (! PosSession::query()->where('session_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.'-'.now()->format('His');
    }

    private function ensurePosSessionDailyClosingSchema(): void
    {
        PosRuntimeSchema::ensureSessionsDailyClosing();
    }

    public function addCashMovement(PosCashMovementRequest $request): RedirectResponse
    {
        $this->ensurePosSessionDailyClosingSchema();
        $session = $this->requireOpenSessionForUser(Auth::user());

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

        $session = $this->requireOpenSessionForUser(Auth::user());
        $wantsJson = $request->expectsJson() && $this->isRestaurantPosRequest($request);
        $checkoutUser = $request->user();

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
            $this->assertPosCheckoutPermissions($checkoutUser, $request, $isCredit, true);
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
        $this->assertItemReductionPermission($request);

        if ($resumeOrderId) {
            $resumeDraft = $this->findDraftOrderForSession($session, $resumeOrderId, $checkoutUser);
            if ($resumeDraft) {
                $this->assertCartQtyNotReducedByNonManager(
                    $resumeDraft->items()->get()->all(),
                    $itemsNormalized,
                    $checkoutUser
                );
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

        try {
            $order = DB::connection('tenant')->transaction(function () use ($request, $session, $isCredit, $contactId, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $resumeOrderId, $customerType, $saleMode, $serviceType, $restaurantTableId, $checkoutUser) {
            $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
            if ($this->isRestaurantPosRequest($request)) {
                $tableId = $restaurantTableId;
            } else {
                $tableId = ($enableTables && $customerType !== 'booking') ? $request->integer('table_id') : null;
            }

            $usesTable = $this->isRestaurantPosRequest($request)
                ? ($serviceType === PosOrder::SERVICE_DINE_IN && $tableId)
                : ($enableTables && $customerType !== 'booking' && $tableId);
            if ($usesTable) {
                $this->orderTaker->assertTableAvailable($tableId, $resumeOrderId ?: null, true);
            }

            $pricing = $this->posPricingOptions();
            $billTax = $pricing['tax_mode'] === 'bill'
                ? round((float) $request->input('bill_tax_percent', $pricing['default_tax_rate']), 3)
                : 0.0;
            $ownerDiscount = $this->isOwnerDiscountRequest($request, $pricing['allow_discount'], $saleMode);
            $billDiscount = $this->resolveBillDiscountPercent($request, $pricing['allow_discount'], $saleMode);
            [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $billTax,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
                'service_type' => $serviceType,
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

            $orderPayload = [
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
                'kitchen_notes'      => $this->nullableText($request->input('kitchen_notes')),
                'serve_time'         => $serveTime,
                'serve_date'         => $serveDate,
                'is_credit'          => $isCredit,
                'refund_of_order_id' => $request->refund_of_order_id,
                'type'               => $request->type,
                'status'             => 'paid',
                'subtotal'           => $subtotal,
                'discount_total'     => $discountTotal,
                'tax_total'          => $taxTotal,
                'service_charge_percent' => $serviceTotal > 0 ? PosServiceCharge::percent() : null,
                'service_charge_total' => $serviceTotal,
                'bill_tax_percent'   => $pricing['tax_mode'] === 'bill' ? $billTax : null,
                'bill_discount_percent' => $pricing['allow_discount'] ? $billDiscount : null,
                'is_owner_discount'  => $ownerDiscount,
                'grand_total'        => $grandTotal,
                'cash_tendered'      => $cashTendered,
                'cash_change'        => $cashChange,
                'paid_at'            => now(),
            ];

            $existingDraft = null;
            if ($resumeOrderId) {
                $billSessionIds = $this->resolvePosBillSessionIds($session, $checkoutUser);
                $existingDraft = PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->whereIn('session_id', $billSessionIds)
                    ->lockForUpdate()
                    ->first();
            }

            $kitchen = app(KitchenService::class);
            $oldKitchenItems = $existingDraft ? $existingDraft->items()->get()->all() : [];
            if ($oldKitchenItems === [] && $resumeOrderId) {
                $billSessionIds = $billSessionIds ?? $this->resolvePosBillSessionIds($session, $checkoutUser);
                $draftForKitchen = PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->whereIn('session_id', $billSessionIds)
                    ->first();
                if ($draftForKitchen) {
                    $oldKitchenItems = $draftForKitchen->items()->get()->all();
                }
            }

            $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags($oldKitchenItems, $itemsData);

            if ($existingDraft) {
                $existingDraft->update($orderPayload + [
                    'session_id' => $session->id,
                    'user_id' => Auth::id(),
                ]);
                $order = $existingDraft;
                $order->items()->delete();
            } else {
                $order = PosOrder::create([
                    'order_no'   => DailyOrderNumber::next(),
                    'session_id' => $session->id,
                ] + $orderPayload);
            }

            foreach ($itemsWithKitchenFlags as $item) {
                PosOrderItem::create(['order_id' => $order->id] + $item);
                $this->applyInventoryForPos($order, $item);
            }

            CreditLedger::query()->where('pos_order_id', $order->id)->delete();
            $order->payments()->delete();

            if ($isCredit) {
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

            if ($resumeOrderId && ! $existingDraft) {
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
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $this->logKitchenVoids($order, $this->normalizedKitchenVoids($request));
        $this->logItemReductions($order, $this->normalizedItemReductions($request));
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

        $session = $this->requireOpenSessionForUser(Auth::user());
        $holdUser = $request->user();

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
            $this->assertPosCheckoutPermissions($holdUser, $request, false, false);
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
        $this->assertItemReductionPermission($request);

        if ($resumeOrderId) {
            $resumeDraft = $this->findDraftOrderForSession($session, $resumeOrderId, $holdUser);
            if ($resumeDraft) {
                $this->assertCartQtyNotReducedByNonManager(
                    $resumeDraft->items()->get()->all(),
                    $itemsNormalized,
                    $holdUser
                );
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

        try {
            $order = DB::connection('tenant')->transaction(function () use ($request, $session, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $customerType, $saleMode, $serviceType, $restaurantTableId, $resumeOrderId, $clientTotals, $sendToKitchen, &$updatedExisting, $holdUser) {
            $enableTables = (string) Setting::get('pos_enable_tables', '1') !== '0';
            if ($this->isRestaurantPosRequest($request)) {
                $tableId = $restaurantTableId;
            } else {
                $tableId = ($enableTables && $customerType !== 'booking') ? $request->integer('table_id') : null;
            }

            $usesTable = $this->isRestaurantPosRequest($request)
                ? ($serviceType === PosOrder::SERVICE_DINE_IN && $tableId)
                : ($enableTables && $customerType !== 'booking' && $tableId);
            if ($usesTable) {
                $this->orderTaker->assertTableAvailable($tableId, $resumeOrderId ?: null, true);
            }

            $pricing = $this->posPricingOptions();
            $billTax = $pricing['tax_mode'] === 'bill'
                ? round((float) $request->input('bill_tax_percent', $pricing['default_tax_rate']), 3)
                : 0.0;
            $ownerDiscount = $this->isOwnerDiscountRequest($request, $pricing['allow_discount'], $saleMode);
            $billDiscount = $this->resolveBillDiscountPercent($request, $pricing['allow_discount'], $saleMode);
            [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $billTax,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
                'service_type' => $serviceType,
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
                'kitchen_notes' => $this->nullableText($request->input('kitchen_notes')),
                'serve_time' => $serveTime,
                'serve_date' => $serveDate,
                'type' => $request->type,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'service_charge_percent' => $serviceTotal > 0 ? PosServiceCharge::percent() : null,
                'service_charge_total' => $serviceTotal,
                'bill_tax_percent' => $pricing['tax_mode'] === 'bill' ? $billTax : null,
                'bill_discount_percent' => $pricing['allow_discount'] ? $billDiscount : null,
                'is_owner_discount' => $ownerDiscount,
                'grand_total' => $grandTotal,
            ];

            if ($resumeOrderId) {
                $billSessionIds = $this->resolvePosBillSessionIds($session, $holdUser);
                $existing = PosOrder::query()
                    ->where('id', $resumeOrderId)
                    ->where('status', 'draft')
                    ->whereIn('session_id', $billSessionIds)
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

                    $existing->update($orderPayload + $kitchenPayload + [
                        'session_id' => $session->id,
                        'user_id' => Auth::id(),
                    ]);
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
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($this->repairDraftOrderIfNeeded($order)) {
            $order->refresh();
            $order->loadMissing('table');
        }

        $this->logKitchenVoids($order, $this->normalizedKitchenVoids($request));
        $this->logItemReductions($order, $this->normalizedItemReductions($request));

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
            $session = $this->requireOpenSessionForUser(Auth::user());
            $order->update(['session_id' => $session->id]);

            return redirect()->route($uiRoute, ['resume_order' => $order->id]);
        }

        $session = $this->requireOpenSessionForUser(Auth::user());
        if ($this->findDraftOrderForSession($session, (int) $order->id) === null) {
            abort(403);
        }

        if ((int) $order->session_id !== (int) $session->id) {
            $order->update(['session_id' => $session->id]);
        }

        return redirect()->route($uiRoute, ['resume_order' => $order->id]);
    }

    public function reopenPaidBill(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless($this->userCanReopenPaidPosBill($request->user()), 403);
        abort_unless($order->status === 'paid', 404);
        abort_unless($order->type === 'sale', 403);

        if (PosOrder::query()->where('refund_of_order_id', $order->id)->exists()) {
            return back()->with('error', 'Is bill ki refund entry maujood hai — pehle refund hataen.');
        }

        $session = $this->requireOpenSessionForUser(Auth::user());

        try {
            DB::connection('tenant')->transaction(function () use ($order, $session) {
                $locked = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'paid') {
                    throw new \RuntimeException('Bill pehle se reopen ho chuki hai.');
                }

                $this->reversePaidOrderInventory($locked);
                CreditLedger::query()->where('pos_order_id', $locked->id)->delete();
                $locked->payments()->delete();
                $this->deletePosJournalEntries($locked);

                $locked->update([
                    'status' => 'draft',
                    'paid_at' => null,
                    'cash_tendered' => null,
                    'cash_change' => null,
                    'session_id' => $session->id,
                    'user_id' => Auth::id(),
                ]);

                ActivityLogger::log(
                    'pos.bill_reopened',
                    'Paid POS bill reopened for editing',
                    $locked->fresh(),
                    ['order_no' => $locked->order_no]
                );
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Bill reopen nahi ho saki.');
        }

        return redirect()
            ->route('restaurant-pos.index', ['resume_order' => $order->id])
            ->with('success', "Bill {$order->order_no} reopen ho gayi — ab edit kar ke dubara pay karein.");
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
        $session = $this->requireOpenSessionForUser(Auth::user());
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

        if ($order->status !== 'draft') {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'Is order ko discard nahi kar sakte.',
                ], 403);
            }

            abort(403);
        }

        if ($this->findDraftOrderForSession($session, (int) $order->id) === null) {
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

        $order->load(['items.product:id,name,sku,department_id', 'items.product.departments:id,name', 'user:id,name', 'table:id,name']);

        $kitchenItems = $order->items->filter(
            fn (PosOrderItem $item) => (bool) $item->kitchen_pending && ! $item->isKitchenServed()
        )->values();

        abort_unless($kitchenItems->isNotEmpty(), 404);

        $departmentName = 'KITCHEN';
        $deptNames = $kitchenItems
            ->map(function (PosOrderItem $item) {
                $dept = $this->resolveItemDepartment($item->product);

                return $dept?->name;
            })
            ->filter()
            ->unique()
            ->values();
        if ($deptNames->count() === 1) {
            $departmentName = (string) $deptNames->first();
        }

        $settings = $this->receiptSettingsMap();
        $autoPrint = ! $request->boolean('noprint', false) && $request->boolean('autoprint', true);
        $backUrl = route('restaurant-pos.index', ['resume_order' => $order->id]);
        $backLabel = '← Back to order';

        return view('pos.kitchen-slip', compact('order', 'kitchenItems', 'settings', 'autoPrint', 'backUrl', 'backLabel', 'departmentName'));
    }

    /**
     * Auto-print kitchen slips: each pending item goes to its department's assigned printer (IP:port).
     * Returns JSON. If no department printer is configured, returns fallback=true so the client
     * can print the normal browser slip instead.
     */
    public function kitchenPrintNetwork(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless($order->status === 'draft', 404);
        $this->assertDraftReceiptAccess($order);
        $this->ensureKitchenAgentSchema();

        $order->load([
            'items.product:id,name,sku,department_id',
            'items.product.departments:id,name,printer_ip,printer_port',
            'user:id,name',
            'table:id,name',
        ]);

        $kitchenItems = $order->items->filter(
            fn (PosOrderItem $item) => (bool) $item->kitchen_pending && ! $item->isKitchenServed()
        )->values();

        if ($kitchenItems->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Koi kitchen item pending nahi.'], 422);
        }

        // Group items by their resolved department printer.
        $groups = [];      // deptId => ['dept' => InventoryDepartment, 'items' => PosOrderItem[]]
        $unrouted = 0;
        foreach ($kitchenItems as $item) {
            $dept = $this->resolveItemDepartment($item->product);
            if ($dept && ! empty($dept->printer_ip)) {
                $groups[$dept->id]['dept'] = $dept;
                $groups[$dept->id]['items'][] = $item;
            } else {
                $unrouted++;
            }
        }

        if ($groups === []) {
            // Nothing has a printer assigned — let the client fall back to browser printing.
            return response()->json([
                'ok' => false,
                'fallback' => true,
                'message' => 'Kisi department ka printer set nahi (Inventory → Kitchen Agents).',
            ]);
        }

        $printer = app(NetworkPrinterService::class);
        $company = Setting::get('company_name', config('app.name'));
        $results = [];

        foreach ($groups as $group) {
            /** @var \App\Models\InventoryDepartment $dept */
            $dept = $group['dept'];
            $payload = $printer->buildKitchenSlip($order, (string) $dept->name, $group['items'], $company);

            try {
                $printer->send((string) $dept->printer_ip, (int) ($dept->printer_port ?: 9100), $payload);
                $results[] = ['department' => $dept->name, 'ok' => true];
            } catch (\Throwable $e) {
                $results[] = ['department' => $dept->name, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        $anyOk = collect($results)->contains(fn ($r) => $r['ok'] === true);

        return response()->json([
            'ok' => $anyOk,
            'results' => $results,
            'unrouted' => $unrouted,
        ], $anyOk ? 200 : 500);
    }

    /**
     * Print the bill to the assigned CASHIER printer (Inventory → Kitchen Agents → CASHIER).
     */
    public function cashierPrintNetwork(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless(in_array($order->status, ['draft', 'paid'], true), 404);
        if ($order->status === 'paid') {
            abort_unless((int) $order->user_id === (int) Auth::id(), 403);
        } else {
            $this->assertDraftReceiptAccess($order);
        }

        $ip = trim((string) Setting::get('cashier_printer_ip', ''));
        if ($ip === '') {
            return response()->json([
                'ok' => false,
                'fallback' => true,
                'message' => 'Cashier printer set nahi (Inventory → Kitchen Agents → CASHIER).',
            ]);
        }

        $order->load(['items.product:id,name,sku', 'user:id,name', 'table:id,name']);
        $settings = $this->receiptSettingsMap();
        $printer = app(NetworkPrinterService::class);
        $payload = $printer->buildBillSlip($order, $settings);

        try {
            $printer->send($ip, (int) (Setting::get('cashier_printer_port', 9100) ?: 9100), $payload);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Resolve which department (with a printer) a product should print to.
     */
    private function resolveItemDepartment(?InventoryProduct $product): ?\App\Models\InventoryDepartment
    {
        if ($product === null) {
            return null;
        }

        $depts = $product->relationLoaded('departments') ? $product->departments : collect();

        if ($depts->isEmpty()) {
            return null;
        }

        // Prefer the product's primary department if it has a printer.
        $primary = $depts->firstWhere('id', $product->department_id);
        if ($primary && ! empty($primary->printer_ip)) {
            return $primary;
        }

        // Otherwise the first tagged department that has a printer.
        $withPrinter = $depts->first(fn ($d) => ! empty($d->printer_ip));
        if ($withPrinter) {
            return $withPrinter;
        }

        return $primary ?: $depts->first();
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
            'company_email' => '',
            'company_logo' => '',
            'currency_symbol' => 'Rs.',
            'pos_allow_bill_print' => '1',
            'pos_enable_tables' => '1',
        ], Setting::all_map());

        $companyName = trim((string) ($settings['company_name'] ?? ''));
        $fixedCompanyName = preg_replace('/\bRESRO\b/iu', 'RESTRO', $companyName) ?? $companyName;
        if ($fixedCompanyName !== '' && $fixedCompanyName !== $companyName) {
            Setting::set('company_name', $fixedCompanyName);
            $settings['company_name'] = $fixedCompanyName;
        }

        $logoPath = (string) ($settings['company_logo'] ?? '');
        $settings['company_logo_url'] = company_logo_url($logoPath) ?? '';
        $settings['company_logo_data_uri'] = company_logo_data_uri($logoPath) ?? '';
        $settings['company_logo_abs_path'] = company_logo_path($logoPath) ?? '';

        return $settings;
    }

    private function openPosSessionForUser(User $user): ?PosSession
    {
        return PosSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
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
     *   allow_discount?: bool,
     *   service_type?: ?string
     * }  $opts
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: list<array<string, mixed>>}
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

        $net = round($subtotal - $discountTotal, 2);
        $serviceTotal = PosServiceCharge::amountOnNet($net, $opts['service_type'] ?? null);
        $grandTotal = round($net + $taxTotal + $serviceTotal, 2);

        return [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $lines];
    }

    private function isOwnerDiscountRequest(PosCheckoutRequest $request, bool $allowDiscount, string $saleMode): bool
    {
        if (! $allowDiscount || $saleMode === 'staff') {
            return false;
        }

        return $request->boolean('is_owner_discount');
    }

    private function resolveBillDiscountPercent(PosCheckoutRequest $request, bool $allowDiscount, string $saleMode): float
    {
        if (! $allowDiscount || $saleMode === 'staff') {
            return 0.0;
        }

        if ($this->isOwnerDiscountRequest($request, $allowDiscount, $saleMode)) {
            return 100.0;
        }

        return max(0.0, min(100.0, round((float) $request->input('bill_discount_percent', 0), 3)));
    }

    private function deletePaidOrder(PosOrder $order): void
    {
        if ($order->type === 'sale' && PosOrder::query()->where('refund_of_order_id', $order->id)->exists()) {
            throw new \RuntimeException('Is bill ki refund entries maujood hain — pehle unhe delete karein.');
        }

        DB::connection('tenant')->transaction(function () use ($order) {
            $this->reversePaidOrderInventory($order);

            InventoryMove::query()
                ->where('reference', $order->order_no)
                ->delete();

            CreditLedger::query()->where('pos_order_id', $order->id)->delete();
            $this->deletePosJournalEntries($order);
            $order->payments()->delete();
            $order->items()->delete();
            $order->delete();
        });
    }

    private function reversePaidOrderInventory(PosOrder $order): void
    {
        if ($order->type !== 'sale') {
            return;
        }

        $order->loadMissing('items');
        $refundOrder = $order->replicate();
        $refundOrder->type = 'refund';

        foreach ($order->items as $line) {
            $this->applyInventoryForPos($refundOrder, [
                'product_id' => (int) $line->product_id,
                'uom' => (string) $line->uom,
                'qty' => (float) $line->qty,
            ]);
        }
    }

    private function deletePosJournalEntries(PosOrder $order): void
    {
        JournalEntry::query()
            ->where('source', 'pos')
            ->where('source_id', $order->id)
            ->each(function (JournalEntry $entry) {
                $entry->lines()->delete();
                $entry->delete();
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
        PosRuntimeSchema::ensureOrdersTable();
    }

    private function ensurePosOrderItemsSchema(): void
    {
        PosRuntimeSchema::ensureOrderItemsTable();
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
            'kitchen_notes' => trim((string) ($order->kitchen_notes ?? '')),
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

            [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $pricing['tax_mode'] === 'bill' ? $billTax : 0.0,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
                'service_type' => $order->serviceTypeKey(),
            ]);

            $delta = abs($storedGrand - $grandTotal);
            if ($best === null || $delta < $best['delta']) {
                $best = [
                    'delta' => $delta,
                    'sale' => $attempt['sale'],
                    'subtotal' => $subtotal,
                    'discount' => $discountTotal,
                    'tax' => $taxTotal,
                    'service' => $serviceTotal,
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
                'service_charge_total' => $best['service'],
                'service_charge_percent' => ($best['service'] ?? 0) > 0 ? PosServiceCharge::percent() : null,
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
     * Pre-kitchen reductions are allowed for cashier.
     * Kitchen-printed qty is enforced separately via assertKitchenLockedQuantitiesPreserved
     * (manager/admin void + reason required).
     *
     * @param  array<int, PosOrderItem>  $existingItems
     * @param  array<int, array<string, mixed>>  $incomingItems
     */
    private function assertCartQtyNotReducedByNonManager(array $existingItems, array $incomingItems, ?User $user): void
    {
        // Intentionally empty — see assertKitchenLockedQuantitiesPreserved().
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
        if (! $user || ! $this->userCanKitchenVoid($user)) {
            throw ValidationException::withMessages([
                'kitchen_voids' => 'Kitchen items sirf manager/admin remove kar sakta hai.',
            ]);
        }
    }

    private function userCanKitchenVoid(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->bypassesModulePermissions()) {
            return true;
        }

        return $this->posStaffCapabilities($user)['is_manager'];
    }

    /**
     * @return list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string}>
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
                'name' => trim((string) ($void['name'] ?? '')),
            ];
        }

        return $voids;
    }

    /**
     * @return list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string}>
     */
    private function normalizedItemReductions(PosCheckoutRequest $request): array
    {
        $rows = [];
        foreach ((array) $request->input('item_reductions', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $reason = trim((string) ($row['reason'] ?? ''));
            $qty = (float) ($row['qty'] ?? 0);
            if ($reason === '' || $qty <= 0) {
                continue;
            }
            $rows[] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'uom' => (string) ($row['uom'] ?? ''),
                'qty' => $qty,
                'reason' => $reason,
                'notes' => trim((string) ($row['notes'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return $rows;
    }

    private function assertItemReductionPermission(PosCheckoutRequest $request): void
    {
        if ($this->normalizedItemReductions($request) === []) {
            return;
        }

        $user = Auth::user();
        if (! $user || (! $this->userCanLogItemReduction($user))) {
            throw ValidationException::withMessages([
                'item_reductions' => 'Item kam karne ka reason sirf manager de sakta hai.',
            ]);
        }
    }

    private function userCanLogItemReduction(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->bypassesModulePermissions()) {
            return true;
        }

        return $this->posStaffCapabilities($user)['is_manager'];
    }

    /**
     * @param  list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string}>  $kitchenVoids
     */
    private function logKitchenVoids(PosOrder $order, array $kitchenVoids): void
    {
        if ($kitchenVoids === []) {
            return;
        }

        $productIds = collect($kitchenVoids)
            ->map(fn (array $void) => (int) ($void['product_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $productNames = $productIds === []
            ? collect()
            : InventoryProduct::query()
                ->whereIn('id', $productIds)
                ->pluck('name', 'id');

        foreach ($kitchenVoids as $void) {
            $label = trim((string) ($void['name'] ?? ''));
            $productId = (int) ($void['product_id'] ?? 0);
            if ($label === '' && $productId > 0) {
                $label = trim((string) ($productNames[$productId] ?? ''));
            }
            if ($label === '') {
                $label = $productId > 0 ? 'Product #'.$productId : 'Item';
            }
            $void['name'] = $label;
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

    /**
     * @param  list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string}>  $reductions
     */
    private function logItemReductions(PosOrder $order, array $reductions): void
    {
        if ($reductions === []) {
            return;
        }

        foreach ($reductions as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            if ($label === '') {
                $label = 'Product #'.(int) ($row['product_id'] ?? 0);
            }
            ActivityLogger::log(
                'pos.item_reduction',
                sprintf(
                    'Item reduced on %s: %s × %s — %s',
                    $order->order_no,
                    $label,
                    (float) ($row['qty'] ?? 0),
                    (string) ($row['reason'] ?? '')
                ),
                $order,
                ['reduction' => $row]
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
