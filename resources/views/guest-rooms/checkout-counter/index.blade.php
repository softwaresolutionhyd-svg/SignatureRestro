@extends('layouts.admin')
@section('title', 'Checkout Counter')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <h4 class="fw-bold mb-0">Checkout Counter</h4>
    <p class="text-secondary small mb-0">Room + Cafe complete bill — yahan se dekhein aur checkout karein.</p>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if(($walkInPendingBills ?? collect())->isNotEmpty())
<div class="card border-0 shadow-sm mb-3 border-start border-primary border-4">
    <div class="card-header bg-white fw-semibold">Walk-in / Cafe pending bills ({{ $walkInPendingBills->count() }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Type</th>
                        <th>Guest / Table</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th class="text-end">Collect</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($walkInPendingBills as $bill)
                        <tr>
                            <td class="fw-semibold">
                                {{ $bill->order_no }}
                                @if($bill->isFromOrderTaker())
                                    <span class="badge text-bg-info ms-1">OT</span>
                                @endif
                            </td>
                            <td>{{ $bill->customerTypeLabel() }}</td>
                            <td>
                                {{ $bill->guest_name ?: ($bill->table?->name ?: 'Walk-in') }}
                                @if($bill->waiter_name)
                                    <div class="small text-secondary">Waiter: {{ $bill->waiter_name }}</div>
                                @endif
                            </td>
                            <td>{{ $bill->items_count }}</td>
                            <td class="fw-semibold">{{ fmt_num((float) $bill->grand_total, 2) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('guest-rooms.checkout-counter.cafe-settle', $bill) }}" class="d-inline-flex gap-1 align-items-center">
                                    @csrf
                                    <select name="payment_method" class="form-select form-select-sm" style="width:6rem" required>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success">Pay</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($departuresToday->isNotEmpty())
<div class="card border-0 shadow-sm mb-3 border-start border-warning border-4">
    <div class="card-header bg-white fw-semibold">Aaj ki departures ({{ $departuresToday->count() }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Room(s)</th>
                        <th>Room charges</th>
                        <th>Cafe</th>
                        <th>Complete bill</th>
                        <th>Collect now</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($departuresToday as $booking)
                        @php
                            $row = $queue->first(fn ($q) => (int) $q['booking']->id === (int) $booking->id);
                        @endphp
                        <tr @if($row['cafe_pending_count'] > 0) class="table-info"@endif>
                            <td class="fw-semibold">{{ $booking->guestDisplayName() }}</td>
                            <td>{{ $booking->roomNumbersLabel() }}</td>
                            <td>{{ fmt_num($row['room_balance_due'] ?? 0, 2) }}</td>
                            <td>
                                @if(($row['cafe_pending_count'] ?? 0) > 0)
                                    <span class="text-danger">{{ fmt_num($row['cafe_pending_total'] ?? 0, 2) }} pending</span>
                                @else
                                    {{ fmt_num($row['cafe_total'] ?? 0, 2) }}
                                @endif
                            </td>
                            <td class="fw-semibold">{{ fmt_num($row['complete_bill_total'] ?? 0, 2) }}</td>
                            <td class="fw-bold text-danger">{{ fmt_num($row['total_due_now'] ?? 0, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('guest-rooms.checkout-counter.show', $booking) }}" class="btn btn-sm btn-warning">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Checked-in guests ({{ $queue->count() }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Room(s)</th>
                        <th>Check-out</th>
                        <th>Room due</th>
                        <th>Cafe total</th>
                        <th>Complete bill</th>
                        <th>Collect now</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queue as $row)
                        @php $booking = $row['booking']; @endphp
                        <tr @if($row['cafe_pending_count'] > 0) class="table-info"@endif>
                            <td class="fw-semibold">{{ $booking->guestDisplayName() }}</td>
                            <td>{{ $booking->roomNumbersLabel() }}</td>
                            <td>
                                {{ $booking->check_out_date ? fmt_date($booking->check_out_date) : '—' }}
                                @if($row['departure_today'])
                                    <span class="badge text-bg-warning text-dark ms-1">Today</span>
                                @endif
                            </td>
                            <td>{{ fmt_num($row['room_balance_due'], 2) }}</td>
                            <td>
                                {{ fmt_num($row['cafe_total'], 2) }}
                                @if($row['cafe_pending_count'] > 0)
                                    <span class="badge text-bg-danger ms-1">{{ $row['cafe_pending_count'] }} pending</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ fmt_num($row['complete_bill_total'], 2) }}</td>
                            <td class="fw-bold {{ $row['total_due_now'] > 0 ? 'text-danger' : 'text-success' }}">
                                {{ fmt_num($row['total_due_now'], 2) }}
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('guest-rooms.checkout-counter.show', $booking) }}" class="btn btn-sm btn-outline-primary">Bill dekhein</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-secondary p-3">Koi checked-in guest nahi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
