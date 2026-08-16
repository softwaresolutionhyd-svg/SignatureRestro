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
                <select name="staff_category_id" class="form-select" style="max-width: 180px;" title="Staff category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach(($staffCategories ?? []) as $cat)
                        <option value="{{ $cat->id }}" @selected((int) ($staffCategoryId ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select name="sort" class="form-select" style="max-width: 160px;" title="Sort by name" onchange="this.form.submit()">
                    <option value="" @selected(($sort ?? '') === '')>Default</option>
                    <option value="name_az" @selected(($sort ?? '') === 'name_az')>Name A → Z</option>
                    <option value="name_za" @selected(($sort ?? '') === 'name_za')>Name Z → A</option>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i> Filter</button>
                @if($q !== '' || $employeeNo !== '' || ($sort ?? '') !== '' || ! empty($staffCategoryId))
                    <a class="btn btn-outline-secondary" href="{{ route('employees.index') }}">Clear</a>
                @endif
            </form>

            <div class="d-flex flex-wrap gap-2">
                @if($u->canAuthorizeQrAttendance())
                    <a href="{{ route('employees.qr-cards') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                        <i class="bi bi-person-vcard me-1"></i> All ID Cards PDF
                    </a>
                @endif
                <a href="{{ route('employees.print-report') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Print Report
                </a>
                @if($u->canManagePayroll())
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
                    <th style="width:3.5rem;"></th>
                    <th>Employee #</th>
                    <th>
                        <a href="{{ route('employees.index', array_filter([
                                'employee_no' => ($employeeNo ?? '') !== '' ? $employeeNo : null,
                                'q' => ($q ?? '') !== '' ? $q : null,
                                'staff_category_id' => ! empty($staffCategoryId) ? $staffCategoryId : null,
                                'sort' => (($sort ?? '') === 'name_az') ? 'name_za' : 'name_az',
                            ])) }}"
                           class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Name
                            @if(($sort ?? '') === 'name_az')
                                <i class="bi bi-sort-alpha-down" title="A to Z"></i>
                            @elseif(($sort ?? '') === 'name_za')
                                <i class="bi bi-sort-alpha-up" title="Z to A"></i>
                            @else
                                <i class="bi bi-arrow-down-up text-secondary" title="Sort A to Z"></i>
                            @endif
                        </a>
                    </th>
                    <th>Designation</th>
                    <th>Username</th>
                    <th>Phone</th>
                    <th>Join date</th>
                    <th>Status</th>
                    @if($u->moduleAllows('hr', 'edit') || $u->canDeleteEmployees())
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $e)
                    <tr>
                        <td>
                            @if($e->photoUrl())
                                <img src="{{ $e->photoUrl() }}" alt="" class="rounded border"
                                     style="width:40px;height:52px;object-fit:cover;">
                            @else
                                <span class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-secondary"
                                      style="width:40px;height:52px;">
                                    <i class="bi bi-person"></i>
                                </span>
                            @endif
                        </td>
                        <td class="fw-semibold">{{ $e->employee_no }}</td>
                        <td class="fw-semibold">{{ $e->name }}</td>
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
                        @if($u->moduleAllows('hr', 'edit') || $u->canDeleteEmployees())
                        <td class="text-end">
                            @if($u->moduleAllows('hr', 'edit'))
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.edit', $e) }}">Edit</a>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.qr-card', $e) }}" target="_blank" rel="noopener" title="Print ID card">
                                <i class="bi bi-qr-code"></i>
                            </a>
                            @if($u->canDeleteEmployees())
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
                    <tr><td colspan="{{ ($u->moduleAllows('hr', 'edit') || $u->canDeleteEmployees()) ? 10 : 9 }}" class="text-center text-secondary py-4">No employees yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            {{ $employees->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

