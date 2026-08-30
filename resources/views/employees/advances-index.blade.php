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
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            </form>
            <a href="{{ route('employees.advances.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Advance
            </a>
        </div>
    </div>

    <div class="alert alert-info small py-2">
        Yahan sirf <strong>active</strong> advances dikhte hain. Salary <strong>Mark paid</strong> hone par advance settle ho kar is list se hat jata hai.
        Purani entries ke liye employee ka <strong>View Ledger</strong> kholo.
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
                            <span class="badge text-bg-warning text-dark">Active</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-danger" href="{{ route('employees.advances.print', $advance) }}" target="_blank" rel="noopener" title="Print advance receipt">
                                <i class="bi bi-printer"></i> Print
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.advances.ledger', $advance->employee) }}">
                                <i class="bi bi-journal-text me-1"></i>View Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No active advance records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $advances->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
