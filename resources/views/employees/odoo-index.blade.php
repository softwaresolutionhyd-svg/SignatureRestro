@extends('layouts.odoo')

@section('title', 'Employees - ' . config('app.name'))

@section('content')
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-2">Employees</h1>
            <p class="text-muted mb-0">Manage your team and their permissions</p>
        </div>
        <a href="{{ route('employees.create') }}" class="odoo-btn odoo-btn-primary">
            <i class="bi bi-person-plus me-2"></i> Add Employee
        </a>
    </div>

    <!-- Search and Filters -->
    <div class="odoo-card">
        <div class="odoo-card-body">
            <form method="GET" action="{{ route('employees.index') }}" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search employee no, name, username, phone...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    @if($q !== '')
                        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Employees Table -->
    <div class="odoo-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><i class="bi bi-hash me-1"></i> Employee #</th>
                        <th><i class="bi bi-person me-1"></i> Name</th>
                        <th><i class="bi bi-building me-1"></i> Department</th>
                        <th><i class="bi bi-briefcase me-1"></i> Designation</th>
                        <th><i class="bi bi-person-badge me-1"></i> Username</th>
                        <th><i class="bi bi-telephone me-1"></i> Phone</th>
                        <th><i class="bi bi-toggle-on me-1"></i> Status</th>
                        <th><i class="bi bi-gear me-1"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td class="fw-semibold">{{ $employee->employee_no }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="odoo-avatar me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                        {{ substr($employee->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $employee->name }}</div>
                                        @if($employee->user)
                                            <small class="text-muted">{{ $employee->user->email }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    {{ $employee->department?->name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    {{ $employee->designation?->name ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $employee->user?->loginUsername() ?? '—' }}</td>
                            <td>{{ $employee->phone ?? '—' }}</td>
                            <td>
                                @if($employee->active)
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i> Active
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle me-1"></i> Inactive
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete this employee?');" 
                                          class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-people text-muted" style="font-size: 48px;"></i>
                                <div class="text-muted mt-3">No employees found</div>
                                <a href="{{ route('employees.create') }}" class="btn btn-primary mt-2">
                                    <i class="bi bi-person-plus me-2"></i> Add Your First Employee
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($employees->hasPages())
            <div class="card-footer bg-white">
                {{ $employees->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="odoo-stats-grid mt-4">
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">{{ $employees->total() }}</div>
            <div class="odoo-stat-label">Total Employees</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">{{ $employees->where('active', true)->count() }}</div>
            <div class="odoo-stat-label">Active Employees</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">{{ $employees->where('active', false)->count() }}</div>
            <div class="odoo-stat-label">Inactive Employees</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">{{ $employees->whereNotNull('user_id')->count() }}</div>
            <div class="odoo-stat-label">With Login Access</div>
        </div>
    </div>
@endsection
