<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosSession;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Notifications\PosActivity;
use App\Services\AttendancePayrollService;
use App\Services\PosPendingBillsService;
use App\Services\PosSessionSummaryService;
use App\Support\LanServerUrl;
use App\Support\PosOrderMetrics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AdminApiController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $currency = Setting::get('currency_symbol', 'Rs.');

        $paidToday = PosOrder::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $todayStart)
            ->sum('grand_total');

        $paidMonth = PosOrder::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->sum('grand_total');

        $billsToday = PosOrder::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $todayStart)
            ->count();

        $pendingCount = PosOrder::query()
            ->where('status', 'draft')
            ->count();

        $expensesToday = Expense::query()
            ->when(
                Schema::hasColumn((new Expense)->getTable(), 'status'),
                fn ($q) => $q->whereIn('status', [
                    Expense::STATUS_APPROVED,
                    Expense::STATUS_PAID,
                ])
            )
            ->whereDate('expense_date', today())
            ->sum('grand_total');

        $expensesMonth = Expense::query()
            ->when(
                Schema::hasColumn((new Expense)->getTable(), 'status'),
                fn ($q) => $q->whereIn('status', [
                    Expense::STATUS_APPROVED,
                    Expense::STATUS_PAID,
                ])
            )
            ->where('expense_date', '>=', $monthStart->toDateString())
            ->sum('grand_total');

        $lowStockCount = InventoryProduct::query()
            ->where('active', true)
            ->where('reorder_level', '>', 0)
            ->whereColumn('qty_on_hand', '<=', 'reorder_level')
            ->count();

        $openSession = PosSession::query()
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first(['id', 'opened_at']);

        return response()->json(array_merge([
            'currency' => $currency,
            'today' => [
                'sales' => round((float) $paidToday, 2),
                'bills' => (int) $billsToday,
                'expenses' => round((float) $expensesToday, 2),
            ],
            'month' => [
                'sales' => round((float) $paidMonth, 2),
                'expenses' => round((float) $expensesMonth, 2),
            ],
            'pending_orders' => (int) $pendingCount,
            'low_stock' => (int) $lowStockCount,
            'session' => $openSession ? [
                'id' => (int) $openSession->id,
                'opened_at' => optional($openSession->opened_at)?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            ] : null,
            'user' => [
                'id' => Auth::id(),
                'name' => Auth::user()?->name,
                'role' => Auth::user()?->role,
            ],
        ], LanServerUrl::apiPayload()));
    }

    public function pendingOrders(Request $request): JsonResponse
    {
        $limit = min(300, max(10, (int) $request->input('limit', 150)));
        $sessionIds = $this->currentOpenSessionIds();

        if ($sessionIds === []) {
            return response()->json(['orders' => [], 'count' => 0]);
        }

        $orders = app(PosPendingBillsService::class)
            ->queryHeldDrafts($sessionIds, false)
            ->filter(fn (PosOrder $o) => $o->isDueForServeDay())
            ->sortByDesc('id')
            ->take($limit)
            ->values();

        if ($orders->isNotEmpty()) {
            $orders->load(['table:id,name', 'items:id,order_id,qty']);
        }

        return response()->json([
            'orders' => $orders->map(fn (PosOrder $o) => $this->orderCard($o))->values(),
            'count' => $orders->count(),
        ]);
    }

    public function paidOrders(Request $request): JsonResponse
    {
        $limit = min(300, max(10, (int) $request->input('limit', 150)));
        $sessionIds = $this->currentOpenSessionIds();

        if ($sessionIds === []) {
            return response()->json([
                'orders' => [],
                'total' => 0,
                'count' => 0,
            ]);
        }

        $oldestOpenedAt = PosSession::query()
            ->whereIn('id', $sessionIds)
            ->min('opened_at');

        $orders = PosOrder::query()
            ->whereIn('session_id', $sessionIds)
            ->where('status', 'paid')
            ->when($oldestOpenedAt, function ($q) use ($oldestOpenedAt) {
                $q->where(function ($sub) use ($oldestOpenedAt) {
                    $sub->where('paid_at', '>=', $oldestOpenedAt)
                        ->orWhereNull('paid_at');
                });
            })
            ->with(['table:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'orders' => $orders->map(fn (PosOrder $o) => $this->orderCard($o, true))->values(),
            'total' => round((float) $orders->sum('grand_total'), 2),
            'count' => $orders->count(),
        ]);
    }

    public function kitchenVoids(Request $request): JsonResponse
    {
        $limit = min(200, max(10, (int) $request->input('limit', 80)));

        // Current open POS session(s) only — same window as restaurant POS kitchen voids.
        $openQuery = PosSession::query()->whereNull('closed_at');
        if (Schema::connection('tenant')->hasColumn('pos_sessions', 'status')) {
            $openQuery->where('status', 'open');
        }

        $openSessions = $openQuery->orderByDesc('id')->get(['id', 'opened_at', 'session_no']);
        if ($openSessions->isEmpty()) {
            return response()->json([
                'items' => [],
                'count' => 0,
                'session' => null,
            ]);
        }

        $billSessionIds = $openSessions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $sessionOpenedAt = $openSessions->min('opened_at') ?? now()->startOfDay();

        $orderIds = PosOrder::query()
            ->whereIn('session_id', $billSessionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

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

                // Legacy logs without session_id: same open-session window.
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
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $items = $logs->map(function (ActivityLog $log) {
            $props = is_array($log->properties) ? $log->properties : [];
            $void = is_array($props['void'] ?? null) ? $props['void'] : [];
            $name = trim((string) ($void['name'] ?? 'Item'));
            $qty = (float) ($void['qty'] ?? 0);
            $reason = trim((string) ($void['reason'] ?? ''));

            return [
                'id' => (int) $log->id,
                'order_no' => (string) ($props['order_no'] ?? ''),
                'item' => $name !== '' ? $name : 'Item',
                'qty' => $qty,
                'reason' => $reason,
                'by' => $log->user?->name ?? '—',
                'at' => optional($log->created_at)?->timezone(config('app.timezone'))->format('d M, H:i'),
            ];
        })->unique('id')->values();

        $labels = $openSessions
            ->map(fn (PosSession $s) => $s->session_no ?: ('#'.$s->id))
            ->filter()
            ->values()
            ->all();

        $openedAt = $openSessions->min('opened_at');
        $openedLabel = $openedAt != null
            ? Carbon::parse($openedAt)->timezone(config('app.timezone'))->format('d M Y, H:i')
            : null;

        return response()->json([
            'items' => $items,
            'count' => $items->count(),
            'session' => [
                'ids' => $billSessionIds,
                'labels' => $labels !== [] ? implode(', ', $labels) : null,
                'opened_at' => $openedLabel,
            ],
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $limit = min(100, max(10, (int) $request->input('limit', 40)));

        $q = Expense::query()->orderByDesc('expense_date')->orderByDesc('id');

        if (Schema::hasColumn((new Expense)->getTable(), 'status')) {
            $q->whereIn('status', [
                Expense::STATUS_SUBMITTED,
                Expense::STATUS_APPROVED,
                Expense::STATUS_PAID,
            ]);
        }

        $rows = $q->limit($limit)->get();

        return response()->json([
            'expenses' => $rows->map(function (Expense $e) {
                return [
                    'id' => (int) $e->id,
                    'title' => (string) ($e->description ?? 'Expense'),
                    'amount' => round((float) ($e->grand_total ?? $e->total_amount ?? 0), 2),
                    'date' => $e->expense_date
                        ? Carbon::parse($e->expense_date)->format('d M Y')
                        : optional($e->created_at)?->format('d M Y'),
                    'status' => (string) ($e->status ?? ''),
                ];
            })->values(),
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $products = InventoryProduct::query()
            ->where('active', true)
            ->where('reorder_level', '>', 0)
            ->whereColumn('qty_on_hand', '<=', 'reorder_level')
            ->orderBy('qty_on_hand')
            ->limit(80)
            ->get(['id', 'name', 'sku', 'uom', 'qty_on_hand', 'reorder_level']);

        return response()->json([
            'products' => $products->map(fn (InventoryProduct $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'uom' => $p->uom,
                'qty' => (float) $p->qty_on_hand,
                'reorder_level' => (float) $p->reorder_level,
            ])->values(),
        ]);
    }

    public function attendanceToday(Request $request, AttendancePayrollService $attendancePayroll): JsonResponse
    {
        $date = $request->input('date');
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }

        $month = now()->format('Y-m');
        $monthLabel = now()->timezone(config('app.timezone'))->format('F Y');

        $employees = Employee::query()
            ->where('active', true)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'employee_no']);

        $byEmp = \App\Models\EmployeeAttendance::query()
            ->whereDate('attendance_date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'status', 'clock_in', 'clock_out'])
            ->keyBy('employee_id');

        $present = 0;
        $absent = 0;
        $rows = $employees->map(function ($e) use ($byEmp, $attendancePayroll, $month, &$present, &$absent) {
            $rec = $byEmp->get($e->id);
            $status = $rec ? (string) ($rec->status ?? 'absent') : 'absent';
            $code = strtolower($status);
            if (in_array($code, ['present', 'p', 'holiday', 'h', 'half'], true)) {
                $present++;
            } else {
                $absent++;
            }

            $monthCounts = $attendancePayroll->monthCountsForEmployee((int) $e->id, $month);

            return [
                'id' => (int) $e->id,
                'name' => (string) $e->name,
                'employee_no' => (string) ($e->employee_no ?? ''),
                'status' => $status,
                'clock_in' => $rec?->clock_in
                    ? Carbon::parse($rec->clock_in)->timezone(config('app.timezone'))->format('H:i')
                    : null,
                'clock_out' => $rec?->clock_out
                    ? Carbon::parse($rec->clock_out)->timezone(config('app.timezone'))->format('H:i')
                    : null,
                'month' => [
                    'present' => (int) ($monthCounts['present'] ?? 0),
                    'absent' => (int) ($monthCounts['absent'] ?? 0),
                    'holiday' => (int) ($monthCounts['holiday'] ?? 0),
                ],
            ];
        })->values();

        return response()->json([
            'date' => $date,
            'month' => $month,
            'month_label' => $monthLabel,
            'present' => $present,
            'absent' => $absent,
            'employees' => $rows,
        ]);
    }

    /**
     * Reports hub KPIs — same figures as web Reports index.
     */
    public function reportsOverview(): JsonResponse
    {
        $currency = Setting::get('currency_symbol', 'Rs.');

        $totalSales = PosOrder::query()
            ->where('status', 'paid')
            ->sum('grand_total');

        $totalPurchases = PurchaseOrder::query()
            ->whereIn('status', ['confirmed', 'received'])
            ->sum('grand_total');

        $expenseQuery = Expense::query();
        if (Schema::hasColumn((new Expense)->getTable(), 'status')) {
            $expenseQuery->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID]);
        }
        $totalExpenses = $expenseQuery->sum('grand_total');

        $totalProducts = InventoryProduct::query()
            ->where('active', true)
            ->count();

        $totalEmployees = Employee::query()
            ->where('active', true)
            ->count();

        return response()->json([
            'currency' => $currency,
            'total_sales' => round((float) $totalSales, 2),
            'total_purchases' => round((float) $totalPurchases, 2),
            'total_expenses' => round((float) $totalExpenses, 2),
            'active_products' => (int) $totalProducts,
            'active_employees' => (int) $totalEmployees,
        ]);
    }

    /** POS activity feed for Stair admin app (order punch / paid / cancel alerts). */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $management = $user->receivesManagementNotifications();

        $query = $user->notifications()->latest();
        if (! $management) {
            $query->where('type', PosActivity::class);
        }

        $notifications = $query
            ->limit(30)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'read_at' => $n->read_at,
                    'created_at' => optional($n->created_at)?->toIso8601String(),
                    'data' => $n->data,
                ];
            });

        $unreadQuery = $user->unreadNotifications();
        if (! $management) {
            $unreadQuery->where('type', PosActivity::class);
        }

        return response()->json([
            'unread_count' => $unreadQuery->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Same live KPIs as web Analytics Overview (no sample/demo numbers).
     */
    public function analytics(PosSessionSummaryService $sessionSummary): JsonResponse
    {
        $currency = Setting::get('currency_symbol', 'Rs.');
        $businessDate = now()->toDateString();
        $snapshotDate = now()->timezone(config('app.timezone'))->format('l, d M Y');

        $openSessions = collect();
        try {
            $openQuery = PosSession::query()
                ->with('user:id,name');

            if (Schema::connection('tenant')->hasColumn('pos_sessions', 'status')) {
                $openQuery->where('status', 'open');
            } elseif (Schema::connection('tenant')->hasColumn('pos_sessions', 'closed_at')) {
                $openQuery->whereNull('closed_at');
            }

            if (Schema::connection('tenant')->hasColumn('pos_sessions', 'shift_started')) {
                $openQuery->where('shift_started', true);
            }

            $openSessions = $openQuery->orderByDesc('id')->get();

            // Fallback: open session exists but shift_started / status flags differ on some installs.
            if ($openSessions->isEmpty()) {
                $fallback = PosSession::query()
                    ->with('user:id,name')
                    ->where(function ($q) {
                        if (Schema::connection('tenant')->hasColumn('pos_sessions', 'status')) {
                            $q->where('status', 'open');
                        }
                        if (Schema::connection('tenant')->hasColumn('pos_sessions', 'closed_at')) {
                            $q->orWhereNull('closed_at');
                        }
                    })
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();

                $openSessions = $fallback;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $sessionSale = 0.0;
        $sessionBills = 0;
        $sessionCash = 0.0;
        $sessionCard = 0.0;
        $sessionBank = 0.0;
        $sessionPending = 0;
        $cashiers = [];
        $labels = [];

        foreach ($openSessions as $session) {
            try {
                $stats = $sessionSummary->stats($session);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            $sessionSale += (float) ($stats['net_sales_total'] ?? 0);
            $sessionBills += (int) ($stats['sales_count'] ?? 0);
            $sessionCash += (float) ($stats['payments_cash'] ?? 0);
            $sessionCard += (float) ($stats['payments_card'] ?? 0);
            $sessionBank += (float) ($stats['payments_bank'] ?? 0);
            $sessionPending += (int) ($stats['held_count'] ?? 0);
            if ($session->user?->name) {
                $cashiers[] = $session->user->name;
            }
            $labels[] = $session->session_no ?: ('#'.$session->id);
        }

        $todayPaidOrders = PosOrder::query()
            ->where('status', 'paid')
            ->whereDate('created_at', $businessDate)
            ->get(['id', 'type', 'grand_total', 'status']);
        $todaySalesCount = $todayPaidOrders->where('type', 'sale')->count();
        $todaySalesTotal = round($todayPaidOrders->sum(fn (PosOrder $o) => PosOrderMetrics::signedGrandTotal($o)), 2);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();
        $monthStartTs = "{$monthStart} 00:00:00";
        $monthEndTs = "{$monthEnd} 23:59:59";
        $lastMonthStartTs = "{$lastMonthStart} 00:00:00";
        $lastMonthEndTs = "{$lastMonthEnd} 23:59:59";

        $posOrdersThisMonth = PosOrder::where('status', 'paid')
            ->whereBetween('created_at', [$monthStartTs, $monthEndTs])
            ->with([
                'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            ])
            ->get();

        $cafeSalesMonth = 0.0;
        $cafeProfitMonth = 0.0;
        foreach ($posOrdersThisMonth as $o) {
            $cafeSalesMonth += PosOrderMetrics::signedGrandTotal($o);
            $cafeProfitMonth += PosOrderMetrics::grossProfitFromLoaded($o);
        }
        $cafeSalesMonth = round($cafeSalesMonth, 2);
        $cafeProfitMonth = round($cafeProfitMonth, 2);

        $cafeSalesLastMonth = round((float) PosOrder::where('status', 'paid')
            ->whereBetween('created_at', [$lastMonthStartTs, $lastMonthEndTs])
            ->get()
            ->sum(fn (PosOrder $o) => PosOrderMetrics::signedGrandTotal($o)), 2);
        $incomeGrowth = $cafeSalesLastMonth > 0
            ? round((($cafeSalesMonth - $cafeSalesLastMonth) / $cafeSalesLastMonth) * 100, 1)
            : 0.0;

        $purchasesMonth = (float) PurchaseOrder::whereIn('status', ['confirmed', 'received'])
            ->whereBetween('order_date', [$monthStart, $monthEnd])
            ->sum('grand_total');
        $expensesMonth = (float) Expense::whereIn('status', ['approved', 'paid'])
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('grand_total');

        $activeEmployees = Employee::query()->excludeAdminAccounts()->where('active', true)->count();

        $totalProducts = InventoryProduct::query()->where('active', true)->count();
        $outOfStock = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->where('qty_on_hand', '<=', 0)
            ->count();
        $lowStock = InventoryProduct::query()
            ->where('active', true)
            ->where('for_purchase', true)
            ->where('qty_on_hand', '>', 0)
            ->where('qty_on_hand', '<=', 10)
            ->excludingActiveBomFinishedProducts()
            ->count();

        $outstandingCredit = 0.0;
        try {
            $outstandingCredit = round((float) CreditLedger::query()
                ->whereIn('contact_id', Contact::query()->select('id'))
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END), 0) as bal', ['credit', 'payment'])
                ->value('bal'), 2);
            if ($outstandingCredit < 0) {
                $outstandingCredit = 0.0;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'currency' => $currency,
            'snapshot_date' => $snapshotDate,
            'session' => [
                'open' => $openSessions->isNotEmpty(),
                'sale' => round($sessionSale, 2),
                'paid_bills' => $sessionBills,
                'pending' => $sessionPending,
                'cash' => round($sessionCash, 2),
                'card' => round($sessionCard, 2),
                'bank' => round($sessionBank, 2),
                'session_nos' => $labels !== [] ? implode(', ', $labels) : null,
                'cashiers' => $cashiers !== [] ? implode(', ', array_values(array_unique($cashiers))) : null,
            ],
            'today' => [
                'sales' => $todaySalesTotal,
                'paid_bills' => $todaySalesCount,
            ],
            'month' => [
                'income' => $cafeSalesMonth,
                'income_growth_pct' => $incomeGrowth,
                'restaurant_profit' => $cafeProfitMonth,
                'purchases' => round($purchasesMonth, 2),
                'expenses' => round($expensesMonth, 2),
            ],
            'outstanding_credit' => $outstandingCredit,
            'active_employees' => $activeEmployees,
            'products' => [
                'total' => $totalProducts,
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ],
        ]);
    }

    /**
     * Session IDs for admin bills — same window as POS floor (open + same business date).
     *
     * @return list<int>
     */
    private function currentOpenSessionIds(): array
    {
        $openQuery = PosSession::query();
        if (Schema::connection('tenant')->hasColumn('pos_sessions', 'status')) {
            $openQuery->where('status', 'open');
        } elseif (Schema::connection('tenant')->hasColumn('pos_sessions', 'closed_at')) {
            $openQuery->whereNull('closed_at');
        }

        $openSessions = $openQuery->orderByDesc('id')->get();
        if ($openSessions->isEmpty()) {
            return [];
        }

        $pending = app(PosPendingBillsService::class);
        $ids = [];
        foreach ($openSessions as $session) {
            $ids = array_merge($ids, $pending->billSessionIdsForSession($session));
            $ids[] = (int) $session->id;
        }

        // Always include every currently open session id.
        foreach ($openSessions as $session) {
            $ids[] = (int) $session->id;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids !== [] ? $ids : $openSessions->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderCard(PosOrder $o, bool $paid = false): array
    {
        $itemsCount = $o->relationLoaded('items')
            ? $o->items->sum(fn ($i) => (float) $i->qty)
            : null;

        return [
            'id' => (int) $o->id,
            'order_no' => (string) $o->order_no,
            'status' => (string) $o->status,
            'service_type' => (string) ($o->service_type ?? ''),
            'table' => $o->table?->name,
            'guest_name' => (string) ($o->guest_name ?? ''),
            'grand_total' => round((float) $o->grand_total, 2),
            'items_qty' => $itemsCount !== null ? round((float) $itemsCount, 3) : null,
            'time' => $paid
                ? optional($o->paid_at)?->timezone(config('app.timezone'))->format('H:i')
                : optional($o->updated_at)?->timezone(config('app.timezone'))->format('d M, H:i'),
        ];
    }
}
