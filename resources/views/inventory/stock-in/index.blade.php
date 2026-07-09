@extends('layouts.admin')

@section('title', 'Stock in (PO receive) — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('inventory.partials.subnav')

    <div class="mb-3">
        <h4 class="fw-bold mb-1">Stock in</h4>
        <p class="text-secondary small mb-0">
            Yahan <strong>confirmed</strong> purchase orders (RFQ → Confirm PO ke baad) dikhte hain.
            <strong>Receive</strong> se stock barhta hai aur pehle <strong>Warehouse</strong> department mein jata hai.
            Phir <a href="{{ route('inventory.issues.create') }}">Issue Stock</a> se departments ko bhejein.
        </p>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>Confirmed POs — receive pending</span>
            <span class="badge bg-primary">{{ $orders->total() }} open</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>PO #</th>
                    <th>Vendor</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                    <th>Confirmed</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="fw-semibold">{{ $o->number }}</td>
                        <td class="text-secondary">{{ $o->vendor->name }}</td>
                        <td>
                            <span class="badge text-bg-{{ ($o->purchase_type ?? 'debit') === 'credit' ? 'warning' : 'info' }}">
                                {{ strtoupper($o->purchase_type ?? 'debit') }}
                            </span>
                            <span class="badge text-bg-{{ ($o->payment_status ?? 'paid') === 'paid' ? 'success' : 'secondary' }}">
                                {{ strtoupper($o->payment_status ?? 'paid') }}
                            </span>
                        </td>
                        <td class="text-end">{{ fmt_num((float) $o->grand_total, 2) }}</td>
                        <td class="text-secondary small">{{ $o->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <a href="{{ route('purchase.orders.edit', $o) }}" class="btn btn-sm btn-outline-secondary">PO detail</a>
                                <form method="POST" action="{{ route('inventory.stock-in.receive', $o) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Receive PO {{ $o->number }} and add stock (FIFO)?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">
                            Koi confirmed PO receive ke liye pending nahi. Pehle Purchase se RFQ confirm karein.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer bg-white">{{ $orders->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
