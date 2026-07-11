@extends('layouts.admin')

@section('title', 'Employees - ' . config('app.name'))
@section('page_title', 'Employees')

@section('content')
    @include('hr.partials.subnav')

    @php($u = auth()->user())
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <form class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('employees.index') }}">
                <input type="text" name="employee_no" value="{{ $employeeNo }}" class="form-control" placeholder="Employee ID e.g. EMP-00001" style="max-width: 200px;">
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search name, username, phone..." style="max-width: 260px;">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i> Filter</button>
                @if($q !== '' || $employeeNo !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Clear</a>
                @endif
            </form>

            <div class="d-flex flex-wrap gap-2">
                @if($u->canViewModule('hr'))
                    <a href="{{ route('employees.attendance.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar-check me-1"></i> Attendance
                    </a>
                @endif
                @if($u->isSuperAdmin())
                    <a href="{{ route('employees.payroll.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-cash-stack me-1"></i> Payroll
                    </a>
                @endif
                @if($u->moduleAllows('hr', 'create'))
                    <a href="{{ route('employees.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> New Employee
                    </a>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee #</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Username</th>
                    <th>Phone</th>
                    <th>Join date</th>
                    <th>Status</th>
                    @if($u->moduleAllows('hr', 'edit') || $u->moduleAllows('hr', 'delete'))
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $e)
                    <tr>
                        <td class="fw-semibold">{{ $e->employee_no }}</td>
                        <td class="fw-semibold">{{ $e->name }}</td>
                        <td class="text-secondary">{{ $e->department?->name ?? '—' }}</td>
                        <td class="text-secondary">{{ $e->designation?->name ?? '—' }}</td>
                        <td class="text-secondary">{{ $e->user?->loginUsername() ?: '—' }}</td>
                        <td class="text-secondary">{{ $e->phone ?: '—' }}</td>
                        <td class="text-secondary">{{ optional($e->join_date)->format('Y-m-d') ?: '—' }}</td>
                        <td>
                            @if($e->active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        @if($u->moduleAllows('hr', 'edit') || $u->moduleAllows('hr', 'delete'))
                        <td class="text-end">
                            @if($u->moduleAllows('hr', 'edit'))
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.edit', $e) }}">Edit</a>
                            @endif
                            @if($u->moduleAllows('hr', 'delete'))
                                <form class="d-inline" method="POST" action="{{ route('employees.destroy', $e) }}"
                                      onsubmit="return confirm('Delete employee?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ ($u->moduleAllows('hr', 'edit') || $u->moduleAllows('hr', 'delete')) ? 9 : 8 }}" class="text-center text-secondary py-4">No employees yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            {{ $employees->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

