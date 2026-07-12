@extends('layouts.admin')

@section('title', ($loan->exists ? 'Edit' : 'New').' Loan — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">{{ $loan->exists ? 'Edit Loan' : 'New Employee Loan' }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ $loan->exists ? route('employees.loans.update', $loan) : route('employees.loans.store') }}">
                        @csrf
                        @if($loan->exists)
                            @method('PUT')
                        @endif

                        @if(!$loan->exists)
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
                                       value="{{ $loan->employee?->employee_no }} — {{ $loan->employee?->name }}">
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Loan Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="loan_amount" class="form-control"
                                       value="{{ old('loan_amount', $loan->loan_amount) }}"
                                       @if($loan->exists && $loan->payments->isNotEmpty()) readonly @else required @endif>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Per Month Instalment <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="monthly_installment" class="form-control"
                                       value="{{ old('monthly_installment', $loan->monthly_installment) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Balance</label>
                                <input type="text" class="form-control" readonly value="{{ number_format((float) $loan->balance, 2) }}">
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="{{ old('start_date', $loan->start_date?->format('Y-m-d')) }}">
                            </div>
                            @if($loan->exists)
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(old('status', $loan->status) === 'active')>Active</option>
                                    <option value="completed" @selected(old('status', $loan->status) === 'completed')>Completed</option>
                                    <option value="cancelled" @selected(old('status', $loan->status) === 'cancelled')>Cancelled</option>
                                </select>
                            </div>
                            @endif
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $loan->notes) }}</textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('employees.loans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($loan->exists)
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Monthly Deduction History</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th class="text-end">Deducted</th>
                            <th class="text-end">Balance After</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($loan->payments as $payment)
                            <tr>
                                <td>{{ $payment->period }}</td>
                                <td class="text-end text-danger">{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $payment->balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-3">Abhi koi deduction nahi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
