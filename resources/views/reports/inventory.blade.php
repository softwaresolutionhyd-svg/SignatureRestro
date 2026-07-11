@extends('layouts.admin')
@section('title', 'Inventory Report — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Inventory Report</h4>
        <div class="text-secondary small">Stock levels, valuation & categories</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-danger btn-sm">Print / PDF</button>
        <a href="{{ route('reports.issue-stock') }}" class="btn btn-outline-primary btn-sm">Issue Stock Report</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

{{-- Filter --}}
<form method="GET" class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label small fw-semibold mb-1">Show</label>
            <select name="filter" class="form-select" onchange="this.form.submit()">
                <option value="all"  {{ $filter==='all'  ? 'selected' : '' }}>All Products</option>
                <option value="low"  {{ $filter==='low'  ? 'selected' : '' }}>Low Stock (≤10)</option>
                <option value="zero" {{ $filter==='zero' ? 'selected' : '' }}>Out of Stock</option>
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Department</label>
            <select name="department_id" class="form-select" style="min-width:180px;" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dep)
                    <option value="{{ $dep->id }}" @selected($departmentId === (int) $dep->id)>
                        {{ $dep->name }}{{ $dep->is_warehouse ? ' (Warehouse)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</form>

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Total Products', $totalProducts, 'bi-box-seam', '#0ea5e9'],
        ['Low Stock', $lowStock, 'bi-exclamation-triangle', '#f97316'],
        ['Out of Stock', $outOfStock, 'bi-x-circle', '#ef4444'],
        ['Stock Value (Cost)', $currency.' '.fmt_num($totalValue,2), 'bi-currency-dollar', '#22c55e'],
        ['Stock Value (Retail)', $currency.' '.fmt_num($retailValue,2), 'bi-tags', '#7c3aed'],
        ['Potential profit (qty×(price−cost))', $currency.' '.fmt_num($stockPotentialProfit ?? 0, 2), 'bi-graph-up', '#16a34a'],
    ] as [$label,$val,$icon,$color])
    <div class="col-6 col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.2rem;"></i>
                    <span class="text-secondary small">{{ $label }}</span>
                </div>
                <div class="fw-bold fs-5">{{ $val }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Products by Category</div>
            <div class="card-body"><canvas id="catChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Products</span>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('reports.inventory.print', array_merge(request()->only(['filter', 'department_id']), ['print' => 1])) }}"
                       target="_blank" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-printer me-1"></i> Print
                    </a>
                    <span class="badge bg-info text-dark">{{ $products->count() }}</span>
                </div>
            </div>
            <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                        <tr><th>SKU</th><th>Name</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th class="text-end">Price</th></tr>
                    </thead>
                    <tbody>
                    @forelse($products as $p)
                    <tr class="{{ $p->qty_on_hand <= 0 ? 'table-danger' : ($p->qty_on_hand <= 10 ? 'table-warning' : '') }}">
                        <td class="small text-secondary">{{ $p->sku }}</td>
                        <td class="small fw-semibold">{{ $p->name }}</td>
                        <td class="small">{{ $p->uom ?: '—' }}</td>
                        <td class="text-end small">
                            {{ fmt_num($p->qty_on_hand,2) }}
                            @if($p->qty_on_hand <= 0)
                                <i class="bi bi-x-circle text-danger ms-1" title="Out of stock"></i>
                            @elseif($p->qty_on_hand <= 10)
                                <i class="bi bi-exclamation-triangle text-warning ms-1" title="Low stock"></i>
                            @endif
                        </td>
                        <td class="text-end small">{{ $currency }} {{ fmt_num($p->cost,2) }}</td>
                        <td class="text-end small">{{ $currency }} {{ fmt_num($p->price,2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-secondary">No products found</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('catChart'), {
    type: 'pie',
    data: {
        labels: @json($chartLabels),
        datasets: [{ data: @json($chartData), backgroundColor: ['#7c3aed','#22c55e','#0ea5e9','#f97316','#ec4899','#eab308','#06b6d4','#64748b','#a855f7','#14b8a6'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
@include('reports.partials.print-portrait')
@endsection
