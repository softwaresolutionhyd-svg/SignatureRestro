@extends('layouts.admin')

@section('title', 'Attendance — ' . config('app.name'))

@push('head')
<style>
.attendance-grid-wrap {
    overflow: auto;
    max-height: calc(100vh - 220px);
}
.attendance-grid {
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.72rem;
    min-width: max-content;
}
.attendance-grid th,
.attendance-grid td {
    border: 1px solid #e9ecef;
    padding: 0.2rem;
    text-align: center;
    vertical-align: middle;
    background: #fff;
}
.attendance-grid thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
    font-weight: 700;
    white-space: nowrap;
}
.attendance-grid .att-sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
    text-align: left;
    min-width: 140px;
    max-width: 180px;
    background: #fff;
    box-shadow: 2px 0 0 #e9ecef;
}
.attendance-grid thead .att-sticky-col {
    z-index: 4;
    background: #f8f9fa;
}
.attendance-grid .att-sticky-summary {
    position: sticky;
    right: 0;
    z-index: 2;
    background: #fffbeb;
    font-weight: 600;
    box-shadow: -2px 0 0 #fde68a;
}
.attendance-grid thead .att-sticky-summary {
    background: #fef3c7;
    z-index: 4;
}
.attendance-grid .att-day-head {
    min-width: 42px;
    line-height: 1.1;
}
.attendance-grid .att-day-head small {
    display: block;
    color: #6c757d;
    font-weight: 500;
}
.attendance-grid th.att-today-col,
.attendance-grid td.att-today-col {
    background: #fff7ed !important;
    box-shadow: inset 0 0 0 2px #f97316;
}
.attendance-grid thead th.att-today-col {
    background: #ffedd5 !important;
    color: #c2410c;
}
.attendance-grid thead th.att-today-col small {
    color: #ea580c;
    font-weight: 700;
}
.attendance-grid thead th.att-today-col .att-today-badge {
    display: block;
    font-size: 0.58rem;
    font-weight: 800;
    color: #fff;
    background: #f97316;
    border-radius: 3px;
    padding: 0 3px;
    margin-top: 2px;
    line-height: 1.3;
}
.attendance-grid select.att-cell.att-today-cell {
    border-color: #f97316;
    box-shadow: 0 0 0 1px #fdba74;
}
.attendance-grid select.att-cell {
    width: 44px;
    min-width: 44px;
    padding: 0.1rem 0.15rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-align: center;
    border-radius: 4px;
    border: 1px solid #ced4da;
}
.attendance-grid select.att-cell.att-p { background: #dcfce7; color: #166534; border-color: #86efac; }
.attendance-grid select.att-cell.att-a { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.attendance-grid select.att-cell.att-h { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
.attendance-grid .att-emp-name {
    font-weight: 600;
    font-size: 0.78rem;
    line-height: 1.2;
}
.attendance-grid .att-emp-meta {
    font-size: 0.65rem;
    color: #6c757d;
}
.attendance-legend .badge {
    font-size: 0.7rem;
    padding: 0.35rem 0.55rem;
}
</style>
@endpush

@section('content')
@include('hr.partials.subnav')
@php
    $attEmployeeNoQs = $employeeNo !== '' ? '&employee_no='.urlencode($employeeNo) : '';
    $todayKey = now()->format('Y-m-d');
@endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="alert alert-info small mb-3">
        Har din <strong>P</strong> (Present), <strong>A</strong> (Absent), ya <strong>H</strong> (Holiday) select karein.
        Sirf <strong>Absent</strong> par salary kat ti hai: <strong>Basic Salary ÷ 30</strong> per day.
        Present aur Holiday par koi deduction nahi.
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center attendance-legend">
                <span class="badge text-bg-success">P = Present</span>
                <span class="badge text-bg-danger">A = Absent</span>
                <span class="badge text-bg-primary">H = Holiday</span>
                <span class="text-secondary small">— = not marked</span>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form class="d-flex gap-2 align-items-center" method="GET" action="{{ route('employees.attendance.index') }}" id="attendanceFilterForm">
                    <input type="hidden" name="month" value="{{ $month }}">
                    @if($activeOnly)
                        <input type="hidden" name="active_only" value="1">
                    @endif
                    <input type="text" name="employee_no" value="{{ $employeeNo }}" class="form-control form-control-sm" placeholder="ID ya naam" style="max-width: 170px;">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                    @if($employeeNo !== '')
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.attendance.index', array_filter(['month' => $month, 'active_only' => $activeOnly ? 1 : null])) }}">Clear</a>
                    @endif
                </form>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.attendance.index', array_filter(['month' => \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->subMonth()->format('Y-m'), 'active_only' => $activeOnly ? 1 : null, 'employee_no' => $employeeNo ?: null])) }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <input type="month" class="form-control form-control-sm" style="max-width: 150px;"
                       value="{{ $month }}"
                       onchange="window.location='{{ route('employees.attendance.index') }}?month='+this.value+'&active_only={{ $activeOnly ? 1 : 0 }}{{ $attEmployeeNoQs }}'">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.attendance.index', array_filter(['month' => \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->addMonth()->format('Y-m'), 'active_only' => $activeOnly ? 1 : null, 'employee_no' => $employeeNo ?: null])) }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <div class="form-check form-check-inline small mb-0 ms-2">
                    <input class="form-check-input" type="checkbox" id="activeOnlyToggle"
                           @checked($activeOnly)
                           onchange="window.location='{{ route('employees.attendance.index') }}?month={{ $month }}&active_only='+(this.checked?1:0)+'{{ $attEmployeeNoQs }}'">
                    <label class="form-check-label" for="activeOnlyToggle">Sirf active</label>
                </div>
                <button type="submit" class="btn btn-sm btn-primary" form="attendanceGridForm">
                    <i class="bi bi-save me-1"></i> Save attendance
                </button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('employees.attendance.grid') }}" id="attendanceGridForm">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        @if($activeOnly)
            <input type="hidden" name="active_only" value="1">
        @endif
        @if($employeeNo !== '')
            <input type="hidden" name="employee_no" value="{{ $employeeNo }}">
        @endif
        <input type="hidden" name="attendance_json" id="attendanceJson" value="">

        <div class="card shadow-sm">
            <div class="card-header bg-white py-2">
                <div class="fw-semibold">
                    {{ \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y') }} — {{ $employees->count() }} employees
                    @if($employeeNo !== '')
                        <span class="text-secondary fw-normal small">· filter: {{ $employeeNo }}</span>
                    @endif
                </div>
            </div>
            <div class="attendance-grid-wrap">
                <table class="attendance-grid mb-0">
                    <thead>
                    <tr>
                        <th class="att-sticky-col">Employee</th>
                        @foreach($dates as $date)
                            @php $dateKey = $date->format('Y-m-d'); $isToday = $dateKey === $todayKey; @endphp
                            <th class="att-day-head {{ $isToday ? 'att-today-col' : '' }}">
                                {{ $date->format('d') }}
                                <small>{{ $date->format('D') }}</small>
                                @if($isToday)
                                    <span class="att-today-badge">Aaj</span>
                                @endif
                            </th>
                        @endforeach
                        <th class="att-sticky-summary">P</th>
                        <th class="att-sticky-summary">A</th>
                        <th class="att-sticky-summary">H</th>
                        <th class="att-sticky-summary" style="min-width: 90px;">Deduction</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if($employees->isEmpty())
                        <tr>
                            <td colspan="{{ count($dates) + 5 }}" class="text-center text-secondary py-4">Koi employee nahi.</td>
                        </tr>
                    @else
                        @foreach($employees as $employee)
                        @php
                            $summary = $summaries[$employee->id] ?? ['present'=>0,'absent'=>0,'holiday'=>0,'deduction'=>0,'per_day'=>0];
                            $rowGrid = $grid[$employee->id] ?? [];
                        @endphp
                        <tr>
                            <td class="att-sticky-col">
                                <div class="att-emp-name">{{ $employee->name }}</div>
                                <div class="att-emp-meta">
                                    {{ $employee->employee_no }} · Rs. {{ number_format((float) $employee->salary, 0) }}
                                    @if(!$employee->active) · inactive @endif
                                </div>
                            </td>
                            @foreach($dates as $date)
                                @php
                                    $dateKey = $date->format('Y-m-d');
                                    $isToday = $dateKey === $todayKey;
                                    $val = $rowGrid[$dateKey] ?? '';
                                    $cls = $val === 'P' ? 'att-p' : ($val === 'A' ? 'att-a' : ($val === 'H' ? 'att-h' : ''));
                                    if ($isToday) {
                                        $cls .= ' att-today-cell';
                                    }
                                @endphp
                                <td class="{{ $isToday ? 'att-today-col' : '' }}">
                                    <select class="form-select form-select-sm att-cell {{ $cls }}"
                                            data-att-cell
                                            data-employee-id="{{ $employee->id }}"
                                            data-date="{{ $dateKey }}">
                                        <option value="" @selected($val === '')>—</option>
                                        <option value="P" @selected($val === 'P')>P</option>
                                        <option value="A" @selected($val === 'A')>A</option>
                                        <option value="H" @selected($val === 'H')>H</option>
                                    </select>
                                </td>
                            @endforeach
                            <td class="att-sticky-summary text-success" data-summary-p="{{ $employee->id }}">{{ $summary['present'] }}</td>
                            <td class="att-sticky-summary text-danger" data-summary-a="{{ $employee->id }}">{{ $summary['absent'] }}</td>
                            <td class="att-sticky-summary text-primary" data-summary-h="{{ $employee->id }}">{{ $summary['holiday'] }}</td>
                            <td class="att-sticky-summary text-danger" data-summary-d="{{ $employee->id }}" data-per-day="{{ $summary['per_day'] }}">
                                {{ number_format($summary['deduction'], 2) }}
                            </td>
                        </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-secondary">Save karne par payroll draft mein absent deduction auto update hogi.</span>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i> Save attendance
                </button>
            </div>
        </div>
    </form>
@endsection

@section('scripts')
<script>
(() => {
    function paintCell(sel) {
        sel.classList.remove('att-p', 'att-a', 'att-h');
        if (sel.value === 'P') sel.classList.add('att-p');
        if (sel.value === 'A') sel.classList.add('att-a');
        if (sel.value === 'H') sel.classList.add('att-h');
    }

    function refreshRowSummary(employeeId) {
        const selects = document.querySelectorAll(`select[name^="attendance[${employeeId}]"]`);
        let p = 0, a = 0, h = 0;
        selects.forEach((s) => {
            if (s.value === 'P') p++;
            if (s.value === 'A') a++;
            if (s.value === 'H') h++;
        });
        const pEl = document.querySelector(`[data-summary-p="${employeeId}"]`);
        const aEl = document.querySelector(`[data-summary-a="${employeeId}"]`);
        const hEl = document.querySelector(`[data-summary-h="${employeeId}"]`);
        const dEl = document.querySelector(`[data-summary-d="${employeeId}"]`);
        if (pEl) pEl.textContent = String(p);
        if (aEl) aEl.textContent = String(a);
        if (hEl) hEl.textContent = String(h);
        if (dEl) {
            const perDay = Number(dEl.dataset.perDay || 0);
            dEl.textContent = (perDay * a).toFixed(2);
        }
    }

    document.querySelectorAll('[data-att-cell]').forEach((sel) => {
        paintCell(sel);
        sel.addEventListener('change', () => {
            paintCell(sel);
            const empId = sel.dataset.employeeId;
            if (empId) refreshRowSummary(empId);
        });
    });

    const form = document.getElementById('attendanceGridForm');
    const jsonInput = document.getElementById('attendanceJson');
    form?.addEventListener('submit', () => {
        const payload = {};
        document.querySelectorAll('[data-att-cell]').forEach((sel) => {
            const empId = sel.dataset.employeeId;
            const date = sel.dataset.date;
            if (!empId || !date) return;
            if (!payload[empId]) payload[empId] = {};
            payload[empId][date] = sel.value;
        });
        if (jsonInput) jsonInput.value = JSON.stringify(payload);
    });

    const wrap = document.querySelector('.attendance-grid-wrap');
    const todayHead = document.querySelector('th.att-today-col');
    if (wrap && todayHead) {
        const stickyWidth = document.querySelector('.attendance-grid .att-sticky-col')?.offsetWidth || 160;
        const targetLeft = todayHead.offsetLeft - stickyWidth - 12;
        wrap.scrollLeft = Math.max(0, targetLeft);
    }
})();
</script>
@endsection
