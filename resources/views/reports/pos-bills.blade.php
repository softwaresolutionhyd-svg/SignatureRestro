@extends('layouts.admin')
@section('title', 'POS Bills Report — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">POS Bills Report</h4>
        <div class="text-secondary small">Paid bills — bill #, date, time, amounts, gross profit (sale − cost, pre-tax). Filter by date.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        {{ session('error') }}
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
        <div>
            <label class="form-label small fw-semibold mb-1">Type</label>
            <select name="type" class="form-select">
                <option value="all" @selected($type === 'all')>All</option>
                <option value="sale" @selected($type === 'sale')>Sales only</option>
                <option value="refund" @selected($type === 'refund')>Refunds only</option>
            </select>
        </div>
        <div class="d-flex gap-2 mt-1">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('reports.pos-bills') }}" class="btn btn-outline-secondary">This month</a>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0 mb-3 no-print">
    <div class="card-body py-3">
        <div class="row g-3 small row-cols-2 row-cols-md-3 row-cols-lg-6">
            <div class="col">
                <div class="text-secondary">Bills</div>
                <div class="fw-bold fs-5">{{ $billCount }}</div>
            </div>
            <div class="col">
                <div class="text-secondary">Subtotal</div>
                <div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totalSubtotal, 2) }}</div>
            </div>
            <div class="col">
                <div class="text-secondary">Discount</div>
                <div class="fw-bold fs-5 text-danger">{{ $currency }} {{ fmt_num($totalDiscount, 2) }}</div>
            </div>
            <div class="col">
                <div class="text-secondary">Tax</div>
                <div class="fw-bold fs-5">{{ $currency }} {{ fmt_num($totalTax, 2) }}</div>
            </div>
            <div class="col">
                <div class="text-secondary">Gross profit</div>
                <div class="fw-bold fs-5 text-success">{{ $currency }} {{ fmt_num($totalGrossProfit, 2) }}</div>
                <div class="text-secondary" style="font-size:10px;">Pre-tax, current cost</div>
            </div>
            <div class="col">
                <div class="text-secondary">Grand total</div>
                <div class="fw-bold fs-5 text-primary">{{ $currency }} {{ fmt_num($totalGrand, 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Bill lines</span>
        <span class="badge bg-primary">{{ $billCount }} bills</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Bill No</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Cashier</th>
                    <th class="text-end">Bill total</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end" title="Line net − cost (pre-tax), current product cost">Gross profit</th>
                    <th class="text-end">Grand total</th>
                    @if(auth()->user()?->isPlatformSuperAdmin())
                        <th class="text-end no-print">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $at = $order->paid_at ?? $order->created_at;
                @endphp
                <tr>
                    <td class="fw-semibold small text-nowrap">{{ $order->order_no }}</td>
                    <td class="small">
                        @if($order->type === 'refund')
                            <span class="badge bg-warning text-dark">Refund</span>
                        @else
                            <span class="badge bg-success">Sale</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">{{ $at->format('d M Y') }}</td>
                    <td class="small text-nowrap">{{ $at->format('H:i') }}</td>
                    <td class="small">{{ optional($order->user)->name ?? '—' }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($order->subtotal, 2) }}</td>
                    <td class="text-end small text-danger">{{ $currency }} {{ fmt_num($order->discount_total, 2) }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($order->tax_total, 2) }}</td>
                    <td class="text-end small fw-semibold {{ ($order->gross_profit ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $currency }} {{ fmt_num($order->gross_profit ?? 0, 2) }}</td>
                    <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num($order->grand_total, 2) }}</td>
                    @if(auth()->user()?->isPlatformSuperAdmin())
                        <td class="text-end no-print">
                            <form method="POST"
                                  action="{{ route('reports.pos-bills.destroy', $order) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Bill {{ $order->order_no }} permanently delete karein? Stock reverse ho jayega.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete bill">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ auth()->user()?->isPlatformSuperAdmin() ? 11 : 10 }}" class="text-center text-secondary py-4">No paid bills in this date range.</td>
                </tr>
            @endforelse
            </tbody>
            @if($orders->isNotEmpty())
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="5" class="text-end">Totals</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totalSubtotal, 2) }}</td>
                    <td class="text-end text-danger">{{ $currency }} {{ fmt_num($totalDiscount, 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num($totalTax, 2) }}</td>
                    <td class="text-end text-success">{{ $currency }} {{ fmt_num($totalGrossProfit, 2) }}</td>
                    <td class="text-end text-primary">{{ $currency }} {{ fmt_num($totalGrand, 2) }}</td>
                    @if(auth()->user()?->isPlatformSuperAdmin())
                        <td class="no-print"></td>
                    @endif
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@include('reports.partials.print-portrait')
@endsection
