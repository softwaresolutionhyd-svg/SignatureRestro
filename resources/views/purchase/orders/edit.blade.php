@extends('layouts.admin')

@section('title', $order->number . ' - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / ' . $order->number)

@section('content')
    @include('purchase.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="fw-semibold">{{ $order->number }}</div>
                @php
                    $badge = match ($order->status) {
                        'rfq' => 'secondary',
                        'confirmed' => 'primary',
                        'received' => 'success',
                        default => 'danger',
                    };
                @endphp
                <span class="badge text-bg-{{ $badge }}">{{ strtoupper($order->status) }}</span>
                <span class="badge text-bg-{{ ($order->purchase_type ?? 'debit') === 'credit' ? 'warning' : 'info' }}">
                    {{ strtoupper($order->purchase_type ?? 'debit') }}
                </span>
                <span class="badge text-bg-{{ ($order->payment_status ?? 'paid') === 'paid' ? 'success' : 'secondary' }}">
                    {{ strtoupper($order->payment_status ?? 'paid') }}
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($order->status === 'rfq')
                    <form method="POST" action="{{ route('purchase.orders.confirm', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-check2-circle me-1"></i> Confirm PO
                        </button>
                    </form>
                @endif
                @if(($order->purchase_type ?? 'debit') === 'credit' && ($order->payment_status ?? 'unpaid') !== 'paid' && $order->status !== 'cancelled')
                    <form method="POST" action="{{ route('purchase.orders.pay', $order) }}" onsubmit="return confirm('Mark this credit purchase as paid?');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-cash-coin me-1"></i> Mark Paid
                        </button>
                    </form>
                @endif

            </div>
        </div>

        <div class="card-body">
            @if($order->status === 'confirmed')
                <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <strong>Stock receive</strong> ab Purchase se nahi hota — confirmed PO yahan se <strong>Inventory → Stock in</strong> par dikhte hain.
                        Jis user ke paas <strong>Inventory</strong> access ho (create / stock-in), wahi receive kar sakta hai.
                    </div>
                    <a href="{{ route('inventory.stock-in.index') }}" class="btn btn-sm btn-primary flex-shrink-0">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Stock in par jayein
                    </a>
                </div>
            @endif
            @if($order->status === 'rfq')
                <form method="POST" action="{{ route('purchase.orders.update', $order) }}">
                    @method('PUT')
                    @include('purchase.orders.form')
                </form>
            @else
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="text-secondary small">Vendor</div>
                        <div class="fw-semibold">{{ $order->vendor->name }}</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="text-secondary small">Order date</div>
                        <div class="fw-semibold">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="text-secondary small">Expected date</div>
                        <div class="fw-semibold">{{ $order->expected_date?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <div class="text-secondary small">Total</div>
                        <div class="fw-bold">{{ fmt_num((float)$order->grand_total, 2) }}</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="text-secondary small">Purchase type</div>
                        <div class="fw-semibold text-uppercase">{{ $order->purchase_type ?? 'debit' }}</div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="text-secondary small">Payment</div>
                        <div class="fw-semibold text-uppercase">
                            {{ $order->payment_status ?? 'paid' }}
                            @if(($order->payment_status ?? 'paid') === 'paid' && $order->paid_at)
                                <span class="text-secondary small">({{ $order->paid_at->format('Y-m-d H:i') }})</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="table-responsive border rounded-3 mt-3">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit price</th>
                            <th class="text-end">Tax %</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($order->lines as $l)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $l->product->name }}</div>
                                    <div class="text-secondary small">{{ $l->product->sku }}</div>
                                </td>
                                <td class="text-end">{{ fmt_num((float)$l->qty, 3) }} {{ $l->uom }}</td>
                                <td class="text-end">{{ fmt_num((float)$l->unit_price, 2) }}</td>
                                <td class="text-end">{{ fmt_num((float)$l->tax_percent, 3) }}</td>
                                <td class="text-end fw-semibold">{{ fmt_num((float)$l->total, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mt-2 justify-content-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="d-flex justify-content-between">
                                <div class="text-secondary">Subtotal</div>
                                <div class="fw-semibold">{{ fmt_num((float)$order->subtotal, 2) }}</div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="text-secondary">Tax</div>
                                <div class="fw-semibold">{{ fmt_num((float)$order->tax_total, 2) }}</div>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <div class="fw-semibold">Total</div>
                                <div class="fw-bold">{{ fmt_num((float)$order->grand_total, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

