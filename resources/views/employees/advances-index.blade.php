@extends('layouts.admin')

@section('title', 'Employee Advances — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <form class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('employees.advances.index') }}">
                <input type="text" name="employee_no" value="{{ $employeeNo }}" class="form-control form-control-sm" placeholder="ID ya naam" style="max-width: 170px;">
                <select name="status" class="form-select form-select-sm" style="max-width: 140px;">
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="settled" @selected($status === 'settled')>Settled</option>
                    <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    <option value="all" @selected($status === 'all')>All</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            </form>
            <a href="{{ route('employees.advances.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Advance
            </a>
        </div>
    </div>

    <div class="alert alert-info small py-2">
        Employee ko diya gaya <strong>advance</strong> usi month payroll ke <strong>Advance</strong> column mein auto deduct hota hai.
        <strong>Mark paid</strong> par advance ledger balance zero ho kar record settled ho jata hai.
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Employee Advances</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th class="text-end">Advance Amount</th>
                    <th class="text-end">Balance</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($advances as $advance)
                    <tr>
                        <td class="fw-semibold">{{ $advance->employee?->employee_no }}</td>
                        <td>{{ $advance->employee?->name }}</td>
                        <td class="text-end">{{ number_format((float) $advance->amount, 2) }}</td>
                        <td class="text-end fw-semibold {{ (float) $advance->balance > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format((float) $advance->balance, 2) }}
                        </td>
                        <td>{{ $advance->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if($advance->status === 'active')
                                <span class="badge text-bg-warning text-dark">Active</span>
                            @elseif($advance->status === 'settled')
                                <span class="badge text-bg-success">Settled</span>
                            @else
                                <span class="badge text-bg-secondary">Cancelled</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.advances.edit', $advance) }}">View / Edit</a>
                            <form class="d-inline" method="POST" action="{{ route('employees.advances.destroy', $advance) }}" onsubmit="return confirm('Is advance ki record delete ho jayegi. Continue?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No advance records yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $advances->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
