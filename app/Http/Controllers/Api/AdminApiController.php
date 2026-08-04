<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosSession;
use App\Models\Setting;
use App\Support\LanServerUrl;
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
        $limit = min(100, max(10, (int) $request->input('limit', 50)));

        $orders = PosOrder::query()
            ->where('status', 'draft')
            ->with(['table:id,name', 'items:id,order_id,qty'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'orders' => $orders->map(fn (PosOrder $o) => $this->orderCard($o))->values(),
        ]);
    }

    public function paidOrders(Request $request): JsonResponse
    {
        $limit = min(100, max(10, (int) $request->input('limit', 40)));
        $date = $request->input('date'); // Y-m-d optional

        $q = PosOrder::query()
            ->where('status', 'paid')
            ->with(['table:id,name'])
            ->orderByDesc('paid_at');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $q->whereDate('paid_at', $date);
        } else {
            $q->where('paid_at', '>=', now()->startOfDay());
        }

        $orders = $q->limit($limit)->get();

        return response()->json([
            'orders' => $orders->map(fn (PosOrder $o) => $this->orderCard($o, true))->values(),
            'total' => round((float) $orders->sum('grand_total'), 2),
        ]);
    }

    public function kitchenVoids(Request $request): JsonResponse
    {
        $limit = min(200, max(10, (int) $request->input('limit', 80)));
        $since = now()->subDays(2)->startOfDay();

        $logs = ActivityLog::query()
            ->where('action', 'pos.kitchen_void')
            ->where('created_at', '>=', $since)
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
                'item' => $name,
                'qty' => $qty,
                'reason' => $reason,
                'by' => $log->user?->name ?? '—',
                'at' => optional($log->created_at)?->timezone(config('app.timezone'))->format('d M, H:i'),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'count' => $items->count(),
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
