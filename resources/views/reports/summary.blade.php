@extends('layouts.admin')
@section('title', 'Financial Summary — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Financial Summary</h4>
        <div class="text-secondary small">
            POS net revenue (sales − refunds), COGS, gross profit, expenses (approved/paid), net profit.
            Daily / weekly / monthly breakdown ya poora period ek line mein.
        </div>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← Reports</a>
    </div>
</div>

<form method="GET" action="{{ route('reports.summary') }}" class="card shadow-sm border-0 mb-4 no-print" id="summaryForm">
    <input type="hidden" name="preset" id="summaryPreset" value="{{ $preset }}">
    <input type="hidden" name="group" id="summaryGroup" value="{{ $group }}">
    <div class="card-body">
        <div class="fw-semibold small mb-2">Date range</div>
        <div class="d-flex flex-wrap gap-1 mb-3">
            @foreach([
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'this_week' => 'This week',
                'last_week' => 'Last week',
                'this_month' => 'This month',
                'last_month' => 'Last month',
                'this_quarter' => 'This quarter',
                'this_year' => 'This year',
                'last_year' => 'Last year',
            ] as $pval => $plbl)
                <button type="button"
                        class="btn btn-sm {{ $preset === $pval ? 'btn-primary' : 'btn-outline-secondary' }}"
                        style="font-size:11px;"
                        data-summary-preset="{{ $pval }}">{{ $plbl }}</button>
            @endforeach
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-auto">
                <label class="form-label small fw-semibold mb-1">Custom — from</label>
                <input type="date" name="from" value="{{ request('from', $from) }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ request('to', $to) }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-sm btn-outline-primary" id="summaryApplyCustom">Apply custom</button>
            </div>
        </div>

        <hr class="my-3">

        <div class="fw-semibold small mb-2">Breakdown (group by)</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach([
                'summary' => 'Whole period (one row)',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
            ] as $gval => $glbl)
                <button type="button" data-summary-group="{{ $gval }}"
                        class="btn btn-sm {{ $group === $gval ? 'btn-dark' : 'btn-outline-dark' }}">
                    {{ $glbl }}
                </button>
            @endforeach
        </div>
    </div>
</form>

@php
    $pl = $presetLabels[$preset] ?? $preset;
@endphp
<div class="alert alert-light border small mb-3 no-print">
    <strong>Period:</strong> {{ $from }} → {{ $to }}
    <span class="text-secondary">({{ $pl }})</span>
    · <strong>Group:</strong> {{ ucfirst($group) }}
</div>

{{-- KPI strip (totals) --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4" style="border-color:#7c3aed!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Total income (net POS)</div>
                <div class="fw-bold fs-5" style="color:#7c3aed;">{{ $currency }} {{ fmt_num($totals['net_revenue'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">Grand total, sales − refunds</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
            <div class="card-body py-3">
                <div class="text-secondary small">Sale (subtotal)</div>
                <div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['net_subtotal'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">POS subtotal (tax alag)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4" style="border-color:#64748b!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">COGS (cost / profit ke baghair)</div>
                <div class="fw-bold fs-5 text-secondary">{{ $currency }} {{ fmt_num($totals['cogs'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">Qty × cost per line</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4" style="border-color:#22c55e!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Gross profit</div>
                <div class="fw-bold fs-5 text-success">{{ $currency }} {{ fmt_num($totals['gross_profit'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">Sale lines − COGS (pre-tax)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4" style="border-color:#f97316!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Total expenses</div>
                <div class="fw-bold fs-5" style="color:#ea580c;">{{ $currency }} {{ fmt_num($totals['expense'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">Approved / paid only</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4" style="border-color:#0ea5e9!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Net profit</div>
                <div class="fw-bold fs-5 {{ $totals['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ $currency }} {{ fmt_num($totals['net_profit'], 2) }}</div>
                <div class="text-secondary" style="font-size:11px;">Gross profit − expenses</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-secondary small">POS bills (count)</div>
                <div class="fw-bold fs-5">{{ fmt_num($totals['pos_bills'], 0) }}</div>
                <div class="text-secondary" style="font-size:11px;">Discount {{ $currency }}{{ fmt_num($totals['discount'], 2) }} · Tax {{ $currency }}{{ fmt_num($totals['tax'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold">Detail</span>
        <span class="text-secondary small ms-2">{{ count($rows) }} row(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Period</th>
                <th class="text-end">Bills</th>
                <th class="text-end">Income (net)</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">COGS</th>
                <th class="text-end">Gross profit</th>
                <th class="text-end">Expenses</th>
                <th class="text-end">Net profit</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold small">{{ $r['label'] }}</td>
                    <td class="text-end small">{{ $r['pos_bills'] }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($r['net_revenue'], 2) }}</td>
                    <td class="text-end small text-secondary">{{ $currency }} {{ fmt_num($r['net_subtotal'], 2) }}</td>
                    <td class="text-end small text-secondary">{{ $currency }} {{ fmt_num($r['cogs'], 2) }}</td>
                    <td class="text-end small text-success">{{ $currency }} {{ fmt_num($r['gross_profit'], 2) }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($r['expense'], 2) }}</td>
                    <td class="text-end fw-semibold small {{ $r['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ $currency }} {{ fmt_num($r['net_profit'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-secondary py-4">Is period mein koi POS bill ya expense nahi mila.</td>
                </tr>
            @endforelse
            @if(count($rows))
                <tr class="table-light fw-bold">
                    <td>Total</td>
                    <td class="text-end">{{ $totals['pos_bills'] }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totals['net_revenue'], 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totals['net_subtotal'], 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totals['cogs'], 2) }}</td>
                    <td class="text-end text-success">{{ $currency }} {{ fmt_num($totals['gross_profit'], 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totals['expense'], 2) }}</td>
                    <td class="text-end {{ $totals['net_profit'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ $currency }} {{ fmt_num($totals['net_profit'], 2) }}</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .no-print, .admin-topbar, .breadcrumb { display: none !important; }
    main { padding: 0 !important; }
}
</style>
@endsection

@section('scripts')
<script>
(function () {
    const form = document.getElementById('summaryForm');
    if (!form) return;
    const presetEl = document.getElementById('summaryPreset');
    const groupEl = document.getElementById('summaryGroup');
    function submitForm() {
        form.submit();
    }
    form.querySelectorAll('[data-summary-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            presetEl.value = btn.getAttribute('data-summary-preset');
            submitForm();
        });
    });
    form.querySelectorAll('[data-summary-group]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            groupEl.value = btn.getAttribute('data-summary-group');
            submitForm();
        });
    });
    document.getElementById('summaryApplyCustom')?.addEventListener('click', function () {
        presetEl.value = 'custom';
        submitForm();
    });
})();
</script>
@endsection
