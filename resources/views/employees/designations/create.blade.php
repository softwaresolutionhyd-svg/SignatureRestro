@extends('layouts.admin')

@section('title', 'New Designation - Employees - ' . config('app.name'))
@section('page_title', 'Employees / Designations / New')

@section('content')
    @include('hr.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">New designation</div>
            <a href="{{ route('employees.designations.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('employees.designations.store') }}">
                @include('employees.designations.form')
            </form>
        </div>
    </div>
@endsection

