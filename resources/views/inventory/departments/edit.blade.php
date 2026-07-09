@extends('layouts.admin')

@section('title', 'Edit Department - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Departments / Edit')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Edit department</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.departments.update', $department) }}">
                @method('PUT')
                @include('inventory.departments.form', ['department' => $department])
            </form>
        </div>
    </div>
@endsection
