@extends('layouts.admin')
@section('title', 'Credit Book — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Credit Book</h4>
        <div class="text-secondary small">All outstanding credit balances</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm">Contacts</a>
        <a href="{{ route('contacts.create') }}" class="btn btn-primary btn-sm">+ New Contact</a>
    </div>
</div>

{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ef4444!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Total Outstanding</div>
                <div class="fw-bold fs-4 mt-1 text-danger">{{ fmt_num($totalOutstanding, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #7c3aed!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Contacts with Credit</div>
                <div class="fw-bold fs-4 mt-1" style="color:#7c3aed;">{{ $totalContacts }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Search name, phone…" value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="filter" class="form-select form-select-sm">
                    <option value="outstanding" @selected($filter==='outstanding')>Outstanding only</option>
                    <option value="all" @selected($filter==='all')>All contacts</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('credit-book.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Contact</th>
                    <th>Phone</th>
                    <th class="text-end">Total Credited</th>
                    <th class="text-end">Total Paid</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $c)
                @php
                    $credit  = (float)($c->total_credit ?? 0);
                    $paid    = (float)($c->total_paid   ?? 0);
                    $balance = round($credit - $paid, 2);
                @endphp
                <tr>
                    <td class="ps-3">
                        <a href="{{ route('contacts.show', $c) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $c->name }}
                        </a>
                    </td>
                    <td class="small text-secondary">{{ $c->phone ?? '—' }}</td>
                    <td class="text-end text-danger small fw-semibold">{{ fmt_num($credit, 2) }}</td>
                    <td class="text-end text-success small fw-semibold">{{ fmt_num($paid, 2) }}</td>
                    <td class="text-end fw-bold">
                        @if($balance > 0)
                            <span class="text-danger">{{ fmt_num($balance, 2) }}</span>
                        @elseif($balance < 0)
                            <span class="text-success">{{ fmt_num(abs($balance), 2) }} <small class="fw-normal">(overpaid)</small></span>
                        @else
                            <span class="badge bg-success bg-opacity-15 text-success">Settled</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        <a href="{{ route('contacts.show', $c) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                            View Ledger
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-secondary">No credit records found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($contacts->hasPages())
        <div class="px-3 py-2 border-top">{{ $contacts->links() }}</div>
        @endif
    </div>
</div>
@endsection
