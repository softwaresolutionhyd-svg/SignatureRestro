@extends('layouts.admin')
@section('title', 'Chart of Accounts — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Chart of Accounts</h4>
    <div class="text-secondary small">Manage ledger accounts for your company</div>
</div>

@include('accounts.partials.subnav')

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Code or name">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach($typeLabels as $val => $label)
                        <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="active" class="form-select form-select-sm">
                    <option value="1" @selected(request('active', '1') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                    <option value="all" @selected(request('active') === 'all')>All</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                <tr>
                    <td class="fw-semibold">
                        <a href="{{ route('accounts.journal-entries.index', ['account_id' => $account->id, 'status' => 'posted']) }}" class="text-decoration-none">{{ $account->code }}</a>
                    </td>
                    <td>
                        <a href="{{ route('accounts.journal-entries.index', ['account_id' => $account->id, 'status' => 'posted']) }}" class="text-decoration-none text-dark">
                            {{ $account->name }}
                        </a>
                        @if($account->is_system)
                            <span class="badge bg-light text-secondary border ms-1">System</span>
                        @endif
                    </td>
                    <td>{{ $typeLabels[$account->type] ?? $account->type }}</td>
                    <td class="text-end">
                        <a href="{{ route('accounts.journal-entries.index', ['account_id' => $account->id, 'status' => 'posted']) }}" class="text-decoration-none text-dark">
                            {{ $currency }} {{ number_format($account->postedBalance(), 2) }}
                        </a>
                    </td>
                    <td>
                        @if($account->active)
                            <span class="badge bg-success-subtle text-success border">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('accounts.chart-of-accounts.edit', $account) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                        @if(!$account->is_system)
                        <form action="{{ route('accounts.chart-of-accounts.destroy', $account) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this account?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($accounts->hasPages())
    <div class="card-footer bg-white">{{ $accounts->links() }}</div>
    @endif
</div>
@endsection
