@extends('layouts.admin')

@section('title', 'Edit Employee - ' . config('app.name'))
@section('page_title', 'Employees / Edit')

@section('content')
    @include('hr.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Edit employee</div>
            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('employees.update', $employee) }}">
                @method('PUT')
                @include('employees.form')
            </form>
        </div>
    </div>

    @if($employee->user && ($employee->user->role ?? '') === 'user')
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">Reset staff password</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Login username: <strong>{{ \App\Support\LoginUsername::display($employee->user->email) }}</strong></p>
                <form method="POST" action="{{ route('employees.reset-password', $employee) }}">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">New password</label>
                            <input type="password" name="password" class="form-control" required minlength="6" maxlength="120" autocomplete="new-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="6" maxlength="120" autocomplete="new-password">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning">Update password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

