@extends('layouts.admin')

@section('title', ($advance->exists ? 'Edit' : 'New').' Advance — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">{{ $advance->exists ? 'Edit Advance' : 'New Employee Advance' }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ $advance->exists ? route('employees.advances.update', $advance) : route('employees.advances.store') }}">
                        @csrf
                        @if($advance->exists)
                            @method('PUT')
                        @endif

                        @if(!$advance->exists)
                            <div class="mb-3">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select employee…</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                            {{ $employee->employee_no }} — {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label">Employee</label>
                                <input type="text" class="form-control" readonly
                                       value="{{ $advance->employee?->employee_no }} — {{ $advance->employee?->name }}">
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Advance Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                                       value="{{ old('amount', $advance->amount) }}"
                                       @if($advance->exists && $advance->status === 'settled') disabled @else required @endif>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Balance</label>
                                <input type="text" class="form-control" readonly value="{{ number_format((float) $advance->balance, 2) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="{{ old('start_date', $advance->start_date?->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            @if($advance->exists)
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(old('status', $advance->status) === 'active')>Active</option>
                                    <option value="settled" @selected(old('status', $advance->status) === 'settled')>Settled</option>
                                    <option value="cancelled" @selected(old('status', $advance->status) === 'cancelled')>Cancelled</option>
                                </select>
                            </div>
                            @endif
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $advance->notes) }}</textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('employees.advances.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($advance->exists)
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Settlement Info</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-secondary">Status</span>
                        <span class="fw-semibold">{{ ucfirst($advance->status) }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-secondary">Settled Period</span>
                        <span class="fw-semibold">{{ $advance->settled_period ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-secondary">Settled At</span>
                        <span class="fw-semibold">{{ $advance->settled_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-secondary">Payroll Slip</span>
                        @if($advance->settledPayrollEntry)
                            <a href="{{ route('employees.payroll.slip', $advance->settledPayrollEntry) }}" target="_blank" rel="noopener">Open slip</a>
                        @else
                            <span class="fw-semibold">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
