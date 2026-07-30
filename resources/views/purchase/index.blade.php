@extends('layouts.admin')

@section('title', __('Purchase') . ' - ' . config('app.name'))
@section('page_title', __('Purchase'))

@section('content')
    @include('purchase.partials.subnav')

    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">{{ __('Vendors') }}</div>
                    <div class="kpi-value">{{ fmt_num($kpis['vendors'], 0) }}</div>
                    <div class="small text-secondary">{{ __('Supplier master') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">{{ __('RFQs') }}</div>
                    <div class="kpi-value">{{ fmt_num($kpis['rfqs'], 0) }}</div>
                    <div class="small text-secondary">{{ __('Draft purchase requests') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">{{ __('Confirmed POs') }}</div>
                    <div class="kpi-value">{{ fmt_num($kpis['confirmed'], 0) }}</div>
                    <div class="small text-secondary">{{ __('Waiting for receipt') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">{{ __('Received') }}</div>
                    <div class="kpi-value">{{ fmt_num($kpis['received'], 0) }}</div>
                    <div class="small text-secondary">{{ __('Stock updated') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">{{ __('Recent RFQs / POs') }}</div>
            <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View all') }}</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Vendor') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Total') }}</th>
                    <th class="text-end">{{ __('Updated') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentOrders as $o)
                    <tr>
                        <td class="fw-semibold">
                            <a class="text-decoration-none" href="{{ route('purchase.orders.edit', $o) }}">{{ $o->number }}</a>
                        </td>
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
                        <td class="text-end">{{ fmt_num((float)$o->grand_total, 2) }}</td>
                        <td class="text-end text-secondary small">{{ $o->updated_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('No orders yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

