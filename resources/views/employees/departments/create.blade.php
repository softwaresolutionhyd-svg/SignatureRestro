@extends('layouts.admin')

@section('title', 'New Department - Employees - ' . config('app.name'))
@section('page_title', 'Employees / Departments / New')

@section('content')
    @include('hr.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">New department</div>
            <a href="{{ route('employees.departments.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('employees.departments.store') }}">
                @include('employees.departments.form')
            </form>
        </div>
    </div>
@endsection

