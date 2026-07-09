@extends('layouts.admin')
@section('title', 'Billing')
@section('content')
@include('guest-rooms.partials.subnav')
<h4 class="fw-bold mb-3">Room Bills</h4>
<form class="mb-3" method="GET"><select name="payment_status" class="form-select form-select-sm" style="width:auto;display:inline-block" onchange="this.form.submit()">
<option value="">All</option><option value="unpaid" @selected(request('payment_status')==='unpaid')>Unpaid</option><option value="partial" @selected(request('payment_status')==='partial')>Partial</option><option value="paid" @selected(request('payment_status')==='paid')>Paid</option></select></form>
<div class="card border-0 shadow-sm"><div class="card-body p-0">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Bill #</th><th>Booking</th><th>Guest</th><th>Room(s)</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end pe-3"></th></tr></thead>
<tbody>@forelse($bills as $bill)<tr><td class="ps-3">{{ $bill->bill_no }}</td><td>{{ $bill->booking?->booking_no }}</td><td>{{ $bill->booking?->guestDisplayName() }}</td><td>{{ $bill->booking?->roomNumbersLabel() }}</td>
<td>{{ number_format($bill->total,2) }}</td><td>{{ number_format($bill->paid_amount,2) }}</td><td>{{ number_format($bill->balance,2) }}</td><td>{{ ucfirst($bill->payment_status) }}</td>
<td class="text-end pe-3">
    <a href="{{ route('guest-rooms.billing.show', $bill) }}" class="btn btn-sm btn-outline-primary">View</a>
    <a href="{{ route('guest-rooms.billing.receipt', ['bill' => $bill, 'print' => 1]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print"><i class="bi bi-printer"></i></a>
</td></tr>
@empty<tr><td colspan="9" class="text-center py-4">No bills yet.</td></tr>@endforelse</tbody></table>
@if($bills->hasPages())<div class="p-2">{{ $bills->links() }}</div>@endif</div></div>
@endsection