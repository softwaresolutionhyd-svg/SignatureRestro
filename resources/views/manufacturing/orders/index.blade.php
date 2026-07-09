@extends('layouts.admin')

@section('title', 'Production Orders — Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">Production orders</h4>
    <div class="text-secondary small">Complete a draft order to consume components and receive finished goods into inventory.</div>
</div>

@include('manufacturing.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">All orders</span>
        <a href="{{ route('manufacturing.orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> New</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Ref</th>
                <th>BoM / Product</th>
                <th>Qty</th>
                <th>Status</th>
                <th>User</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($orders as $o)
                <tr>
                    <td><a href="{{ route('manufacturing.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->reference }}</a></td>
                    <td>
                        <div class="small text-secondary">{{ $o->bom->name ?? '' }}</div>
                        {{ $o->bom->finishedProduct->name ?? '—' }}
                    </td>
                    <td>{{ fmt_num((float) $o->qty_ordered, 3) }}</td>
                    <td>
                        @if($o->status === \App\Models\ManufacturingOrder::STATUS_DONE)
                            <span class="badge bg-success">Done</span>
                        @elseif($o->status === \App\Models\ManufacturingOrder::STATUS_DRAFT)
                            <span class="badge bg-warning text-dark">Draft</span>
                        @else
                            <span class="badge bg-secondary">{{ $o->status }}</span>
                        @endif
                    </td>
                    <td class="small">{{ $o->user->name ?? '—' }}</td>
                    <td class="small text-secondary">{{ $o->created_at->format('d M Y H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('manufacturing.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">No orders.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div class="card-body border-top">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
