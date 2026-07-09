@extends('layouts.admin')

@section('title', 'Stock Moves - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Stock Moves')

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="fw-semibold">Moves</div>
                <div class="btn-group ms-2" role="group" aria-label="Type filter">
                    <a class="btn btn-sm btn-outline-secondary {{ $type ? '' : 'active' }}" href="{{ route('inventory.moves.index') }}">All</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $type === 'in' ? 'active' : '' }}" href="{{ route('inventory.moves.index', ['type' => 'in']) }}">IN</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $type === 'out' ? 'active' : '' }}" href="{{ route('inventory.moves.index', ['type' => 'out']) }}">OUT</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $type === 'adjust' ? 'active' : '' }}" href="{{ route('inventory.moves.index', ['type' => 'adjust']) }}">ADJUST</a>
                    <a class="btn btn-sm btn-outline-secondary {{ $type === 'wastage' ? 'active' : '' }}" href="{{ route('inventory.moves.index', ['type' => 'wastage']) }}">WASTAGE</a>
                </div>
            </div>
            <a href="{{ route('inventory.moves.create') }}" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> Stock Adjustment
            </a>
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
                    <th>Reference</th>
                    <th>Reason / Note</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                @forelse($moves as $m)
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
                        @php
                            $uomLabel = $m->uom ?? $m->product->uom;
                            $factor = (float) ($m->factor_to_base ?? 0);
                            $hasFactor = $factor > 0;
                            $qtyDisp = $hasFactor ? ((float) ($m->qty_uom ?? ($m->qty / $factor))) : (float) $m->qty;
                            $beforeDisp = $hasFactor ? ((float) $m->qty_before / $factor) : (float) $m->qty_before;
                            $afterDisp = $hasFactor ? ((float) $m->qty_after / $factor) : (float) $m->qty_after;
                        @endphp
                        <td class="text-end">{{ fmt_num($qtyDisp, 3) }} {{ $uomLabel }}</td>
                        <td class="text-end text-secondary">{{ fmt_num($beforeDisp, 3) }} {{ $uomLabel }}</td>
                        <td class="text-end fw-semibold">{{ fmt_num($afterDisp, 3) }} {{ $uomLabel }}</td>
                        <td class="text-secondary small">{{ $m->reference ?? '—' }}</td>
                        <td class="text-secondary small">{{ $m->note ?? '—' }}</td>
                        <td class="text-secondary small">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">No moves yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            {{ $moves->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

