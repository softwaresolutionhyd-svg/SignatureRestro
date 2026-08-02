<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryProduct;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosSession;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Services\PosSessionSummaryService;
use App\Support\PosOrderMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends Controller
{
    public function index(PosSessionSummaryService $sessionSummary)
    {
        $currency = Setting::get('currency_symbol', 'Rs.');
        $company  = Setting::get('company_name', config('app.name'));

        /* ── Current open POS session sale(s) — today only (matches POS Closing) ── */
        $openSessions = collect();
        $businessDate = now()->toDateString();
        try {
            $openQuery = PosSession::query()
                ->with('user:id,name')
                ->where('status', 'open')
                ->where(function ($q) use ($businessDate) {
                    $q->whereDate('business_date', $businessDate)
                        ->orWhere(function ($q2) use ($businessDate) {
                            $q2->whereNull('business_date')
                                ->whereDate('opened_at', $businessDate);
                        });
                });

            if (Schema::connection('tenant')->hasColumn('pos_sessions', 'shift_started')) {
                $openQuery->where('shift_started', true);
            }

            $openSessions = $openQuery->orderByDesc('id')->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $currentSessionSale = 0.0;
        $currentSessionBills = 0;
        $currentSessionCash = 0.0;
        $currentSessionCard = 0.0;
        $currentSessionBank = 0.0;
        $currentSessionPending = 0;
        $currentSessionCashiers = [];
        $currentSessionLabels = [];

        foreach ($openSessions as $session) {
            try {
                $stats = $sessionSummary->stats($session);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            $currentSessionSale += (float) ($stats['net_sales_total'] ?? 0);
            $currentSessionBills += (int) ($stats['sales_count'] ?? 0);
            $currentSessionCash += (float) ($stats['payments_cash'] ?? 0);
            $currentSessionCard += (float) ($stats['payments_card'] ?? 0);
            $currentSessionBank += (float) ($stats['payments_bank'] ?? 0);
            $currentSessionPending += (int) ($stats['held_count'] ?? 0);
            if ($session->user?->name) {
                $currentSessionCashiers[] = $session->user->name;
            }
            $currentSessionLabels[] = $session->session_no ?: ('#'.$session->id);
        }

        $currentSessionSale = round($currentSessionSale, 2);
        $currentSessionCash = round($currentSessionCash, 2);
        $currentSessionCard = round($currentSessionCard, 2);
        $currentSessionBank = round($currentSessionBank, 2);
        $currentSessionOpen = $openSessions->isNotEmpty();
        $currentSessionCashierLabel = $currentSessionCashiers !== []
            ? implode(', ', array_values(array_unique($currentSessionCashiers)))
            : null;
        $currentSessionNoLabel = $currentSessionLabels !== []
            ? implode(', ', $currentSessionLabels)
            : null;

        $todayPaidOrders = PosOrder::query()
            ->where('status', 'paid')
            ->whereDate('created_at', $businessDate)
            ->get(['id', 'type', 'grand_total', 'status']);
        $todaySalesCount = $todayPaidOrders->where('type', 'sale')->count();
        $todaySalesTotal = round($todayPaidOrders->sum(fn (PosOrder $o) => PosOrderMetrics::signedGrandTotal($o)), 2);

        /* ── KPI cards ────────────────────────────────── */
        $today            = now()->toDateString();
        $monthStart       = now()->startOfMonth()->toDateString();
        $monthEnd         = now()->endOfMonth()->toDateString();
        $lastMonthStart   = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd     = now()->subMonth()->endOfMonth()->toDateString();

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

        $incomeThisMonth = $cafeSalesMonth;

        $cafeSalesLastMonth = round((float) PosOrder::where('status', 'paid')
            ->whereBetween('created_at', [$lastMonthStartTs, $lastMonthEndTs])
            ->get()
            ->sum(fn (PosOrder $o) => PosOrderMetrics::signedGrandTotal($o)), 2);
        $incomeLastMonth = $cafeSalesLastMonth;
        $incomeGrowth = $incomeLastMonth > 0
            ? round((($incomeThisMonth - $incomeLastMonth) / $incomeLastMonth) * 100, 1)
            : 0;

        $salesThisMonth = $cafeSalesMonth;

        $purchasesMonth    = PurchaseOrder::whereIn('status',['confirmed','received'])->whereBetween('order_date', [$monthStart, $monthEnd])->sum('grand_total');
        $expensesMonth     = Expense::whereIn('status',['approved','paid'])->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('grand_total');

        $activeEmployees   = Employee::query()->excludeAdminAccounts()->where('active', true)->count();
        $totalProducts     = InventoryProduct::where('active', true)->count();
        $lowStock          = InventoryProduct::where('active', true)->where('for_purchase', true)->where('qty_on_hand', '>', 0)->where('qty_on_hand', '<=', 10)->excludingActiveBomFinishedProducts()->count();
        $outOfStock        = InventoryProduct::where('active', true)->where('for_purchase', true)->where('qty_on_hand', '<=', 0)->count();

        // Same outstanding logic as Credit Book (valid contacts only; company-scoped)
        try {
            CreditLedger::query()
                ->whereNotNull('contact_id')
                ->whereNotIn('contact_id', Contact::query()->select('id'))
                ->delete();
        } catch (\Throwable $e) {
            report($e);
        }
        $outstandingCredit = round((float) CreditLedger::query()
            ->whereIn('contact_id', Contact::query()->select('id'))
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END), 0) as bal', ['credit', 'payment'])
            ->value('bal'), 2);
        if ($outstandingCredit < 0) {
            $outstandingCredit = 0.0;
        }

        /* ── 1. Daily sales – last 30 days (area chart) ── */
        $raw30 = PosOrder::where('status','paid')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(grand_total) as total, COUNT(*) as orders')
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $days30      = collect(range(29, 0))->map(fn($d) => now()->subDays($d)->format('Y-m-d'));
        $sales30Lbl  = $days30->map(fn($d) => date('d M', strtotime($d)));
        $sales30Val  = $days30->map(fn($d) => (float)($raw30[$d]->total ?? 0));
        $orders30Val = $days30->map(fn($d) => (int)($raw30[$d]->orders ?? 0));

        /* ── 2. Monthly Sales vs Purchases (last 12 months) ── */
        $months12 = collect(range(11, 0))->map(fn($i) => now()->subMonths($i));
        $monthly12Lbl = $months12->map(fn($m) => $m->format('M Y'));

        $monthlySalesRaw = PosOrder::where('status','paid')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mo, SUM(grand_total) as total")
            ->groupBy('mo')->pluck('total','mo');

        $monthlyPurchRaw = PurchaseOrder::whereIn('status',['confirmed','received'])
            ->where('order_date', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as mo, SUM(grand_total) as total")
            ->groupBy('mo')->pluck('total','mo');

        $monthly12Sales = $months12->map(fn($m) => (float)($monthlySalesRaw[$m->format('Y-m')] ?? 0));
        $monthly12Purch = $months12->map(fn($m) => (float)($monthlyPurchRaw[$m->format('Y-m')] ?? 0));

        /* ── 3. Top 10 products by revenue (last 30 days) ── */
        $topProducts = PosOrderItem::topSellingGrouped(
            fn ($q) => $q->where('status', 'paid')
                ->where('created_at', '>=', now()->subDays(29)->startOfDay()),
            10
        );

        $topProdLbl = $topProducts->map(fn ($p) => $p->display_name ?? $p->product?->name ?? 'Unknown');
        $topProdRev = $topProducts->map(fn ($p) => (float) $p->total_revenue);
        $topProdQty = $topProducts->map(fn ($p) => (float) $p->total_qty);

        /* ── 4. Expense by category ── */
        $expByCat = Expense::with('category')
            ->whereIn('status',['approved','paid'])
            ->selectRaw('category_id, SUM(grand_total) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $expCatLbl = $expByCat->map(fn($e) => $e->category?->name ?? 'Other');
        $expCatVal = $expByCat->map(fn($e) => (float)$e->total);

        /* ── 5. Inventory by category (value & count) ── */
        $invByCat = InventoryProduct::with('category')
            ->where('active', true)
            ->get()
            ->groupBy(fn($p) => $p->category?->name ?? 'Uncategorized');

        $invCatLbl   = $invByCat->keys();
        $invCatCount = $invByCat->map(fn($g) => $g->count())->values();
        $invCatValue = $invByCat->map(fn($g) => round($g->sum(fn($p) => (float)$p->qty_on_hand * (float)($p->cost ?? 0)), 2))->values();

        /* ── 6. Employees by department ── */
        $empByDept = Employee::with('department')
            ->where('active', true)
            ->get()
            ->groupBy(fn($e) => $e->department?->name ?? 'No Dept');

        $empDeptLbl   = $empByDept->keys();
        $empDeptCount = $empByDept->map(fn($g) => $g->count())->values();
        $empDeptSal   = $empByDept->map(fn($g) => (float)$g->sum('salary'))->values();

        /* ── 7. Payment method: cash vs credit (this month) ── */
        $cashSales   = PosOrder::where('status','paid')->where('is_credit', false)
            ->whereBetween('created_at', ["$monthStart 00:00:00","$monthEnd 23:59:59"])->sum('grand_total');
        $creditSales = PosOrder::where('status','paid')->where('is_credit', true)
            ->whereBetween('created_at', ["$monthStart 00:00:00","$monthEnd 23:59:59"])->sum('grand_total');

        /* ── 8. Top debtors (credit book) ── */
        $topDebtors = Contact::where('active', true)
            ->withSum(['creditLedger as tc' => fn($x) => $x->where('type','credit')], 'amount')
            ->withSum(['creditLedger as tp' => fn($x) => $x->where('type','payment')], 'amount')
            ->get()
            ->map(fn($c) => [
                'name'    => $c->name,
                'balance' => round((float)($c->tc ?? 0) - (float)($c->tp ?? 0), 2),
            ])
            ->filter(fn($c) => $c['balance'] > 0)
            ->sortByDesc('balance')
            ->take(8)
            ->values();

        $debtorLbl = $topDebtors->pluck('name');
        $debtorVal = $topDebtors->pluck('balance');

        /* ── 9. Hourly sales today ── */
        $hourlySales = PosOrder::where('status','paid')
            ->whereDate('created_at', $today)
            ->selectRaw('HOUR(created_at) as hr, SUM(grand_total) as total, COUNT(*) as cnt')
            ->groupBy('hr')->orderBy('hr')->get()->keyBy('hr');

        $hourlyLbl = collect(range(0,23))->map(fn($h) => sprintf('%02d:00', $h));
        $hourlyVal = collect(range(0,23))->map(fn($h) => (float)($hourlySales[$h]->total ?? 0));

        /* ── 10. Expense status breakdown ── */
        $expStatus = Expense::selectRaw('status, COUNT(*) as cnt, SUM(grand_total) as total')
            ->groupBy('status')->get()->keyBy('status');

        return view('analytics.index', compact(
            'currency','company',
            // Current session
            'currentSessionOpen','currentSessionSale','currentSessionBills',
            'currentSessionCash','currentSessionCard','currentSessionBank',
            'currentSessionPending','currentSessionCashierLabel','currentSessionNoLabel',
            'todaySalesTotal','todaySalesCount',
            // KPIs
            'incomeThisMonth','incomeGrowth',
            'cafeProfitMonth',
            'salesThisMonth',
            'purchasesMonth','expensesMonth','activeEmployees',
            'totalProducts','lowStock','outOfStock','outstandingCredit',
            // Chart 1
            'sales30Lbl','sales30Val','orders30Val',
            // Chart 2
            'monthly12Lbl','monthly12Sales','monthly12Purch',
            // Chart 3
            'topProdLbl','topProdRev','topProdQty',
            // Chart 4
            'expCatLbl','expCatVal',
            // Chart 5
            'invCatLbl','invCatCount','invCatValue',
            // Chart 6
            'empDeptLbl','empDeptCount','empDeptSal',
            // Chart 7
            'cashSales','creditSales',
            // Chart 8
            'debtorLbl','debtorVal',
            // Chart 9
            'hourlyLbl','hourlyVal',
            // Chart 10
            'expStatus'
        ));
    }

}
