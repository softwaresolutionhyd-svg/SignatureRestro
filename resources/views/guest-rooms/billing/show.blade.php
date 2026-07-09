@extends('layouts.admin')
@section('title', 'Bill ' . $bill->bill_no)
@section('content')
@include('guest-rooms.partials.subnav')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card border-0 shadow-sm"><div class="card-body">
@if($bill->booking?->status === 'checked_in')
<div class="alert alert-info py-2 small mb-3">Running bill — guest is still checked in. Amount may change until checkout.</div>
@endif
<h4 class="fw-bold">{{ $bill->bill_no }}</h4>
<p>Guest: <strong>{{ $bill->booking?->guestDisplayName() }}</strong> | Booking: {{ $bill->booking?->booking_no }} | Room(s): {{ $bill->booking?->roomNumbersLabel() }}</p>
<table class="table table-sm">
    <tr><td>Room charges</td><td class="text-end">{{ number_format($bill->room_charges, 2) }}</td></tr>
    <tr><td>Extra charges</td><td class="text-end">{{ number_format($bill->extra_charges, 2) }}</td></tr>
    <tr><td>Discount</td><td class="text-end">-{{ number_format($bill->discount, 2) }}</td></tr>
    <tr><td>Tax</td><td class="text-end">{{ number_format($bill->tax_amount, 2) }}</td></tr>
    <tr class="fw-bold"><td>Total</td><td class="text-end">{{ number_format($bill->total, 2) }}</td></tr>
    <tr><td>Advance (paid)</td><td class="text-end text-success">{{ number_format($bill->paid_amount, 2) }}</td></tr>
    <tr><td>Balance</td><td class="text-end text-danger fw-bold">{{ number_format($bill->balance, 2) }}</td></tr>
</table>

@if($bill->balance > 0)
<hr>
<h6 class="fw-semibold">Receive balance payment</h6>
<form method="POST" action="{{ route('guest-rooms.billing.pay', $bill) }}">
    @csrf
    @include('guest-rooms.partials.payment-settlement', [
        'advance' => (float) $bill->paid_amount,
        'total' => (float) $bill->total,
    ])
    <button type="submit" class="btn btn-success">Received</button>
</form>
@else
<div class="alert alert-success">Bill fully paid.</div>
@endif

<div class="d-flex flex-wrap gap-2 mt-3">
    <a href="{{ route('guest-rooms.billing.receipt', ['bill' => $bill, 'print' => 1]) }}" class="btn btn-primary" target="_blank"><i class="bi bi-printer me-1"></i> Print bill</a>
    <a href="{{ route('guest-rooms.billing.receipt', $bill) }}" class="btn btn-outline-primary" target="_blank">Preview receipt</a>
    <a href="{{ route('guest-rooms.billing.index') }}" class="btn btn-outline-secondary">← Bills</a>
</div>
</div></div>
@endsection
