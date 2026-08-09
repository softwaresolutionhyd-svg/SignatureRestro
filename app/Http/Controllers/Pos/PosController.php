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
use App\Support\PosCustomProduct;
use App\Support\PosTablesSchema;
use App\Support\MenuCategory;
use App\Support\ActivityLogger;
use App\Services\KitchenService;
use App\Services\ManufacturingStockService;
use App\Services\AutoJournalService;
use App\Services\NetworkPrinterService;
use App\Services\OfflineFullBackupService;
use App\Services\OrderTakerService;
use App\Services\PosOrderSplitIndicator;
use App\Services\PosPendingBillsService;
use App\Services\PosSessionSummaryService;
use App\Services\Sync\SyncAwareDelete;
use App\Support\StaffNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    use EnsuresKitchenAgentSchema;

    private const FIFO_EPSILON = 0.000001;

    /** @var array<int, list<int>> */
    private array $splitParentOrderIdsBySession = [];

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
        MenuCategory::assignPosProducts();

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

        $paidOrders = $this->paidOrdersForSession($session, $user);

        $paidBillsDetail = $paidOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order, true))
            ->values();

        $pendingBillsDetail = $heldOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order, false))
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

        $customProduct = PosCustomProduct::ensure();

        $products = InventoryProduct::query()
            ->where(function ($q) use ($resumeProductIds, $customProduct) {
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
                $q->orWhere('id', $customProduct->id);
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
            'custom_product_id' => (int) $customProduct->id,
            'custom_product_sku' => PosCustomProduct::SKU,
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

        // Manager gets the same POS checkout surface as admin/cashier:
        // pay, discount, credit, cancel / kitchen void.
        return [
            'can_pay' => $isManager || $isCashier,
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
        if ($this->labelMatchesPosManager($this->employeeDesignationText($employee))) {
            return true;
        }

        return $this->labelMatchesPosManager($this->employeeStaffCategoryText($employee));
    }

    private function labelMatchesPosManager(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        foreach (['manager', 'owner', 'admin', 'supervis', 'proprietor', 'director'] as $keyword) {
            if (str_contains($value, $keyword)) {
                return true;
            }
        }

        return false;
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
        if (! $this->posUsesSharedBills($user)) {
            return [(int) $session->id];
        }

        $date = $session->business_date instanceof \Illuminate\Support\Carbon
            ? $session->business_date->toDateString()
            : (string) ($session->business_date ?: $this->todayBusinessDate());

        $ids = $this->sessionIdsForBusinessDate($date);

        // Managers / admins should see every open-floor bill, not only their own session day.
        if ($user?->bypassesModulePermissions() || $this->posStaffCapabilities($user)['is_manager']) {
            $openIds = PosSession::query()
                ->where('status', 'open')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge(
                $ids,
                $this->sessionIdsForBusinessDate($this->todayBusinessDate()),
                $openIds
            )));
        }

        return $ids === [] ? [(int) $session->id] : $ids;
    }

    private function assertPosCheckoutPermissions(User $user, PosCheckoutRequest $request, bool $isCredit, bool $isCheckout): void
    {
        $caps = $this->posStaffCapabilities($user);

        if ($isCheckout) {
            if ($isCredit) {
                abort_unless($caps['can_discount_credit'], 403, 'Credit sirf manager de sakta hai.');
            } else {
                abort_unless($caps['can_pay'], 403, 'Pay sirf cashier ya manager kar sakta hai.');
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
        $user = Auth::user();
        $session = $this->requireOpenSessionForUser($user);

        $heldOrders = $this->heldOrdersForSession($session, $user);
        $pending = $heldOrders
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order, false))
            ->values();

        $paid = $this->paidOrdersForSession($session, $user)
            ->map(fn (PosOrder $order) => $this->posOrderDetailsPayload($order, true))
            ->values();

        $resumed = null;
        if ($request->filled('resume_order_id')) {
            $resumedOrder = $this->findDraftOrderForSession(
                $session,
                $request->integer('resume_order_id'),
                $user
            );

            if ($resumedOrder !== null) {
                $resumedOrder->loadMissing(['items.product:id,name']);
                $resumed = [
                    'id' => $resumedOrder->id,
                    'items' => $resumedOrder->items->map(fn (PosOrderItem $item) => [
                        'id' => (int) $item->id,
                        'product_id' => (int) $item->product_id,
                        'uom' => (string) $item->uom,
                        'qty' => (float) $item->qty,
                        'unit_price' => (float) $item->unit_price,
                        'tax_percent' => (float) $item->tax_percent,
                        'notes' => (string) ($item->notes ?? ''),
                        'item_name' => $item->item_name,
                        'is_custom' => (bool) $item->is_custom,
                        'kitchen_served' => $item->isKitchenServed(),
                        'kitchen_pending' => (bool) $item->kitchen_pending,
                        'kitchen_printed' => $item->kitchen_printed_at !== null,
                    ])->values()->all(),
                ];
            }
        }

        return response()->json([
            'pending' => $pending,
            'paid' => $paid,
            'count' => $pending->count(),
            'paid_count' => $paid->count(),
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

        $sessionOpenedAt = $session->opened_at ?? now()->startOfDay();

        // Include voids for deleted/cancelled bills too (subject order may no longer exist).
        $logs = ActivityLog::query()
            ->where('action', 'pos.kitchen_void')
            ->where(function ($q) use ($orderIds, $billSessionIds, $sessionOpenedAt) {
                if ($orderIds !== []) {
                    $q->where(function ($inner) use ($orderIds) {
                        $inner->where('subject_type', PosOrder::class)
                            ->whereIn('subject_id', $orderIds);
                    });
                }

                foreach ($billSessionIds as $sid) {
                    $sid = (int) $sid;
                    $q->orWhere('properties->session_id', $sid)
                        ->orWhere('properties->session_id', (string) $sid);
                }

                // Legacy logs (no session_id): same open-session window.
                $q->orWhere(function ($inner) use ($sessionOpenedAt) {
                    $inner->where('subject_type', PosOrder::class)
                        ->where('created_at', '>=', $sessionOpenedAt)
                        ->where(function ($p) {
                            $p->whereNull('properties->session_id')
                                ->orWhere('properties->session_id', '')
                                ->orWhere('properties->session_id', 0)
                                ->orWhere('properties->session_id', '0');
                        });
                });
            })
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
            $props = is_array($log->properties) ? $log->properties : [];
            $void = is_array($props['void'] ?? null) ? $props['void'] : [];

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

            $orderNo = trim((string) ($order?->order_no ?? ''));
            if ($orderNo === '') {
                $orderNo = trim((string) ($props['order_no'] ?? ''));
            }
            if ($orderNo === '') {
                $orderNo = '#'.(int) ($props['order_id'] ?? $log->subject_id);
            }

            return [
                'id' => (int) $log->id,
                'order_id' => (int) ($props['order_id'] ?? $log->subject_id),
                'order_no' => $orderNo,
                'product' => $name,
                'qty' => round((float) ($void['qty'] ?? 0), 3),
                'uom' => (string) ($void['uom'] ?? ''),
                'reason' => (string) ($void['reason'] ?? ''),
                'notes' => (string) ($void['notes'] ?? ''),
                'cancelled_at' => $log->created_at?->format('d M Y, h:i A') ?? '',
                'cancelled_by' => (string) ($log->user?->name ?? '—'),
            ];
        })->unique('id')->values();

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
            $heldOrders->load(['items.product:id,name', 'table:id,name', 'user:id,name']);
            $heldOrders->loadCount('items');
        }

        // Do NOT run repairDraftOrderIfNeeded here — it recalculates every draft on each
        // POS page/sync and made pending lists feel multi-second. Repair on hold/checkout instead.

        return $heldOrders
            ->filter(fn (PosOrder $order) => $order->isDueForServeDay())
            ->sortByDesc('id')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    private function paidOrdersForSession(PosSession $session, ?User $user = null): \Illuminate\Support\Collection
    {
        $user = $user ?? Auth::user();
        $billSessionIds = $this->resolvePosBillSessionIds($session, $user);
        if ($billSessionIds === []) {
            return collect();
        }

        $oldestOpenedAt = PosSession::query()
            ->whereIn('id', $billSessionIds)
            ->min('opened_at');

        return PosOrder::query()
            ->whereIn('session_id', $billSessionIds)
            ->where('status', 'paid')
            ->when($oldestOpenedAt, function ($q) use ($oldestOpenedAt) {
                $q->where(function ($sub) use ($oldestOpenedAt) {
                    $sub->where('paid_at', '>=', $oldestOpenedAt)
                        ->orWhereNull('paid_at');
                });
            })
            ->with(['table:id,name', 'payments:id,order_id,method,amount', 'user:id,name'])
            ->withCount('items')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get();
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
                'cashMovements' => collect(),
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
        $cashMovements = PosCashMovement::query()
            ->where('session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'type', 'amount', 'reason', 'created_at']);

        return view('pos.closing.index', [
            'session' => $session,
            'stats' => $stats,
            'cash' => $summary['cash'],
            'cashMovements' => $cashMovements,
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
        $cashMovements = PosCashMovement::query()
            ->where('session_id', $session->id)
            ->orderBy('id')
            ->get(['id', 'type', 'amount', 'reason', 'created_at']);

        return view('pos.closing.print', [
            'session' => $session->loadMissing('user:id,name'),
            'stats' => $summary['stats'],
            'cash' => $summary['cash'],
            'cashMovements' => $cashMovements,
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
        abort_unless($this->userCanOpenPosSession($user), 403, 'POS session sirf cashier ya manager open kar sakta hai.');

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
            __('Only manager or admin can close the POS session.')
        );

        $session = $this->getOpenPosSessionForUser($user);
        abort_if($session === null, 404, __('No open POS session.'));

        $heldDraft = $this->heldOrdersForSession($session, $user)->count();
        if ($heldDraft > 0) {
            return back()->with(
                'error',
                __('Session cannot be closed: :count pending bill(s) still exist. Pay or discard them on Restaurant POS first.', ['count' => $heldDraft])
            );
        }

        $this->finalizeSessionClose(
            $session,
            $request->note,
            $request->filled('counted_cash') ? round((float) $request->input('counted_cash'), 2) : null
        );

        // Night close: full software + MySQL dump → offline backup/ folder (before PC shutdown).
        $backup = app(OfflineFullBackupService::class)->createQuiet();
        $message = __('POS session closed and saved.').' '.$backup['message'];

        return redirect()
            ->route('reports.pos-sessions')
            ->with($backup['ok'] ? 'success' : 'warning', $message);
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
            if ($this->userCanOpenPosSession($user) && ! $this->posSessionShiftStarted($own)) {
                return null;
            }

            return $own;
        }

        // Shared floor session: cashier/manager/admin can use any started open session.
        if ($this->posUsesSharedBills($user)) {
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

    private function userIsPosManager(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $employee = $this->resolvePosEmployee($user);

        return $employee !== null && $this->employeeIsPosManager($employee);
    }

    private function userCanOpenPosSession(?User $user): bool
    {
        return $this->userIsPosCashier($user) || $this->userIsPosManager($user);
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

        $created = 0;
        foreach ($request->input('lines', []) as $line) {
            PosCashMovement::create([
                'session_id' => $session->id,
                'user_id' => Auth::id(),
                'type' => $request->type,
                'amount' => (float) $line['amount'],
                'reason' => $line['reason'],
            ]);
            $created++;
        }

        $label = $request->type === 'in' ? __('Cash In') : __('Cash Out');

        return back()->with('success', __(':type saved (:count lines).', [
            'type' => $label,
            'count' => $created,
        ]));
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

        // Power-cut retry: pay already committed — skip stock re-check (inventory already moved).
        $resumeAlreadyPaid = null;
        if ($resumeOrderId) {
            $resumeAlreadyPaid = PosOrder::query()
                ->whereKey($resumeOrderId)
                ->where('status', 'paid')
                ->where('type', 'sale')
                ->first();
        }

        if ($request->type === 'sale' && ! $resumeAlreadyPaid) {
            $this->validatePosProductsForCustomerType($itemsNormalized, $customerType);
            $this->validatePosStockForSale($itemsNormalized);
        }

        $this->assertKitchenVoidPermission($request);
        $this->assertItemReductionPermission($request);

        if ($resumeOrderId && ! $resumeAlreadyPaid) {
            $resumeDraft = $this->findDraftOrderForSession($session, $resumeOrderId, $checkoutUser);
            if ($resumeDraft) {
                $oldItems = $resumeDraft->items()->get()->all();
                $requestKitchenVoids = $this->normalizedKitchenVoids($request);
                $kitchenVoids = $this->mergePersistedKitchenVoids((int) $resumeOrderId, $requestKitchenVoids);
                $itemsNormalized = app(KitchenService::class)->appendMissingKitchenLockedNormalized(
                    $oldItems,
                    $itemsNormalized,
                    $kitchenVoids
                );
                $this->assertCartQtyNotReducedByNonManager($oldItems, $itemsNormalized, $checkoutUser);
                $this->assertKitchenLockedQuantitiesPreserved($oldItems, $itemsNormalized, $kitchenVoids, hardFail: true);
            }
        }

        if ($resumeAlreadyPaid) {
            $order = $resumeAlreadyPaid;
            $order->loadMissing(['table', 'items.product', 'payments']);
            $openReceipt = Setting::get('pos_open_receipt_after_sale', '1') === '1';
            $msg = __('Order pehle se paid hai — duplicate bill nahi bani.');

            if ($wantsJson) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'already_paid' => true,
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'order' => $this->paidOrderPayloadForJson($order),
                    'table_board' => $this->orderTaker->tableBoard(),
                    // Client prints once via cashier-print API (avoid double with afterResponse queue).
                    'cashier_print_queued' => false,
                    'receipt_url' => $openReceipt ? route('restaurant-pos.receipt', $order) : null,
                    'redirect_url' => $openReceipt
                        ? route('restaurant-pos.receipt', $order)
                        : route('restaurant-pos.index'),
                ]);
            }

            if ($openReceipt) {
                return redirect()->route('restaurant-pos.receipt', $order)->with('success', $msg);
            }

            return redirect()->route('restaurant-pos.index')->with('success', $msg)->with('last_pos_order_id', $order->id)->with('pos_active_tab', 'paid');
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
            $checkoutResult = DB::connection('tenant')->transaction(function () use ($request, $session, $isCredit, $contactId, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $resumeOrderId, $customerType, $saleMode, $serviceType, $restaurantTableId, $checkoutUser) {
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
            $alreadyPaidResume = null;
            if ($resumeOrderId) {
                $lockedResume = PosOrder::query()
                    ->whereKey($resumeOrderId)
                    ->lockForUpdate()
                    ->first();

                // Power-cut retry: first pay already committed — return same bill, do not create a duplicate.
                if ($lockedResume && $lockedResume->status === 'paid' && $lockedResume->type === 'sale') {
                    $alreadyPaidResume = $lockedResume;
                } elseif ($lockedResume && $lockedResume->status === 'draft') {
                    $billSessionIds = $this->resolvePosBillSessionIds($session, $checkoutUser);
                    $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
                        && Schema::hasColumn('pos_orders', 'ready_for_pos_at');
                    $sessionOk = in_array((int) $lockedResume->session_id, array_map('intval', $billSessionIds), true);
                    $otOk = $hasOrderTakerColumns
                        && $lockedResume->order_source === OrderTakerService::SOURCE_ORDER_TAKER
                        && $lockedResume->ready_for_pos_at !== null;
                    if ($sessionOk || $otOk) {
                        $existingDraft = $lockedResume;
                    }
                }
            }

            if ($alreadyPaidResume !== null) {
                return ['order' => $alreadyPaidResume, 'idempotent' => true];
            }

            $clientPayKey = trim((string) $request->input('client_request_id', ''));
            if ($clientPayKey !== '' && $request->type === 'sale') {
                $cidCacheKey = 'pos:pay:cid:'.(Auth::id() ?? 0).':'.$session->id.':'.$clientPayKey;
                $cachedPayId = Cache::get($cidCacheKey);
                if ($cachedPayId) {
                    $cachedPayOrder = PosOrder::query()->find((int) $cachedPayId);
                    if ($cachedPayOrder && $cachedPayOrder->status === 'paid' && $cachedPayOrder->type === 'sale') {
                        return ['order' => $cachedPayOrder, 'idempotent' => true];
                    }
                }
            }

            // No resume id: still attach a matching recent draft (same cart) so Pay doesn't mint a twin bill.
            if (! $existingDraft && ! $resumeOrderId && $request->type === 'sale') {
                $twinDraft = $this->findRecentSameCartDraft(
                    (int) $session->id,
                    $serviceType,
                    $itemsNormalized,
                    $guestName,
                    $roomNo,
                    $tableId ? (int) $tableId : null,
                    Auth::id() ? (int) Auth::id() : null
                );
                if ($twinDraft !== null) {
                    $lockedTwin = PosOrder::query()
                        ->whereKey($twinDraft->id)
                        ->lockForUpdate()
                        ->first();
                    if ($lockedTwin && $lockedTwin->status === 'draft') {
                        $existingDraft = $lockedTwin;
                    }
                }
            }

            $payItemsFp = $this->posItemsOnlyFingerprint((int) $session->id, $serviceType, $itemsNormalized);
            $payLock = null;
            if (! $existingDraft && $request->type === 'sale') {
                $payLock = Cache::lock('pos:pay:lock:'.$payItemsFp, 25);
                if (! $payLock->block(8)) {
                    $racePaid = $this->findRecentSameCartPaid(
                        (int) $session->id,
                        $serviceType,
                        $itemsNormalized,
                        $guestName,
                        $roomNo,
                        $tableId ? (int) $tableId : null,
                        Auth::id() ? (int) Auth::id() : null,
                        round((float) $grandTotal, 2)
                    );
                    if ($racePaid !== null) {
                        return ['order' => $racePaid, 'idempotent' => true];
                    }
                    throw new \RuntimeException('Payment already submitting — Pending/Paid tab check karein.');
                }
            }

            try {
            // Same cart already paid moments ago (double Pay / cart re-punch) — return that bill, do not create another.
            if (! $existingDraft && $request->type === 'sale') {
                $twinPaid = $this->findRecentSameCartPaid(
                    (int) $session->id,
                    $serviceType,
                    $itemsNormalized,
                    $guestName,
                    $roomNo,
                    $tableId ? (int) $tableId : null,
                    Auth::id() ? (int) Auth::id() : null,
                    round((float) $grandTotal, 2)
                );
                if ($twinPaid !== null) {
                    if ($clientPayKey !== '') {
                        Cache::put(
                            'pos:pay:cid:'.(Auth::id() ?? 0).':'.$session->id.':'.$clientPayKey,
                            (int) $twinPaid->id,
                            now()->addMinutes(12)
                        );
                    }
                    $this->rememberPaidCartFingerprint(
                        (int) $session->id,
                        $serviceType,
                        $itemsNormalized,
                        (int) $twinPaid->id
                    );

                    return ['order' => $twinPaid, 'idempotent' => true];
                }
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
                SyncAwareDelete::relation($order->items());
            } else {
                $order = PosOrder::create([
                    'order_no'   => DailyOrderNumber::next($session),
                    'session_id' => $session->id,
                ] + $orderPayload);
            }

            foreach ($itemsWithKitchenFlags as $item) {
                PosOrderItem::create(['order_id' => $order->id] + $item);
                $this->applyInventoryForPos($order, $item);
            }

            SyncAwareDelete::query(CreditLedger::query()->where('pos_order_id', $order->id));

            if ($isCredit) {
                $this->clearOrderPayments($order);
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
                $this->replaceOrderPayments($order, $request->payments ?? [], $grandTotal);
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

            $kitchen->dismissFromKitchenWhenPaid($order);

            // Block Hold/Kitchen from recreating this cart as a new draft right after pay.
            $this->rememberPaidCartFingerprint(
                (int) $session->id,
                $serviceType,
                $itemsNormalized,
                (int) $order->id
            );
            if ($clientPayKey !== '') {
                Cache::put(
                    'pos:pay:cid:'.(Auth::id() ?? 0).':'.$session->id.':'.$clientPayKey,
                    (int) $order->id,
                    now()->addMinutes(12)
                );
            }

            return ['order' => $order, 'idempotent' => false];
            } finally {
                optional($payLock)->release();
            }
        });
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $order = $checkoutResult['order'];
        $idempotentPay = (bool) ($checkoutResult['idempotent'] ?? false);

        if (! $idempotentPay) {
            $this->logKitchenVoids($order, $this->normalizedKitchenVoids($request));
            $this->logItemReductions($order, $this->normalizedItemReductions($request));
            $this->autoJournal->postPosSale($order);
            \App\Services\PosActivityNotifier::orderPaid($order);
        }

        $order->refresh();
        $order->loadMissing(['table', 'items.product', 'payments']);

        $openReceipt = Setting::get('pos_open_receipt_after_sale', '1') === '1';
        $msg = $idempotentPay
            ? __('Order pehle se paid hai — duplicate bill nahi bani.')
            : ($isCredit ? 'Credit sale recorded successfully.' : 'Order paid successfully.');

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'already_paid' => $idempotentPay,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'order' => $this->paidOrderPayloadForJson($order),
                'table_board' => $this->orderTaker->tableBoard(),
                // Client prints once via cashier-print API (avoid double with afterResponse queue).
                'cashier_print_queued' => false,
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
            $resumePaid = PosOrder::query()
                ->whereKey($resumeOrderId)
                ->where('status', 'paid')
                ->first();
            if ($resumePaid) {
                $message = 'Yeh bill pehle se paid hai ('.$resumePaid->order_no.'). Nayi pending bill nahi banegi.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'already_paid' => true, 'order_id' => $resumePaid->id], 422);
                }

                return back()->with('error', $message);
            }

            $resumeDraft = $this->findDraftOrderForSession($session, $resumeOrderId, $holdUser);
            if ($resumeDraft) {
                $oldItems = $resumeDraft->items()->get()->all();
                $requestKitchenVoids = $this->normalizedKitchenVoids($request);
                $kitchenVoids = $this->mergePersistedKitchenVoids((int) $resumeOrderId, $requestKitchenVoids);
                $itemsNormalized = app(KitchenService::class)->appendMissingKitchenLockedNormalized(
                    $oldItems,
                    $itemsNormalized,
                    $kitchenVoids
                );
                $this->assertCartQtyNotReducedByNonManager($oldItems, $itemsNormalized, $holdUser);
                $this->assertKitchenLockedQuantitiesPreserved($oldItems, $itemsNormalized, $kitchenVoids, hardFail: true);
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
        $reusedDuplicateCreate = false;
        $clientTotals = $this->clientHoldTotalsFromRequest($request);
        $sendToKitchen = $this->isRestaurantPosRequest($request)
            ? $request->boolean('send_to_kitchen')
            : true;
        // Request voids = new cancels this submit. Merged voids also include prior cancels
        // so a later autosave cannot re-append already-cancelled kitchen lines.
        $requestKitchenVoids = $this->normalizedKitchenVoids($request);
        $kitchenVoids = $resumeOrderId
            ? $this->mergePersistedKitchenVoids((int) $resumeOrderId, $requestKitchenVoids)
            : $requestKitchenVoids;

        try {
            $order = DB::connection('tenant')->transaction(function () use ($request, $session, $itemsNormalized, $guestName, $roomNo, $waiterName, $serveTime, $serveDate, $orderNotes, $customerType, $saleMode, $serviceType, $restaurantTableId, $resumeOrderId, $clientTotals, $sendToKitchen, &$updatedExisting, &$reusedDuplicateCreate, $holdUser, $kitchenVoids) {
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
            $lineOpts = [
                'tax_mode' => $pricing['tax_mode'],
                'bill_tax_percent' => $billTax,
                'bill_discount_percent' => $billDiscount,
                'allow_discount' => $pricing['allow_discount'],
                'service_type' => $serviceType,
            ];
            $workingNormalized = $itemsNormalized;
            [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($workingNormalized, $lineOpts);
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
                $lockedResume = PosOrder::query()
                    ->whereKey($resumeOrderId)
                    ->lockForUpdate()
                    ->first();

                // Power-cut: do not create a sibling draft when the resume target is already paid.
                if ($lockedResume && $lockedResume->status === 'paid') {
                    throw new \RuntimeException(
                        'Yeh bill pehle se paid hai ('.$lockedResume->order_no.'). Nayi pending bill nahi banegi.'
                    );
                }

                $existing = ($lockedResume && $lockedResume->status === 'draft'
                    && in_array((int) $lockedResume->session_id, array_map('intval', $billSessionIds), true))
                    ? $lockedResume
                    : null;

                if ($existing) {
                    $kitchen = app(KitchenService::class);
                    $oldItems = $existing->items()->get()->all();
                    $preservedNormalized = $kitchen->appendMissingKitchenLockedNormalized(
                        $oldItems,
                        $workingNormalized,
                        $kitchenVoids
                    );
                    if (count($preservedNormalized) !== count($workingNormalized)) {
                        $workingNormalized = $preservedNormalized;
                        [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($workingNormalized, $lineOpts);
                        // Stale cart missed kitchen lines — trust rebuilt server totals.
                        $orderPayload['subtotal'] = $subtotal;
                        $orderPayload['discount_total'] = $discountTotal;
                        $orderPayload['tax_total'] = $taxTotal;
                        $orderPayload['service_charge_percent'] = $serviceTotal > 0 ? PosServiceCharge::percent() : null;
                        $orderPayload['service_charge_total'] = $serviceTotal;
                        $orderPayload['grand_total'] = $grandTotal;
                    } else {
                        $workingNormalized = $preservedNormalized;
                    }

                    // Final guard: kitchen-printed qty cannot shrink on save.
                    $this->assertKitchenLockedQuantitiesPreserved($oldItems, $workingNormalized, $kitchenVoids, hardFail: true);

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
                    if ($oldItems !== [] && $itemsWithKitchenFlags === []) {
                        throw ValidationException::withMessages([
                            'items' => 'Pending bill items wipe block — cart khali save allow nahi.',
                        ]);
                    }
                    $hasNewKitchenItems = collect($itemsWithKitchenFlags)->contains(
                        fn (array $item) => (bool) ($item['kitchen_pending'] ?? false)
                            && empty($item['kitchen_printed_at'])
                    );

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
                    SyncAwareDelete::relation($existing->items());
                    foreach ($itemsWithKitchenFlags as $item) {
                        PosOrderItem::create(['order_id' => $existing->id] + $item);
                    }
                    $updatedExisting = true;

                    return $existing->fresh(['table', 'items']);
                }
            }

            $kitchen = app(KitchenService::class);
            $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags([], $itemsData, $sendToKitchen);

            // Already paid same cart (e.g. Pay Now, then Hold/Kitchen again) — do not mint twin bill.
            $recentPaid = $this->findRecentSameCartPaid(
                (int) $session->id,
                $serviceType,
                $itemsNormalized,
                $guestName,
                $roomNo,
                $tableId ? (int) $tableId : null,
                Auth::id() ? (int) Auth::id() : null,
                isset($orderPayload['grand_total']) ? round((float) $orderPayload['grand_total'], 2) : null
            );
            if ($recentPaid === null) {
                $cachedPaidId = Cache::get(
                    'pos:paid:cart:'.$this->posItemsOnlyFingerprint((int) $session->id, $serviceType, $itemsNormalized)
                );
                if ($cachedPaidId) {
                    $cachedPaid = PosOrder::query()->find((int) $cachedPaidId);
                    if ($cachedPaid && $cachedPaid->status === 'paid' && $cachedPaid->type === 'sale') {
                        $recentPaid = $cachedPaid;
                    }
                }
            }
            if ($recentPaid !== null) {
                throw new \RuntimeException(
                    'Yeh bill pehle se paid hai ('.$recentPaid->order_no.'). Dobara Hold/Kitchen Print mat karein — Pending/Paid tab check karein.'
                );
            }

            // Kitchen pehle (bina contact), phir unpaid contact ke sath — same cart = update, naya order nahi.
            $reuseDraft = $this->findRecentSameCartDraft(
                (int) $session->id,
                $serviceType,
                $itemsNormalized,
                $guestName,
                $roomNo,
                $tableId ? (int) $tableId : null,
                Auth::id() ? (int) Auth::id() : null
            );
            if ($reuseDraft !== null) {
                $existing = PosOrder::query()
                    ->whereKey($reuseDraft->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing && $existing->status === 'draft') {
                    $oldItems = $existing->items()->get()->all();
                    $itemsWithKitchenFlags = $kitchen->applyKitchenPendingFlags($oldItems, $itemsData, $sendToKitchen);
                    $existing->update($orderPayload + [
                        'session_id' => $session->id,
                        'user_id' => Auth::id(),
                    ]);
                    SyncAwareDelete::relation($existing->items());
                    foreach ($itemsWithKitchenFlags as $item) {
                        PosOrderItem::create(['order_id' => $existing->id] + $item);
                    }
                    $updatedExisting = true;
                    Cache::put(
                        'pos:hold:create:'.$this->posItemsOnlyFingerprint((int) $session->id, $serviceType, $itemsNormalized),
                        (int) $existing->id,
                        now()->addSeconds(120)
                    );

                    return $existing->fresh(['table', 'items']);
                }
            }

            $fingerprint = $this->posNewHoldFingerprint(
                (int) $session->id,
                $request,
                $customerType,
                $serviceType,
                $guestName,
                $roomNo,
                $tableId ? (int) $tableId : null,
                $this->nullableText($request->input('kitchen_notes')),
                $itemsNormalized
            );
            $itemsFp = $this->posItemsOnlyFingerprint((int) $session->id, $serviceType, $itemsNormalized);
            $cacheKey = 'pos:hold:create:'.$fingerprint;
            $itemsCacheKey = 'pos:hold:create:'.$itemsFp;
            $lock = Cache::lock('pos:hold:lock:'.$itemsFp, 25);

            if (! $lock->block(8)) {
                $existingDup = $this->findCachedHoldCreate($itemsCacheKey)
                    ?: $this->findCachedHoldCreate($cacheKey);
                if ($existingDup !== null) {
                    $reusedDuplicateCreate = true;

                    return $existingDup;
                }
                throw new \RuntimeException('Order already submitting — thora wait karke Pending check karein.');
            }

            try {
                $existingDup = $this->findCachedHoldCreate($itemsCacheKey)
                    ?: $this->findCachedHoldCreate($cacheKey);
                if ($existingDup !== null) {
                    $reusedDuplicateCreate = true;

                    return $existingDup;
                }

                $order = PosOrder::create([
                    'order_no' => DailyOrderNumber::next($session),
                    'session_id' => $session->id,
                    'user_id' => Auth::id(),
                    'status' => 'draft',
                ] + $orderPayload);

                foreach ($itemsWithKitchenFlags as $item) {
                    PosOrderItem::create(['order_id' => $order->id] + $item);
                }

                Cache::put($cacheKey, (int) $order->id, now()->addSeconds(120));
                Cache::put($itemsCacheKey, (int) $order->id, now()->addSeconds(120));

                return $order->fresh(['table', 'items']);
            } finally {
                optional($lock)->release();
            }
        });
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($this->repairDraftOrderIfNeeded($order)) {
            $order->refresh();
            $order->load(['table', 'items']);
        }

        if ($requestKitchenVoids !== []) {
            $this->persistKitchenVoids((int) $order->id, $requestKitchenVoids);
        }

        // Only log / print newly submitted voids (not cached prior cancels).
        $removedPrint = $this->logKitchenVoids($order, $requestKitchenVoids);
        $this->logItemReductions($order, $this->normalizedItemReductions($request));

        $message = $updatedExisting ? 'Held order updated.' : 'Order held successfully.';
        if ($reusedDuplicateCreate) {
            $message = 'Order pehle se save ho chuki hai ('.$order->order_no.').';
            $updatedExisting = true;
        }

        if (! $reusedDuplicateCreate && ($sendToKitchen || ! $updatedExisting)) {
            \App\Services\PosActivityNotifier::orderPlaced($order->loadMissing(['table']), $updatedExisting);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updatedExisting,
                'duplicate_blocked' => $reusedDuplicateCreate,
                'order' => $this->posOrderDetailsPayload($order->loadMissing(['items.product', 'table', 'user', 'payments', 'contact'])),
                'held_count' => $this->heldOrdersForSession($session)->count(),
                'removed_print' => $removedPrint,
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
                SyncAwareDelete::query(CreditLedger::query()->where('pos_order_id', $locked->id));
                $this->clearOrderPayments($locked);
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
                \App\Services\PosActivityNotifier::billReopened($locked->fresh(['table']));
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Bill reopen nahi ho saki.');
        }

        return redirect()
            ->route('restaurant-pos.index', ['resume_order' => $order->id])
            ->with('success', "Bill {$order->order_no} reopen ho gayi — ab edit kar ke dubara pay karein.");
    }

    public function moveTable(Request $request, PosOrder $order): \Illuminate\Http\JsonResponse
    {
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

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Order %s: Table %s → %s',
                $moved->order_no,
                $result['from_table_name'],
                $result['to_table_name']
            ),
            'order' => $this->posOrderDetailsPayload($moved->fresh(['table', 'items.product', 'payments', 'user:id,name'])),
            'from_table_id' => $result['from_table_id'],
            'from_table_name' => $result['from_table_name'],
            'to_table_id' => $result['to_table_id'],
            'to_table_name' => $result['to_table_name'],
            'table_board' => $result['table_board'],
            'print' => $result['print'],
        ]);
    }

    public function splitBill(Request $request, PosOrder $order, \App\Services\PosBillSplitService $splitter): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:item,member'],
            'item_ids' => ['required_if:mode,item', 'array'],
            'item_ids.*' => ['integer'],
            'members' => ['required_if:mode,member', 'integer', 'min:2', 'max:20'],
        ]);

        $session = $this->requireOpenSessionForUser(Auth::user());
        $draft = $this->findDraftOrderForSession($session, (int) $order->id, $request->user());
        if ($draft === null) {
            return response()->json(['ok' => false, 'message' => 'Pending bill nahi mili / access nahi.'], 404);
        }

        try {
            $result = DB::connection('tenant')->transaction(function () use ($draft, $data, $splitter) {
                $locked = PosOrder::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
                if (($data['mode'] ?? '') === 'member') {
                    return $splitter->splitMemberWise($locked, (int) $data['members']);
                }

                return $splitter->splitItemWise($locked, $data['item_ids'] ?? []);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Bill split fail ho gayi.',
            ], 422);
        }

        /** @var PosOrder $original */
        $original = $result['original'];
        /** @var list<PosOrder> $created */
        $created = $result['created'];

        $this->splitParentOrderIdsBySession = [];

        $pendingPayload = collect([$original])
            ->merge($created)
            ->map(fn (PosOrder $o) => $this->posOrderDetailsPayload($o))
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Bill split ho gayi — %s + %d new pending bill%s.',
                $original->order_no,
                count($created),
                count($created) === 1 ? '' : 's'
            ),
            'original' => $this->posOrderDetailsPayload($original),
            'created' => collect($created)->map(fn (PosOrder $o) => $this->posOrderDetailsPayload($o))->values()->all(),
            'pending_updates' => $pendingPayload,
            'table_board' => $this->orderTaker->tableBoard(),
        ]);
    }

    /** Super admin: permanently delete a paid POS bill and reverse its stock impact. */
    public function destroyPaidBill(Request $request, PosOrder $order): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformSuperAdmin(), 403);
        abort_unless($order->status === 'paid', 404);

        $orderNo = $order->order_no;
        $order->loadMissing(['table']);

        try {
            \App\Services\PosActivityNotifier::billDeleted($order);
            $this->deletePaidOrder($order);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Bill delete nahi ho saki.');
        }

        return back()->with('success', "Bill {$orderNo} deleted.");
    }

    /** Delete a draft held order for the current open register session (items cascade). */
    public function discardHeld(Request $request, int $orderId): RedirectResponse|JsonResponse
    {
        $session = $this->requireOpenSessionForUser(Auth::user());
        $order = PosOrder::query()->find($orderId);

        if ($order === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'already_discarded' => true,
                    'message' => 'Order pehle se khatam ho chuki hai.',
                ]);
            }

            return back()->with('warning', 'Order pehle se khatam ho chuki hai.');
        }

        if ($order->status !== 'draft') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Is order ko discard nahi kar sakte.',
                ], 403);
            }

            abort(403);
        }

        if ($this->findDraftOrderForSession($session, (int) $order->id) === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Is order ko discard nahi kar sakte.',
                ], 403);
            }

            abort(403);
        }

        $order->loadMissing('items');
        $kitchen = app(KitchenService::class);
        $hasKitchenLocked = $order->items->contains(
            fn (PosOrderItem $item) => $kitchen->isKitchenLockedLine($item)
        );

        // Empty-cart / whole-order cancel: voids must print before draft delete.
        $kitchenVoids = $this->kitchenVoidsFromInput($request->input('kitchen_voids'));
        $cancelWholeOrder = $request->boolean('cancel_whole_order');

        // Kitchen-printed pending bill: never silent-delete — manager cancel + voids only.
        if ($hasKitchenLocked) {
            if (! $cancelWholeOrder || $kitchenVoids === []) {
                $message = 'Kitchen print wali pending bill cancel ke bina delete nahi ho sakti. Manager se Cancel Order use karein.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }
        }

        if ($kitchenVoids !== [] || $cancelWholeOrder) {
            $user = Auth::user();
            if (! $user || ! $this->userCanKitchenVoid($user)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Kitchen items / order sirf manager/admin cancel kar sakta hai.',
                    ], 422);
                }

                return back()->with('error', 'Kitchen items / order sirf manager/admin cancel kar sakta hai.');
            }
        }

        if ($cancelWholeOrder && $kitchenVoids === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Kitchen print ke baad cancel ke liye removed items zaroori hain.',
                ], 422);
            }

            return back()->with('error', 'Kitchen print ke baad cancel ke liye removed items zaroori hain.');
        }

        if ($hasKitchenLocked) {
            // Voids must cover every kitchen-printed qty — otherwise items would vanish unpaid.
            try {
                $kitchen->assertLockedQuantitiesPreserved(
                    $order->items->all(),
                    [],
                    $kitchenVoids,
                    true
                );
            } catch (\RuntimeException $e) {
                $message = 'Kitchen items poori tarah void kiye baghair pending bill delete nahi ho sakti.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }
        }

        $removedPrint = $this->logKitchenVoids($order, $kitchenVoids);
        $orderNo = (string) $order->order_no;
        $itemCount = $order->items->count();

        if ($cancelWholeOrder) {
            $reason = trim((string) ($kitchenVoids[0]['reason'] ?? 'Order cancelled'));
            ActivityLogger::log(
                'pos.order_cancelled',
                sprintf('Whole order cancelled: %s — %s', $orderNo, $reason),
                $order,
                [
                    'reason' => $reason,
                    'void_count' => count($kitchenVoids),
                    'voids' => $kitchenVoids,
                    'item_count' => $itemCount,
                ]
            );
            \App\Services\PosActivityNotifier::orderCancelled($order, $reason);
        } else {
            ActivityLogger::log(
                'pos.order_discarded',
                sprintf('Pending bill discarded: %s (%d items)', $orderNo, $itemCount),
                $order,
                [
                    'item_count' => $itemCount,
                    'had_kitchen_lock' => false,
                ]
            );
        }

        $order->delete();
        $this->forgetPersistedKitchenVoids((int) $orderId);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $cancelWholeOrder ? 'Order cancelled.' : 'Held order discarded.',
                'removed_print' => $removedPrint,
                'cancelled' => $cancelWholeOrder,
            ]);
        }

        return back()->with('success', $cancelWholeOrder ? 'Order cancelled.' : 'Held order discarded.');
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

            $this->replaceOrderPayments($locked, [[
                'method' => $paymentMethod,
                'amount' => (float) $locked->grand_total,
                'reference' => 'Checkout Counter',
            ]], (float) $locked->grand_total);

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

    public function quotationReceipt(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $this->userCanKitchenVoid($user)) {
            abort(403, 'Quotation bill sirf manager/admin print kar sakta hai.');
        }

        $order = $this->ephemeralQuotationOrderFromRequest($request);
        $settings = $this->receiptSettingsMap();
        $isUnpaid = false;
        $isQuotation = true;
        $allowBillPrint = true;
        $autoPrint = ! $request->boolean('noprint', false) && $request->boolean('autoprint', true);
        $backUrl = route('restaurant-pos.index');
        $backLabel = '← Back to Restaurant POS';

        return view('pos.receipt', compact(
            'order',
            'settings',
            'autoPrint',
            'allowBillPrint',
            'backUrl',
            'backLabel',
            'isUnpaid',
            'isQuotation'
        ));
    }

    public function kitchenSlip(Request $request, PosOrder $order): View
    {
        abort_unless($order->status === 'draft', 404);
        $this->assertDraftReceiptAccess($order);

        \App\Support\PosRuntimeSchema::ensureOrderItemsTable();
        $order->unsetRelation('items');
        $order->load(['items.product:id,name,sku,department_id', 'items.product.departments:id,name', 'items.product.department:id,name', 'user:id,name', 'table:id,name']);

        $isAddonPrint = $order->items->contains(fn (PosOrderItem $item) => $item->kitchen_printed_at !== null);

        $kitchenItems = $order->items->filter(function (PosOrderItem $item) {
            if (! (bool) $item->kitchen_pending || $item->isKitchenServed()) {
                return false;
            }

            return $item->kitchen_printed_at === null;
        })->values();

        abort_unless($kitchenItems->isNotEmpty(), 404);

        $departmentName = 'KITCHEN';
        $printer = app(NetworkPrinterService::class);
        $deptNames = $kitchenItems
            ->map(function (PosOrderItem $item) use ($printer) {
                $dept = $printer->resolveItemDepartment($item->product);

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

        // Mark lines printed once the kitchen slip is actually used for printing
        // (auto-print or silent iframe print with noprint=1).
        $shouldMarkPrinted = $autoPrint || $request->boolean('noprint', false) || $request->boolean('mark_printed', false);
        if ($shouldMarkPrinted) {
            app(KitchenService::class)->markItemsKitchenPrinted(
                $kitchenItems->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        return view('pos.kitchen-slip', compact(
            'order',
            'kitchenItems',
            'settings',
            'autoPrint',
            'backUrl',
            'backLabel',
            'departmentName',
            'isAddonPrint'
        ));
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

        $result = app(NetworkPrinterService::class)->dispatchPendingKitchenPrints($order);
        $orderPayload = fn () => $this->posOrderDetailsPayload($order->fresh(['table', 'items.product', 'contact']));

        if (($result['fallback'] ?? false) === true) {
            return response()->json([
                'ok' => false,
                'complete' => false,
                'fallback' => true,
                'needs_browser_fallback' => true,
                'is_addon' => $result['is_addon'] ?? false,
                'unrouted' => $result['unrouted'] ?? 0,
                'pending_item_ids' => $result['pending_item_ids'] ?? [],
                'remaining_pending_ids' => $result['remaining_pending_ids'] ?? [],
                'order' => $orderPayload(),
                'message' => $result['message'] ?? 'Kisi department ka printer set nahi (Inventory → Kitchen Agents).',
            ]);
        }

        if (($result['empty_pending'] ?? false) === true) {
            return response()->json([
                'ok' => false,
                'complete' => true,
                'empty_pending' => true,
                'message' => $result['message'] ?? 'Koi naya kitchen item pending nahi.',
                'order' => $orderPayload(),
            ], 422);
        }

        $complete = (bool) ($result['complete'] ?? false);
        $anyOk = (bool) ($result['ok'] ?? false);

        return response()->json([
            'ok' => $anyOk,
            'complete' => $complete,
            'needs_browser_fallback' => (bool) ($result['needs_browser_fallback'] ?? false),
            'results' => $result['results'] ?? [],
            'unrouted' => $result['unrouted'] ?? 0,
            'is_addon' => $result['is_addon'] ?? false,
            'printed_item_ids' => $result['printed_item_ids'] ?? [],
            'pending_item_ids' => $result['pending_item_ids'] ?? [],
            'remaining_pending_ids' => $result['remaining_pending_ids'] ?? [],
            'remaining_with_printer' => $result['remaining_with_printer'] ?? 0,
            'order' => $orderPayload(),
            'message' => $result['message'] ?? null,
        ], ($complete || $anyOk) ? 200 : 500);
    }

    /**
     * Print the bill to the assigned CASHIER printer (Inventory → Kitchen Agents → CASHIER).
     */
    public function cashierPrintNetwork(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless(in_array($order->status, ['draft', 'paid'], true), 404);
        if ($order->status === 'paid') {
            abort_unless($this->userCanAccessPaidReceipt($request->user(), $order), 403);
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

        $order->load(['items.product:id,name,sku', 'user:id,name', 'table:id,name', 'payments', 'contact:id,name,phone']);
        $settings = $this->receiptSettingsMap(forThermal: true);
        $printer = app(NetworkPrinterService::class);
        $payload = $printer->buildBillSlip($order, $settings);

        try {
            $printer->send(
                $ip,
                (int) (Setting::get('cashier_printer_port', 9100) ?: 9100),
                $payload,
                4
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Print Quotation Bill from cart (no DB order created) to CASHIER printer.
     * Admin/manager only. Table not required.
     */
    public function quotationPrintNetwork(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $this->userCanKitchenVoid($user)) {
            return response()->json([
                'ok' => false,
                'message' => 'Quotation bill sirf manager/admin print kar sakta hai.',
            ], 403);
        }

        $ip = trim((string) Setting::get('cashier_printer_ip', ''));
        if ($ip === '') {
            return response()->json([
                'ok' => false,
                'fallback' => true,
                'message' => 'Cashier printer set nahi (Inventory → Kitchen Agents → CASHIER).',
            ]);
        }

        try {
            $order = $this->ephemeralQuotationOrderFromRequest($request);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Quotation tayyar nahi ho saki.',
            ], 422);
        }

        $settings = $this->receiptSettingsMap(forThermal: true);
        $printer = app(NetworkPrinterService::class);
        $payload = $printer->buildBillSlip($order, $settings, 'quotation');

        try {
            $printer->send(
                $ip,
                (int) (Setting::get('cashier_printer_port', 9100) ?: 9100),
                $payload,
                4
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'saved' => false]);
    }

    /**
     * Build an unsaved PosOrder snapshot for quotation print/preview (never persisted).
     */
    private function ephemeralQuotationOrderFromRequest(Request $request): PosOrder
    {
        $rawItems = $request->input('items', []);
        if (is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true) ?: [];
        }
        if (! is_array($rawItems) || $rawItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Quotation ke liye pehle item add karein.',
            ]);
        }

        $itemsNormalized = $this->normalizePosCheckoutItems(
            $rawItems,
            'mess_use',
            'customer',
            'sale',
            false,
            true
        );

        $serviceType = $this->normalizeServiceType($request->input('service_type', 'dine_in'));
        [$subtotal, $discountTotal, $taxTotal, $serviceTotal, $grandTotal, $itemsData] = $this->buildLines($itemsNormalized, [
            'tax_mode' => Setting::get('pos_tax_mode', 'line'),
            'bill_tax_percent' => (float) $request->input('bill_tax_percent', 0),
            'bill_discount_percent' => (float) $request->input('bill_discount_percent', 0),
            'allow_discount' => true,
            'service_type' => $serviceType,
            'trust_line_totals' => true,
        ]);

        $order = new PosOrder([
            'order_no' => 'QT-'.now()->format('ymdHis'),
            'status' => 'draft',
            'type' => 'sale',
            'service_type' => $serviceType,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'service_charge_total' => $serviceTotal,
            'grand_total' => $grandTotal,
            'order_notes' => $this->nullableText($request->input('order_notes')),
            'user_id' => Auth::id(),
            'table_id' => null,
        ]);
        $order->exists = false;
        $order->setRelation('user', Auth::user());
        $order->setRelation('table', null);
        $order->setRelation('payments', collect());
        $order->setRelation('contact', null);

        $lineModels = collect($itemsData)->map(function (array $row) {
            $item = new PosOrderItem([
                'product_id' => (int) ($row['product_id'] ?? 0),
                'item_name' => $row['item_name'] ?? null,
                'is_custom' => (bool) ($row['is_custom'] ?? false),
                'uom' => (string) ($row['uom'] ?? ''),
                'qty' => (float) ($row['qty'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'tax_percent' => (float) ($row['tax_percent'] ?? 0),
                'discount_percent' => (float) ($row['discount_percent'] ?? 0),
                'total' => (float) ($row['total'] ?? $row['line_total'] ?? 0),
                'notes' => (string) ($row['notes'] ?? ''),
            ]);
            $item->exists = false;
            if (! empty($row['product_id']) && empty($row['is_custom'])) {
                $product = InventoryProduct::query()->find((int) $row['product_id'], ['id', 'name', 'sku']);
                if ($product) {
                    $item->setRelation('product', $product);
                }
            }

            return $item;
        });
        $order->setRelation('items', $lineModels);

        return $order;
    }

    /**
     * Print "Removed Items (Don't make)" slip to each item's department printer.
     */
    public function removedItemsPrintNetwork(Request $request, PosOrder $order): JsonResponse
    {
        abort_unless($order->status === 'draft', 404);
        $this->assertDraftReceiptAccess($order);

        $user = Auth::user();
        if (! $user || ! $this->userCanKitchenVoid($user)) {
            return response()->json([
                'ok' => false,
                'message' => 'Kitchen items sirf manager/admin remove kar sakta hai.',
            ], 403);
        }

        $voids = $this->kitchenVoidsFromInput($request->input('kitchen_voids'));
        if ($voids === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Removed items list khali hai.',
            ], 422);
        }

        $result = $this->logKitchenVoids($order, $voids);

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'results' => $result['results'] ?? [],
            'unrouted' => $result['unrouted'] ?? 0,
            'message' => $result['message'] ?? null,
        ], ($result['ok'] ?? false) ? 200 : 500);
    }

    /**
     * Resolve which department (with a printer) a product should print to.
     */
    private function resolveItemDepartment(?InventoryProduct $product): ?\App\Models\InventoryDepartment
    {
        return app(NetworkPrinterService::class)->resolveItemDepartment($product);
    }

    private function renderReceipt(Request $request, PosOrder $order, bool $paidOnly): View
    {
        if ($paidOnly) {
            abort_unless($order->status === 'paid', 404);
            abort_unless($this->userCanAccessPaidReceipt($request->user(), $order), 403);
        } else {
            abort_unless($order->status === 'draft', 404);
            $this->assertDraftReceiptAccess($order);
        }

        $order->load(['items.product:id,name,sku', 'payments', 'contact:id,name,phone', 'user:id,name', 'table:id,name']);

        $settings = $this->receiptSettingsMap();
        $isUnpaid = ! $paidOnly;
        $isQuotation = false;
        $allowBillPrint = (($settings['pos_allow_bill_print'] ?? '1') === '1');
        $autoPrint = ! $request->boolean('noprint', false) && (
            $paidOnly
                ? Setting::get('pos_auto_print_receipt', '1') === '1'
                : $request->boolean('autoprint', true)
        );
        $backUrl = route('restaurant-pos.index');
        $backLabel = '← Back to Restaurant POS';

        return view('pos.receipt', compact('order', 'settings', 'autoPrint', 'allowBillPrint', 'backUrl', 'backLabel', 'isUnpaid', 'isQuotation'));
    }

    private function userCanAccessPaidReceipt(?User $user, PosOrder $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $this->userCanReopenPaidPosBill($user);
    }

    private function assertDraftReceiptAccess(PosOrder $order): void
    {
        $user = Auth::user();
        if ($user === null) {
            abort(403);
        }

        if ((int) $order->user_id === (int) $user->id) {
            return;
        }

        // Admin / platform bypass: any draft receipt.
        if ($user->bypassesModulePermissions()) {
            return;
        }

        // Same visibility as pending bill cards: shared floor sessions + order-taker drafts.
        $session = $this->getOpenPosSessionForUser($user);
        if ($session !== null && $this->findDraftOrderForSession($session, (int) $order->id, $user) !== null) {
            return;
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptSettingsMap(bool $forThermal = false): array
    {
        $companyId = function_exists('current_company_id') ? (current_company_id() ?? 0) : 0;
        $cacheKey = 'pos:receipt_settings:'.($forThermal ? 'thermal' : 'full').':c'.$companyId;

        /** @var array<string, mixed>|null $cached */
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

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
        if ($fixedCompanyName !== '') {
            $settings['company_name'] = $fixedCompanyName;
        }

        $logoPath = (string) ($settings['company_logo'] ?? '');
        $settings['company_logo_abs_path'] = company_logo_path($logoPath) ?? '';
        // Browser receipt needs URL / data-uri; thermal ESC/POS only needs abs path.
        if (! $forThermal) {
            $settings['company_logo_url'] = company_logo_url($logoPath) ?? '';
            $settings['company_logo_data_uri'] = company_logo_data_uri($logoPath) ?? '';
        } else {
            $settings['company_logo_url'] = '';
            $settings['company_logo_data_uri'] = '';
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, $settings, now()->addMinutes(10));

        return $settings;
    }

    /** Print paid bill to cashier printer after HTTP response (UI does not wait). */
    private function queueCashierBillPrint(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $ip = trim((string) Setting::get('cashier_printer_ip', ''));
        if ($ip === '') {
            return;
        }

        $port = (int) (Setting::get('cashier_printer_port', 9100) ?: 9100);
        $settings = $this->receiptSettingsMap(forThermal: true);

        dispatch(function () use ($orderId, $ip, $port, $settings) {
            try {
                $order = PosOrder::query()
                    ->with(['items.product:id,name,sku', 'user:id,name', 'table:id,name', 'payments', 'contact:id,name,phone'])
                    ->find($orderId);
                if ($order === null || $order->status !== 'paid') {
                    return;
                }

                $printer = app(NetworkPrinterService::class);
                $payload = $printer->buildBillSlip($order, $settings);
                // Fast + one retry — afterResponse must not hang on dead printer.
                $printer->send($ip, $port, $payload, 4);
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
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
            if (filter_var($item['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

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
            $isCustom = filter_var($item['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $itemName = $this->nullableText($item['item_name'] ?? null);

            $lineSub = $qty * $price;

            $product = $products->get($pid);
            if ($isCustom) {
                if ($itemName === null) {
                    abort(422, 'On Demand product name required hai.');
                }
                $uom = 'unit';
                $factor = 1.0;
                if ($product === null || ! PosCustomProduct::isCustomSku($product->sku ?? null)) {
                    abort(422, 'Invalid On Demand product line.');
                }
            } else {
                $uom = (string) $item['uom'];
                $factor = $product ? $product->factorToBaseForUom($uom) : null;
                if ($product === null || $factor === null || $factor <= 0) {
                    abort(422, 'Invalid UOM or product for a cart line.');
                }
            }

            $subtotal += $lineSub;
            $rawLines[] = [
                'product_id' => $pid,
                'item_name' => $isCustom ? $itemName : null,
                'is_custom' => $isCustom,
                'uom' => $isCustom ? 'unit' : $uom,
                'qty' => $qty,
                'unit_price' => $price,
                'line_sub' => $lineSub,
                'tax_pct' => $taxPct,
                'notes' => $this->nullableText($item['notes'] ?? null),
                'kitchen_locked_qty' => array_key_exists('kitchen_locked_qty', $item)
                    ? max(0.0, (float) $item['kitchen_locked_qty'])
                    : null,
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

            $line = [
                'product_id' => $raw['product_id'],
                'item_name' => $raw['item_name'],
                'is_custom' => $raw['is_custom'],
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
            if ($raw['kitchen_locked_qty'] !== null) {
                $line['kitchen_locked_qty'] = (float) $raw['kitchen_locked_qty'];
            }
            $lines[] = $line;
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

    /**
     * Delete every payment row for an order (ignore company scope so orphans cannot remain).
     */
    private function clearOrderPayments(PosOrder $order): void
    {
        SyncAwareDelete::query(
            PosPayment::withoutGlobalScopes()->where('order_id', $order->id)
        );
    }

    /**
     * Replace payments atomically and assert sum matches the bill total.
     *
     * @param  list<array{method?: string, amount?: mixed, reference?: mixed}>  $payments
     */
    private function replaceOrderPayments(PosOrder $order, array $payments, float $grandTotal): void
    {
        $this->clearOrderPayments($order);

        foreach ($payments as $payment) {
            PosPayment::create([
                'order_id' => $order->id,
                'company_id' => $order->company_id,
                'method' => $payment['method'],
                'amount' => (float) $payment['amount'],
                'reference' => $payment['reference'] ?? null,
            ]);
        }

        $paySum = round((float) PosPayment::withoutGlobalScopes()->where('order_id', $order->id)->sum('amount'), 2);
        if (abs($paySum - round($grandTotal, 2)) > 0.01) {
            throw new \RuntimeException('Payments total must match order total.');
        }
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
                ->get()
                ->each(fn (InventoryMove $move) => $move->delete());

            SyncAwareDelete::query(CreditLedger::query()->where('pos_order_id', $order->id));
            $this->deletePosJournalEntries($order);
            $this->clearOrderPayments($order);
            SyncAwareDelete::relation($order->items());
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
                'is_custom' => (bool) $line->is_custom,
            ]);
        }
    }

    private function deletePosJournalEntries(PosOrder $order): void
    {
        JournalEntry::query()
            ->where('source', 'pos')
            ->where('source_id', $order->id)
            ->each(function (JournalEntry $entry) {
                SyncAwareDelete::relation($entry->lines());
                $entry->delete();
            });
    }

    private function applyInventoryForPos(PosOrder $order, array $item): void
    {
        if (filter_var($item['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $product = InventoryProduct::query()
            ->with('uomConversions')
            ->withExists(['manufacturingBoms' => fn ($q) => $q->where('active', true)])
            ->findOrFail($item['product_id']);

        if (PosCustomProduct::isCustomSku($product->sku ?? null)) {
            return;
        }

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

        $finished->loadMissing(['department', 'departments']);
        $consumeDeptId = app(\App\Services\InventoryStockService::class)
            ->consumptionDepartmentIdForProduct($finished);

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
                        true,
                        $consumeDeptId
                    );
                    $this->notifyStockUpdate($component, 'out', $needBase, $order->order_no);
                } else {
                    $this->manufacturingStock->stockIn(
                        $component,
                        $needBase,
                        Auth::id(),
                        $ref,
                        $notePrefix.' — '.$finished->name.' (BoM)',
                        (float) $component->cost,
                        $consumeDeptId
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
        PosCustomProduct::ensure();
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
    private function posOrderDetailsPayload(PosOrder $order, bool $listOnly = false): array
    {
        if ($listOnly) {
            $order->loadMissing(['table:id,name', 'payments:id,order_id,method,amount', 'user:id,name']);
        } else {
            $order->loadMissing(['table:id,name', 'items.product:id,name', 'payments:id,order_id,method,amount', 'user:id,name']);
        }

        $tableRoomParts = [];
        if ($order->table) {
            $tableRoomParts[] = $order->table->name;
        }
        if ($order->room_no) {
            $tableRoomParts[] = $order->room_no;
        }

        $payMethods = $order->relationLoaded('payments')
            ? $order->payments
                ->pluck('method')
                ->map(fn ($m) => ucfirst((string) $m))
                ->unique()
                ->values()
            : collect();

        $orderAt = $order->ready_for_pos_at ?? $order->created_at;
        $serveTime = trim((string) ($order->serve_time ?? ''));
        $serveDate = $order->serve_date instanceof \Illuminate\Support\Carbon
            ? $order->serve_date->format('Y-m-d')
            : trim((string) ($order->serve_date ?? ''));
        $punchedBy = trim((string) ($order->user?->name ?? ''));

        // Guest-name split label only — avoid scanning all session drafts per card.
        $splitLabel = $this->orderSplitLabelFromGuest($order);

        $itemsCount = $order->items_count
            ?? ($order->relationLoaded('items') ? $order->items->count() : 0);

        $payload = [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'is_pending' => $order->status === 'draft',
            'customer_type' => $order->customerTypeKey(),
            'service_type' => $order->serviceTypeKey(),
            'service_type_label' => $order->serviceTypeLabel(),
            'guest_name' => $order->guest_name,
            'is_split' => $splitLabel !== null,
            'split_label' => $splitLabel,
            'waiter_name' => $order->waiter_name,
            'punched_by' => $punchedBy !== '' ? $punchedBy : null,
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
            'bill_discount_percent' => (float) ($order->bill_discount_percent ?? 0),
            'is_owner_discount' => (bool) ($order->is_owner_discount ?? false),
            'items_count' => (int) $itemsCount,
            'paid_at' => $order->paid_at?->format('H:i'),
            'paid_at_full' => $order->paid_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            'serve_time' => $serveTime !== '' ? $serveTime : null,
            'serve_date' => $serveDate !== '' ? $serveDate : null,
            'order_time' => $order->isFromOrderTaker() && $orderAt ? $orderAt->format('H:i') : null,
            'served_at' => $order->kitchen_completed_at?->format('H:i'),
            'kitchen_status_label' => $order->pendingKitchenStatusLabel(),
            'kitchen_status_badge' => $order->pendingKitchenStatusBadgeClass(),
            'created_at' => $order->created_at?->format('Y-m-d H:i'),
        ];

        if ($listOnly) {
            $payload['served_count'] = 0;
            $payload['pending_count'] = 0;
            $payload['timeline'] = [];
            $payload['items'] = [];

            return $payload;
        }

        $payload['served_count'] = $order->items->filter(fn (PosOrderItem $item) => $item->isKitchenServed())->count();
        $payload['pending_count'] = $order->items->filter(fn (PosOrderItem $item) => ! $item->isKitchenServed() && (bool) $item->kitchen_pending)->count();
        // Timeline is unused on POS cards — skip building it on list/sync hot paths.
        $payload['timeline'] = [];
        $payload['items'] = $order->items->map(fn (PosOrderItem $item) => [
            'id' => (int) $item->id,
            'product_id' => (int) $item->product_id,
            'name' => $item->displayName(),
            'item_name' => $item->item_name,
            'is_custom' => (bool) $item->is_custom,
            'qty' => fmt_num((float) $item->qty, 3),
            'uom' => $item->uom,
            'unit_price' => (float) $item->unit_price,
            'tax_percent' => (float) $item->tax_percent,
            'total' => (float) $item->total,
            'notes' => trim((string) ($item->notes ?? '')),
            'kitchen_served' => $item->isKitchenServed(),
            'kitchen_pending' => (bool) $item->kitchen_pending,
            'kitchen_printed' => $item->kitchen_printed_at !== null,
            'kitchen_served_at' => $item->kitchen_served_at?->format('H:i'),
        ])->values()->all();

        return $payload;
    }

    private function orderIsSplitBill(PosOrder $order): bool
    {
        return $this->orderSplitLabel($order) !== null;
    }

    private function orderSplitLabel(PosOrder $order): ?string
    {
        $fromGuest = $this->orderSplitLabelFromGuest($order);
        if ($fromGuest !== null) {
            return $fromGuest;
        }

        if ($this->orderIsSplitSourceBill($order)) {
            return 'Source';
        }

        return null;
    }

    private function orderSplitLabelFromGuest(PosOrder $order): ?string
    {
        $guest = trim((string) ($order->guest_name ?? ''));
        if ($guest === '') {
            return null;
        }
        if (preg_match('/ · Split (.+)$/u', $guest, $m) === 1) {
            $label = trim((string) ($m[1] ?? ''));

            return $label !== '' ? $label : null;
        }

        return null;
    }

    private function orderIsSplitSourceBill(PosOrder $order): bool
    {
        $sessionId = $order->session_id ? (int) $order->session_id : 0;
        if ($sessionId <= 0) {
            return false;
        }

        return in_array((int) $order->id, $this->splitParentOrderIdsForSession($sessionId), true);
    }

    /**
     * Draft bills jin se item-wise split hua (bina guest label ke purani entries bhi).
     *
     * @return list<int>
     */
    private function splitParentOrderIdsForSession(int $sessionId): array
    {
        if (isset($this->splitParentOrderIdsBySession[$sessionId])) {
            return $this->splitParentOrderIdsBySession[$sessionId];
        }

        $drafts = PosOrder::query()
            ->where('status', 'draft')
            ->where('session_id', $sessionId)
            ->with('table:id,name')
            ->get(['id', 'guest_name', 'table_id', 'session_id']);

        $splitChildren = $drafts->filter(
            fn (PosOrder $o) => str_contains((string) $o->guest_name, ' · Split ')
        );

        $parentIds = [];
        foreach ($drafts as $parent) {
            if (str_contains((string) $parent->guest_name, ' · Split ')) {
                continue;
            }
            $tableName = strtolower(trim((string) ($parent->table?->name ?? '')));
            foreach ($splitChildren as $child) {
                if ((int) $child->id === (int) $parent->id) {
                    continue;
                }
                $guest = trim((string) $child->guest_name);
                if (preg_match('/^(.+?) · Split /u', $guest, $m) !== 1) {
                    continue;
                }
                $childBase = strtolower(trim((string) ($m[1] ?? '')));
                if ($tableName !== '' && $childBase === $tableName) {
                    $parentIds[] = (int) $parent->id;
                    break;
                }
            }
        }

        $this->splitParentOrderIdsBySession[$sessionId] = array_values(array_unique($parentIds));

        return $this->splitParentOrderIdsBySession[$sessionId];
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
            'item_name' => $i->item_name,
            'is_custom' => (bool) $i->is_custom,
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
            $oldItems = $order->items()->get()->all();
            $order->update([
                'sale_mode' => $best['sale'],
                'subtotal' => $best['subtotal'],
                'discount_total' => $best['discount'],
                'tax_total' => $best['tax'],
                'service_charge_total' => $best['service'],
                'service_charge_percent' => ($best['service'] ?? 0) > 0 ? PosServiceCharge::percent() : null,
                'grand_total' => $best['grand'],
            ]);
            $itemsWithFlags = app(KitchenService::class)->applyKitchenPendingFlags(
                $oldItems,
                $best['itemsData'],
                true
            );
            SyncAwareDelete::relation($order->items());
            foreach ($itemsWithFlags as $item) {
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

    /**
     * Double Hold / Kitchen Print: same new bill within ~60s returns the first draft.
     *
     * @param  list<array<string, mixed>>  $itemsNormalized
     */
    private function posNewHoldFingerprint(
        int $sessionId,
        PosCheckoutRequest $request,
        string $customerType,
        ?string $serviceType,
        ?string $guestName,
        ?string $roomNo,
        ?int $tableId,
        ?string $kitchenNotes,
        array $itemsNormalized
    ): string {
        $clientKey = trim((string) $request->input('client_request_id', ''));
        if ($clientKey !== '') {
            return hash('sha256', 'cid:'.(Auth::id() ?? 0).':'.$sessionId.':'.$clientKey);
        }

        $normItems = [];
        foreach ($itemsNormalized as $row) {
            $normItems[] = [
                (int) ($row['product_id'] ?? 0),
                (bool) ($row['is_custom'] ?? false),
                strtolower(trim((string) ($row['item_name'] ?? ''))),
                strtolower(trim((string) ($row['uom'] ?? ''))),
                round((float) ($row['qty'] ?? 0), 3),
                round((float) ($row['unit_price'] ?? 0), 2),
                trim((string) ($row['notes'] ?? '')),
            ];
        }
        usort($normItems, static fn ($a, $b) => $a <=> $b);

        return hash('sha256', json_encode([
            'u' => Auth::id() ?? 0,
            's' => $sessionId,
            'ct' => $customerType,
            'st' => (string) ($serviceType ?? ''),
            'g' => (string) ($guestName ?? ''),
            'r' => (string) ($roomNo ?? ''),
            't' => (int) ($tableId ?? 0),
            'k' => (string) ($kitchenNotes ?? ''),
            'i' => $normItems,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function findCachedHoldCreate(string $cacheKey): ?PosOrder
    {
        $id = Cache::get($cacheKey);
        if (! $id) {
            return null;
        }

        $order = PosOrder::query()
            ->with(['table', 'items'])
            ->find((int) $id);

        return ($order && $order->status === 'draft') ? $order : null;
    }

    /**
     * Same cart (items + service) recent draft — e.g. kitchen without contact, then unpaid with phone.
     *
     * @param  list<array<string, mixed>>  $itemsNormalized
     */
    private function findRecentSameCartDraft(
        int $sessionId,
        ?string $serviceType,
        array $itemsNormalized,
        ?string $guestName,
        ?string $roomNo,
        ?int $tableId,
        ?int $userId
    ): ?PosOrder {
        $want = $this->itemsFingerprintPayload($itemsNormalized);
        $newContact = trim((string) (($roomNo !== null && $roomNo !== '') ? $roomNo : ($guestName ?? '')));

        $query = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'draft')
            ->where('created_at', '>=', now()->subMinutes(45))
            ->orderByDesc('id')
            ->limit(20);

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }
        if ($tableId) {
            $query->where('table_id', $tableId);
        } else {
            $query->whereNull('table_id');
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        foreach ($query->with('items')->get() as $draft) {
            if (! $draft instanceof PosOrder) {
                continue;
            }
            $have = $this->itemsFingerprintPayload(
                $draft->items->map(static fn ($it) => [
                    'product_id' => (int) $it->product_id,
                    'is_custom' => (bool) ($it->is_custom ?? false),
                    'item_name' => (string) ($it->item_name ?? ''),
                    'uom' => (string) ($it->uom ?? ''),
                    'qty' => (float) $it->qty,
                    'unit_price' => (float) $it->unit_price,
                    'notes' => (string) ($it->notes ?? ''),
                ])->all()
            );
            if ($have !== $want) {
                continue;
            }

            $draftContact = trim((string) (($draft->room_no !== null && $draft->room_no !== '')
                ? $draft->room_no
                : ($draft->guest_name ?? '')));

            // Different takeaway/delivery customer → not the same bill.
            if ($draftContact !== '' && $newContact !== '' && $draftContact !== $newContact) {
                continue;
            }

            return $draft;
        }

        return null;
    }

    /**
     * Same cart recently paid — blocks twin Hold/Kitchen/Pay after a successful sale.
     *
     * @param  list<array<string, mixed>>  $itemsNormalized
     */
    private function findRecentSameCartPaid(
        int $sessionId,
        ?string $serviceType,
        array $itemsNormalized,
        ?string $guestName,
        ?string $roomNo,
        ?int $tableId,
        ?int $userId,
        ?float $grandTotal = null,
        int $withinMinutes = 12
    ): ?PosOrder {
        $want = $this->itemsFingerprintPayload($itemsNormalized);
        $newContact = trim((string) (($roomNo !== null && $roomNo !== '') ? $roomNo : ($guestName ?? '')));

        $query = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'paid')
            ->where('type', 'sale')
            ->where(function ($q) use ($withinMinutes) {
                $q->where('paid_at', '>=', now()->subMinutes($withinMinutes))
                    ->orWhere(function ($q2) use ($withinMinutes) {
                        $q2->whereNull('paid_at')
                            ->where('created_at', '>=', now()->subMinutes($withinMinutes));
                    });
            })
            ->orderByDesc('id')
            ->limit(25);

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }
        if ($tableId) {
            $query->where('table_id', $tableId);
        } else {
            $query->whereNull('table_id');
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($grandTotal !== null) {
            $query->whereBetween('grand_total', [$grandTotal - 0.02, $grandTotal + 0.02]);
        }

        foreach ($query->with('items')->get() as $paid) {
            if (! $paid instanceof PosOrder) {
                continue;
            }
            $have = $this->itemsFingerprintPayload(
                $paid->items->map(static fn ($it) => [
                    'product_id' => (int) $it->product_id,
                    'is_custom' => (bool) ($it->is_custom ?? false),
                    'item_name' => (string) ($it->item_name ?? ''),
                    'uom' => (string) ($it->uom ?? ''),
                    'qty' => (float) $it->qty,
                    'unit_price' => (float) $it->unit_price,
                    'notes' => (string) ($it->notes ?? ''),
                ])->all()
            );
            if ($have !== $want) {
                continue;
            }

            $paidContact = trim((string) (($paid->room_no !== null && $paid->room_no !== '')
                ? $paid->room_no
                : ($paid->guest_name ?? '')));

            if ($paidContact !== '' && $newContact !== '' && $paidContact !== $newContact) {
                continue;
            }

            // Anonymous takeaway: only treat as twin when contact side also empty.
            if ($paidContact !== $newContact) {
                continue;
            }

            return $paid;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $itemsNormalized
     */
    private function rememberPaidCartFingerprint(
        int $sessionId,
        ?string $serviceType,
        array $itemsNormalized,
        int $orderId
    ): void {
        Cache::put(
            'pos:paid:cart:'.$this->posItemsOnlyFingerprint($sessionId, $serviceType, $itemsNormalized),
            $orderId,
            now()->addMinutes(12)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $itemsNormalized
     */
    private function posItemsOnlyFingerprint(int $sessionId, ?string $serviceType, array $itemsNormalized): string
    {
        return hash('sha256', json_encode([
            'u' => Auth::id() ?? 0,
            's' => $sessionId,
            'st' => (string) ($serviceType ?? ''),
            'i' => $this->itemsFingerprintPayload($itemsNormalized),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<int, mixed>>
     */
    private function itemsFingerprintPayload(array $items): array
    {
        $normItems = [];
        foreach ($items as $row) {
            $normItems[] = [
                (int) ($row['product_id'] ?? 0),
                (bool) ($row['is_custom'] ?? false),
                strtolower(trim((string) ($row['item_name'] ?? ''))),
                strtolower(trim((string) ($row['uom'] ?? ''))),
                round((float) ($row['qty'] ?? 0), 3),
                round((float) ($row['unit_price'] ?? 0), 2),
            ];
        }
        usort($normItems, static fn ($a, $b) => $a <=> $b);

        return $normItems;
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
        } elseif ($serviceType === PosOrder::SERVICE_TAKEAWAY) {
            // Contact No. is stored in both guest_name + room_no (same as POS JS).
            $contact = $this->nullableText($request->input('room_no'))
                ?: $this->nullableText($request->input('guest_name'));
            $guestName = $contact;
            $roomNo = $contact;
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
        PosTablesSchema::ensure();
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
        $customProduct = PosCustomProduct::ensure();
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $isCustom = filter_var($item['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $isCustom) {
                continue;
            }
            $items[$i]['is_custom'] = true;
            $items[$i]['product_id'] = (int) $customProduct->id;
            $items[$i]['uom'] = 'unit';
            $items[$i]['item_name'] = trim((string) ($item['item_name'] ?? ''));
        }

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
            if (filter_var($item['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

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
     * Kitchen-printed/served qty cannot be reduced or removed on hold/checkout
     * unless a matching kitchen void reason is supplied by an admin.
     * Unprinted pending lines remain freely editable.
     *
     * @param  array<int, PosOrderItem>  $existingItems
     * @param  array<int, array<string, mixed>>  $incomingItems
     * @param  array<int, array<string, mixed>>  $kitchenVoids
     */
    private function assertKitchenLockedQuantitiesPreserved(array $existingItems, array $incomingItems, array $kitchenVoids = [], bool $hardFail = false): void
    {
        try {
            app(KitchenService::class)->assertLockedQuantitiesPreserved($existingItems, $incomingItems, $kitchenVoids, $hardFail);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'items' => $e->getMessage(),
            ]);
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
     * @return list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string, item_name?: string, is_custom?: bool}>
     */
    private function normalizedKitchenVoids(PosCheckoutRequest $request): array
    {
        return $this->kitchenVoidsFromInput($request->input('kitchen_voids', []));
    }

    /**
     * @param  mixed  $raw
     * @return list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string, item_name?: string, is_custom?: bool}>
     */
    private function kitchenVoidsFromInput(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $voids = [];
        foreach ($raw as $void) {
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
                'item_name' => trim((string) ($void['item_name'] ?? '')),
                'is_custom' => filter_var($void['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'order_item_id' => (int) ($void['order_item_id'] ?? 0) ?: null,
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
                'item_name' => trim((string) ($row['item_name'] ?? '')),
                'is_custom' => filter_var($row['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
     * Remember kitchen voids for an order so a later autosave (without voids in the
     * request) cannot re-append already-cancelled printed lines.
     *
     * @param  list<array<string, mixed>>  $voids
     */
    private function persistKitchenVoids(int $orderId, array $voids): void
    {
        if ($orderId <= 0 || $voids === []) {
            return;
        }

        $merged = $this->mergePersistedKitchenVoids($orderId, $voids);
        Cache::put($this->kitchenVoidCacheKey($orderId), $merged, now()->addDays(2));
    }

    /**
     * @param  list<array<string, mixed>>  $requestVoids
     * @return list<array<string, mixed>>
     */
    private function mergePersistedKitchenVoids(int $orderId, array $requestVoids): array
    {
        $cached = Cache::get($this->kitchenVoidCacheKey($orderId), []);
        if (! is_array($cached)) {
            $cached = [];
        }

        $out = [];
        foreach (array_merge($cached, $requestVoids) as $void) {
            if (! is_array($void)) {
                continue;
            }
            $qty = (float) ($void['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $out[] = [
                'product_id' => (int) ($void['product_id'] ?? 0),
                'uom' => (string) ($void['uom'] ?? ''),
                'qty' => $qty,
                'reason' => trim((string) ($void['reason'] ?? 'void')) ?: 'void',
                'notes' => trim((string) ($void['notes'] ?? '')),
                'name' => trim((string) ($void['name'] ?? '')),
                'item_name' => trim((string) ($void['item_name'] ?? '')),
                'is_custom' => filter_var($void['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'order_item_id' => (int) ($void['order_item_id'] ?? 0) ?: null,
            ];
        }

        return $out;
    }

    private function forgetPersistedKitchenVoids(int $orderId): void
    {
        if ($orderId > 0) {
            Cache::forget($this->kitchenVoidCacheKey($orderId));
        }
    }

    private function kitchenVoidCacheKey(int $orderId): string
    {
        return 'pos:order:kitchen_voids:'.$orderId;
    }

    /**
     * @param  list<array{product_id: int, uom: string, qty: float, reason: string, notes?: string, name?: string}>  $kitchenVoids
     * @return array{ok: bool, results: list<array{department: string, ok: bool, error?: string}>, unrouted: int, message?: string|null}
     */
    private function logKitchenVoids(PosOrder $order, array $kitchenVoids): array
    {
        if ($kitchenVoids === []) {
            return ['ok' => true, 'results' => [], 'unrouted' => 0, 'message' => null];
        }

        // Drop exact duplicate voids already logged in the last few minutes (retry / race).
        $kitchenVoids = $this->dedupeRecentKitchenVoids((int) $order->id, $kitchenVoids);
        if ($kitchenVoids === []) {
            return ['ok' => true, 'results' => [], 'unrouted' => 0, 'message' => null];
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

        foreach ($kitchenVoids as &$void) {
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
                [
                    'void' => $void,
                    // Keep after order delete so Kitchen Cancelled tab still finds this row.
                    'session_id' => (int) ($order->session_id ?? 0) ?: null,
                    'order_no' => (string) ($order->order_no ?? ''),
                    'order_id' => (int) $order->id,
                ]
            );
            $this->markKitchenVoidLogged((int) $order->id, $void);
        }
        unset($void);

        \App\Services\PosActivityNotifier::itemsCancelled($order, $kitchenVoids);

        try {
            return app(NetworkPrinterService::class)->dispatchRemovedItemsPrints($order, $kitchenVoids);
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'results' => [],
                'unrouted' => 0,
                'message' => 'Removed items print fail: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $voids
     * @return list<array<string, mixed>>
     */
    private function dedupeRecentKitchenVoids(int $orderId, array $voids): array
    {
        $out = [];
        foreach ($voids as $void) {
            if (! is_array($void)) {
                continue;
            }
            $sig = $this->kitchenVoidDedupeSignature($void);
            if (Cache::has('pos:kitchen_void_logged:'.$orderId.':'.$sig)) {
                continue;
            }
            $out[] = $void;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $void
     */
    private function markKitchenVoidLogged(int $orderId, array $void): void
    {
        $sig = $this->kitchenVoidDedupeSignature($void);
        Cache::put('pos:kitchen_void_logged:'.$orderId.':'.$sig, 1, now()->addMinutes(10));
    }

    /**
     * @param  array<string, mixed>  $void
     */
    private function kitchenVoidDedupeSignature(array $void): string
    {
        return hash('sha256', json_encode([
            (int) ($void['product_id'] ?? 0),
            mb_strtolower(trim((string) ($void['uom'] ?? ''))),
            round((float) ($void['qty'] ?? 0), 3),
            mb_strtolower(trim((string) ($void['reason'] ?? ''))),
            (int) ($void['order_item_id'] ?? 0),
            filter_var($void['is_custom'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ], JSON_UNESCAPED_UNICODE));
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

        StaffNotifier::notifyManagement(
            new StockUpdated($payload),
            function_exists('current_company_id') ? current_company_id() : null
        );
    }
}
