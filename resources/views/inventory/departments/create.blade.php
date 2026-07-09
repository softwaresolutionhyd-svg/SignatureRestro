@extends('layouts.admin')

@section('title', 'New Department - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Departments / New')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Create department</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.departments.store') }}">
                @include('inventory.departments.form', ['department' => null])
            </form>
        </div>
    </div>
@endsection
