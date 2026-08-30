@extends('layouts.admin')

@section('title', 'New Advance — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $prefillEmployee = $prefillEmployee ?? null;
        $returnToLedger = $returnToLedger ?? false;
    @endphp

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">New Employee Advance</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('employees.advances.store') }}">
                        @csrf
                        @if($returnToLedger)
                            <input type="hidden" name="return_to_ledger" value="1">
                        @endif

                        @if($prefillEmployee)
                            <div class="mb-3">
                                <label class="form-label">Employee</label>
                                <input type="text" class="form-control" readonly
                                       value="{{ $prefillEmployee->employee_no }} — {{ $prefillEmployee->name }}">
                                <input type="hidden" name="employee_id" value="{{ $prefillEmployee->id }}">
                            </div>
                        @else
                            <div class="mb-3">
                                @include('employees.partials.employee-search-select', [
                                    'employees' => $employees,
                                    'fieldId' => 'advanceEmployeePicker',
                                ])
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Advance Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                                       value="{{ old('amount', $advance->amount) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="{{ old('start_date', $advance->start_date?->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $advance->notes) }}</textarea>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">Save Entry</button>
                            @if($prefillEmployee)
                                <a href="{{ route('employees.advances.ledger', $prefillEmployee) }}" class="btn btn-outline-secondary">Back to Ledger</a>
                            @else
                                <a href="{{ route('employees.advances.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
