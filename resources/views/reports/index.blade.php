@extends('layouts.admin')
@section('title', 'Reports — ' . config('app.name'))

@section('content')
@php($ru = auth()->user())
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Reports</h4>
        <div class="text-secondary small">Business overview & analytics</div>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Total Sales (All Time)</div>
                <div class="fw-bold fs-4" style="color:#7c3aed;">{{ $currency }} {{ fmt_num($totalSales,2) }}</div>
                <a href="{{ route('reports.sales') }}" class="small text-decoration-none">View Sales →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Total Purchases (All Time)</div>
                <div class="fw-bold fs-4" style="color:#22c55e;">{{ $currency }} {{ fmt_num($totalPurchases,2) }}</div>
                <a href="{{ route('reports.purchases') }}" class="small text-decoration-none">View Purchases →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Active Products</div>
                <div class="fw-bold fs-4" style="color:#0ea5e9;">{{ $totalProducts }}</div>
                <a href="{{ route('reports.inventory') }}" class="small text-decoration-none">View Inventory →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Active Employees</div>
                <div class="fw-bold fs-4" style="color:#ec4899;">{{ $totalEmployees }}</div>
                <a href="{{ route('reports.employees') }}" class="small text-decoration-none">View Employees →</a>
            </div>
        </div>
    </div>
</div>

{{-- Sales Chart --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Sales — Last 7 Days</span>
                <a href="{{ route('reports.sales') }}" class="btn btn-sm btn-outline-primary">Full Report</a>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Quick Links</div>
            <div class="card-body p-0">
                <a href="{{ route('reports.summary') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark hover-bg-light">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(124,58,237,.15);">
                        <i class="bi bi-graph-up-arrow" style="color:#7c3aed;"></i>
                    </span>
                    <div><div class="fw-semibold">Financial Summary</div><div class="text-secondary small">Income, COGS, profit, expenses — daily / weekly / monthly</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.pos-bills') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark hover-bg-light">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(99,102,241,.12);">
                        <i class="bi bi-receipt-cutoff" style="color:#6366f1;"></i>
                    </span>
                    <div><div class="fw-semibold">POS Bills</div><div class="text-secondary small">Per bill: date, time, discount, tax, profit</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.pos-sessions') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark hover-bg-light">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(180,83,9,.12);">
                        <i class="bi bi-cash-stack" style="color:#b45309;"></i>
                    </span>
                    <div><div class="fw-semibold">Session Reports</div><div class="text-secondary small">Closed POS sessions — sales, cash, card, bank by date</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.sales') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark hover-bg-light">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(124,58,237,.1);">
                        <i class="bi bi-bar-chart-fill" style="color:#7c3aed;"></i>
                    </span>
                    <div><div class="fw-semibold">Sales Report</div><div class="text-secondary small">POS orders, revenue, top products</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.purchases') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(34,197,94,.1);">
                        <i class="bi bi-cart-fill" style="color:#22c55e;"></i>
                    </span>
                    <div><div class="fw-semibold">Purchase Report</div><div class="text-secondary small">POs, vendors, spend</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.inventory') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(14,165,233,.1);">
                        <i class="bi bi-box-fill" style="color:#0ea5e9;"></i>
                    </span>
                    <div><div class="fw-semibold">Inventory Report</div><div class="text-secondary small">Stock levels, valuation</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.issue-stock') }}" class="d-flex align-items-center gap-3 px-4 py-3 border-bottom text-decoration-none text-dark">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(249,115,22,.1);">
                        <i class="bi bi-box-arrow-right" style="color:#f97316;"></i>
                    </span>
                    <div><div class="fw-semibold">Issue Stock Report</div><div class="text-secondary small">Date wise warehouse → department issues</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
                <a href="{{ route('reports.employees') }}" class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none text-dark">
                    <span class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(236,72,153,.1);">
                        <i class="bi bi-people-fill" style="color:#ec4899;"></i>
                    </span>
                    <div><div class="fw-semibold">Employee Report</div><div class="text-secondary small">Staff, salary breakdown</div></div>
                    <i class="bi bi-chevron-right ms-auto text-secondary"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Sales',
            data: @json($chartSales),
            backgroundColor: 'rgba(124,58,237,0.7)',
            borderColor: '#7c3aed',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '{{ $currency }} ' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endsection
