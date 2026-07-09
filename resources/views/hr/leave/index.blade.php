@extends('layouts.admin')
@section('title', 'Leave Requests — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['key' => 'pending', 'label' => 'Pending', 'color' => '#f59e0b'],
        ['key' => 'approved', 'label' => 'Approved', 'color' => '#22c55e'],
        ['key' => 'rejected', 'label' => 'Rejected', 'color' => '#ef4444'],
    ] as $kpi)
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm" style="border-left:4px solid {{ $kpi['color'] }} !important;">
            <div class="card-body py-3">
                <div class="text-secondary small">{{ $kpi['label'] }}</div>
                <div class="fw-bold fs-4" style="color:{{ $kpi['color'] }}">{{ $kpis[$kpi['key']] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <form class="row g-2 align-items-end" method="GET" action="{{ route('hr.leave.index') }}">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach($statusLabels as $key => $meta)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @if($employees->isNotEmpty())
            <div class="col-md-3">
                <label class="form-label small mb-1">Employee</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value="">All employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected($employeeId === $emp->id)>{{ $emp->name }} ({{ $emp->employee_no }})</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-3">
                <label class="form-label small mb-1">Month</label>
                <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.leave.index') }}">Clear</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $leave)
                @php $st = $statusLabels[$leave->status] ?? ['label'=>$leave->status,'color'=>'secondary']; @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $leave->employee?->name ?? '—' }}</div>
                        <div class="small text-secondary">{{ $leave->employee?->employee_no }}</div>
                    </td>
                    <td>{{ $typeLabels[$leave->leave_type] ?? $leave->leave_type }}</td>
                    <td>{{ $leave->start_date->format('Y-m-d') }}</td>
                    <td>{{ $leave->end_date->format('Y-m-d') }}</td>
                    <td>{{ $leave->days }}</td>
                    <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                    <td class="small text-secondary">{{ $leave->created_at?->format('d M Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('hr.leave.show', $leave) }}" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No leave requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="card-footer bg-white">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
