@php($u = auth()->user())
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.index') }}" class="btn btn-outline-primary {{ request()->routeIs('hr.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.index', 'employees.create', 'employees.edit') ? 'active' : '' }}">
            <i class="bi bi-people me-1"></i> Employees
        </a>
        <a href="{{ route('employees.designations.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.designations.*') ? 'active' : '' }}">
            <i class="bi bi-briefcase me-1"></i> Designations
        </a>
        <a href="{{ route('employees.staff-categories.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.staff-categories.*') ? 'active' : '' }}">
            <i class="bi bi-collection me-1"></i> Staff Categories
        </a>
        @if($u->canManageTeamAttendance())
        <a href="{{ route('employees.attendance.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.attendance.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check me-1"></i> Attendance
        </a>
        @endif
        <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-primary {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}">
            <i class="bi bi-calendar2-week me-1"></i> Leave
        </a>
        @if($u->canManagePayroll())
            <a href="{{ route('employees.loans.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.loans.*') ? 'active' : '' }}">
                <i class="bi bi-wallet2 me-1"></i> Loans
            </a>
            <a href="{{ route('employees.payroll.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.payroll.*') ? 'active' : '' }}">
                <i class="bi bi-cash-stack me-1"></i> Payroll
            </a>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(request()->routeIs('hr.leave.*') && ($u->moduleAllows('hr', 'create') || $u->bypassesModulePermissions()))
            <a href="{{ route('hr.leave.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Request Leave
            </a>
        @elseif(request()->routeIs('employees.loans.*') && $u->canManagePayroll())
            <a href="{{ route('employees.loans.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Loan
            </a>
        @elseif(request()->routeIs('employees.index', 'employees.designations.*') && $u->moduleAllows('hr', 'create'))
            <a href="{{ route('employees.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Employee
            </a>
        @endif
    </div>
</div>
