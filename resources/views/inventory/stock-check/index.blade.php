@extends('layouts.admin')

@section('title', 'Stock check — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('inventory.partials.subnav')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Stock check</h4>
            <p class="text-secondary small mb-0">Physical count (base UOM) → submit → company admin approve → stock adjust (FIFO).</p>
        </div>
        <a href="{{ route('inventory.stock-check.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Naya count
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-secondary me-1">Filter:</span>
            <a class="btn btn-sm {{ $status ? 'btn-outline-secondary' : 'btn-secondary' }}" href="{{ route('inventory.stock-check.index') }}">All</a>
            @foreach (['draft' => 'Draft', 'pending_approval' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $lab)
                <a class="btn btn-sm {{ $status === $k ? 'btn-secondary' : 'btn-outline-secondary' }}"
                   href="{{ route('inventory.stock-check.index', ['status' => $k]) }}">{{ $lab }}</a>
            @endforeach
        </div>
    </div>

    <div class="table-responsive card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Status</th>
                <th class="text-end">Lines</th>
                <th>Updated</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($checks as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->number }}</td>
                    <td>{{ $c->title ?: '—' }}</td>
                    <td>
                        @php
                            $badgeClass = match ($c->status) {
                                'draft' => 'text-bg-secondary',
                                'pending_approval' => 'text-bg-warning',
                                'approved' => 'text-bg-success',
                                'rejected' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', strtoupper($c->status)) }}</span>
                    </td>
                    <td class="text-end">{{ $c->lines_count }}</td>
                    <td class="text-secondary small">{{ $c->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('inventory.stock-check.show', $c) }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">Abhi koi stock check nahi.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        @if ($checks->hasPages())
            <div class="card-footer bg-white">{{ $checks->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
