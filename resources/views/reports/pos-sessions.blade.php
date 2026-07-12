@extends('layouts.admin')
@section('title', 'Session Reports — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Session Reports</h4>
        <div class="text-secondary small">Closed POS sessions — sales, discount, service charges, cash / bank / card</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('restaurant-pos.closing') }}" class="btn btn-outline-primary btn-sm">POS Closing</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="GET" class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label small fw-semibold mb-1">From date</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">To date</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control">
        </div>
        <div class="d-flex gap-2 mt-1">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('reports.pos-sessions') }}" class="btn btn-outline-secondary">This month</a>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0 mb-3 no-print">
    <div class="card-body py-3">
        <div class="row g-3 small row-cols-2 row-cols-md-4 row-cols-lg-7">
            <div class="col"><div class="text-secondary">Sessions</div><div class="fw-bold fs-5">{{ $totals['sessions'] }}</div></div>
            <div class="col"><div class="text-secondary">Net sales</div><div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['net_sales'], 2) }}</div></div>
            <div class="col"><div class="text-secondary">Discount</div><div class="fw-bold fs-5 text-danger">{{ $currency }} {{ fmt_num($totals['discount'], 2) }}</div></div>
            <div class="col"><div class="text-secondary">Service ch.</div><div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['service_charge'], 2) }}</div></div>
            <div class="col"><div class="text-secondary">Cash</div><div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['cash'], 2) }}</div></div>
            <div class="col"><div class="text-secondary">Bank</div><div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['bank'], 2) }}</div></div>
            <div class="col"><div class="text-secondary">Card</div><div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totals['card'], 2) }}</div></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Session</th>
                        <th>Cashier</th>
                        <th class="text-end">Sales</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Svc ch.</th>
                        <th class="text-end">Cash</th>
                        <th class="text-end">Bank</th>
                        <th class="text-end">Card</th>
                        <th class="text-end">Diff</th>
                        <th class="text-end pe-3 no-print">Print</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $s = $row['session'];
                            $st = $row['stats'];
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $s->business_date?->format('d M Y') ?? $s->closed_at?->format('d M Y') }}</div>
                                <div class="text-secondary" style="font-size:11px;">Closed {{ $s->closed_at?->format('H:i') }}</div>
                            </td>
                            <td>{{ $s->session_no ?? '#'.$s->id }}</td>
                            <td>{{ $s->user?->name ?? '—' }}</td>
                            <td class="text-end fw-semibold">{{ $currency }} {{ fmt_num($st['net_sales_total'], 2) }}</td>
                            <td class="text-end text-danger">{{ $currency }} {{ fmt_num($st['discount_total'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ fmt_num($st['service_charge_total'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ fmt_num($st['payments_cash'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ fmt_num($st['payments_bank'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ fmt_num($st['payments_card'], 2) }}</td>
                            <td class="text-end @if((float) $s->cash_difference !== 0.0) text-warning @endif">
                                {{ $currency }} {{ fmt_num((float) $s->cash_difference, 2) }}
                            </td>
                            <td class="text-end pe-3 no-print">
                                <a href="{{ route('reports.pos-sessions.print', $s) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-secondary py-5">
                                Is date range mein koi closed session nahi mili.
                                <a href="{{ route('restaurant-pos.closing') }}" class="d-block mt-2">POS Closing →</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>@media print { .no-print { display: none !important; } }</style>
@endsection
