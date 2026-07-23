@extends('layouts.admin')
@section('title', 'Expenses — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Expenses</h4>
        <div class="text-secondary small">Track & manage employee expense claims</div>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()?->bypassesModulePermissions() || in_array(auth()->user()?->role ?? '', ['admin'], true))
        <a href="{{ route('expenses.categories.index') }}" class="btn btn-outline-secondary btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><rect x="2" y="2" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/><rect x="11" y="2" width="7" height="7" rx="1.5" fill="currentColor"/><rect x="2" y="11" width="7" height="7" rx="1.5" fill="currentColor"/><rect x="11" y="11" width="7" height="7" rx="1.5" fill="currentColor" opacity=".7"/></svg>
            Categories
        </a>
        @endif
        <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
            New Expense
        </a>
    </div>
</div>

{{-- Status KPI ribbon --}}
<div class="row g-3 mb-4">
    @php
        $ribbonItems = [
            ['key' => 'draft',     'label' => 'Draft',     'color' => '#64748b', 'icon' => 'M4 8h12M4 12h8'],
            ['key' => 'submitted', 'label' => 'Submitted', 'color' => '#0ea5e9', 'icon' => 'M5 10l4 4 6-7'],
            ['key' => 'approved',  'label' => 'Approved',  'color' => '#7c3aed', 'icon' => 'M4 10l4 4 8-8'],
            ['key' => 'paid',      'label' => 'Paid',      'color' => '#22c55e', 'icon' => 'M3 10a7 7 0 1 0 14 0 7 7 0 0 0-14 0zm4 0l2.5 2.5L12 7'],
        ];
    @endphp
    @foreach($ribbonItems as $ri)
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 cursor-pointer" style="border-left:4px solid {{ $ri['color'] }} !important;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small">{{ $ri['label'] }}</div>
                        <div class="fw-bold fs-4 mt-1" style="color:{{ $ri['color'] }}">{{ $kpis[$ri['key']] }}</div>
                    </div>
                    <svg width="28" height="28" fill="none" viewBox="0 0 20 20" style="color:{{ $ri['color'] }};opacity:.35"><path d="{{ $ri['icon'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach($statusMap as $val => $info)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $info['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1">Employee</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value="">All Employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Description</th>
                        <th>Employee</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                    @php $s = $statusMap[$expense->status] ?? ['label'=>$expense->status,'color'=>'secondary'] @endphp
                    <tr>
                        <td class="ps-3 text-secondary small">{{ $expense->id }}</td>
                        <td>
                            <a href="{{ route('expenses.show', $expense) }}" class="fw-semibold text-decoration-none text-dark">
                                {{ Str::limit($expense->description, 45) }}
                            </a>
                            @if($expense->receipt_path)
                                <svg width="12" height="12" fill="none" viewBox="0 0 20 20" class="text-muted ms-1" title="Has receipt"><path d="M4 4h12v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" stroke="currentColor" stroke-width="1.5"/><path d="M8 4V2h4v2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                            @endif
                        </td>
                        <td class="small">{{ $expense->employee?->name ?? '—' }}</td>
                        <td class="small text-secondary">{{ $expense->category?->name ?? '—' }}</td>
                        <td class="small text-secondary">{{ $expense->expense_date?->format('d M Y') }}</td>
                        <td class="text-end fw-semibold small">{{ fmt_num($expense->grand_total, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $s['color'] }} bg-opacity-15 text-{{ $s['color'] }} border border-{{ $s['color'] }} border-opacity-25 px-2">
                                {{ $s['label'] }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('expenses.show', $expense) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 20 20"><path d="M1 10S4 4 10 4s9 6 9 6-3 6-9 6-9-6-9-6z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                </a>
                                @if(in_array($expense->status, ['draft','refused']))
                                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 20 20"><path d="M14.5 3.5l2 2-10 10-3 1 1-3 10-10z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-5 text-secondary">No expenses found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
        <div class="px-3 py-2 border-top">{{ $expenses->links() }}</div>
        @endif
    </div>
</div>
@endsection
