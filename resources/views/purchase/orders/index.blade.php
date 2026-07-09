@extends('layouts.admin')

@section('title', 'RFQs / POs - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / RFQs & POs')

@section('content')
    @include('purchase.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="fw-semibold">Orders</div>
                <div class="btn-group ms-2" role="group" aria-label="Status filter">
                    <a class="btn btn-sm btn-outline-secondary {{ $status ? '' : 'active' }}" href="{{ route('purchase.orders.index') }}">All</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $status === 'rfq' ? 'active' : '' }}" href="{{ route('purchase.orders.index', ['status' => 'rfq']) }}">RFQ</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $status === 'confirmed' ? 'active' : '' }}" href="{{ route('purchase.orders.index', ['status' => 'confirmed']) }}">Confirmed</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $status === 'received' ? 'active' : '' }}" href="{{ route('purchase.orders.index', ['status' => 'received']) }}">Received</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $status === 'cancelled' ? 'active' : '' }}" href="{{ route('purchase.orders.index', ['status' => 'cancelled']) }}">Cancelled</a>
                </div>
            </div>
            <a href="{{ route('purchase.orders.create') }}" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> New Purchase
            </a>
        </div>

        <div class="alert alert-light border small mb-0 py-2">
            <strong>Confirmed</strong> PO ka stock receive ab <a href="{{ route('inventory.stock-in.index') }}">Inventory → Stock in</a> se hota hai (Inventory create access).
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Number</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($orders as $o)
                    <tr>
                        <td class="fw-semibold">{{ $o->number }}</td>
                        <td class="text-secondary">{{ $o->vendor->name }}</td>
                        <td>
                            @php
                                $badge = match ($o->status) {
                                    'rfq' => 'secondary',
                                    'confirmed' => 'primary',
                                    'received' => 'success',
                                    default => 'danger',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ strtoupper($o->status) }}</span>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ ($o->purchase_type ?? 'debit') === 'credit' ? 'warning' : 'info' }}">
                                {{ strtoupper($o->purchase_type ?? 'debit') }}
                            </span>
                            <span class="badge text-bg-{{ ($o->payment_status ?? 'paid') === 'paid' ? 'success' : 'secondary' }}">
                                {{ strtoupper($o->payment_status ?? 'paid') }}
                            </span>
                        </td>
                        <td class="text-end">{{ fmt_num((float)$o->grand_total, 2) }}</td>
                        <td class="text-end text-secondary small">{{ $o->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('purchase.orders.edit', $o) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            {{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

