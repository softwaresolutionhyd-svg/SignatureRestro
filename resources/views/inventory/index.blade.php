@extends('layouts.admin')

@section('title', 'Inventory - ' . config('app.name'))
@section('page_title', 'Inventory')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('inventory.partials.subnav')

    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Products</div>
                    <div class="kpi-value">{{ fmt_num($kpis['products'], 0) }}</div>
                    <div class="small text-secondary">All products</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Active Products</div>
                    <div class="kpi-value">{{ fmt_num($kpis['active_products'], 0) }}</div>
                    <div class="small text-secondary">Available for stock moves</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Total On Hand</div>
                    <div class="kpi-value">{{ fmt_num($kpis['on_hand_total'], 3) }}</div>
                    <div class="small text-secondary">Sum of quantities</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Moves Today</div>
                    <div class="kpi-value">{{ fmt_num($kpis['moves_today'], 0) }}</div>
                    <div class="small text-secondary">Stock activity</div>
                </div>
            </div>
        </div>
    </div>

    @if($kpis['low_stock'] > 0 || $kpis['out_of_stock'] > 0)
    <div class="alert border-0 mt-3 d-flex align-items-center justify-content-between gap-3 py-2 px-3 flex-wrap"
         style="background:#fffbeb;border-left:4px solid #f59e0b!important;">
        <div class="d-flex align-items-center gap-2">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" style="color:#f59e0b;flex-shrink:0;">
                <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div>
                @if($kpis['out_of_stock'] > 0)
                    <span class="text-danger fw-semibold">{{ $kpis['out_of_stock'] }} out of stock</span>&nbsp;&nbsp;
                @endif
                @if($kpis['low_stock'] > 0)
                    <span class="text-warning fw-semibold">{{ $kpis['low_stock'] }} below reorder level</span>
                @endif
            </div>
        </div>
        <a href="{{ route('inventory.low-stock') }}" class="btn btn-warning btn-sm">View Low Stock Report →</a>
    </div>
    @endif

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Recent stock moves</div>
            <a href="{{ route('inventory.moves.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Before</th>
                    <th class="text-end">After</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentMoves as $m)
                    <tr>
                        <td class="text-secondary small">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="fw-semibold">{{ $m->product->name }}</div>
                            <div class="text-secondary small">{{ $m->product->sku }}</div>
                        </td>
                        <td>
                            @php
                                $badge = match ($m->type) {
                                    'in' => 'success',
                                    'out' => 'danger',
                                    'wastage' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ strtoupper($m->type) }}</span>
                        </td>
                        <td class="text-end">{{ fmt_num((float)$m->qty, 3) }} {{ $m->product->uom }}</td>
                        <td class="text-end text-secondary">{{ fmt_num((float)$m->qty_before, 3) }}</td>
                        <td class="text-end fw-semibold">{{ fmt_num((float)$m->qty_after, 3) }}</td>
                        <td class="text-secondary small">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No moves yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

