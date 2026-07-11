<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\ReportTemplate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryDepartment;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVendor;
use App\Models\Setting;
use App\Support\PosOrderMetrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'currency', 'totalSales', 'totalPurchases',
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
                $row['tax'] = round($row['tax'], 2);
                $row['cogs'] = round($row['cogs'], 2);
                $row['gross_profit'] = round($row['gross_profit'], 2);
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
            'totalGrand',
            'totalGrossProfit',
            'billCount'
        ));
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
        $totalTax      = $orders->sum('tax_total');
        $totalGrossProfit = round((float) $orders->sum('gross_profit'), 2);
        $orderCount    = $orders->count();
        $avgOrder      = $orderCount ? $totalRevenue / $orderCount : 0;

        // Top products
        $topProducts = PosOrderItem::with('product')
            ->whereHas('order', fn($q) => $q->where('status', 'paid')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->selectRaw('product_id, SUM(qty) as total_qty, SUM(total) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Daily chart
        $dailySales = PosOrder::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(grand_total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $chartLabels = $dailySales->pluck('day')->map(fn($d) => date('d M', strtotime($d)));
        $chartData   = $dailySales->pluck('total')->map(fn($v) => (float) $v);

        return view('reports.sales', compact(
            'orders', 'from', 'to', 'currency',
            'totalRevenue', 'totalDiscount', 'totalTax', 'totalGrossProfit', 'orderCount', 'avgOrder',
            'topProducts', 'chartLabels', 'chartData'
        ));
    }

    /* ──────────────────────────────────────────
     |  Purchase Report
     ─────────────────────────────────────────── */
    public function purchases(Request $request)
    {
        $from     = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to       = $request->input('to', now()->format('Y-m-d'));
        $vendor   = $request->input('vendor');
        $status   = $request->input('status', '');
        $currency = Setting::get('currency_symbol', 'Rs.');

        $query = PurchaseOrder::with(['vendor', 'creator'])
            ->whereBetween('order_date', [$from, $to]);

        if ($vendor) $query->where('vendor_id', $vendor);
        if ($status) $query->where('status', $status);

        $orders = $query->orderByDesc('order_date')->get();

        $totalAmount = $orders->sum('grand_total');
        $totalTax    = $orders->sum('tax_total');
        $orderCount  = $orders->count();

        // By vendor breakdown
        $byVendor = $orders->groupBy('vendor_id')->map(fn($group) => [
            'name'  => optional($group->first()->vendor)->name ?? 'Unknown',
            'count' => $group->count(),
            'total' => $group->sum('grand_total'),
        ])->sortByDesc('total');

        $vendors  = PurchaseVendor::orderBy('name')->get(['id', 'name']);

        $chartLabels = $byVendor->pluck('name');
        $chartData   = $byVendor->pluck('total')->map(fn($v) => (float) $v);

        return view('reports.purchases', compact(
            'orders', 'from', 'to', 'vendor', 'status', 'currency',
            'totalAmount', 'totalTax', 'orderCount',
            'byVendor', 'vendors', 'chartLabels', 'chartData'
        ));
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

        $applyDepartment = function ($query) use ($departmentId) {
            if ($departmentId !== null) {
                $query->whereHas(
                    'departments',
                    fn ($dep) => $dep->where('inventory_departments.id', $departmentId)
                );
            }

            return $query;
        };

        $query = InventoryProduct::with(['category', 'departments:id,name'])->where('active', true);
        $applyDepartment($query);

        if ($filter === 'low') {
            $query->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts();
        } elseif ($filter === 'zero') {
            $query->where('qty_on_hand', '<=', 0)->excludingActiveBomFinishedProducts();
        }

        $products = $query->orderBy('name')->get();

        $stockPotentialProfit = round((float) $products->sum(function (InventoryProduct $p) {
            return (float) $p->qty_on_hand * ((float) $p->price - (float) $p->cost);
        }), 2);

        $kpiBase = InventoryProduct::where('active', true);
        $applyDepartment($kpiBase);

        $totalProducts = (clone $kpiBase)->count();
        $lowStock      = (clone $kpiBase)->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts()->count();
        $outOfStock    = (clone $kpiBase)->where('qty_on_hand', '<=', 0)->count();
        $totalValue    = (clone $kpiBase)->selectRaw('SUM(qty_on_hand * cost) as val')->value('val') ?? 0;
        $retailValue   = (clone $kpiBase)->selectRaw('SUM(qty_on_hand * price) as val')->value('val') ?? 0;

        $byCategory = $products
            ->groupBy(fn ($p) => optional($p->category)->name ?? 'Uncategorized')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($v) => $v);

        $chartLabels = $byCategory->keys();
        $chartData   = $byCategory->values();

        return view('reports.inventory', compact(
            'products', 'filter', 'currency',
            'departmentId', 'departments',
            'totalProducts', 'lowStock', 'outOfStock', 'totalValue', 'retailValue',
            'stockPotentialProfit', 'chartLabels', 'chartData'
        ));
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
