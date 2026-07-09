@extends('layouts.admin')

@section('title', 'Activity logs — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Activity logs</div>
            <form class="row g-2 align-items-end flex-grow-1 justify-content-end" method="GET" action="{{ route('activity-logs.index') }}" style="max-width: 920px;">
                <div class="col-auto">
                    <label class="form-label small mb-0">Action</label>
                    <input type="text" name="action" value="{{ $action }}" class="form-control form-control-sm" placeholder="e.g. auth.login">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((string)$userId === (string)$u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('activity-logs.index') }}">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-secondary text-nowrap">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($log->user)
                                <span class="fw-semibold">{{ $log->user->name }}</span>
                                <div class="small text-secondary">{{ $log->user->email }}</div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">{{ $log->description ?? '—' }}</td>
                        <td class="small text-secondary">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No logs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
