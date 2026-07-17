@extends('layouts.admin')

@section('title', 'Employee Loans — ' . config('app.name'))

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
            <form class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('employees.loans.index') }}">
                <input type="text" name="employee_no" value="{{ $employeeNo }}" class="form-control form-control-sm" placeholder="ID ya naam" style="max-width: 170px;">
                <select name="status" class="form-select form-select-sm" style="max-width: 140px;">
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="completed" @selected($status === 'completed')>Completed</option>
                    <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    <option value="all" @selected($status === 'all')>All</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            </form>
            <a href="{{ route('employees.loans.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Loan
            </a>
        </div>
    </div>

    <div class="alert alert-info small py-2">
        Har month payroll sync par <strong>monthly installment</strong> auto Loan column mein aati hai (loan lene wale month mein nahi — <strong>agle month</strong> se).
        <strong>Mark paid</strong> par balance update hota hai aur payment history save hoti hai.
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Employee Loans</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th class="text-end">Loan Amount</th>
                    <th class="text-end">Per Month Instalment</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($loans as $loan)
                    <tr>
                        <td class="fw-semibold">{{ $loan->employee?->employee_no }}</td>
                        <td>{{ $loan->employee?->name }}</td>
                        <td class="text-end">{{ number_format((float) $loan->loan_amount, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $loan->monthly_installment, 2) }}</td>
                        <td class="text-end fw-semibold {{ (float)$loan->balance > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format((float) $loan->balance, 2) }}
                        </td>
                        <td>
                            @if($loan->status === 'active')
                                <span class="badge text-bg-warning text-dark">Active</span>
                            @elseif($loan->status === 'completed')
                                <span class="badge text-bg-success">Completed</span>
                            @else
                                <span class="badge text-bg-secondary">Cancelled</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.loans.edit', $loan) }}">View / Edit</a>
                            @if(($loan->payments_count ?? 0) === 0)
                                <form class="d-inline" method="POST" action="{{ route('employees.loans.destroy', $loan) }}" onsubmit="return confirm('Delete this loan?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No loan records yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $loans->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
