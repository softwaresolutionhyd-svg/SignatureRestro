@extends('layouts.admin')
@section('title', 'Analytics — ' . $company)

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Analytics Overview</h4>
        <div class="text-secondary small">Real-time business snapshot — {{ now()->format('l, d M Y') }}</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 4v4h10V4M5 16H3V9h14v7h-2M5 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Print
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">← Home</a>
    </div>
</div>

{{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['label'=>'Income This Month',     'value'=> $currency . fmt_num($incomeThisMonth,2),      'sub'=>'Restaurant sales · '.($incomeGrowth >= 0 ? '▲ ' : '▼ ').abs($incomeGrowth).'% vs last month', 'color'=>'#7c3aed','icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label'=>'Restaurant Income (month)',   'value'=> $currency . fmt_num($cafeProfitMonth,2),      'sub'=>'Restaurant profit only (sales − discount − product cost)',            'color'=>$cafeProfitMonth>=0?'#16a34a':'#dc2626','icon'=>'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['label'=>'Purchases This Month',  'value'=> $currency . fmt_num($purchasesMonth,2),       'sub'=>'Confirmed & received POs',                                       'color'=>'#0ea5e9','icon'=>'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
        ['label'=>'Expenses This Month',   'value'=> $currency . fmt_num($expensesMonth,2),        'sub'=>'Approved & paid expenses',                                       'color'=>'#f59e0b','icon'=>'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
        ['label'=>'Outstanding Credit',    'value'=> $currency . fmt_num($outstandingCredit,2),    'sub'=>'Total unpaid credit dues',                                       'color'=>'#ef4444','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['label'=>'Active Employees',      'value'=> $activeEmployees,                                   'sub'=>'Currently active staff',                                         'color'=>'#ec4899','icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['label'=>'Total Products',        'value'=> $totalProducts,                                     'sub'=> $outOfStock . ' out of stock · ' . $lowStock . ' low',           'color'=>'#14b8a6','icon'=>'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $k['color'] }}!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="small text-secondary fw-semibold">{{ $k['label'] }}</div>
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" style="color:{{ $k['color'] }};flex-shrink:0;opacity:.8;">
                        <path d="{{ $k['icon'] }}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="fw-bold" style="font-size:1.25rem;color:{{ $k['color'] }};">{{ $k['value'] }}</div>
                <div class="text-secondary" style="font-size:11px;">{{ $k['sub'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Row 1: Area chart (30-day sales) + Hourly sales today ─────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Sales Revenue — Last 30 Days</div>
                <span class="badge rounded-pill" style="background:#7c3aed22;color:#7c3aed;font-size:11px;">Area Chart</span>
            </div>
            <div class="card-body py-3" style="min-height:240px;">
                <canvas id="chartSales30" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Hourly Sales — Today</div>
                <span class="badge rounded-pill" style="background:#f97316;color:#fff;font-size:11px;">{{ now()->format('d M') }}</span>
            </div>
            <div class="card-body py-3" style="min-height:240px;">
                <canvas id="chartHourly" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: Monthly Sales vs Purchases + Payment method ────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Monthly Sales vs Purchases — Last 12 Months</div>
                <span class="badge rounded-pill" style="background:#0ea5e922;color:#0ea5e9;font-size:11px;">Bar Chart</span>
            </div>
            <div class="card-body py-3" style="min-height:240px;">
                <canvas id="chartMonthly" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Payment Method — This Month</div>
                <span class="badge rounded-pill" style="background:#22c55e22;color:#22c55e;font-size:11px;">Doughnut</span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                <canvas id="chartPayMethod" style="max-height:180px;max-width:180px;"></canvas>
                <div class="d-flex gap-4 mt-3 small">
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#22c55e;margin-right:4px;"></span>Cash: {{ $currency }}{{ fmt_num($cashSales,2) }}</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#ef4444;margin-right:4px;"></span>Credit: {{ $currency }}{{ fmt_num($creditSales,2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Top products + Expense by category ──────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Top 10 Products by Revenue — Last 30 Days</div>
                <span class="badge rounded-pill" style="background:#f59e0b22;color:#f59e0b;font-size:11px;">Horizontal Bar</span>
            </div>
            <div class="card-body py-3" style="min-height:280px;">
                <canvas id="chartTopProd" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Expenses by Category</div>
                <span class="badge rounded-pill" style="background:#14b8a622;color:#14b8a6;font-size:11px;">Pie Chart</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center py-3" style="min-height:280px;">
                @if($expCatVal->sum() > 0)
                <canvas id="chartExpCat" style="max-height:240px;max-width:240px;"></canvas>
                @else
                <div class="text-secondary small text-center">
                    <svg width="36" height="36" fill="none" viewBox="0 0 36 36" class="mb-2 opacity-25"><circle cx="18" cy="18" r="14" stroke="currentColor" stroke-width="2"/><path d="M18 10v8l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <div>No approved expenses yet</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Row 4: Inventory by category + Employees by dept ───────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Inventory by Category</div>
                <span class="badge rounded-pill" style="background:#0ea5e922;color:#0ea5e9;font-size:11px;">Doughnut</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center py-3" style="min-height:220px;">
                <canvas id="chartInvCat" style="max-height:200px;max-width:200px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Employees by Department</div>
                <span class="badge rounded-pill" style="background:#ec489922;color:#ec4899;font-size:11px;">Doughnut</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center py-3" style="min-height:220px;">
                @if($empDeptCount->sum() > 0)
                <canvas id="chartEmpDept" style="max-height:200px;max-width:200px;"></canvas>
                @else
                <div class="text-secondary small text-center">No active employees</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Row 5: Top debtors + Expense status breakdown ──────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Top Debtors — Outstanding Credit</div>
                <span class="badge rounded-pill" style="background:#ef444422;color:#ef4444;font-size:11px;">Bar Chart</span>
            </div>
            <div class="card-body py-3" style="min-height:220px;">
                @if($debtorVal->sum() > 0)
                <canvas id="chartDebtors" style="max-height:200px;"></canvas>
                @else
                <div class="text-secondary small text-center mt-5">
                    <svg width="36" height="36" fill="none" viewBox="0 0 36 36" class="mb-2 opacity-25"><path d="M18 8v20M8 18h20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                    <div>No outstanding credit dues</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div class="fw-semibold small">Expense Status Breakdown</div>
                <span class="badge rounded-pill" style="background:#f59e0b22;color:#f59e0b;font-size:11px;">Bar</span>
            </div>
            <div class="card-body py-3" style="min-height:220px;">
                <canvas id="chartExpStatus" style="max-height:200px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Inventory Alert Strip ────────────────────────────────────────────── --}}
@if($lowStock > 0 || $outOfStock > 0)
<div class="card border-0 mb-3" style="background:linear-gradient(135deg,#fef3c7,#fff7ed);border-left:4px solid #f59e0b!important;">
    <div class="card-body py-3 d-flex align-items-center gap-3">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" style="color:#f59e0b;flex-shrink:0;">
            <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div>
            <div class="fw-semibold small">Inventory Alert</div>
            <div class="small text-secondary">
                @if($outOfStock > 0)<span class="text-danger fw-semibold">{{ $outOfStock }} products out of stock</span>@endif
                @if($outOfStock > 0 && $lowStock > 0) &nbsp;·&nbsp; @endif
                @if($lowStock > 0)<span class="text-warning fw-semibold">{{ $lowStock }} products low stock</span>@endif
                &nbsp;— <a href="{{ route('inventory.index') }}" class="text-primary small">View Inventory →</a>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
@media print {
    .btn, .odoo-topbar, .admin-app-body > nav { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #eee !important; }
}
</style>
<script>
(function () {
'use strict';

const C = {
    violet : '#7c3aed',
    orange : '#f97316',
    green  : '#22c55e',
    blue   : '#0ea5e9',
    pink   : '#ec4899',
    teal   : '#14b8a6',
    amber  : '#f59e0b',
    red    : '#ef4444',
    indigo : '#6366f1',
    lime   : '#84cc16',
};
const PALETTE = Object.values(C);

Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.padding  = 14;

function alpha(hex, a) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${a})`;
}

// ── 1. Daily Sales (30 days) ─────────────────────────────────────────
(function() {
    const lbl = @json($sales30Lbl->values());
    const rev = @json($sales30Val->values());
    const ord = @json($orders30Val->values());
    new Chart(document.getElementById('chartSales30'), {
        type: 'line',
        data: {
            labels: lbl,
            datasets: [
                {
                    label: 'Revenue',
                    data: rev,
                    fill: true,
                    backgroundColor: alpha(C.violet, 0.12),
                    borderColor: C.violet,
                    borderWidth: 2.5,
                    pointBackgroundColor: C.violet,
                    pointRadius: 2.5,
                    tension: 0.4,
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: ord,
                    fill: false,
                    borderColor: C.orange,
                    borderWidth: 1.5,
                    pointRadius: 2,
                    tension: 0.4,
                    borderDash: [4,3],
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0 } },
                y:  { position: 'left',  grid: { color: '#f1f5f9' }, ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
                y1: { position: 'right', grid: { display: false }, ticks: { stepSize: 1 } },
            }
        }
    });
})();

// ── 2. Hourly today ─────────────────────────────────────────────────
(function() {
    const lbl = @json($hourlyLbl->values());
    const val = @json($hourlyVal->values());
    new Chart(document.getElementById('chartHourly'), {
        type: 'bar',
        data: {
            labels: lbl,
            datasets: [{
                label: 'Revenue',
                data: val,
                backgroundColor: val.map(v => v > 0 ? alpha(C.orange, 0.85) : alpha(C.orange, 0.15)),
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '{{ $currency }}' + v } },
            }
        }
    });
})();

// ── 3. Monthly Sales vs Purchases ────────────────────────────────────
(function() {
    const lbl   = @json($monthly12Lbl->values());
    const sales = @json($monthly12Sales->values());
    const purch = @json($monthly12Purch->values());
    new Chart(document.getElementById('chartMonthly'), {
        type: 'bar',
        data: {
            labels: lbl,
            datasets: [
                { label: 'Sales', data: sales, backgroundColor: alpha(C.violet, 0.8), borderRadius: 4 },
                { label: 'Purchases', data: purch, backgroundColor: alpha(C.blue, 0.75), borderRadius: 4 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 30 } },
                y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
            }
        }
    });
})();

// ── 4. Payment method ────────────────────────────────────────────────
(function() {
    new Chart(document.getElementById('chartPayMethod'), {
        type: 'doughnut',
        data: {
            labels: ['Cash', 'Credit'],
            datasets: [{
                data: [{{ $cashSales }}, {{ $creditSales }}],
                backgroundColor: [alpha(C.green, 0.85), alpha(C.red, 0.85)],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();

// ── 5. Top Products (horizontal bar) ────────────────────────────────
(function() {
    const lbl = @json($topProdLbl->values());
    const rev = @json($topProdRev->values());
    if (!lbl.length) return;
    new Chart(document.getElementById('chartTopProd'), {
        type: 'bar',
        data: {
            labels: lbl,
            datasets: [{
                label: 'Revenue',
                data: rev,
                backgroundColor: PALETTE.map(c => alpha(c, 0.8)),
                borderRadius: 5,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
                y: { grid: { display: false } },
            }
        }
    });
})();

// ── 6. Expense by category ───────────────────────────────────────────
(function() {
    const lbl = @json($expCatLbl->values());
    const val = @json($expCatVal->values());
    if (!val.length || val.reduce((a,b)=>a+b,0) === 0) return;
    new Chart(document.getElementById('chartExpCat'), {
        type: 'pie',
        data: {
            labels: lbl,
            datasets: [{
                data: val,
                backgroundColor: PALETTE.map(c => alpha(c, 0.82)),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
        }
    });
})();

// ── 7. Inventory by category ─────────────────────────────────────────
(function() {
    const lbl   = @json($invCatLbl->values());
    const count = @json($invCatCount->values());
    if (!lbl.length) return;
    new Chart(document.getElementById('chartInvCat'), {
        type: 'doughnut',
        data: {
            labels: lbl,
            datasets: [{
                data: count,
                backgroundColor: PALETTE.map(c => alpha(c, 0.82)),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            cutout: '55%',
            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, generateLabels: (chart) => {
                const ds = chart.data.datasets[0];
                return chart.data.labels.map((lbl, i) => ({
                    text: lbl + ' (' + ds.data[i] + ')',
                    fillStyle: ds.backgroundColor[i],
                    strokeStyle: '#fff',
                    lineWidth: 2,
                    index: i,
                    hidden: false,
                }));
            }}}}
        }
    });
})();

// ── 8. Employees by dept ─────────────────────────────────────────────
(function() {
    const lbl   = @json($empDeptLbl->values());
    const count = @json($empDeptCount->values());
    if (!lbl.length) return;
    new Chart(document.getElementById('chartEmpDept'), {
        type: 'doughnut',
        data: {
            labels: lbl,
            datasets: [{
                data: count,
                backgroundColor: [C.pink, C.violet, C.orange, C.teal, C.blue, C.amber].map(c => alpha(c, 0.82)),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            cutout: '55%',
            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
        }
    });
})();

// ── 9. Top debtors ───────────────────────────────────────────────────
(function() {
    const lbl = @json($debtorLbl->values());
    const val = @json($debtorVal->values());
    if (!lbl.length) return;
    new Chart(document.getElementById('chartDebtors'), {
        type: 'bar',
        data: {
            labels: lbl,
            datasets: [{
                label: 'Balance Due',
                data: val,
                backgroundColor: alpha(C.red, 0.75),
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
                y: { grid: { display: false } },
            }
        }
    });
})();

// ── 10. Expense status ───────────────────────────────────────────────
(function() {
    const statuses = ['draft','submitted','approved','paid','refused'];
    const labels   = ['Draft','Submitted','Approved','Paid','Refused'];
    const colors   = [alpha(C.amber,0.75), alpha(C.blue,0.75), alpha(C.green,0.75), alpha(C.violet,0.85), alpha(C.red,0.75)];
    const data = @json($expStatus);
    const counts = statuses.map(s => data[s]?.cnt ?? 0);
    const totals = statuses.map(s => parseFloat(data[s]?.total ?? 0));

    new Chart(document.getElementById('chartExpStatus'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Count',  data: counts, backgroundColor: colors, borderRadius: 4, yAxisID: 'y' },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
            }
        }
    });
})();

})();
</script>
@endsection
