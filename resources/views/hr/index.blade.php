@extends('layouts.admin')
@section('title', 'HR — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Human Resources</h4>
    <div class="text-secondary small">Employees, attendance, leave & payroll</div>
</div>

@include('hr.partials.subnav')

<div class="row g-3 mb-4">
    @php
        $ribbon = [
            ['key' => 'employees', 'label' => 'Active Employees', 'color' => '#ec4899'],
            ['key' => 'present_today', 'label' => 'Present Today', 'color' => '#22c55e'],
            ['key' => 'pending_leave', 'label' => 'Pending Leave', 'color' => '#f59e0b'],
            ['key' => 'payroll_draft', 'label' => 'Payroll Draft (This Month)', 'color' => '#6366f1'],
        ];
    @endphp
    @foreach($ribbon as $ri)
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $ri['color'] }} !important;">
            <div class="card-body py-3">
                <div class="text-secondary small">{{ $ri['label'] }}</div>
                <div class="fw-bold fs-4 mt-1" style="color:{{ $ri['color'] }}">{{ $kpis[$ri['key']] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Recent Leave Requests</span>
                <a href="{{ route('hr.leave.index') }}" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLeave as $leave)
                        @php $st = \App\Models\LeaveRequest::statusLabels()[$leave->status] ?? ['label'=>$leave->status,'color'=>'secondary']; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('hr.leave.show', $leave) }}" class="text-decoration-none fw-semibold">{{ $leave->employee?->name ?? '—' }}</a>
                                <div class="small text-secondary">{{ $leave->employee?->employee_no }}</div>
                            </td>
                            <td>{{ \App\Models\LeaveRequest::typeLabels()[$leave->leave_type] ?? $leave->leave_type }}</td>
                            <td class="small">{{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}</td>
                            <td>{{ $leave->days }}</td>
                            <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No leave requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Recently Added Employees</span>
                <a href="{{ route('employees.index') }}" class="small">View all</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($recentEmployees as $emp)
                <a href="{{ route('employees.edit', $emp) }}" class="list-group-item list-group-item-action">
                    <div class="fw-semibold">{{ $emp->name }}</div>
                    <div class="small text-secondary">
                        {{ $emp->employee_no }}
                        @if($emp->department?->name) · {{ $emp->department->name }} @endif
                        @if($emp->designation?->name) · {{ $emp->designation->name }} @endif
                    </div>
                </a>
                @empty
                <div class="list-group-item text-secondary text-center py-4">No employees yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
