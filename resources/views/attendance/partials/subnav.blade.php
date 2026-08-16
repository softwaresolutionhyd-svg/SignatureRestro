@php($u = auth()->user())
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('attendance.index') }}" class="btn btn-outline-primary {{ request()->routeIs('attendance.index', 'attendance.print-today') ? 'active' : '' }}">
            <i class="bi bi-calendar-check me-1"></i> {{ __('Attendance') }}
        </a>
        <a href="{{ route('attendance.scan') }}" class="btn btn-outline-primary {{ request()->routeIs('attendance.scan') ? 'active' : '' }}">
            <i class="bi bi-qr-code-scan me-1"></i> {{ __('QR Scan') }}
        </a>
    </div>
</div>
