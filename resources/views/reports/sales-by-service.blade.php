@extends('layouts.admin')
@section('title', $label.' bills — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $label }} bills</h4>
        <div class="text-secondary small">
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            · {{ $orders->count() }} bills
        </div>
    </div>
    <a href="{{ route('reports.sales', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary btn-sm">← Sales Report</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        {{ $label }}
        <span class="badge bg-primary">{{ $orders->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Bill #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Cashier</th>
                    <th class="text-end">Total</th>
                    <th class="text-center" style="width:56px;">View</th>
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
                    <td class="small">{{ $order->user?->name ?: '—' }}</td>
                    <td class="text-end small fw-bold">{{ $currency }} {{ fmt_num((float) $order->grand_total, 2) }}</td>
                    <td class="text-center">
                        <a href="{{ route('reports.sales.show', $order) }}"
                           class="btn btn-sm btn-outline-primary px-2 py-1"
                           title="View order detail">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">Is period mein koi {{ strtolower($label) }} bill nahi</td></tr>
            @endforelse
            </tbody>
            @if($orders->count())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="6">Totals</td>
                    <td class="text-end">{{ $currency }} {{ fmt_num((float) $orders->sum('grand_total'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
