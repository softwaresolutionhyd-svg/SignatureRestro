@extends('layouts.admin')
@section('title', 'Purchase Report — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Purchase Report</h4>
        <div class="text-secondary small">Purchase orders, vendors & spend analysis</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.purchases.print', array_merge(request()->only(['from', 'to', 'vendor', 'status']), ['print' => 1])) }}"
           target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print Report
        </a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label small fw-semibold mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Vendor</label>
            <select name="vendor" class="form-select">
                <option value="">All Vendors</option>
                @foreach($vendors as $v)
                <option value="{{ $v->id }}" {{ $vendor == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                @foreach(['rfq'=>'RFQ','confirmed'=>'Confirmed','received'=>'Received','cancelled'=>'Cancelled'] as $k=>$v)
                <option value="{{ $k }}" {{ $status===$k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Apply</button>
        <a href="{{ route('reports.purchases') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Orders', $orderCount, 'bi-file-text', '#22c55e'],
        ['Products', $productCount, 'bi-box-seam', '#0ea5e9'],
        ['Total Spend', $currency.' '.fmt_num($totalAmount,2), 'bi-cash-stack', '#7c3aed'],
        ['Tax', $currency.' '.fmt_num($totalTax,2), 'bi-percent', '#f97316'],
    ] as [$label,$val,$icon,$color])
    <div class="col-6 col-md-3">
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
    {{-- Vendor chart --}}
    <div class="col-12 col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Spend by Vendor</div>
            <div class="card-body"><canvas id="vendorChart" height="180"></canvas></div>
        </div>
    </div>
    {{-- Vendor table --}}
    <div class="col-12 col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Vendor Breakdown</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Vendor</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($byVendor as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="text-end">{{ $row['count'] }}</td>
                        <td class="text-end fw-semibold">{{ $currency }} {{ fmt_num($row['total'],2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-secondary py-3">No data</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Products summary --}}
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Purchased Products (Summary)</span>
        <span class="badge bg-primary">{{ $productCount }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>UOM</th>
                <th class="text-end">Total Qty</th>
                <th class="text-end">Total Amount</th>
                <th class="text-end">Lines</th>
            </tr>
            </thead>
            <tbody>
            @forelse($byProduct as $row)
                <tr>
                    <td class="small fw-semibold">{{ $row['name'] }}</td>
                    <td class="small text-secondary">{{ $row['sku'] }}</td>
                    <td class="small">{{ $row['uom'] }}</td>
                    <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                    <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num($row['total'], 2) }}</td>
                    <td class="text-end small text-secondary">{{ $row['lines'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">No products purchased in this period</td></tr>
            @endforelse
            </tbody>
            @if($byProduct->isNotEmpty())
            <tfoot class="table-light">
            <tr>
                <th colspan="4" class="text-end">Total</th>
                <th class="text-end">{{ $currency }} {{ fmt_num($purchaseLines->sum('total'), 2) }}</th>
                <th class="text-end">{{ $lineCount }}</th>
            </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('vendorChart'), {
    type: 'doughnut',
    data: {
        labels: @json($chartLabels),
        datasets: [{ data: @json($chartData), backgroundColor: ['#7c3aed','#22c55e','#0ea5e9','#f97316','#ec4899','#eab308','#06b6d4','#64748b'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
@endsection
