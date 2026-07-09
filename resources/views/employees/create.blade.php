@extends('layouts.admin')

@section('title', 'New Employee - ' . config('app.name'))
@section('page_title', 'Employees / New')

@section('content')
    @include('hr.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">New employee</div>
            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('employees.store') }}">
                @include('employees.form')
            </form>
        </div>
    </div>
@endsection

