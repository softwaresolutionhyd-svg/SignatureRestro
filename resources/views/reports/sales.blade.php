@extends('layouts.admin')
@section('title', 'Sales Report — ' . config('app.name'))

@section('content')
@include('reports.partials.print-header', ['reportName' => 'Sales Report', 'period' => 'Period: '.\Carbon\Carbon::parse($from)->format('d M Y').' — '.\Carbon\Carbon::parse($to)->format('d M Y')])
{{-- Header --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-0">Sales Report</h4>
        <div class="text-secondary small">POS revenue, orders & top-selling products</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-danger btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M4 2h8l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/><path d="M12 2v4h4" stroke="currentColor" stroke-width="1.5"/></svg>
            Print / PDF
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

{{-- Date filter --}}
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
        <div class="d-flex gap-2 mt-1">
            <button class="btn btn-primary">Apply</button>
            <a href="{{ route('reports.sales') }}" class="btn btn-outline-secondary">This Month</a>
        </div>
    </div>
</form>

{{-- KPI cards --}}
<div class="row g-3 mb-4 no-print report-kpis">
    @foreach([
        ['Orders', $orderCount, 'bi-receipt', '#7c3aed', ''],
        ['Revenue', $currency.' '.fmt_num($totalRevenue,2), 'bi-cash-stack', '#22c55e', ''],
        ['Gross profit', $currency.' '.fmt_num($totalGrossProfit ?? 0, 2), 'bi-wallet2', '#16a34a', ''],
        ['Avg Order', $currency.' '.fmt_num($avgOrder,2), 'bi-graph-up', '#0ea5e9', ''],
        ['Tax Collected', $currency.' '.fmt_num($totalTax,2), 'bi-percent', '#f97316', ''],
        ['Discounts Given', $currency.' '.fmt_num($totalDiscount,2), 'bi-tag', '#ec4899', ''],
        ['Owner 100% Discount', $currency.' '.fmt_num($ownerDiscountTotal ?? 0, 2).' · '.($ownerDiscountCount ?? 0).' bills', 'bi-gift', '#a855f7', ''],
    ] as [$label,$val,$icon,$color])
    <div class="col-6 col-md">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
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
    {{-- Daily chart --}}
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Daily Sales</div>
            <div class="card-body"><canvas id="salesChart" height="90"></canvas></div>
        </div>
    </div>

    {{-- Top products --}}
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Top 10 Products</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th>
                        </tr></thead>
                        <tbody>
                        @forelse($topProducts as $item)
                        <tr>
                            <td class="small">{{ optional($item->product)->name ?? '—' }}</td>
                            <td class="text-end small">{{ fmt_num($item->total_qty,2) }}</td>
                            <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num($item->total_revenue,2) }}</td>
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
</div>

{{-- Orders table --}}
<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        Orders <span class="badge bg-primary">{{ $orders->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ser No</th>
                    <th>Bill #</th>
                    <th>Customer name</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Profit</th>
                    <th class="text-end">Gas Charges</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Service Charges</th>
                    <th class="text-end">Discount (%)</th>
                    <th class="text-end">Discount Amount</th>
                    <th class="text-end">Total Bill</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="small">{{ $loop->iteration }}</td>
                <td class="fw-semibold small">{{ $order->order_no }}</td>
                <td class="small">{{ $order->customerDisplayNameForReport() }}</td>
                <td class="small">{{ $order->created_at->format('d M Y') }}</td>
                <td class="small">{{ $order->created_at->format('h:i A') }}</td>
                <td class="text-end small">{{ $currency }} {{ fmt_num($order->cost_total ?? 0,2) }}</td>
                <td class="text-end small fw-semibold {{ ($order->gross_profit ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $currency }} {{ fmt_num($order->gross_profit ?? 0, 2) }}</td>
                <td class="text-end small">{{ $currency }} {{ fmt_num($order->gas_total ?? 0,2) }}</td>
                <td class="text-end small">{{ $currency }} {{ fmt_num($order->subtotal,2) }}</td>
                <td class="text-end small">{{ $currency }} {{ fmt_num($order->service_total ?? 0,2) }}</td>
                <td class="text-end small">{{ fmt_num($order->discount_percent_effective ?? 0,2) }}%</td>
                <td class="text-end small text-danger">
                    {{ $currency }} {{ fmt_num($order->discount_total,2) }}
                    @if(!empty($order->is_owner_discount))
                        <span class="badge text-bg-warning text-dark ms-1" style="font-size:0.62rem;">Owner 100%</span>
                    @endif
                </td>
                <td class="text-end small fw-bold">{{ $currency }} {{ fmt_num($order->grand_total,2) }}</td>
            </tr>
            @empty
            <tr><td colspan="13" class="text-center py-4 text-secondary">No orders in this period</td></tr>
            @endforelse
            </tbody>
            @if($orders->count())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="5">Totals</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($orders->sum('cost_total'),2) }}</td>
                    <td class="text-end text-success">{{ $currency }} {{ fmt_num($orders->sum('gross_profit'), 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($orders->sum('gas_total'),2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($orders->sum('subtotal'),2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($orders->sum('service_total'),2) }}</td>
                    <td class="text-end">{{ fmt_num($orders->avg('discount_percent_effective') ?? 0,2) }}%</td>
                    <td class="text-end text-danger">{{ $currency }} {{ fmt_num($totalDiscount,2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totalRevenue,2) }}</td>
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
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Revenue',
            data: @json($chartData),
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124,58,237,0.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#7c3aed',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>
@include('reports.partials.print-portrait')
@endsection
