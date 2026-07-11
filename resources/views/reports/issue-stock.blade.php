@extends('layouts.admin')
@section('title', 'Issue Stock Report — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Issue Stock Report</h4>
        <div class="text-secondary small">Warehouse se departments ko issued stock — date wise</div>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('reports.issue-stock.print', array_merge(request()->only(['from', 'to', 'department_id']), ['print' => 1])) }}"
           target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </a>
        <a href="{{ route('reports.inventory') }}" class="btn btn-outline-primary btn-sm">Inventory Report</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Department</label>
                <select name="department_id" class="form-select" style="min-width:180px;">
                    <option value="">All Departments</option>
                    @foreach($departments as $dep)
                        <option value="{{ $dep->id }}" @selected($departmentId === (int) $dep->id)>{{ $dep->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('reports.issue-stock') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @php
                $presets = [
                    'This Month' => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
                    'Last Month' => [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
                    'Last 7 Days' => [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')],
                    'Today' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
                ];
            @endphp
            @foreach($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('reports.issue-stock', array_filter(['from' => $pFrom, 'to' => $pTo, 'department_id' => $departmentId])) }}"
                   class="btn btn-sm btn-outline-secondary">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</form>

<div class="alert alert-light border small mb-4">
    <strong>Period:</strong> {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
    @if($selectedDepartment)
        &nbsp;|&nbsp; <strong>Department:</strong> {{ $selectedDepartment->name }}
    @endif
</div>

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Issue Lines', $issueCount, 'bi-box-arrow-right', '#0ea5e9'],
        ['Total Qty (base UOM)', fmt_num($totalQty, 3), 'bi-stack', '#7c3aed'],
        ['Total Value (Cost)', $currency.' '.fmt_num($totalValue, 2), 'bi-currency-dollar', '#22c55e'],
        ['Departments', $departmentHit, 'bi-building', '#f97316'],
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
    <div class="col-12 col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Issues by Day</div>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Value</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($byDay as $row)
                        <tr>
                            <td class="small fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-end small">{{ $row['lines'] }}</td>
                            <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                            <td class="text-end small">{{ $currency }} {{ fmt_num($row['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No issues in this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold">Issues by Department</div>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Value</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($byDepartment as $row)
                        <tr>
                            <td class="small fw-semibold">{{ $row['name'] }}</td>
                            <td class="text-end small">{{ $row['lines'] }}</td>
                            <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                            <td class="text-end small">{{ $currency }} {{ fmt_num($row['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No issues in this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($byDay->isNotEmpty())
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold">Daily Issue Trend</div>
    <div class="card-body"><canvas id="dayChart" height="90"></canvas></div>
</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Issue Details</span>
        <span class="badge bg-info text-dark">{{ $issues->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Date / Time</th>
                <th>Product</th>
                <th class="text-end">Qty</th>
                <th>From</th>
                <th>To</th>
                <th>By</th>
                <th class="text-end">Value</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            @forelse($issues as $issue)
                <tr>
                    <td class="small text-secondary">{{ $issue->created_at?->format('d M Y H:i') }}</td>
                    <td>
                        <div class="small fw-semibold">{{ $issue->product?->name ?? '—' }}</div>
                        <div class="small text-secondary">{{ $issue->product?->sku }}</div>
                    </td>
                    <td class="text-end small">{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                    <td class="small">{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                    <td class="small"><span class="badge text-bg-primary">{{ $issue->toDepartment?->name ?? '—' }}</span></td>
                    <td class="small">{{ $issue->user?->name ?? '—' }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num((float) ($issue->line_value ?? 0), 2) }}</td>
                    <td class="small text-secondary">{{ $issue->note ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-secondary">Is period me koi stock issue nahi hua.</td></tr>
            @endforelse
            </tbody>
            @if($issues->isNotEmpty())
            <tfoot class="table-light">
            <tr>
                <th colspan="6" class="text-end">Total</th>
                <th class="text-end">{{ $currency }} {{ fmt_num($totalValue, 2) }}</th>
                <th></th>
            </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@section('scripts')
@if($byDay->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('dayChart'), {
    type: 'bar',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Issue lines',
            data: @json($chartData),
            backgroundColor: 'rgba(14,165,233,0.7)',
            borderColor: '#0ea5e9',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }
    }
});
</script>
@endif
<style media="print">
    @page { size: A4 portrait; margin: 12mm; }
    .no-print, .admin-topbar, nav[aria-label="breadcrumb"] { display: none !important; }
</style>
@endsection
