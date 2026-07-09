@extends('layouts.admin')

@section('title', 'Attendance — ' . config('app.name'))

@php($canManage = auth()->user()->canManageTeamAttendance())

@section('content')
@include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if(!$canManage)
        <div class="alert alert-info small mb-3">
            Aap <strong>sab employees</strong> ki attendance dekh sakte ho. Record lagane / edit / delete ke liye
            <strong>Admin</strong> hona zaroori hai, ya user ko <strong>Employees → Add / Edit / Delete</strong> mein se koi ijazat di ho.
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <div class="fw-semibold">Sab employees — {{ $month }} khulasa</div>
                <div class="small text-secondary">Har employee ka is mahine kitna present / absent / leave.</div>
            </div>
            <form class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('employees.attendance.index') }}">
                <input type="hidden" name="month" value="{{ $month }}">
                @if($employeeId)
                    <input type="hidden" name="employee_id" value="{{ $employeeId }}">
                @endif
                <div class="form-check form-check-inline small mb-0">
                    <input class="form-check-input" type="checkbox" name="active_only" value="1" id="activeOnly" @checked($activeOnly)>
                    <label class="form-check-label" for="activeOnly">Sirf active employees</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="submit">Refresh</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Status</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Leave</th>
                    <th class="text-center">Half day</th>
                    <th class="text-center">Days marked</th>
                    <th class="text-end">Filter log</th>
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $e)
                    @php($s = $statsByEmployee[$e->id] ?? ['present'=>0,'absent'=>0,'leave'=>0,'half_day'=>0,'total'=>0])
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $e->name }}</span>
                            <div class="small text-secondary">{{ $e->employee_no }}</div>
                        </td>
                        <td>
                            @if($e->active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $s['present'] }}</td>
                        <td class="text-center">{{ $s['absent'] }}</td>
                        <td class="text-center">{{ $s['leave'] }}</td>
                        <td class="text-center">{{ $s['half_day'] }}</td>
                        <td class="text-center fw-semibold">{{ $s['total'] }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.attendance.index', array_filter(['month' => $month, 'employee_id' => $e->id, 'active_only' => $activeOnly ? 1 : null])) }}">Log</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Koi employee record nahi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        @if($canManage)
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Attendance lagayein (manual)</div>
                <div class="card-body">
                    <div class="small text-secondary mb-2">Kisi bhi employee ke liye — neeche se sab employees chun sakte ho.</div>
                    <form method="POST" action="{{ route('employees.attendance.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">Employee</label>
                            <select name="employee_id" class="form-select form-select-sm @error('employee_id') is-invalid @enderror" required>
                                <option value="">—</option>
                                @foreach($employees as $e)
                                    <option value="{{ $e->id }}" @selected((string)old('employee_id') === (string)$e->id)>
                                        {{ $e->name }} ({{ $e->employee_no }})@if(!$e->active) — inactive @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Date</label>
                            <input type="date" name="attendance_date" value="{{ old('attendance_date', now()->format('Y-m-d')) }}" class="form-control form-control-sm @error('attendance_date') is-invalid @enderror" required>
                            @error('attendance_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small">Clock in</label>
                                <input type="datetime-local" name="clock_in" value="{{ old('clock_in') }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Clock out</label>
                                <input type="datetime-local" name="clock_out" value="{{ old('clock_out') }}" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-2 mt-2">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach(['present'=>'Present','absent'=>'Absent','leave'=>'Leave','half_day'=>'Half day'] as $k=>$v)
                                    <option value="{{ $k }}" @selected(old('status','present') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Notes</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>
                        </div>
                        <button class="btn btn-primary btn-sm w-100" type="submit">Save attendance</button>
                    </form>
                </div>
            </div>
        </div>
        @endif
        <div class="col-12 {{ $canManage ? 'col-lg-8' : '' }}">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="fw-semibold">Detail log</div>
                    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('employees.attendance.index') }}">
                        <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm">
                        <select name="employee_id" class="form-select form-select-sm" style="max-width: 220px;">
                            <option value="">Sab employees</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->id }}" @selected((string)$employeeId === (string)$e->id)>{{ $e->name }}@if(!$e->active) (inactive)@endif</option>
                            @endforeach
                        </select>
                        @if($activeOnly)
                            <input type="hidden" name="active_only" value="1">
                        @endif
                        <button class="btn btn-sm btn-outline-primary" type="submit">Apply</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                            <th>Source</th>
                            @if($canManage)<th class="text-end">Actions</th>@endif
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td class="text-nowrap">{{ $row->attendance_date?->format('Y-m-d') }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $row->employee?->name }}</span>
                                    <div class="small text-secondary">{{ $row->employee?->employee_no }}</div>
                                </td>
                                <td class="small text-secondary">{{ $row->clock_in?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="small text-secondary">{{ $row->clock_out?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td><span class="badge text-bg-light border">{{ $row->status }}</span></td>
                                <td class="small">{{ $row->source }}</td>
                                @if($canManage)
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $row->id }}">Edit</button>
                                    <form class="d-inline" method="POST" action="{{ route('employees.attendance.destroy', $row) }}" onsubmit="return confirm('Remove this row?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @if($canManage)
                            <tr class="collapse bg-light" id="edit-{{ $row->id }}">
                                <td colspan="7" class="p-3">
                                    <form method="POST" action="{{ route('employees.attendance.update', $row) }}" class="row g-2 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-3">
                                            <label class="form-label small">Clock in</label>
                                            <input type="datetime-local" name="clock_in" value="{{ optional($row->clock_in)->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Clock out</label>
                                            <input type="datetime-local" name="clock_out" value="{{ optional($row->clock_out)->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Status</label>
                                            <select name="status" class="form-select form-select-sm">
                                                @foreach(['present','absent','leave','half_day'] as $k)
                                                    <option value="{{ $k }}" @selected($row->status === $k)>{{ $k }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Notes</label>
                                            <input type="text" name="notes" value="{{ $row->notes }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-1">
                                            <button class="btn btn-sm btn-primary w-100" type="submit">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endif
                        @empty
                            <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-secondary py-4">Is filter ke liye koi row nahi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    {{ $rows->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
