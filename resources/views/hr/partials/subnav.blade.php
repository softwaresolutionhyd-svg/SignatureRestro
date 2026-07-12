@php($u = auth()->user())
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.index') }}" class="btn btn-outline-primary {{ request()->routeIs('hr.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.index', 'employees.create', 'employees.edit') ? 'active' : '' }}">
            <i class="bi bi-people me-1"></i> Employees
        </a>
        <a href="{{ route('employees.departments.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.departments.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3 me-1"></i> Departments
        </a>
        <a href="{{ route('employees.designations.index') }}" class="btn btn-outline-primary {{ request()->routeIs('employees.designations.*') ? 'active' : '' }}">
            <i class="bi bi-briefcase me-1"></i> Designations
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
        @elseif(request()->routeIs('employees.index', 'employees.departments.*', 'employees.designations.*') && $u->moduleAllows('hr', 'create'))
            <a href="{{ route('employees.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Employee
            </a>
        @endif
    </div>
</div>
