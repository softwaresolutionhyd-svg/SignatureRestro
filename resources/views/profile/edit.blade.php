@extends('layouts.admin')

@section('title', 'Profile — ' . config('app.name'))

@push('head')
<style>
.profile-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%);
    border-radius: 1rem;
    color: #fff;
    overflow: hidden;
    position: relative;
}
.profile-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 85% 15%, rgba(255,255,255,.12), transparent 45%);
    pointer-events: none;
}
.profile-hero-inner { position: relative; z-index: 1; }
.profile-avatar {
    width: 72px; height: 72px;
    border-radius: 1rem;
    background: rgba(255,255,255,.15);
    border: 2px solid rgba(255,255,255,.35);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 700;
}
.profile-logo {
    width: 52px; height: 52px;
    object-fit: contain;
    border-radius: .75rem;
    background: rgba(255,255,255,.95);
    padding: .35rem;
}
.profile-stat {
    background: #fff;
    border-radius: .875rem;
    border: 1px solid rgba(0,0,0,.06);
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.profile-stat .label { font-size: .78rem; color: #64748b; }
.profile-stat .value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.profile-card { border: 0; border-radius: .875rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.profile-card .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; font-weight: 600; }
.attendance-badge-present { background: #dcfce7; color: #166534; }
.attendance-badge-absent { background: #fee2e2; color: #991b1b; }
.attendance-badge-leave { background: #fef3c7; color: #92400e; }
.attendance-badge-half_day { background: #dbeafe; color: #1e40af; }
.login-log-row + .login-log-row { border-top: 1px solid #f1f5f9; }
</style>
@endpush

@section('content')
@php
    $roleLabel = match($user->role ?? '') {
        'super_admin' => 'Super Admin',
        'company_admin', 'admin' => 'Company Admin',
        default => 'Staff',
    };
    $initials = collect(explode(' ', trim($user->name)))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

@if (!empty($mustChangePassword))
    <div class="alert alert-warning">
        <strong>Password change required.</strong> Admin ne temporary password diya hai — pehle naya strong password set karein.
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="profile-hero mb-4">
    <div class="profile-hero-inner p-4 p-md-5">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar">{{ $initials ?: 'U' }}</div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h4 class="fw-bold mb-0">{{ $user->name }}</h4>
                        <span class="badge rounded-pill text-bg-light text-dark">{{ $roleLabel }}</span>
                    </div>
                    <div class="opacity-75 small">
                        <i class="bi bi-person-badge me-1"></i> {{ $username }}
                        @if($employee)
                            · {{ $employee->employee_no }}
                        @endif
                    </div>
                    @if($employee)
                    <div class="opacity-75 small mt-1">
                        {{ $employee->department?->name ?? '—' }}
                        @if($employee->designation?->name)
                            · {{ $employee->designation->name }}
                        @endif
                        @if($employee->join_date)
                            · Joined {{ $employee->join_date->format('M Y') }}
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            <div class="text-md-end">
                @if(!empty(trim($companyLogo ?? '')))
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($companyLogo) }}" alt="{{ $companyName }}" class="profile-logo mb-2">
                @endif
                <div class="fw-semibold">{{ $companyName }}</div>
                <div class="small opacity-75">{{ $monthLabel }}</div>
            </div>
        </div>
    </div>
</div>

@if($employee)
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="profile-stat p-3 h-100">
            <div class="label">Present ({{ $monthLabel }})</div>
            <div class="value text-success">{{ $attendanceStats['present'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="profile-stat p-3 h-100">
            <div class="label">Leave days</div>
            <div class="value text-warning">{{ $attendanceStats['leave'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="profile-stat p-3 h-100">
            <div class="label">Approved leave</div>
            <div class="value text-primary">{{ $leaveStats['days'] }} <span class="fs-6 fw-normal text-secondary">days</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="profile-stat p-3 h-100">
            <div class="label">Pending requests</div>
            <div class="value text-danger">{{ $leaveStats['pending'] }}</div>
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card profile-card mb-4">
            <div class="card-header py-3">Account settings</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="name">Display name</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required maxlength="150">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="{{ $username }}" disabled>
                        <div class="form-text">Login username — admin se change hota hai.</div>
                    </div>

                    @if($employee && $employee->phone)
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" value="{{ $employee->phone }}" disabled>
                    </div>
                    @endif

                    <hr class="my-4">

                    <p class="fw-semibold small text-secondary mb-3">
                        {{ !empty($mustChangePassword) ? 'New password (required)' : 'Password change (optional)' }}
                    </p>

                    @if(empty($mustChangePassword))
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input type="password" name="current_password" id="current_password"
                               class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input type="password" name="password" id="password"
                               class="form-control @error('password') is-invalid @enderror" autocomplete="new-password"
                               @if(!empty($mustChangePassword)) required @endif>
                        <div class="form-text">Minimum 8 characters, upper + lower + number.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password"
                            @if(!empty($mustChangePassword)) required @endif>
                    </div>

                    <button type="submit" class="btn btn-primary">{{ !empty($mustChangePassword) ? 'Set new password' : 'Save changes' }}</button>
                </form>
            </div>
        </div>

        <div class="card profile-card">
            <div class="card-header py-3">Google Authenticator (2FA)</div>
            <div class="card-body">
                <p class="text-secondary small mb-3">Login par extra security ke liye Authenticator app se code mangwaya jayega.</p>

                @if ($user->hasTwoFactorEnabled())
                    <div class="alert alert-success py-2 small mb-3">
                        <i class="bi bi-shield-check"></i> 2FA enabled hai.
                    </div>
                    <form method="POST" action="{{ route('profile.two-factor.disable') }}" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="disable_current_password">Current password</label>
                            <input type="password" name="current_password" id="disable_current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="disable_code">Authenticator ya recovery code</label>
                            <input type="text" name="code" id="disable_code"
                                   class="form-control @error('code') is-invalid @enderror"
                                   autocomplete="one-time-code" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-outline-danger btn-sm">Disable 2FA</button>
                    </form>
                @else
                    <a href="{{ route('profile.two-factor.setup') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-shield-lock"></i> Enable Google Authenticator
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @if($employee)
        <div class="card profile-card mb-4">
            <div class="card-header py-3">
                <span><i class="bi bi-calendar-check me-1"></i> Attendance — {{ $monthLabel }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>In</th>
                            <th>Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendanceRows as $row)
                        @php $cls = 'attendance-badge-'.($row->status ?? 'present'); @endphp
                        <tr>
                            <td>{{ $row->attendance_date->format('D, d M') }}</td>
                            <td><span class="badge rounded-pill {{ $cls }}">{{ ucfirst(str_replace('_', ' ', $row->status)) }}</span></td>
                            <td>{{ $row->clock_in?->format('H:i') ?? '—' }}</td>
                            <td>{{ $row->clock_out?->format('H:i') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">Is mahine abhi koi attendance record nahi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card profile-card mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar2-week me-1"></i> Leave — {{ $monthLabel }}</span>
                @if(auth()->user()->canViewModule('hr') || auth()->user()->bypassesModulePermissions())
                    <a href="{{ route('hr.leave.index') }}" class="btn btn-sm btn-outline-primary">All leave</a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaveRows as $leave)
                        @php $st = $leaveStatusLabels[$leave->status] ?? ['label'=>$leave->status,'color'=>'secondary']; @endphp
                        <tr>
                            <td>{{ $leaveTypeLabels[$leave->leave_type] ?? $leave->leave_type }}</td>
                            <td>{{ $leave->start_date->format('d M') }}</td>
                            <td>{{ $leave->end_date->format('d M') }}</td>
                            <td>{{ $leave->days }}</td>
                            <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">Is mahine koi leave request nahi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($employee && (auth()->user()->moduleAllows('hr', 'create') || auth()->user()->bypassesModulePermissions()))
            <div class="card-footer bg-white">
                <a href="{{ route('hr.leave.create') }}" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Request leave
                </a>
            </div>
            @endif
        </div>
        @else
        <div class="card profile-card mb-4">
            <div class="card-body text-secondary">
                <i class="bi bi-info-circle me-1"></i>
                Aap ka account kisi employee record se link nahi — attendance aur leave yahan nahi dikhengi.
            </div>
        </div>
        @endif

        <div class="card profile-card">
            <div class="card-header py-3">
                <i class="bi bi-clock-history me-1"></i> Login activity
            </div>
            <div class="card-body p-0">
                @forelse($loginLogs as $log)
                <div class="login-log-row px-3 py-3 d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <span class="badge {{ $log->action === 'auth.login' ? 'text-bg-success' : 'text-bg-secondary' }} me-2">
                            {{ $log->action === 'auth.login' ? 'Login' : 'Logout' }}
                        </span>
                        <span class="small text-secondary">{{ $log->description }}</span>
                    </div>
                    <div class="text-end small">
                        <div class="fw-semibold">{{ $log->created_at?->format('d M Y, H:i') }}</div>
                        @if($log->ip_address)
                            <div class="text-secondary">IP: {{ $log->ip_address }}</div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center text-secondary py-4">Abhi koi login log nahi mila.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
