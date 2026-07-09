@extends('layouts.admin')
@section('title', 'Order Taker — ' . config('app.name'))
@section('page-title', 'Order Taker')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Order Taker</h4>
        <div class="text-secondary small">Naya order POS pending bill ban jata hai aur kitchen screen par seedha dikhai deta hai.</div>
    </div>
    <a href="{{ route('order-taker.create') }}" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Naya Order
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
        <span>POS Pending Bills</span>
        <span class="badge text-bg-warning">{{ $pendingBills->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order</th>
                    <th>Type</th>
                    <th>Guest</th>
                    <th>Table / Room</th>
                    <th>Waiter</th>
                    <th>Serve At</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pendingBills as $o)
                @php
                    $tableRoom = [];
                    if ($o->table) {
                        $tableRoom[] = $o->table->name;
                    }
                    if ($o->room_no) {
                        $tableRoom[] = $o->room_no;
                    }
                    $isBooking = $o->customerTypeKey() === 'booking';
                    $isAstOffr = $o->customerTypeKey() === 'ast_offr';
                @endphp
                <tr>
                    <td class="small">
                        {{ $o->order_no }}
                        @if($o->isFromOrderTaker())
                            <span class="badge text-bg-info ms-1">Order Taker</span>
                            @php $orderAt = $o->ready_for_pos_at ?? $o->created_at; @endphp
                            @if($orderAt)
                                <div class="text-secondary">Order {{ $orderAt->format('H:i') }}</div>
                            @endif
                        @else
                            <span class="badge text-bg-secondary ms-1">POS</span>
                        @endif
                        @if($o->kitchen_completed_at)
                            <div class="text-success">Served {{ $o->kitchen_completed_at->format('H:i') }}</div>
                        @endif
                    </td>
                    <td>
                        @if($isAstOffr)
                            <span class="badge text-bg-info">{{ \App\Models\PosOrder::MESS_BILL_LABEL }}</span>
                        @elseif($isBooking)
                            <span class="badge text-bg-primary">In-House</span>
                        @else
                            <span class="badge text-bg-secondary">Walk-In</span>
                        @endif
                    </td>
                    <td class="small">{{ $o->guest_name ?: '—' }}</td>
                    <td class="small">{{ $tableRoom !== [] ? implode(' / ', $tableRoom) : '—' }}</td>
                    <td class="small">{{ $o->waiter_name ?: '—' }}</td>
                    <td class="small">{{ $o->serveScheduleLabel() ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $o->pendingKitchenStatusBadgeClass() }}">{{ $o->pendingKitchenStatusLabel() }}</span>
                    </td>
                    <td>{{ $o->items_count }}</td>
                    <td class="text-end">{{ fmt_num($o->grand_total, 2) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('order-taker.edit', $o) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add items
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-secondary text-center py-4">POS par koi pending bill nahi — naya order banayein.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
