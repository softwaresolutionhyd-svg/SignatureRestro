@extends('layouts.admin')
@section('title', 'Request Leave — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">New Leave Request</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hr.leave.store') }}">
            @csrf

            @if($employees->isNotEmpty())
            <div class="mb-3">
                <label class="form-label">Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->name }} ({{ $emp->employee_no }})</option>
                    @endforeach
                </select>
                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @elseif($myEmployee)
            <div class="alert alert-light border small mb-3">
                Requesting leave for <strong>{{ $myEmployee->name }}</strong> ({{ $myEmployee->employee_no }})
            </div>
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Leave type <span class="text-danger">*</span></label>
                    <select name="leave_type" class="form-select @error('leave_type') is-invalid @enderror" required>
                        @foreach($typeLabels as $key => $label)
                            <option value="{{ $key }}" @selected(old('leave_type', 'annual') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control @error('start_date') is-invalid @enderror" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">End date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control @error('end_date') is-invalid @enderror" required>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" placeholder="Optional notes for HR">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
