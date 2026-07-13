@extends('layouts.admin')
@section('title', 'Low Stock Report — ' . config('app.name'))

@section('content')

<div class="no-print">@include('inventory.partials.subnav')</div>

@include('reports.partials.print-header', ['reportName' => 'Low Stock Report', 'period' => 'Filter: '.ucfirst($filter)])

{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-0">Low Stock Report</h4>
        <div class="text-secondary small">Generated on {{ now()->format('l, d M Y H:i') }}</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-danger btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M4 2h8l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/><path d="M12 2v4h4" stroke="currentColor" stroke-width="1.5"/></svg>
            Print / PDF
        </button>
        <button id="btnCsvExport" class="btn btn-outline-success btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 10h6M10 7v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Export CSV
        </button>
        <a href="{{ route('inventory.products.index') }}" class="btn btn-outline-secondary btn-sm">← Products</a>
    </div>
</div>

{{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4 no-print report-kpis">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ef4444!important;">
            <div class="card-body py-3">
                <div class="small text-secondary">Out of Stock</div>
                <div class="fw-bold fs-4 text-danger">{{ $kpiZero }}</div>
                <div class="small text-secondary">Qty = 0</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f59e0b!important;">
            <div class="card-body py-3">
                <div class="small text-secondary">Below Reorder Level</div>
                <div class="fw-bold fs-4 text-warning">{{ $kpiLow }}</div>
                <div class="small text-secondary">Reorder threshold set</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #dc2626!important;">
            <div class="card-body py-3">
                <div class="small text-secondary">Critical (≤50% reorder)</div>
                <div class="fw-bold fs-4" style="color:#dc2626;">{{ $kpiCritical }}</div>
                <div class="small text-secondary">Needs urgent reorder</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #22c55e!important;">
            <div class="card-body py-3">
                <div class="small text-secondary">Stock OK</div>
                <div class="fw-bold fs-4 text-success">{{ $kpiOk }}</div>
                <div class="small text-secondary">Above reorder level</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Filters ──────────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('inventory.low-stock') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-end py-3">
        <div>
            <label class="form-label small fw-semibold mb-1">Status Filter</label>
            <div class="d-flex gap-1">
                @foreach(['low'=>'Low & Out of Stock','zero'=>'Out of Stock Only','all'=>'All Products'] as $val => $lbl)
                <a href="{{ route('inventory.low-stock', array_merge(request()->query(), ['filter' => $val])) }}"
                   class="btn btn-sm {{ $filter === $val ? 'btn-primary' : 'btn-outline-secondary' }}" style="font-size:11px;">
                    {{ $lbl }}
                </a>
                @endforeach
            </div>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Category</label>
            <select name="category_id" class="form-select form-select-sm" style="min-width:160px;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected((string) $category === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm" style="min-width:160px;" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dep)
                    <option value="{{ $dep->id }}" @selected($departmentId === (int) $dep->id)>
                        {{ $dep->name }}{{ $dep->is_warehouse ? ' (Warehouse)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="ms-auto d-flex align-items-center gap-1 text-secondary small">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20"><path d="M10 2L2 17h16L10 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8v4M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            {{ $products->count() }} products shown
        </div>
    </div>
</form>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm" id="reportTable">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">
            @if($filter === 'zero') Out of Stock Products
            @elseif($filter === 'all') All Products
            @else Low Stock & Out of Stock Products
            @endif
        </span>
        <span class="badge bg-primary bg-opacity-15 text-primary">{{ $products->count() }} records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th class="text-end">On Hand</th>
                    <th class="text-end">Reorder Level</th>
                    <th class="text-end">Shortage</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Stock Value</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $i => $p)
                @php
                    $qty      = (float)$p->qty_on_hand;
                    $reorder  = (float)$p->reorder_level;
                    $shortage = $reorder > 0 ? max(0, $reorder - $qty) : 0;
                    $isZero   = $qty <= 0;
                    $isCrit   = $reorder > 0 && $qty <= ($reorder * 0.5);
                    $isLow    = $reorder > 0 && $qty <= $reorder && !$isZero;
                    $pct      = $reorder > 0 ? min(100, round(($qty / $reorder) * 100)) : 100;
                @endphp
                <tr class="{{ $isZero ? 'table-danger' : ($isCrit ? 'table-warning' : '') }}">
                    <td class="ps-3 text-secondary small">{{ $i + 1 }}</td>
                    <td class="small fw-semibold">{{ $p->sku }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $p->name }}</div>
                        @if($reorder > 0)
                        <div class="progress mt-1" style="height:4px;width:80px;">
                            <div class="progress-bar {{ $isZero ? 'bg-danger' : ($isCrit ? 'bg-warning' : 'bg-success') }}"
                                 style="width:{{ $pct }}%;"></div>
                        </div>
                        @endif
                    </td>
                    <td class="small text-secondary">{{ $p->category?->name ?? '—' }}</td>
                    <td class="text-end fw-semibold {{ $isZero ? 'text-danger' : ($isLow || $isCrit ? 'text-warning' : '') }}">
                        {{ fmt_num($qty, 3) }} <span class="text-secondary small">{{ $p->uom }}</span>
                    </td>
                    <td class="text-end small text-secondary">
                        @if($reorder > 0)
                            {{ fmt_num($reorder, 3) }} {{ $p->uom }}
                        @else
                            <span class="text-muted">Not set</span>
                        @endif
                    </td>
                    <td class="text-end small {{ $shortage > 0 ? 'text-danger fw-semibold' : 'text-secondary' }}">
                        {{ $shortage > 0 ? fmt_num($shortage, 3) . ' ' . $p->uom : '—' }}
                    </td>
                    <td class="text-center">
                        @if($isZero)
                            <span class="badge bg-danger" style="font-size:10px;">Out of Stock</span>
                        @elseif($isCrit)
                            <span class="badge bg-danger bg-opacity-75" style="font-size:10px;">Critical</span>
                        @elseif($isLow)
                            <span class="badge bg-warning text-dark" style="font-size:10px;">Low Stock</span>
                        @else
                            <span class="badge bg-success bg-opacity-75" style="font-size:10px;">OK</span>
                        @endif
                    </td>
                    <td class="text-end small">
                        {{ $currency }}{{ fmt_num($qty * (float)($p->cost ?? 0), 2) }}
                    </td>
                    <td>
                        <a href="{{ route('inventory.products.edit', ['product' => $p, 'return' => request()->getRequestUri()]) }}" class="btn btn-xs btn-outline-primary" style="padding:2px 8px;font-size:11px;">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-secondary">
                        <svg width="36" height="36" fill="none" viewBox="0 0 36 36" class="mb-2 opacity-25"><path d="M18 8v10l4 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="18" cy="18" r="14" stroke="currentColor" stroke-width="2"/></svg>
                        <div class="fw-semibold">All products are well stocked!</div>
                        <div class="small">No low stock or out-of-stock products found.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($products->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="fw-semibold small ps-3">Total</td>
                    <td class="text-end fw-semibold small">{{ fmt_num($products->sum('qty_on_hand'), 3) }}</td>
                    <td colspan="3"></td>
                    <td class="text-end fw-semibold small">
                        {{ $currency }}{{ fmt_num($products->sum(fn($p) => (float)$p->qty_on_hand * (float)($p->cost ?? 0)), 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@include('reports.partials.print-portrait')

@endsection

@section('scripts')
<script>
document.getElementById('btnCsvExport').addEventListener('click', function () {
    const rows = [];
    const headers = ['#','SKU','Product','Category','On Hand','UOM','Reorder Level','Shortage','Status','Stock Value'];
    rows.push(headers.join(','));

    document.querySelectorAll('#reportTable tbody tr').forEach((tr, i) => {
        const tds = tr.querySelectorAll('td');
        if (!tds.length) return;
        const row = [
            tds[0]?.textContent.trim(),
            '"' + (tds[1]?.textContent.trim() ?? '') + '"',
            '"' + (tds[2]?.querySelector('.fw-semibold')?.textContent.trim() ?? '') + '"',
            '"' + (tds[3]?.textContent.trim() ?? '') + '"',
            tds[4]?.textContent.trim().split('\n')[0].trim(),
            tds[4]?.querySelector('.text-secondary')?.textContent.trim() ?? '',
            tds[5]?.textContent.trim(),
            tds[6]?.textContent.trim(),
            '"' + (tds[7]?.textContent.trim() ?? '') + '"',
            tds[8]?.textContent.trim(),
        ];
        rows.push(row.join(','));
    });

    const csv  = rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), {
        href: url,
        download: 'low-stock-report-{{ now()->format('Y-m-d') }}.csv'
    });
    a.click();
    URL.revokeObjectURL(url);
});
</script>
@endsection
