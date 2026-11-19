@extends('layouts.admin')

@section('title', 'Super Admin Audit Log — ' . config('app.name'))

@section('content')
<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Super Admin Audit Log</h4>
        <div class="text-secondary small">Edit, delete, password change and other Super Admin actions with reason</div>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← Reports Hub</a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <form class="row g-2 align-items-end" method="GET" action="{{ route('reports.super-admin-audit') }}">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Action</label>
                <select name="action_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['add'=>'Add','edit'=>'Edit','delete'=>'Delete','password_change'=>'Password change','other'=>'Other'] as $k=>$v)
                        <option value="{{ $k }}" @selected($actionType === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string)$userId === (string)$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.super-admin-audit') }}">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Date / time</th>
                <th>User</th>
                <th>Action</th>
                <th>Subject</th>
                <th>Reason</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $props = is_array($log->properties) ? $log->properties : [];
                    $reason = $props['reason'] ?? '—';
                    if (!empty($props['order_number'])) {
                        $subject = $props['order_number'];
                    } elseif (!empty($props['employee_no'])) {
                        $subject = 'Emp '.$props['employee_no'];
                    } elseif ($log->subject_type) {
                        $subject = class_basename($log->subject_type).($log->subject_id ? ' #'.$log->subject_id : '');
                    } else {
                        $subject = '—';
                    }
                @endphp
                <tr>
                    <td class="text-secondary text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>
                        @if($log->user)
                            <span class="fw-semibold">{{ $log->user->name }}</span>
                            <div class="small text-secondary">{{ $log->user->email }}</div>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td><span class="badge text-bg-secondary">{{ \App\Support\SuperAdminAudit::typeLabel($log->action) }}</span></td>
                    <td class="small">{{ $subject }}</td>
                    <td class="small">{{ $reason }}</td>
                    <td class="small text-secondary">{{ $log->description ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No Super Admin audit entries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
