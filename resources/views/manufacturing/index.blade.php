@extends('layouts.admin')

@section('title', 'Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Manufacturing</h4>
        <div class="text-secondary small">BoMs define what to consume; production orders move stock via inventory (FIFO).</div>
    </div>
</div>

@include('manufacturing.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Active BoMs</div>
                <div class="kpi-value">{{ fmt_num($kpis['boms'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Total BoMs</div>
                <div class="kpi-value">{{ fmt_num($kpis['boms_total'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Draft orders</div>
                <div class="kpi-value">{{ fmt_num($kpis['orders_draft']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Completed (month)</div>
                <div class="kpi-value">{{ fmt_num($kpis['orders_done_month'], 0) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Recent production orders</span>
        <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Ref</th>
                <th>Finished product</th>
                <th>Qty</th>
                <th>Status</th>
                <th>By</th>
                <th>When</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recentOrders as $o)
                <tr>
                    <td><a href="{{ route('manufacturing.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->reference }}</a></td>
                    <td>{{ $o->bom->finishedProduct->name ?? '—' }}</td>
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
                    <td class="small text-secondary">{{ $o->user->name ?? '—' }}</td>
                    <td class="small text-secondary">{{ $o->created_at->format('d M H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No orders yet. Create a BoM, then start a production order.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
