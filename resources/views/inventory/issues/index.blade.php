@extends('layouts.admin')

@section('title', 'Issue Stock - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Issue to Department')

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">Warehouse se departments ko stock issue karein</div>
                    <div class="small text-secondary">Purchase receive hone par stock pehle <strong>{{ $warehouse?->name ?? 'Warehouse' }}</strong> mein aata hai. Yahan se aap doosre departments ko issue kar sakte hain.</div>
                </div>
                <a href="{{ route('inventory.issues.create') }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-right me-1"></i> New Issue
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Recent issues</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>To</th>
                    <th>By</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                @forelse($issues as $issue)
                    <tr>
                        <td class="small text-secondary">{{ $issue->created_at?->format('d M Y H:i') }}</td>
                        <td>
                            <div class="fw-semibold">{{ $issue->product?->name ?? '—' }}</div>
                            <div class="small text-secondary">{{ $issue->product?->sku }}</div>
                        </td>
                        <td>{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                        <td>{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                        <td><span class="badge text-bg-primary">{{ $issue->toDepartment?->name ?? '—' }}</span></td>
                        <td class="small">{{ $issue->user?->name ?? '—' }}</td>
                        <td class="small text-secondary">{{ $issue->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Abhi koi issue nahi hua.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $issues->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
