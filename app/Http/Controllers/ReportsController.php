<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\ReportTemplate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryDepartment;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\InventoryProductStock;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosSession;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseVendor;
use App\Models\Setting;
use App\Services\PurchaseTotalsReconciler;
use App\Support\PosOrderMetrics;
use App\Services\NetworkPrinterService;
use App\Services\PosSessionSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReportsController extends Controller
{
    /* ──────────────────────────────────────────
     |  Reports Hub (index)
     ─────────────────────────────────────────── */
    public function index()
    {
        $currency = Setting::get('currency_symbol', 'Rs.');

        // KPI summary cards
        $totalSales     = PosOrder::where('status', 'paid')->sum('grand_total');
        $totalPurchases = PurchaseOrder::whereIn('status', ['confirmed', 'received'])->sum('grand_total');
        $totalExpenses  = Expense::whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])->sum('grand_total');
        $totalProducts  = InventoryProduct::where('active', true)->count();
        $totalEmployees = Employee::where('active', true)->count();

        // Sales last 7 days (for mini chart)
        $salesLast7 = PosOrder::where('status', 'paid')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(grand_total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $chartDays    = collect(range(6, 0))->map(fn($d) => now()->subDays($d)->format('Y-m-d'));
        $chartLabels  = $chartDays->map(fn($d) => date('D d', strtotime($d)));
        $chartSales   = $chartDays->map(fn($d) => (float) ($salesLast7[$d] ?? 0));

        return view('reports.index', compact(
            'currency', 'totalSales', 'totalPurchases', 'totalExpenses',
            'totalProducts', 'totalEmployees', 'chartLabels', 'chartSales'
        ));
    }

    /* ──────────────────────────────────────────
     |  POS Bills (register) — line list by date
     ─────────────────────────────────────────── */
    /**
     * Income / expense / sales / profit summary with POS (paid_at) + expenses (approved/paid).
     * Group: whole period, daily, weekly (ISO), or monthly buckets.
     */
    public function summary(Request $request)
    {
        $preset = $request->input('preset', 'this_month');
        $group = $request->input('group', 'summary');
        if (! in_array($group, ['summary', 'daily', 'weekly', 'monthly'], true)) {
            $group = 'summary';
        }

        $rangeRequest = Request::create('/', 'GET', array_merge($request->query(), [
            'preset' => $preset,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ]));
        [$from, $to] = $this->resolveDateRange($rangeRequest);

        $currency = Setting::get('currency_symbol', 'Rs.');

        $orders = PosOrder::query()
            ->where('status', 'paid')
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with([
                'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            ])
            ->orderByRaw('COALESCE(paid_at, created_at)')
            ->get();

        foreach ($orders as $order) {
            $order->setAttribute('gross_profit', PosOrderMetrics::grossProfitFromLoaded($order));
            $order->setAttribute('cogs_loaded', PosOrderMetrics::cogsFromLoaded($order));
        }

        $buckets = [];

        $touchBucket = static function (string $key, string $sortKey, string $label) use (&$buckets): void {
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'key' => $key,
                    'sort' => $sortKey,
                    'label' => $label,
                    'pos_bills' => 0,
                    'net_revenue' => 0.0,
                    'net_subtotal' => 0.0,
                    'discount' => 0.0,
                    'service_charge' => 0.0,
                    'tax' => 0.0,
                    'cogs' => 0.0,
                    'gross_profit' => 0.0,
                    'expense' => 0.0,
                ];
            }
        };

        foreach ($orders as $order) {
            $dt = Carbon::parse($order->paid_at ?? $order->created_at);
            [$key, $sortKey, $label] = $this->summaryBucketMeta($dt, $group, $from, $to);
            $touchBucket($key, $sortKey, $label);

            $sign = $order->type === 'refund' ? -1.0 : 1.0;
            $buckets[$key]['pos_bills']++;
            $buckets[$key]['net_revenue'] += $sign * (float) $order->grand_total;
            $buckets[$key]['net_subtotal'] += $sign * (float) $order->subtotal;
            $buckets[$key]['discount'] += $sign * (float) $order->discount_total;
            $buckets[$key]['service_charge'] += $sign * (float) ($order->service_charge_total ?? 0);
            $buckets[$key]['tax'] += $sign * (float) $order->tax_total;
            $buckets[$key]['cogs'] += (float) $order->cogs_loaded;
            $buckets[$key]['gross_profit'] += (float) $order->gross_profit;
        }

        $expenseQuery = Expense::query()
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->whereBetween('expense_date', [$from, $to]);

        foreach ($expenseQuery->cursor() as $exp) {
            $dt = Carbon::parse($exp->expense_date);
            [$key, $sortKey, $label] = $this->summaryBucketMeta($dt, $group, $from, $to);
            $touchBucket($key, $sortKey, $label);
            $buckets[$key]['expense'] += (float) $exp->grand_total;
        }

        if ($group === 'summary' && $buckets === []) {
            $touchBucket('all', '0', $from.' → '.$to);
        }

        $rows = collect($buckets)
            ->sortBy('sort')
            ->map(function (array $row) {
                $row['net_revenue'] = round($row['net_revenue'], 2);
                $row['net_subtotal'] = round($row['net_subtotal'], 2);
                $row['discount'] = round($row['discount'], 2);
                $row['service_charge'] = round($row['service_charge'], 2);
                $row['tax'] = round($row['tax'], 2);
                $row['cogs'] = round($row['cogs'], 2);
                // Gross profit from net POS income − COGS − service − discount.
                $row['gross_profit'] = round($row['net_revenue'] - $row['cogs'] - $row['service_charge'] - $row['discount'], 2);
                $row['expense'] = round($row['expense'], 2);
                $row['net_profit'] = round($row['gross_profit'] - $row['expense'], 2);

                return $row;
            })
            ->values()
            ->all();

        $totals = [
            'pos_bills' => (int) collect($rows)->sum('pos_bills'),
            'net_revenue' => round(collect($rows)->sum('net_revenue'), 2),
            'net_subtotal' => round(collect($rows)->sum('net_subtotal'), 2),
            'discount' => round(collect($rows)->sum('discount'), 2),
            'service_charge' => round(collect($rows)->sum('service_charge'), 2),
            'tax' => round(collect($rows)->sum('tax'), 2),
            'cogs' => round(collect($rows)->sum('cogs'), 2),
            'gross_profit' => round(collect($rows)->sum('gross_profit'), 2),
            'expense' => round(collect($rows)->sum('expense'), 2),
            'net_profit' => round(collect($rows)->sum('net_profit'), 2),
        ];

        $presetLabels = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This week',
            'last_week' => 'Last week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'last_quarter' => 'Last quarter',
            'this_year' => 'This year',
            'last_year' => 'Last year',
            'custom' => 'Custom range',
        ];

        return view('reports.summary', compact(
            'currency',
            'from',
            'to',
            'preset',
            'group',
            'rows',
            'totals',
            'presetLabels'
        ));
    }

    /**
     * Formal Profit & Loss statement — Total Sale, COGS, Gross Profit,
     * expense categories (from Expenses module), Profit Before Tax, Tax & Fine, Net P&L.
     */
    public function profitLoss(Request $request)
    {
        $preset = $request->input('preset', 'this_month');
        $rangeRequest = Request::create('/', 'GET', array_merge($request->query(), [
            'preset' => $preset,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ]));
        [$from, $to] = $this->resolveDateRange($rangeRequest);

        $currency = Setting::get('currency_symbol', 'Rs.');
        $companyName = Setting::get('company_name', config('app.name'));

        $orders = PosOrder::query()
            ->where('status', 'paid')
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with([
                'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            ])
            ->get();

        $totalSale = 0.0;
        $cogs = 0.0;
        $serviceCharges = 0.0;
        $discountTotal = 0.0;
        foreach ($orders as $order) {
            $sign = $order->type === 'refund' ? -1.0 : 1.0;
            $totalSale += $sign * (float) $order->grand_total;
            $cogs += (float) PosOrderMetrics::cogsFromLoaded($order);
            $serviceCharges += $sign * (float) ($order->service_charge_total ?? 0);
            $discountTotal += $sign * (float) ($order->discount_total ?? 0);
        }
        $totalSale = round($totalSale, 2);
        $cogs = round($cogs, 2);
        $serviceCharges = round($serviceCharges, 2);
        $discountTotal = round($discountTotal, 2);
        $grossProfit = round($totalSale - $cogs - $serviceCharges - $discountTotal, 2);

        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'active']);

        $expenseSums = Expense::query()
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('category_id, SUM(grand_total) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $operatingExpenses = [];
        $taxFineExpenses = [];

        $uncategorized = round((float) Expense::query()
            ->whereIn('status', [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
            ->whereBetween('expense_date', [$from, $to])
            ->whereNull('category_id')
            ->sum('grand_total'), 2);

        foreach ($categories as $cat) {
            $amount = round((float) ($expenseSums[$cat->id] ?? 0), 2);
            if (! $cat->active && $amount <= 0) {
                continue;
            }
            $row = [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'amount' => $amount,
            ];
            if ($this->isTaxFineExpenseCategory($cat->name)) {
                $taxFineExpenses[] = $row;
            } else {
                $operatingExpenses[] = $row;
            }
        }

        if ($uncategorized > 0) {
            $operatingExpenses[] = [
                'id' => null,
                'name' => 'Uncategorized',
                'description' => null,
                'amount' => $uncategorized,
            ];
        }

        $operatingTotal = round(collect($operatingExpenses)->sum('amount'), 2);
        $taxFineTotal = round(collect($taxFineExpenses)->sum('amount'), 2);
        $profitBeforeTax = round($grossProfit - $operatingTotal, 2);
        $netProfit = round($profitBeforeTax - $taxFineTotal, 2);

        $fromCarbon = Carbon::parse($from);
        $toCarbon = Carbon::parse($to);
        if ($fromCarbon->isSameMonth($toCarbon) && $fromCarbon->isSameYear($toCarbon)
            && $fromCarbon->day === 1
            && $toCarbon->day === $toCarbon->copy()->endOfMonth()->day) {
            $periodLabel = 'Month of '.$fromCarbon->format('F Y');
        } else {
            $periodLabel = $fromCarbon->format('d M Y').' — '.$toCarbon->format('d M Y');
        }

        $presetLabels = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This week',
            'last_week' => 'Last week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'last_quarter' => 'Last quarter',
            'this_year' => 'This year',
            'last_year' => 'Last year',
            'custom' => 'Custom range',
        ];

        return view('reports.profit-loss', compact(
            'currency',
            'companyName',
            'from',
            'to',
            'preset',
            'presetLabels',
            'periodLabel',
            'totalSale',
            'cogs',
            'serviceCharges',
            'discountTotal',
            'grossProfit',
            'operatingExpenses',
            'operatingTotal',
            'taxFineExpenses',
            'taxFineTotal',
            'profitBeforeTax',
            'netProfit'
        ));
    }

    private function isTaxFineExpenseCategory(string $name): bool
    {
        $n = strtolower($name);

        return str_contains($n, 'tax') || str_contains($n, 'fine');
    }

    /**
     * @return array{0:string,1:string,2:string} key, sort key, display label
     */
    private function summaryBucketMeta(Carbon $dt, string $group, string $from, string $to): array
    {
        if ($group === 'summary') {
            return ['all', '0', "{$from} → {$to}"];
        }

        if ($group === 'daily') {
            $key = $dt->format('Y-m-d');

            return [$key, $key, $dt->format('D, j M Y')];
        }

        if ($group === 'weekly') {
            $y = $dt->isoWeekYear();
            $w = $dt->isoWeek();
            $key = sprintf('%04d-W%02d', $y, $w);
            $sort = sprintf('%04d%02d', $y, $w);

            return [$key, $sort, "Week {$w}, {$y}"];
        }

        // monthly
        $key = $dt->format('Y-m');
        $sort = $dt->format('Ym');

        return [$key, $sort, $dt->format('F Y')];
    }

    public function posBills(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $type = $request->input('type', 'all');
        if (! in_array($type, ['all', 'sale', 'refund'], true)) {
            $type = 'all';
        }

        $currency = Setting::get('currency_symbol', 'Rs.');

        $q = PosOrder::query()
            ->with(['user:id,name'])
            ->where('status', 'paid')
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from.' 00:00:00', $to.' 23:59:59']);

        if ($type === 'sale') {
            $q->where('type', 'sale');
        } elseif ($type === 'refund') {
            $q->where('type', 'refund');
        }

        $orders = $q->orderByRaw('COALESCE(paid_at, created_at) DESC')->get();

        if ($orders->isNotEmpty()) {
            $orders->load([
                'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            ]);
            foreach ($orders as $order) {
                $order->setAttribute('gross_profit', PosOrderMetrics::grossProfitFromLoaded($order));
            }
        }

        $totalSubtotal = (float) $orders->sum('subtotal');
        $totalDiscount = (float) $orders->sum('discount_total');
        $totalTax = (float) $orders->sum('tax_total');
        $totalService = (float) $orders->sum('service_charge_total');
        $totalGrand = (float) $orders->sum('grand_total');
        $totalGrossProfit = round((float) $orders->sum('gross_profit'), 2);
        $billCount = $orders->count();

        return view('reports.pos-bills', compact(
            'orders',
            'from',
            'to',
            'type',
            'currency',
            'totalSubtotal',
            'totalDiscount',
            'totalTax',
            'totalService',
            'totalGrand',
            'totalGrossProfit',
            'billCount'
        ));
    }

    public function posSessions(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $currency = Setting::get('currency_symbol', 'Rs.');
        $summaryService = app(PosSessionSummaryService::class);

        $sessions = PosSession::query()
            ->with('user:id,name')
            ->where('status', 'closed')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('business_date', [$from, $to])
                    ->orWhereBetween('closed_at', [$from.' 00:00:00', $to.' 23:59:59']);
            })
            ->orderByDesc('closed_at')
            ->get();

        $rows = $sessions->map(function (PosSession $session) use ($summaryService) {
            $payload = $summaryService->summaryPayload($session);

            return [
                'session' => $session,
                'stats' => $payload['stats'],
                'amount_to_collect' => $payload['amount_to_collect'],
            ];
        });

        $totals = [
            'sessions' => $rows->count(),
            'gross_sales' => round($rows->sum(fn ($r) => $r['stats']['gross_sales_total'] ?? (
                (float) ($r['stats']['net_sales_total'] ?? 0) + (float) ($r['stats']['service_charge_total'] ?? 0)
            )), 2),
            'net_sales' => round($rows->sum(fn ($r) => $r['stats']['net_sales_total']), 2),
            'discount' => round($rows->sum(fn ($r) => $r['stats']['discount_total']), 2),
            'service_charge' => round($rows->sum(fn ($r) => $r['stats']['service_charge_total']), 2),
            'cash' => round($rows->sum(fn ($r) => $r['stats']['payments_cash']), 2),
            'bank' => round($rows->sum(fn ($r) => $r['stats']['payments_bank']), 2),
            'card' => round($rows->sum(fn ($r) => $r['stats']['payments_card']), 2),
        ];

        return view('reports.pos-sessions', compact('rows', 'from', 'to', 'currency', 'totals'));
    }

    /* ──────────────────────────────────────────
     |  Sales Report
     ─────────────────────────────────────────── */
    public function sales(Request $request)
    {
        $from     = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to       = $request->input('to', now()->format('Y-m-d'));
        $currency = Setting::get('currency_symbol', 'Rs.');

        $orders = PosOrder::with([
            'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            'user',
            'contact:id,name',
        ])
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($orders as $order) {
            $lineCost = 0.0;
            $lineGas = 0.0;
            $lineService = 0.0;

            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }
                $factor = $product->factorToBaseForUom((string) $item->uom);
                if ($factor === null || $factor <= 0) {
                    continue;
                }
                $qtyBase = (float) $item->qty * $factor;
                $lineCost += $qtyBase * (float) $product->cost;
                $lineGas += $qtyBase * (float) ($product->gas_charges ?? 0);
                $lineService += $qtyBase * (float) ($product->service_charges ?? 0);
            }

            $lineCost = round($lineCost, 2);
            $lineGas = round($lineGas, 2);
            $lineService = round($lineService, 2);
            $grossProfit = round((float) $order->subtotal - (float) $order->discount_total - $lineCost - $lineGas - $lineService, 2);
            $discountPercent = (float) $order->subtotal > 0
                ? round(((float) $order->discount_total / (float) $order->subtotal) * 100, 2)
                : 0.0;

            $order->setAttribute('cost_total', $lineCost);
            $order->setAttribute('gas_total', $lineGas);
            $order->setAttribute('service_total', $lineService);
            $order->setAttribute('gross_profit', $grossProfit);
            $order->setAttribute('discount_percent_effective', $discountPercent);
        }

        // KPIs
        $totalRevenue  = $orders->sum('grand_total');
        $totalDiscount = $orders->sum('discount_total');
        $ownerDiscountTotal = Schema::hasColumn('pos_orders', 'is_owner_discount')
            ? round((float) $orders->where('is_owner_discount', true)->sum('discount_total'), 2)
            : 0.0;
        $ownerDiscountCount = Schema::hasColumn('pos_orders', 'is_owner_discount')
            ? $orders->where('is_owner_discount', true)->count()
            : 0;
        $totalTax      = $orders->sum('tax_total');
        $totalGrossProfit = round((float) $orders->sum('gross_profit'), 2);
        $orderCount    = $orders->count();
        $avgOrder      = $orderCount ? $totalRevenue / $orderCount : 0;

        // Sales by service type (Dine-in / Takeaway / Delivery)
        $serviceTypeStats = collect(PosOrder::serviceTypeLabels())->map(function (string $label, string $key) use ($orders) {
            $group = $orders->filter(fn ($o) => $o->serviceTypeKey() === $key);

            return [
                'key' => $key,
                'label' => $label,
                'qty' => $group->count(),
                'revenue' => round((float) $group->sum('grand_total'), 2),
            ];
        })->values();

        // Daily chart
        $dailySales = PosOrder::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(grand_total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $chartLabels = $dailySales->pluck('day')->map(fn($d) => date('d M', strtotime($d)));
        $chartData   = $dailySales->pluck('total')->map(fn($v) => (float) $v);

        $voidCount = $this->salesVoidRowsForPeriod($from, $to)->count();

        return view('reports.sales', compact(
            'orders', 'from', 'to', 'currency',
            'totalRevenue', 'totalDiscount', 'totalTax', 'totalGrossProfit', 'orderCount', 'avgOrder',
            'ownerDiscountTotal', 'ownerDiscountCount',
            'serviceTypeStats', 'chartLabels', 'chartData',
            'voidCount'
        ));
    }

    /**
     * Void items / cancelled bills list (from Sales by order type).
     */
    public function salesVoids(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $currency = Setting::get('currency_symbol', 'Rs.');
        $voidRows = $this->salesVoidRowsForPeriod($from, $to);

        return view('reports.sales-voids', compact('voidRows', 'from', 'to', 'currency'));
    }

    /**
     * Void / cancelled-bill detail from activity log.
     */
    public function salesVoidShow(Request $request, ActivityLog $activityLog): View
    {
        abort_unless(in_array($activityLog->action, ['pos.kitchen_void', 'pos.order_cancelled'], true), 404);

        $activityLog->loadMissing(['user:id,name', 'subject']);
        $row = $this->salesVoidRowFromLog($activityLog);
        abort_unless($row !== null, 404);

        $currency = Setting::get('currency_symbol', 'Rs.');
        $from = $request->input('from');
        $to = $request->input('to');

        return view('reports.sales-void-show', [
            'log' => $activityLog,
            'row' => $row,
            'currency' => $currency,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Print void/cancel slip on CASHIER network printer (provisional style). */
    public function salesVoidCashierPrint(ActivityLog $activityLog): JsonResponse
    {
        abort_unless(in_array($activityLog->action, ['pos.kitchen_void', 'pos.order_cancelled'], true), 404);

        $activityLog->loadMissing(['user:id,name', 'subject']);
        $row = $this->salesVoidRowFromLog($activityLog);
        if ($row === null) {
            return response()->json(['ok' => false, 'message' => 'Void record nahi mili.'], 404);
        }

        $ip = trim((string) Setting::get('cashier_printer_ip', ''));
        if ($ip === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Cashier printer set nahi (Inventory → Kitchen Agents → CASHIER).',
            ]);
        }

        $settings = array_merge([
            'company_name' => config('app.name'),
            'currency_symbol' => 'Rs.',
        ], Setting::all_map());

        $printer = app(NetworkPrinterService::class);
        $payload = $printer->buildCancellationBillSlip(
            [
                'kind' => $row['kind'],
                'order_no' => $row['order_no'],
                'order_type' => $row['order_type'],
                'table' => $row['table'],
                'date' => $row['cancelled_at'],
                'cashier' => $row['cashier'],
                'cancelled_by' => $row['cancelled_by'],
                'reason' => $row['reason'],
            ],
            $row['items'],
            $settings
        );

        try {
            $printer->send($ip, (int) (Setting::get('cashier_printer_port', 9100) ?: 9100), $payload);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'message' => 'Cancel / void slip cashier printer pe bhej di.']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function salesVoidRowsForPeriod(string $from, string $to): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return collect();
        }

        $logs = ActivityLog::query()
            ->whereIn('action', ['pos.kitchen_void', 'pos.order_cancelled'])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with(['user:id,name', 'subject'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $billCancelOrderIds = [];
        foreach ($logs as $log) {
            if ($log->action !== 'pos.order_cancelled') {
                continue;
            }
            $props = is_array($log->properties) ? $log->properties : [];
            $oid = (int) ($props['order_id'] ?? $log->subject_id ?? 0);
            if ($oid > 0) {
                $billCancelOrderIds[$oid] = $log->created_at?->timestamp ?? 0;
            }
        }

        $rows = collect();
        foreach ($logs as $log) {
            // Skip item voids that belong to a whole-bill cancel (same order, ~2 min window).
            if ($log->action === 'pos.kitchen_void') {
                $props = is_array($log->properties) ? $log->properties : [];
                $oid = (int) ($props['order_id'] ?? $log->subject_id ?? 0);
                if ($oid > 0 && isset($billCancelOrderIds[$oid])) {
                    $cancelTs = $billCancelOrderIds[$oid];
                    $voidTs = $log->created_at?->timestamp ?? 0;
                    if (abs($voidTs - $cancelTs) <= 120) {
                        continue;
                    }
                }
            }

            $row = $this->salesVoidRowFromLog($log);
            if ($row !== null) {
                $rows->push($row);
            }
        }

        return $rows->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salesVoidRowFromLog(ActivityLog $log): ?array
    {
        $props = is_array($log->properties) ? $log->properties : [];
        /** @var PosOrder|null $order */
        $order = $log->subject instanceof PosOrder ? $log->subject : null;

        $orderNo = trim((string) ($order?->order_no ?? $props['order_no'] ?? ''));
        if ($orderNo === '') {
            $oid = (int) ($props['order_id'] ?? $log->subject_id ?? 0);
            $orderNo = $oid > 0 ? '#'.$oid : '—';
        }

        $cashier = trim((string) ($props['cashier_name'] ?? ''));
        if ($cashier === '') {
            $cashier = trim((string) ($order?->user?->name ?? ''));
        }
        if ($cashier === '' && $order) {
            $order->loadMissing('user:id,name');
            $cashier = trim((string) ($order->user?->name ?? ''));
        }

        $orderType = '';
        if ($order) {
            $orderType = (string) ($order->serviceTypeLabel() ?: '');
        } elseif (! empty($props['service_type'])) {
            $labels = PosOrder::serviceTypeLabels();
            $key = (string) $props['service_type'];
            $orderType = (string) ($labels[$key] ?? $key);
        }

        $table = trim((string) ($props['table_name'] ?? $order?->table?->name ?? ''));
        if ($table === '' && $order) {
            $order->loadMissing('table:id,name');
            $table = trim((string) ($order->table?->name ?? ''));
        }

        if ($log->action === 'pos.order_cancelled') {
            $reason = trim((string) ($props['reason'] ?? ''));
            $voids = is_array($props['voids'] ?? null) ? $props['voids'] : [];
            $items = [];
            foreach ($voids as $void) {
                if (! is_array($void)) {
                    continue;
                }
                $name = trim((string) ($void['name'] ?? $void['item_name'] ?? ''));
                if ($name === '') {
                    $name = 'Item';
                }
                $items[] = [
                    'name' => $name,
                    'qty' => (float) ($void['qty'] ?? 0),
                    'uom' => (string) ($void['uom'] ?? ''),
                    'reason' => (string) ($void['reason'] ?? $reason),
                ];
            }

            $detail = $items !== []
                ? count($items).' item(s) cancelled'
                : ('Complete bill'.(! empty($props['item_count']) ? ' ('.(int) $props['item_count'].' items)' : ''));

            return [
                'id' => (int) $log->id,
                'kind' => 'bill',
                'kind_label' => 'Bill Cancelled',
                'order_no' => $orderNo,
                'detail' => $detail,
                'reason' => $reason !== '' ? $reason : '—',
                'cancelled_by' => (string) ($log->user?->name ?? '—'),
                'cashier' => $cashier !== '' ? $cashier : '—',
                'cancelled_at' => $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                'order_type' => $orderType,
                'table' => $table,
                'items' => $items,
            ];
        }

        if ($log->action === 'pos.kitchen_void') {
            $void = is_array($props['void'] ?? null) ? $props['void'] : [];
            $name = trim((string) ($void['name'] ?? $void['item_name'] ?? ''));
            if ($name === '') {
                $name = 'Item';
            }
            $qty = (float) ($void['qty'] ?? 0);
            $uom = (string) ($void['uom'] ?? '');
            $reason = trim((string) ($void['reason'] ?? ''));
            $qtyLabel = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
            $detail = $qtyLabel.($uom !== '' ? ' '.$uom : '').' × '.$name;

            return [
                'id' => (int) $log->id,
                'kind' => 'item',
                'kind_label' => 'Item Void',
                'order_no' => $orderNo,
                'detail' => $detail,
                'reason' => $reason !== '' ? $reason : '—',
                'cancelled_by' => (string) ($log->user?->name ?? '—'),
                'cashier' => $cashier !== '' ? $cashier : '—',
                'cancelled_at' => $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                'order_type' => $orderType,
                'table' => $table,
                'items' => [[
                    'name' => $name,
                    'qty' => $qty,
                    'uom' => $uom,
                    'reason' => $reason,
                ]],
            ];
        }

        return null;
    }

    /**
     * List all paid bills for a service type in the selected period.
     */
    public function salesByService(Request $request, string $serviceType)
    {
        $labels = PosOrder::serviceTypeLabels();
        abort_unless(array_key_exists($serviceType, $labels), 404);

        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $currency = Setting::get('currency_symbol', 'Rs.');
        $label = $labels[$serviceType];

        $orders = PosOrder::with(['user:id,name', 'table:id,name', 'contact:id,name'])
            ->where('status', 'paid')
            ->where('service_type', $serviceType)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();

        return view('reports.sales-by-service', compact(
            'orders', 'from', 'to', 'currency', 'serviceType', 'label'
        ));
    }

    /**
     * View paid order line-item detail.
     */
    public function salesShow(PosOrder $order)
    {
        abort_unless($order->status === 'paid', 404);

        $order->load([
            'items.product:id,name,sku',
            'user:id,name',
            'table:id,name',
            'payments',
            'contact:id,name,phone',
            'session:id,session_no,business_date',
        ]);

        $currency = Setting::get('currency_symbol', 'Rs.');

        return view('reports.sales-show', compact('order', 'currency'));
    }

    /** Print paid sales-report bill on CASHIER network printer (ESC/POS). */
    public function salesCashierPrint(PosOrder $order): JsonResponse
    {
        abort_unless($order->status === 'paid', 404);

        $ip = trim((string) Setting::get('cashier_printer_ip', ''));
        if ($ip === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Cashier printer set nahi (Inventory → Kitchen Agents → CASHIER).',
            ]);
        }

        $order->load([
            'items.product:id,name,sku',
            'user:id,name',
            'table:id,name',
            'payments',
            'contact:id,name,phone',
        ]);

        $settings = array_merge([
            'company_name' => config('app.name'),
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_logo' => '',
            'currency_symbol' => 'Rs.',
        ], Setting::all_map());

        $logoPath = (string) ($settings['company_logo'] ?? '');
        $settings['company_logo_abs_path'] = company_logo_path($logoPath) ?? '';

        $printer = app(NetworkPrinterService::class);
        $payload = $printer->buildBillSlip($order, $settings);

        try {
            $printer->send($ip, (int) (Setting::get('cashier_printer_port', 9100) ?: 9100), $payload);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'message' => 'Bill cashier printer pe bhej diya.']);
    }

    /* ──────────────────────────────────────────
     |  Purchase Report
     ─────────────────────────────────────────── */
    public function purchases(Request $request)
    {
        $data = $this->purchaseReportData($request);

        return view('reports.purchases', $data);
    }

    public function purchasesPrint(Request $request)
    {
        $data = $this->purchaseReportData($request);

        return view('reports.purchases-print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseReportData(Request $request): array
    {
        $from     = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to       = $request->input('to', now()->format('Y-m-d'));
        $vendor   = $request->input('vendor');
        $status   = $request->input('status', '');
        $currency = Setting::get('currency_symbol', 'Rs.');

        app(PurchaseTotalsReconciler::class)->repairOnce();

        $query = PurchaseOrder::with(['vendor', 'creator', 'lines.product'])
            ->whereBetween('order_date', [$from, $to]);

        if ($vendor) {
            $query->where('vendor_id', $vendor);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->orderByDesc('order_date')->get();

        $totalAmount = $orders->sum('grand_total');
        $totalTax    = $orders->sum('tax_total');
        $totalDiscount = $orders->sum('discount_total');
        $orderCount  = $orders->count();

        $purchaseLines = PurchaseOrderLine::query()
            ->whereIn('purchase_order_id', $orders->pluck('id'))
            ->with([
                'product:id,sku,name,uom',
                'order:id,number,order_date,vendor_id,status',
                'order.vendor:id,name',
            ])
            ->get()
            ->sortByDesc(fn (PurchaseOrderLine $line) => $line->order?->order_date?->format('Y-m-d') . $line->order?->number);

        $byProduct = $purchaseLines
            ->groupBy(fn (PurchaseOrderLine $line) => $line->product_id ?: 'desc:' . $line->description)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'name'  => $first->product?->name ?? $first->description ?? 'Unknown',
                    'sku'   => $first->product?->sku ?? '—',
                    'uom'   => $first->uom ?: ($first->product?->uom ?? '—'),
                    'qty'   => round((float) $group->sum('qty'), 3),
                    'total' => round((float) $group->sum('total'), 2),
                    'lines' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $lineCount    = $purchaseLines->count();
        $productCount = $byProduct->count();

        $byVendor = $orders->groupBy('vendor_id')->map(fn ($group) => [
            'name'  => optional($group->first()->vendor)->name ?? 'Unknown',
            'count' => $group->count(),
            'total' => $group->sum('grand_total'),
        ])->sortByDesc('total');

        // Purchased products grouped per vendor, then by date (which day how much came in)
        $byVendorProducts = $purchaseLines
            ->groupBy(fn (PurchaseOrderLine $line) => $line->order?->vendor_id ?: 'none')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'vendor' => optional($first->order?->vendor)->name ?? 'Unknown',
                    'orders' => $group->pluck('purchase_order_id')->unique()->count(),
                    'qty'    => round((float) $group->sum('qty'), 3),
                    'total'  => round((float) $group->sum('total'), 2),
                    'lines'  => $group
                        ->sortBy(fn (PurchaseOrderLine $l) => $l->order?->order_date?->format('Y-m-d') . str_pad((string) ($l->order?->number ?? ''), 12, '0', STR_PAD_LEFT))
                        ->map(fn (PurchaseOrderLine $l) => [
                            'date'    => $l->order?->order_date,
                            'number'  => $l->order?->number,
                            'product' => $l->product?->name ?? $l->description ?? 'Unknown',
                            'sku'     => $l->product?->sku ?? '—',
                            'uom'     => $l->uom ?: ($l->product?->uom ?? '—'),
                            'qty'     => round((float) $l->qty, 3),
                            'total'   => round((float) $l->total, 2),
                        ])->values(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $vendors = PurchaseVendor::orderBy('name')->get(['id', 'name']);

        $chartLabels = $byVendor->pluck('name');
        $chartData   = $byVendor->pluck('total')->map(fn ($v) => (float) $v);

        $selectedVendor = $vendor ? $vendors->firstWhere('id', (int) $vendor) : null;
        $statusLabel    = $status ? ucfirst($status) : null;

        return compact(
            'orders', 'from', 'to', 'vendor', 'status', 'currency',
            'totalAmount', 'totalTax', 'totalDiscount', 'orderCount',
            'byVendor', 'byVendorProducts', 'vendors', 'chartLabels', 'chartData',
            'purchaseLines', 'byProduct', 'lineCount', 'productCount',
            'selectedVendor', 'statusLabel'
        );
    }

    /* ──────────────────────────────────────────
     |  Inventory Report
     ─────────────────────────────────────────── */
    public function inventory(Request $request)
    {
        $filter   = $request->input('filter', 'all'); // all | low | zero
        $departmentId = (int) $request->input('department_id', 0);
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $currency = Setting::get('currency_symbol', 'Rs.');

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->orderByDesc('is_warehouse')
            ->orderBy('name')
            ->get(['id', 'name', 'is_warehouse']);

        // Department stock: warehouse issue / kitchen stock rows — not only product↔department pivot.
        $applyDepartment = function ($query) use ($departmentId) {
            if ($departmentId === null) {
                return $query;
            }

            return $query->where(function ($q) use ($departmentId) {
                $q->whereHas(
                    'stocks',
                    fn ($s) => $s->where('department_id', $departmentId)->where('qty_on_hand', '>', 0)
                )->orWhereHas(
                    'departments',
                    fn ($dep) => $dep->where('inventory_departments.id', $departmentId)
                );
            });
        };

        // Ingredients / stock only — POS menu sell products hide.
        $applyIngredientsOnly = static function ($query) {
            return $query
                ->where('for_purchase', true)
                ->where(function ($q) {
                    $q->where('for_pos', false)->orWhereNull('for_pos');
                });
        };

        $query = InventoryProduct::with(['category', 'departments:id,name'])->where('active', true);
        $applyIngredientsOnly($query);
        $applyDepartment($query);

        if ($departmentId !== null) {
            $query->withSum(
                ['stocks as report_qty' => fn ($s) => $s->where('department_id', $departmentId)],
                'qty_on_hand'
            );

            if ($filter === 'low') {
                $query->whereHas(
                    'stocks',
                    fn ($s) => $s->where('department_id', $departmentId)
                        ->where('qty_on_hand', '>', 0)
                        ->where('qty_on_hand', '<=', 10)
                )->excludingActiveBomFinishedProducts();
            } elseif ($filter === 'zero') {
                $query->where(function ($q) use ($departmentId) {
                    $q->whereDoesntHave(
                        'stocks',
                        fn ($s) => $s->where('department_id', $departmentId)
                    )->orWhereHas(
                        'stocks',
                        fn ($s) => $s->where('department_id', $departmentId)->where('qty_on_hand', '<=', 0)
                    );
                })->excludingActiveBomFinishedProducts();
            }
        } else {
            if ($filter === 'low') {
                $query->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts();
            } elseif ($filter === 'zero') {
                $query->where('qty_on_hand', '<=', 0)->excludingActiveBomFinishedProducts();
            }
        }

        $products = $query->orderBy('name')->get();

        // Department selected → show that department's qty (issue stock), not master warehouse total.
        if ($departmentId !== null) {
            $products->each(function (InventoryProduct $p) {
                $p->setAttribute('qty_on_hand', round((float) ($p->report_qty ?? 0), 3));
            });
        }

        $kpiBase = InventoryProduct::where('active', true);
        $applyIngredientsOnly($kpiBase);
        $applyDepartment($kpiBase);

        if ($departmentId !== null) {
            $kpiBase->withSum(
                ['stocks as report_qty' => fn ($s) => $s->where('department_id', $departmentId)],
                'qty_on_hand'
            );
            $kpiProducts = (clone $kpiBase)->orderBy('name')->get()->each(function (InventoryProduct $p) {
                $p->setAttribute('qty_on_hand', round((float) ($p->report_qty ?? 0), 3));
            });
            $totalProducts = $kpiProducts->count();
            $lowStock = $kpiProducts->filter(fn (InventoryProduct $p) => $p->qty_on_hand > 0 && $p->qty_on_hand <= 10)->count();
            $outOfStock = $kpiProducts->filter(fn (InventoryProduct $p) => $p->qty_on_hand <= 0)->count();
            $totalValue = round((float) $kpiProducts->sum(fn (InventoryProduct $p) => (float) $p->qty_on_hand * (float) $p->cost), 2);
        } else {
            $totalProducts = (clone $kpiBase)->count();
            $lowStock      = (clone $kpiBase)->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts()->count();
            $outOfStock    = (clone $kpiBase)->where('qty_on_hand', '<=', 0)->count();
            $totalValue    = (clone $kpiBase)->selectRaw('SUM(qty_on_hand * cost) as val')->value('val') ?? 0;
        }

        $byCategory = $products
            ->groupBy(fn ($p) => optional($p->category)->name ?? 'Uncategorized')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($v) => $v);

        $chartLabels = $byCategory->keys();
        $chartData   = $byCategory->values();

        return view('reports.inventory', compact(
            'products', 'filter', 'currency',
            'departmentId', 'departments',
            'totalProducts', 'lowStock', 'outOfStock', 'totalValue',
            'chartLabels', 'chartData'
        ));
    }

    public function inventoryProductsPrint(Request $request)
    {
        $filter       = $request->input('filter', 'all');
        $departmentId = (int) $request->input('department_id', 0);
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $currency     = Setting::get('currency_symbol', 'Rs.');

        $department = $departmentId ? InventoryDepartment::find($departmentId) : null;

        $query = InventoryProduct::with('category')->where('active', true)
            ->where('for_purchase', true)
            ->where(function ($q) {
                $q->where('for_pos', false)->orWhereNull('for_pos');
            });

        if ($departmentId !== null) {
            $query->where(function ($q) use ($departmentId) {
                $q->whereHas(
                    'stocks',
                    fn ($s) => $s->where('department_id', $departmentId)->where('qty_on_hand', '>', 0)
                )->orWhereHas(
                    'departments',
                    fn ($dep) => $dep->where('inventory_departments.id', $departmentId)
                );
            });
            $query->withSum(
                ['stocks as report_qty' => fn ($s) => $s->where('department_id', $departmentId)],
                'qty_on_hand'
            );

            if ($filter === 'low') {
                $query->whereHas(
                    'stocks',
                    fn ($s) => $s->where('department_id', $departmentId)
                        ->where('qty_on_hand', '>', 0)
                        ->where('qty_on_hand', '<=', 10)
                )->excludingActiveBomFinishedProducts();
            } elseif ($filter === 'zero') {
                $query->where(function ($q) use ($departmentId) {
                    $q->whereDoesntHave(
                        'stocks',
                        fn ($s) => $s->where('department_id', $departmentId)
                    )->orWhereHas(
                        'stocks',
                        fn ($s) => $s->where('department_id', $departmentId)->where('qty_on_hand', '<=', 0)
                    );
                })->excludingActiveBomFinishedProducts();
            }
        } else {
            if ($filter === 'low') {
                $query->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts();
            } elseif ($filter === 'zero') {
                $query->where('qty_on_hand', '<=', 0)->excludingActiveBomFinishedProducts();
            }
        }

        $products = $query->orderBy('name')->get();

        if ($departmentId !== null) {
            $products->each(function (InventoryProduct $p) {
                $p->setAttribute('qty_on_hand', round((float) ($p->report_qty ?? 0), 3));
            });
        }

        $filterLabel = match ($filter) {
            'low'  => 'Low Stock (≤10)',
            'zero' => 'Out of Stock',
            default => 'Ingredients (stock)',
        };

        $totalValue = round((float) $products->sum(fn (InventoryProduct $p) => (float) $p->qty_on_hand * (float) $p->cost), 2);

        return view('reports.inventory-products-print', compact(
            'products', 'filter', 'filterLabel', 'department', 'currency', 'totalValue'
        ));
    }

    /* ──────────────────────────────────────────
     |  Issue Stock Report
     ─────────────────────────────────────────── */
    public function issueStock(Request $request)
    {
        $data = $this->issueStockReportData($request);

        return view('reports.issue-stock', $data);
    }

    public function issueStockPrint(Request $request)
    {
        $data = $this->issueStockReportData($request);

        return view('reports.issue-stock-print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function issueStockReportData(Request $request): array
    {
        $from         = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to           = $request->input('to', now()->format('Y-m-d'));
        $departmentId = (int) $request->input('department_id', 0);
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $currency     = Setting::get('currency_symbol', 'Rs.');

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->where('is_warehouse', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = InventoryMove::query()
            ->where('type', 'transfer')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with([
                'product:id,sku,name,uom,cost,gas_charges,extra_costs',
                'fromDepartment:id,name',
                'toDepartment:id,name',
                'user:id,name',
            ]);

        if ($departmentId !== null) {
            $query->where('to_department_id', $departmentId);
        }

        $issues = $query->orderByDesc('created_at')->get();

        $moveValue = fn (InventoryMove $issue) => $this->issueMoveValue($issue);
        $issues->each(function (InventoryMove $issue) use ($moveValue) {
            $issue->line_value = $moveValue($issue);
        });

        $issueCount    = $issues->count();
        $totalQty      = round((float) $issues->sum('qty'), 3);
        $totalValue    = round((float) $issues->sum($moveValue), 2);
        $departmentHit = $issues->pluck('to_department_id')->filter()->unique()->count();

        $byDay = $issues
            ->groupBy(fn (InventoryMove $issue) => $issue->created_at?->format('Y-m-d') ?? 'unknown')
            ->map(function ($group, $day) use ($moveValue) {
                return [
                    'date'  => $day,
                    'label' => $day !== 'unknown' ? Carbon::parse($day)->format('d M Y (D)') : 'Unknown',
                    'lines' => $group->count(),
                    'qty'   => round((float) $group->sum('qty'), 3),
                    'value' => round((float) $group->sum($moveValue), 2),
                ];
            })
            ->sortKeysDesc();

        $byDepartment = $issues
            ->groupBy('to_department_id')
            ->map(function ($group) use ($moveValue) {
                $first = $group->first();

                return [
                    'name'  => $first?->toDepartment?->name ?? 'Unknown',
                    'lines' => $group->count(),
                    'qty'   => round((float) $group->sum('qty'), 3),
                    'value' => round((float) $group->sum($moveValue), 2),
                ];
            })
            ->sortByDesc('value')
            ->values();

        $chartLabels = $byDay->pluck('label')->reverse()->values();
        $chartData   = $byDay->pluck('lines')->reverse()->values();

        $selectedDepartment = $departmentId
            ? $departments->firstWhere('id', $departmentId)
            : null;

        return compact(
            'issues', 'from', 'to', 'departmentId', 'departments', 'selectedDepartment', 'currency',
            'issueCount', 'totalQty', 'totalValue', 'departmentHit',
            'byDay', 'byDepartment', 'chartLabels', 'chartData'
        );
    }

    private function issueMoveValue(InventoryMove $issue): float
    {
        if ((float) $issue->total_cost > 0) {
            return (float) $issue->total_cost;
        }

        $unitCost = (float) ($issue->unit_cost ?? 0);
        if ($unitCost <= 0 && $issue->relationLoaded('product') && $issue->product) {
            $unitCost = (float) $issue->product->total;
        }

        return round((float) $issue->qty * $unitCost, 2);
    }

    /* ──────────────────────────────────────────
     |  Consumption Report (department / recipe)
     ─────────────────────────────────────────── */
    public function consumption(Request $request)
    {
        return view('reports.consumption', $this->consumptionReportData($request));
    }

    public function consumptionPrint(Request $request)
    {
        return view('reports.consumption-print', $this->consumptionReportData($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function consumptionReportData(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $departmentId = (int) $request->input('department_id', 0);
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $currency = Setting::get('currency_symbol', 'Rs.');

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->where('is_warehouse', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $deptMap = $departments->pluck('name', 'id');

        $items = PosOrderItem::query()
            ->whereHas('order', function ($q) use ($from, $to) {
                $q->where('status', 'paid')
                    ->where(function ($inner) {
                        $inner->where('type', 'sale')->orWhereNull('type');
                    })
                    ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
            })
            ->with([
                'order:id,created_at,status,type,order_no',
                'product' => fn ($q) => $q->with([
                    'department:id,name,is_warehouse',
                    'departments:id,name,is_warehouse',
                    'uomConversions' => fn ($c) => $c->where('active', true),
                ]),
            ])
            ->get();

        $recipeRows = [];
        foreach ($items as $item) {
            $product = $item->product;
            $deptId = $this->resolveOperatingDepartmentId($product);
            if ($deptId === null) {
                continue;
            }
            if ($departmentId !== null && $deptId !== $departmentId) {
                continue;
            }

            $day = $item->order?->created_at?->format('Y-m-d') ?? 'unknown';
            $productId = (int) ($item->product_id ?? 0);
            $key = $day.'|'.$deptId.'|'.$productId;

            $qty = (float) $item->qty;
            $saleAmount = (float) $item->total;
            $uom = (string) ($item->uom ?: ($product?->uom ?? ''));

            if (! isset($recipeRows[$key])) {
                $recipeRows[$key] = [
                    'date' => $day,
                    'date_label' => $day !== 'unknown' ? Carbon::parse($day)->format('d M Y (D)') : 'Unknown',
                    'department_id' => $deptId,
                    'department' => (string) ($deptMap[$deptId] ?? $product?->department?->name ?? 'Unknown'),
                    'product_id' => $productId,
                    'recipe' => (string) ($product?->name ?? '—'),
                    'sku' => (string) ($product?->sku ?? ''),
                    'uom' => $uom,
                    'qty' => 0.0,
                    'sale_amount' => 0.0,
                ];
            }

            $recipeRows[$key]['qty'] += $qty;
            $recipeRows[$key]['sale_amount'] += $saleAmount;
            if ($recipeRows[$key]['uom'] === '' && $uom !== '') {
                $recipeRows[$key]['uom'] = $uom;
            }
        }

        $recipeRows = collect($recipeRows)
            ->map(function (array $row) {
                $row['qty'] = round($row['qty'], 3);
                $row['sale_amount'] = round($row['sale_amount'], 2);

                return $row;
            })
            ->sortBy([
                ['date', 'desc'],
                ['department', 'asc'],
                ['recipe', 'asc'],
            ])
            ->values();

        $byDay = $recipeRows
            ->groupBy('date')
            ->map(function (Collection $group, string $day) {
                return [
                    'date' => $day,
                    'label' => $day !== 'unknown' ? Carbon::parse($day)->format('d M Y (D)') : 'Unknown',
                    'recipes' => $group->count(),
                    'qty' => round((float) $group->sum('qty'), 3),
                    'sale_amount' => round((float) $group->sum('sale_amount'), 2),
                ];
            })
            ->sortKeysDesc()
            ->values();

        $byDepartment = $recipeRows
            ->groupBy('department_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'department_id' => $first['department_id'] ?? null,
                    'name' => $first['department'] ?? 'Unknown',
                    'recipes' => $group->pluck('product_id')->unique()->count(),
                    'qty' => round((float) $group->sum('qty'), 3),
                    'sale_amount' => round((float) $group->sum('sale_amount'), 2),
                ];
            })
            ->sortByDesc('sale_amount')
            ->values();

        $stockQuery = InventoryProductStock::query()
            ->whereHas('department', fn ($q) => $q->where('is_warehouse', false)->where('active', true))
            ->with([
                'product:id,sku,name,uom,cost',
                'department:id,name,is_warehouse',
            ]);

        if ($departmentId !== null) {
            $stockQuery->where('department_id', $departmentId);
        }

        $stockRows = $stockQuery->get()
            ->map(function (InventoryProductStock $row) {
                $qty = (float) $row->qty_on_hand;
                $unitCost = (float) ($row->product?->cost ?? 0);
                $amount = round($qty * $unitCost, 2);

                return [
                    'department_id' => (int) $row->department_id,
                    'department' => (string) ($row->department?->name ?? 'Unknown'),
                    'product_id' => (int) $row->product_id,
                    'sku' => (string) ($row->product?->sku ?? ''),
                    'product' => (string) ($row->product?->name ?? '—'),
                    'uom' => (string) ($row->product?->uom ?? ''),
                    'qty' => round($qty, 3),
                    'unit_cost' => round($unitCost, 6),
                    'amount' => $amount,
                ];
            })
            ->filter(fn (array $row) => abs($row['qty']) > 0.0000001)
            ->sortBy([
                ['department', 'asc'],
                ['product', 'asc'],
            ])
            ->values();

        $stockByDepartment = $stockRows
            ->groupBy('department_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'name' => $first['department'] ?? 'Unknown',
                    'items' => $group->count(),
                    'qty' => round((float) $group->sum('qty'), 3),
                    'amount' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        // Actual ingredient consumption from recipe/BoM POS stock outs (+ refund returns netted).
        $ingredientMoveQuery = InventoryMove::query()
            ->where('note', 'like', '%(BoM)%')
            ->whereIn('type', ['out', 'in'])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with([
                'product:id,sku,name,uom,cost',
                'fromDepartment:id,name',
                'toDepartment:id,name',
            ]);

        if ($departmentId !== null) {
            $ingredientMoveQuery->where(function ($q) use ($departmentId) {
                $q->where(function ($out) use ($departmentId) {
                    $out->where('type', 'out')->where('from_department_id', $departmentId);
                })->orWhere(function ($inn) use ($departmentId) {
                    $inn->where('type', 'in')->where('to_department_id', $departmentId);
                });
            });
        }

        $ingredientRowsMap = [];
        foreach ($ingredientMoveQuery->orderBy('created_at')->get() as $move) {
            $isOut = $move->type === 'out';
            $deptId = $isOut
                ? (int) ($move->from_department_id ?? 0)
                : (int) ($move->to_department_id ?? 0);
            if ($deptId <= 0) {
                continue;
            }
            if ($departmentId !== null && $deptId !== $departmentId) {
                continue;
            }

            $day = $move->created_at?->format('Y-m-d') ?? 'unknown';
            $productId = (int) ($move->product_id ?? 0);
            $key = $day.'|'.$deptId.'|'.$productId;
            $sign = $isOut ? 1.0 : -1.0;
            $qty = $sign * (float) $move->qty;
            $cost = $sign * abs((float) ($move->total_cost ?? 0));
            if ($cost == 0.0 && $move->product) {
                $cost = $sign * abs((float) $move->qty * (float) ($move->product->cost ?? 0));
            }

            if (! isset($ingredientRowsMap[$key])) {
                $deptName = $isOut
                    ? (string) ($move->fromDepartment?->name ?? ($deptMap[$deptId] ?? 'Unknown'))
                    : (string) ($move->toDepartment?->name ?? ($deptMap[$deptId] ?? 'Unknown'));

                $ingredientRowsMap[$key] = [
                    'date' => $day,
                    'date_label' => $day !== 'unknown' ? Carbon::parse($day)->format('d M Y (D)') : 'Unknown',
                    'department_id' => $deptId,
                    'department' => $deptName,
                    'product_id' => $productId,
                    'ingredient' => (string) ($move->product?->name ?? '—'),
                    'sku' => (string) ($move->product?->sku ?? ''),
                    'uom' => (string) ($move->uom ?: ($move->product?->uom ?? '')),
                    'qty' => 0.0,
                    'amount' => 0.0,
                ];
            }

            $ingredientRowsMap[$key]['qty'] += $qty;
            $ingredientRowsMap[$key]['amount'] += $cost;
            if ($ingredientRowsMap[$key]['uom'] === '' && $move->uom) {
                $ingredientRowsMap[$key]['uom'] = (string) $move->uom;
            }
        }

        $ingredientRows = collect($ingredientRowsMap)
            ->map(function (array $row) {
                $row['qty'] = round($row['qty'], 3);
                $row['amount'] = round($row['amount'], 2);

                return $row;
            })
            ->filter(fn (array $row) => abs($row['qty']) > 0.0000001 || abs($row['amount']) > 0.0000001)
            ->sortBy([
                ['date', 'desc'],
                ['department', 'asc'],
                ['ingredient', 'asc'],
            ])
            ->values();

        $ingredientSummary = $ingredientRows
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'product_id' => $first['product_id'] ?? null,
                    'ingredient' => $first['ingredient'] ?? '—',
                    'sku' => $first['sku'] ?? '',
                    'uom' => $first['uom'] ?? '',
                    'qty' => round((float) $group->sum('qty'), 3),
                    'amount' => round((float) $group->sum('amount'), 2),
                    'departments' => $group->pluck('department')->unique()->values()->all(),
                ];
            })
            ->sortByDesc('qty')
            ->values();

        $totalSaleQty = round((float) $recipeRows->sum('qty'), 3);
        $totalSaleAmount = round((float) $recipeRows->sum('sale_amount'), 2);
        $totalStockAmount = round((float) $stockRows->sum('amount'), 2);
        $totalIngredientQty = round((float) $ingredientSummary->sum('qty'), 3);
        $totalIngredientAmount = round((float) $ingredientSummary->sum('amount'), 2);
        $departmentHit = $recipeRows->pluck('department_id')->unique()->count();
        $recipeHit = $recipeRows->pluck('product_id')->unique()->count();
        $ingredientHit = $ingredientSummary->count();

        $selectedDepartment = $departmentId
            ? $departments->firstWhere('id', $departmentId)
            : null;

        return compact(
            'from', 'to', 'departmentId', 'departments', 'selectedDepartment', 'currency',
            'recipeRows', 'byDay', 'byDepartment',
            'stockRows', 'stockByDepartment',
            'ingredientRows', 'ingredientSummary',
            'totalSaleQty', 'totalSaleAmount', 'totalStockAmount',
            'totalIngredientQty', 'totalIngredientAmount',
            'departmentHit', 'recipeHit', 'ingredientHit'
        );
    }

    private function resolveOperatingDepartmentId(?InventoryProduct $product): ?int
    {
        if (! $product) {
            return null;
        }

        $product->loadMissing(['department', 'departments']);
        $candidates = collect();

        if ($product->department_id && $product->department) {
            $candidates->push($product->department);
        }

        foreach ($product->departments as $dept) {
            if (! $candidates->contains('id', (int) $dept->id)) {
                $candidates->push($dept);
            }
        }

        $operating = $candidates->first(fn ($dept) => ! (bool) $dept->is_warehouse);

        return $operating ? (int) $operating->id : null;
    }

    /* ──────────────────────────────────────────
     |  Report Builder (UI)
     ─────────────────────────────────────────── */
    public function builder()
    {
        $vendors     = PurchaseVendor::orderBy('name')->get(['id','name']);
        $departments = EmployeeDepartment::orderBy('name')->get(['id','name']);
        $expCats     = ExpenseCategory::where('active',true)->orderBy('name')->get(['id','name']);
        $invCats     = InventoryCategory::orderBy('name')->get(['id','name']);
        $currency    = Setting::get('currency_symbol', 'Rs.');
        $templates   = Schema::hasTable('report_templates')
            ? ReportTemplate::orderBy('name')->get()
            : collect();

        return view('reports.builder', compact(
            'vendors','departments','expCats','invCats','currency','templates'
        ));
    }

    /* ──────────────────────────────────────────
     |  Template: list (JSON)
     ─────────────────────────────────────────── */
    public function templatesList()
    {
        if (! Schema::hasTable('report_templates')) {
            return response()->json([]);
        }

        $templates = ReportTemplate::orderBy('name')->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'report_type' => $t->report_type,
                'type_label'  => $t->typeLabel(),
                'type_color'  => $t->typeColor(),
                'preset'      => $t->preset,
                'cols'        => $t->cols,
                'filters'     => $t->filters ?? [],
                'created_by'  => $t->creator?->name,
                'created_at'  => $t->created_at->format('d M Y'),
            ]);
        return response()->json($templates);
    }

    /* ──────────────────────────────────────────
     |  Template: save
     ─────────────────────────────────────────── */
    public function templateSave(\Illuminate\Http\Request $request)
    {
        if (! Schema::hasTable('report_templates')) {
            return response()->json([
                'ok'    => false,
                'error' => 'Database table report_templates is missing. Run: php artisan migrate',
            ], 503);
        }

        $validated = $request->validate([
            'name'        => ['required','string','max:120'],
            'report_type' => ['required','in:sales,purchases,inventory,employees,expenses,credit'],
            'preset'      => ['required','string','max:30'],
            'cols'        => ['required','array','min:1'],
            'cols.*'      => ['string'],
            'filters'     => ['nullable','array'],
        ]);

        $template = ReportTemplate::create([
            'name'        => $validated['name'],
            'report_type' => $validated['report_type'],
            'preset'      => $validated['preset'],
            'cols'        => $validated['cols'],
            'filters'     => $validated['filters'] ?? [],
            'created_by'  => auth()->id(),
        ]);

        return response()->json([
            'ok'          => true,
            'id'          => $template->id,
            'name'        => $template->name,
            'report_type' => $template->report_type,
            'type_label'  => $template->typeLabel(),
            'type_color'  => $template->typeColor(),
            'preset'      => $template->preset,
            'cols'        => $template->cols,
            'filters'     => $template->filters ?? [],
            'created_at'  => $template->created_at->format('d M Y'),
        ]);
    }

    /* ──────────────────────────────────────────
     |  Template: delete
     ─────────────────────────────────────────── */
    public function templateDelete(ReportTemplate $template)
    {
        if (! Schema::hasTable('report_templates')) {
            return response()->json(['ok' => false], 503);
        }

        $template->delete();
        return response()->json(['ok' => true]);
    }

    /* ──────────────────────────────────────────
     |  Report Builder — JSON data endpoint
     ─────────────────────────────────────────── */
    public function data(Request $request)
    {
        $type = $request->input('type', 'sales');
        [$from, $to] = $this->resolveDateRange($request);
        $cols  = (array) $request->input('cols', []);

        $rows   = [];
        $totals = [];

        switch ($type) {
            case 'sales':       [$rows, $totals] = $this->dataSales($request, $from, $to, $cols);    break;
            case 'purchases':   [$rows, $totals] = $this->dataPurchases($request, $from, $to, $cols); break;
            case 'inventory':   [$rows, $totals] = $this->dataInventory($request, $cols);             break;
            case 'employees':   [$rows, $totals] = $this->dataEmployees($request, $cols);             break;
            case 'expenses':    [$rows, $totals] = $this->dataExpenses($request, $from, $to, $cols);  break;
            case 'credit':      [$rows, $totals] = $this->dataCredit($request, $cols);                break;
        }

        return response()->json([
            'rows'   => $rows,
            'totals' => $totals,
            'from'   => $from,
            'to'     => $to,
            'count'  => count($rows),
        ]);
    }

    /* ──────────────────────────────────────────
     |  Data helpers
     ─────────────────────────────────────────── */
    private function resolveDateRange(Request $request): array
    {
        $preset = $request->input('preset', 'this_month');
        $from   = $request->input('from');
        $to     = $request->input('to');

        if ($preset !== 'custom' || !$from) {
            [$from, $to] = match($preset) {
                'today'          => [now()->toDateString(), now()->toDateString()],
                'yesterday'      => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
                'this_week'      => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                'last_week'      => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
                'this_month'     => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                'last_month'     => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
                'this_quarter'   => [now()->startOfQuarter()->toDateString(), now()->endOfQuarter()->toDateString()],
                'last_quarter'   => [now()->subQuarter()->startOfQuarter()->toDateString(), now()->subQuarter()->endOfQuarter()->toDateString()],
                'this_year'      => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
                'last_year'      => [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
                default          => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            };
        }
        return [$from ?? now()->startOfMonth()->toDateString(), $to ?? now()->toDateString()];
    }

    private function dataSales(Request $r, string $from, string $to, array $cols): array
    {
        $q = PosOrder::with(['contact', 'user'])
            ->where('status','paid')
            ->whereBetween('created_at', ["$from 00:00:00", "$to 23:59:59"]);

        if ($r->filled('contact_id')) $q->where('contact_id', $r->contact_id);
        if ($r->filled('is_credit'))  $q->where('is_credit',  $r->is_credit === '1');

        $orders = $q->orderByDesc('created_at')->get();

        if ($orders->isNotEmpty()) {
            $orders->load([
                'items.product' => fn ($q) => $q->with(['uomConversions' => fn ($c) => $c->where('active', true)]),
            ]);
            foreach ($orders as $order) {
                $order->setAttribute('gross_profit', PosOrderMetrics::grossProfitFromLoaded($order));
            }
        }

        $allCols = [
            'order_no'    => 'Order #',
            'date'        => 'Date',
            'contact'     => 'Customer name',
            'cashier'     => 'Cashier',
            'subtotal'    => 'Subtotal',
            'discount'    => 'Discount',
            'tax'         => 'Tax',
            'gross_profit'=> 'Gross profit',
            'grand_total' => 'Grand Total',
            'is_credit'   => 'Credit',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $orders->map(function($o) use ($useCols) {
            $row = [];
            foreach ($useCols as $c) {
                $row[$c] = match($c) {
                    'order_no'    => $o->order_no,
                    'date'        => $o->created_at->format('d M Y H:i'),
                    'contact'     => $o->customerDisplayNameForReport(),
                    'cashier'     => $o->user?->name ?? '—',
                    'subtotal'    => fmt_num($o->subtotal, 2),
                    'discount'    => fmt_num($o->discount_total, 2),
                    'tax'         => fmt_num($o->tax_total, 2),
                    'gross_profit'=> fmt_num($o->gross_profit ?? 0, 2),
                    'grand_total' => fmt_num($o->grand_total, 2),
                    'is_credit'   => $o->is_credit ? 'Credit' : 'Cash',
                    default       => '',
                };
            }
            return $row;
        })->values()->all();

        return [$rows, [
            'Total Orders'   => $orders->count(),
            'Total Revenue'  => fmt_num($orders->sum('grand_total'), 2),
            'Total Discount' => fmt_num($orders->sum('discount_total'), 2),
            'Total Tax'      => fmt_num($orders->sum('tax_total'), 2),
            'Gross profit'   => fmt_num($orders->sum('gross_profit'), 2),
        ]];
    }

    private function dataPurchases(Request $r, string $from, string $to, array $cols): array
    {
        $q = PurchaseOrder::with(['vendor','creator'])
            ->whereBetween('order_date', [$from, $to]);

        if ($r->filled('vendor_id')) $q->where('vendor_id', $r->vendor_id);
        if ($r->filled('status'))    $q->where('status', $r->status);

        $orders = $q->orderByDesc('order_date')->get();

        $allCols = [
            'order_no'    => 'PO #',
            'date'        => 'Date',
            'vendor'      => 'Vendor',
            'creator'     => 'Created By',
            'subtotal'    => 'Subtotal',
            'tax'         => 'Tax',
            'grand_total' => 'Grand Total',
            'status'      => 'Status',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $orders->map(function($o) use ($useCols) {
            $row = [];
            foreach ($useCols as $c) {
                $row[$c] = match($c) {
                    'order_no'    => $o->order_no ?? "PO-{$o->id}",
                    'date'        => $o->order_date ? (is_string($o->order_date) ? $o->order_date : $o->order_date->format('d M Y')) : '—',
                    'vendor'      => $o->vendor?->name ?? '—',
                    'creator'     => $o->creator?->name ?? '—',
                    'subtotal'    => fmt_num($o->subtotal ?? 0, 2),
                    'tax'         => fmt_num($o->tax_total ?? 0, 2),
                    'grand_total' => fmt_num($o->grand_total ?? 0, 2),
                    'status'      => ucfirst($o->status),
                    default       => '',
                };
            }
            return $row;
        })->values()->all();

        return [$rows, [
            'Total Orders' => $orders->count(),
            'Total Spend'  => fmt_num($orders->sum('grand_total'), 2),
            'Total Tax'    => fmt_num($orders->sum('tax_total'), 2),
        ]];
    }

    private function dataInventory(Request $r, array $cols): array
    {
        $q = InventoryProduct::with('category')->where('active', true);

        if ($r->filled('category_id'))   $q->where('category_id', $r->category_id);
        if ($r->input('stock') === 'low')  $q->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10);
        if ($r->input('stock') === 'zero') $q->where('qty_on_hand', '<=', 0);
        if ($r->input('stock') === 'in')   $q->where('qty_on_hand', '>', 0);

        $products = $q->orderBy('name')->get();

        $allCols = [
            'sku'        => 'SKU',
            'name'       => 'Product',
            'category'   => 'Category',
            'uom'        => 'UOM',
            'qty'        => 'Stock Qty',
            'cost'       => 'Cost',
            'price'      => 'Sale Price',
            'unit_profit'=> 'Unit profit',
            'margin_pct' => 'Margin %',
            'cost_value' => 'Stock Value (Cost)',
            'sale_value' => 'Stock Value (Sale)',
            'stock_profit' => 'Stock profit (if sold)',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $products->map(function($p) use ($useCols) {
            $unitProfit = (float) ($p->price ?? 0) - (float) ($p->cost ?? 0);
            $marginPct = (float) ($p->price ?? 0) > 0
                ? round(100 * $unitProfit / (float) $p->price, 2)
                : 0.0;
            $stockProfit = (float) $p->qty_on_hand * $unitProfit;
            $row = [];
            foreach ($useCols as $c) {
                $row[$c] = match($c) {
                    'sku'        => $p->sku ?? '—',
                    'name'       => $p->name,
                    'category'   => $p->category?->name ?? '—',
                    'uom'        => $p->uom,
                    'qty'        => fmt_num($p->qty_on_hand, 3),
                    'cost'       => fmt_num($p->cost ?? 0, 2),
                    'price'      => fmt_num($p->price ?? 0, 2),
                    'unit_profit'=> fmt_num($unitProfit, 2),
                    'margin_pct' => fmt_num($marginPct, 2),
                    'cost_value' => fmt_num((float)$p->qty_on_hand * (float)($p->cost ?? 0), 2),
                    'sale_value' => fmt_num((float)$p->qty_on_hand * (float)($p->price ?? 0), 2),
                    'stock_profit' => fmt_num($stockProfit, 2),
                    default      => '',
                };
            }
            return $row;
        })->values()->all();

        $sumStockProfit = $products->sum(fn ($p) => (float) $p->qty_on_hand * ((float) ($p->price ?? 0) - (float) ($p->cost ?? 0)));

        return [$rows, [
            'Total Products'  => $products->count(),
            'Total Qty'       => fmt_num($products->sum('qty_on_hand'), 2),
            'Stock Value'     => fmt_num($products->sum(fn($p) => (float)$p->qty_on_hand * (float)($p->cost ?? 0)), 2),
            'Retail Value'    => fmt_num($products->sum(fn($p) => (float)$p->qty_on_hand * (float)($p->price ?? 0)), 2),
            'Potential profit'=> fmt_num($sumStockProfit, 2),
        ]];
    }

    private function dataEmployees(Request $r, array $cols): array
    {
        $q = Employee::with(['department','designation','user']);
        if ($r->filled('department_id')) $q->where('department_id', $r->department_id);
        if ($r->input('status') === 'active')   $q->where('active', true);
        if ($r->input('status') === 'inactive') $q->where('active', false);

        $employees = $q->orderBy('name')->get();

        $allCols = [
            'employee_no'  => 'Emp #',
            'name'         => 'Name',
            'department'   => 'Department',
            'designation'  => 'Designation',
            'phone'        => 'Phone',
            'email'        => 'Email',
            'join_date'    => 'Join Date',
            'salary'       => 'Salary',
            'status'       => 'Status',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $employees->map(function($e) use ($useCols) {
            $row = [];
            foreach ($useCols as $c) {
                $row[$c] = match($c) {
                    'employee_no' => $e->employee_no ?? "EMP-{$e->id}",
                    'name'        => $e->name,
                    'department'  => $e->department?->name ?? '—',
                    'designation' => $e->designation?->name ?? '—',
                    'phone'       => $e->phone ?? '—',
                    'email'       => $e->user?->email ?? '—',
                    'join_date'   => $e->join_date ? $e->join_date->format('d M Y') : '—',
                    'salary'      => fmt_num($e->salary ?? 0, 2),
                    'status'      => $e->active ? 'Active' : 'Inactive',
                    default       => '',
                };
            }
            return $row;
        })->values()->all();

        return [$rows, [
            'Total Employees'   => $employees->count(),
            'Active'            => $employees->where('active', true)->count(),
            'Monthly Payroll'   => fmt_num($employees->where('active', true)->sum('salary'), 2),
        ]];
    }

    private function dataExpenses(Request $r, string $from, string $to, array $cols): array
    {
        $q = Expense::with(['employee','category','approvedBy'])
            ->whereBetween('expense_date', [$from, $to]);

        if ($r->filled('employee_id'))  $q->where('employee_id', $r->employee_id);
        if ($r->filled('category_id'))  $q->where('category_id', $r->category_id);
        if ($r->filled('status'))       $q->where('status', $r->status);

        $expenses = $q->orderByDesc('expense_date')->get();

        $allCols = [
            'date'        => 'Date',
            'employee'    => 'Employee',
            'category'    => 'Category',
            'description' => 'Description',
            'qty'         => 'Qty',
            'unit_amount' => 'Unit Cost',
            'total'       => 'Subtotal',
            'tax'         => 'Tax',
            'grand_total' => 'Grand Total',
            'status'      => 'Status',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $expenses->map(function($e) use ($useCols) {
            $row = [];
            foreach ($useCols as $c) {
                $row[$c] = match($c) {
                    'date'        => $e->expense_date->format('d M Y'),
                    'employee'    => $e->employee?->name ?? '—',
                    'category'    => $e->category?->name ?? '—',
                    'description' => $e->description,
                    'qty'         => fmt_num($e->qty, 3),
                    'unit_amount' => fmt_num($e->unit_amount, 2),
                    'total'       => fmt_num($e->total_amount, 2),
                    'tax'         => fmt_num($e->tax_amount, 2),
                    'grand_total' => fmt_num($e->grand_total, 2),
                    'status'      => ucfirst($e->status),
                    default       => '',
                };
            }
            return $row;
        })->values()->all();

        return [$rows, [
            'Total Expenses' => $expenses->count(),
            'Total Amount'   => fmt_num($expenses->sum('grand_total'), 2),
            'Approved'       => $expenses->whereIn('status', ['approved','paid'])->count(),
        ]];
    }

    private function dataCredit(Request $r, array $cols): array
    {
        $q = Contact::with('creditLedger')->where('active', true);
        if ($r->filled('search')) {
            $s = '%'.$r->search.'%';
            $q->where(fn($w) => $w->where('name','like',$s)->orWhere('phone','like',$s));
        }

        $contacts = $q->withSum(['creditLedger as tc' => fn($x) => $x->where('type','credit')], 'amount')
                      ->withSum(['creditLedger as tp' => fn($x) => $x->where('type','payment')], 'amount')
                      ->orderBy('name')
                      ->get();

        $allCols = [
            'name'      => 'Contact Name',
            'phone'     => 'Phone',
            'city'      => 'City',
            'credit'    => 'Total Credit',
            'paid'      => 'Total Paid',
            'balance'   => 'Balance Due',
        ];
        $useCols = $cols ?: array_keys($allCols);

        $rows = $contacts->map(function($c) use ($useCols) {
            $credit  = (float)($c->tc ?? 0);
            $paid    = (float)($c->tp ?? 0);
            $balance = round($credit - $paid, 2);
            $row = [];
            foreach ($useCols as $col) {
                $row[$col] = match($col) {
                    'name'    => $c->name,
                    'phone'   => $c->phone ?? '—',
                    'city'    => $c->city ?? '—',
                    'credit'  => fmt_num($credit, 2),
                    'paid'    => fmt_num($paid, 2),
                    'balance' => fmt_num($balance, 2),
                    default   => '',
                };
            }
            return $row;
        })->values()->all();

        $totalBalance = $contacts->sum(fn($c) => (float)($c->tc ?? 0) - (float)($c->tp ?? 0));

        return [$rows, [
            'Total Contacts' => $contacts->count(),
            'Outstanding'    => fmt_num($totalBalance, 2),
        ]];
    }

    /* ──────────────────────────────────────────
     |  Employee Report
     ─────────────────────────────────────────── */
    public function employees(Request $request)
    {
        $dept     = $request->input('dept');
        $status   = $request->input('status', 'active');
        $currency = Setting::get('currency_symbol', 'Rs.');

        $query = Employee::with(['department', 'designation', 'user']);

        if ($dept)             $query->where('department_id', $dept);
        if ($status === 'active')   $query->where('active', true);
        if ($status === 'inactive') $query->where('active', false);

        $employees = $query->orderBy('name')->get();

        $totalSalary   = $employees->sum('salary');
        $activeCount   = $employees->where('active', true)->count();
        $inactiveCount = $employees->where('active', false)->count();

        // Salary by department chart
        $byDept = $employees->groupBy(fn($e) => optional($e->department)->name ?? 'No Dept')
            ->map(fn($g) => (float) $g->sum('salary'))
            ->sortByDesc(fn($v) => $v);

        $chartLabels = $byDept->keys();
        $chartData   = $byDept->values();

        $departments = \App\Models\EmployeeDepartment::orderBy('name')->get(['id', 'name']);

        return view('reports.employees', compact(
            'employees', 'dept', 'status', 'currency',
            'totalSalary', 'activeCount', 'inactiveCount',
            'byDept', 'chartLabels', 'chartData', 'departments'
        ));
    }
}
