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
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('inventory.issues.warehouse-stock-print', ['print' => 1]) }}" class="btn btn-outline-secondary" target="_blank">
                        <i class="bi bi-printer me-1"></i> Warehouse Stock Print
                    </a>
                    <a href="{{ route('inventory.issues.create') }}" class="btn btn-primary">
                        <i class="bi bi-box-arrow-right me-1"></i> New Issue
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="fw-semibold">
                    {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                    <span class="text-secondary fw-normal small">· {{ $totalLines }} items · {{ $grouped->count() }} departments</span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form method="GET" action="{{ route('inventory.issues.index') }}" class="d-flex align-items-center gap-2">
                        <label class="small text-secondary mb-0" for="issueDate">Date</label>
                        <input id="issueDate" type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    </form>
                    <a href="{{ route('inventory.issues.daily-print', ['date' => $date, 'print' => 1]) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="bi bi-printer me-1"></i> Print
                    </a>
                </div>
            </div>
        </div>

        @if($grouped->isNotEmpty())
            <div class="card-body py-3 border-bottom">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($grouped as $dept)
                        <span class="badge text-bg-primary fs-6 fw-normal">
                            {{ $dept['name'] }}
                            <span class="opacity-75">· {{ $dept['count'] }} {{ $dept['count'] === 1 ? 'item' : 'items' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @forelse($grouped as $dept)
            <div class="border-bottom">
                <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                    <div class="fw-semibold text-uppercase">{{ $dept['name'] }}</div>
                    <div class="small text-secondary">{{ $dept['count'] }} {{ $dept['count'] === 1 ? 'item' : 'items' }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>From</th>
                            <th>By</th>
                            <th>Time</th>
                            <th>Note</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dept['items'] as $issue)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $issue->product?->name ?? '—' }}</div>
                                    <div class="small text-secondary">{{ $issue->product?->sku }}</div>
                                </td>
                                <td>{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                                <td>{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                                <td class="small">{{ $issue->user?->name ?? '—' }}</td>
                                <td class="small text-secondary">{{ $issue->created_at?->format('H:i') }}</td>
                                <td class="small text-secondary">{{ $issue->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="text-center text-secondary py-5">Is date pe koi stock issue nahi hua.</div>
        @endforelse
    </div>
@endsection
