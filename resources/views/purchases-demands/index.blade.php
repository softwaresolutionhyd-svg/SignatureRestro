@extends('layouts.admin')

@section('title', 'Purchases & Demands — ' . config('app.name'))
@section('page_title', 'Purchases & Demands')

@push('head')
<style>
.pd-hub {
    max-width: 720px;
    margin: 0 auto;
}
.pd-card {
    border: 1px solid #e8e2d8;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.pd-card-header {
    background: #f3ebe0;
    padding: .9rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
}
.pd-card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #8b1e1e;
    letter-spacing: .01em;
}
.pd-tabs {
    display: inline-flex;
    background: #e5ddd2;
    border-radius: 999px;
    padding: 3px;
    gap: 2px;
}
.pd-tab {
    border: 0;
    background: transparent;
    color: #5c5348;
    font-size: .82rem;
    font-weight: 600;
    padding: .35rem .9rem;
    border-radius: 999px;
    text-decoration: none;
}
.pd-tab.active {
    background: #fff;
    color: #1f2937;
    box-shadow: 0 1px 2px rgba(0,0,0,.06);
}
.pd-cols {
    display: grid;
    grid-template-columns: 72px 1fr auto;
    gap: .75rem;
    padding: .55rem 1.1rem;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .06em;
    color: #9ca3af;
    text-transform: uppercase;
    border-bottom: 1px solid #f1f5f9;
}
.pd-row {
    display: grid;
    grid-template-columns: 72px 1fr auto;
    gap: .75rem;
    align-items: center;
    padding: .85rem 1.1rem;
    border-bottom: 1px solid #f1f5f9;
}
.pd-row:last-child { border-bottom: 0; }
.pd-date {
    color: #6b7280;
    font-size: .92rem;
    line-height: 1.2;
}
.pd-order-no {
    font-weight: 700;
    color: #1e3a8a;
    text-decoration: none;
    font-size: .98rem;
}
.pd-order-no:hover { text-decoration: underline; }
.pd-status {
    color: #9ca3af;
    font-size: .8rem;
    margin-top: .1rem;
}
.pd-amount {
    font-weight: 600;
    color: #111827;
    text-align: right;
    white-space: nowrap;
}
.pd-actions {
    display: flex;
    gap: .35rem;
    justify-content: flex-end;
    margin-top: .35rem;
}
.pd-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: #9ca3af;
}
@media (max-width: 520px) {
    .pd-cols, .pd-row {
        grid-template-columns: 56px 1fr;
    }
    .pd-amount-wrap {
        grid-column: 2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .5rem;
    }
}
</style>
@endpush

@section('content')
@include('partials.super-admin-reason-modal')

<div class="pd-hub">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="pd-card">
        <div class="pd-card-header">
            <h1 class="pd-card-title">Purchases &amp; Demands</h1>
            <div class="pd-tabs" role="tablist">
                @if($canPurchase)
                    <a class="pd-tab {{ $tab === 'purchases' ? 'active' : '' }}"
                       href="{{ route('purchases-demands.index', ['tab' => 'purchases']) }}">Purchases</a>
                @endif
                @if($canDemand)
                    <a class="pd-tab {{ $tab === 'demands' ? 'active' : '' }}"
                       href="{{ route('purchases-demands.index', ['tab' => 'demands']) }}">Demands</a>
                @endif
            </div>
        </div>

        <div class="pd-cols">
            <div>Date</div>
            <div>Order</div>
            <div class="text-end">Amount</div>
        </div>

        @if($tab === 'purchases')
            @forelse($purchases as $o)
                @php
                    $date = $o->order_date ?? $o->created_at;
                    $dateLabel = $date ? \Illuminate\Support\Carbon::parse($date)->format('j M') : '—';
                    $statusLabel = ucfirst((string) $o->status);
                @endphp
                <div class="pd-row">
                    <div class="pd-date">{{ $dateLabel }}</div>
                    <div>
                        <a class="pd-order-no" href="{{ route('purchase.orders.edit', $o) }}">{{ $o->number }}</a>
                        <div class="pd-status">{{ $statusLabel }}</div>
                        @if($isSuperAdmin)
                            <div class="pd-actions">
                                <form method="POST" action="{{ route('purchases-demands.purchases.begin-edit', $o) }}" class="d-inline">
                                    @csrf
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-sa-reason-submit
                                            data-sa-reason-title="Reason for edit">Edit</button>
                                </form>
                                <form method="POST" action="{{ route('purchases-demands.purchases.destroy', $o) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-sa-reason-submit
                                            data-sa-reason-title="Reason for delete">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <div class="pd-amount-wrap">
                        <div class="pd-amount">{{ $currency }} {{ fmt_num((float) $o->grand_total, 0) }}</div>
                    </div>
                </div>
            @empty
                <div class="pd-empty">No purchases yet.</div>
            @endforelse
        @else
            @forelse($demands as $d)
                @php
                    $dateLabel = $d->demand_date ? $d->demand_date->format('j M') : '—';
                    $statusLabel = ucfirst((string) $d->status);
                    $lineCount = $d->lines?->count() ?? 0;
                @endphp
                <div class="pd-row">
                    <div class="pd-date">{{ $dateLabel }}</div>
                    <div>
                        <span class="pd-order-no">{{ $d->demand_no }}</span>
                        <div class="pd-status">{{ $statusLabel }}@if($d->department) · {{ $d->department->name }}@endif</div>
                        @if($isSuperAdmin)
                            <div class="pd-actions">
                                <form method="POST" action="{{ route('purchases-demands.demands.begin-edit', $d) }}" class="d-inline">
                                    @csrf
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-sa-reason-submit
                                            data-sa-reason-title="Reason for edit">Edit</button>
                                </form>
                                <form method="POST" action="{{ route('purchases-demands.demands.destroy', $d) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-sa-reason-submit
                                            data-sa-reason-title="Reason for delete">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <div class="pd-amount-wrap">
                        <div class="pd-amount text-secondary">{{ $lineCount }} item{{ $lineCount === 1 ? '' : 's' }}</div>
                    </div>
                </div>
            @empty
                <div class="pd-empty">No demands yet.</div>
            @endforelse
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
        @if($canPurchase)
            <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-outline-secondary">All purchase orders</a>
            <a href="{{ route('purchase.orders.create') }}" class="btn btn-sm btn-success">New purchase</a>
        @endif
        @if($canDemand)
            <a href="{{ route('demand.index') }}" class="btn btn-sm btn-outline-secondary">Demand module</a>
        @endif
    </div>
</div>
@endsection
