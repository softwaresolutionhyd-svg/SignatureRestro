@extends('layouts.admin')
@section('title', 'Profit & Loss — ' . config('app.name'))

@section('content')
@include('reports.partials.print-portrait')

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-0">Profit &amp; Loss Report</h4>
        <div class="text-secondary small">
            Total sale, COGS, gross profit, expense categories, and net profit — print-ready.
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← Reports</a>
    </div>
</div>

<form method="GET" action="{{ route('reports.profit-loss') }}" class="card shadow-sm border-0 mb-4 no-print" id="plForm">
    <input type="hidden" name="preset" id="plPreset" value="{{ $preset }}">
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
                        data-pl-preset="{{ $pval }}">{{ $plbl }}</button>
            @endforeach
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" value="{{ request('from', $from) }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ request('to', $to) }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-sm btn-primary" id="plApplyCustom">Apply</button>
            </div>
        </div>
    </div>
</form>

{{-- On-screen report --}}
<div class="card border-0 shadow-sm pl-sheet">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-2">
            <div class="fw-bold text-uppercase" style="font-size:1.35rem;letter-spacing:.04em;">{{ $companyName }}</div>
            <div class="fw-semibold mt-1" style="font-size:1.05rem;text-decoration:underline;">Final Profit &amp; Loss Report</div>
            <div class="d-inline-block mt-3 px-4 py-1 border border-dark fw-semibold small">{{ $periodLabel }}</div>
        </div>

        <table class="table table-bordered mb-0 mt-4 pl-table">
            <colgroup>
                <col style="width:68%">
                <col style="width:32%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="fw-semibold">Total Sale</td>
                    <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($totalSale, 2) }}</td>
                </tr>
                <tr>
                    <td class="fw-semibold">Cost of Goods Sold</td>
                    <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($cogs, 2) }}</td>
                </tr>
                <tr>
                    <td class="fw-semibold">Service Charges</td>
                    <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($serviceCharges, 2) }}</td>
                </tr>
                <tr>
                    <td class="fw-semibold">Discount</td>
                    <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($discountTotal, 2) }}</td>
                </tr>
                <tr class="pl-section">
                    <td class="fw-bold">Gross Profit</td>
                    <td class="text-end font-monospace fw-bold">{{ $currency }} {{ fmt_num($grossProfit, 2) }}</td>
                </tr>

                @forelse($operatingExpenses as $row)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $row['name'] }}</span>
                        @if(!empty($row['description']))
                            <div class="text-secondary small">{{ $row['description'] }}</div>
                        @endif
                    </td>
                    <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td class="text-secondary">No expense categories</td>
                    <td class="text-end font-monospace">{{ $currency }} 0</td>
                </tr>
                @endforelse

                <tr class="pl-section">
                    <td class="fw-bold">Profit Before Tax</td>
                    <td class="text-end font-monospace fw-bold">{{ $currency }} {{ fmt_num($profitBeforeTax, 2) }}</td>
                </tr>

                @if(count($taxFineExpenses))
                    @foreach($taxFineExpenses as $row)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $row['name'] }}</span>
                            @if(!empty($row['description']))
                                <div class="text-secondary small">{{ $row['description'] }}</div>
                            @endif
                        </td>
                        <td class="text-end font-monospace">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td class="fw-semibold">Tax &amp; Fine Expense</td>
                        <td class="text-end font-monospace">{{ $currency }} {{ fmt_num(0, 2) }}</td>
                    </tr>
                @endif

                <tr class="pl-section pl-final">
                    <td class="fw-bold">Net Profit &amp; Loss</td>
                    <td class="text-end font-monospace fw-bold">{{ $currency }} {{ fmt_num($netProfit, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="text-secondary small mt-3 no-print">
            Sales &amp; COGS from paid POS bills. Expenses = Approved / Paid in the selected dates.
            Categories whose name contains “tax” or “fine” appear under Profit Before Tax.
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
.pl-table td { vertical-align: middle; padding: .65rem .85rem; }
.pl-section td { border-top-width: 2px !important; background: #f8fafc; }
.pl-final td { border-bottom-width: 2px !important; }
@media print {
    .pl-sheet { box-shadow: none !important; }
    .pl-sheet .card-body { padding: 0 !important; }
    .pl-table { border: 1px solid #000 !important; }
    .pl-table td {
        border: 1px solid #000 !important;
        background: transparent !important;
        font-size: 11pt !important;
    }
    .pl-section td { border-top: 2px solid #000 !important; font-weight: bold !important; }
}
</style>
@endpush

@section('scripts')
<script>
(function () {
    const form = document.getElementById('plForm');
    const presetInput = document.getElementById('plPreset');
    document.querySelectorAll('[data-pl-preset]').forEach(btn => {
        btn.addEventListener('click', () => {
            presetInput.value = btn.getAttribute('data-pl-preset');
            form.querySelector('[name="from"]').value = '';
            form.querySelector('[name="to"]').value = '';
            form.submit();
        });
    });
    document.getElementById('plApplyCustom')?.addEventListener('click', () => {
        presetInput.value = 'custom';
        form.submit();
    });
})();
</script>
@endsection
