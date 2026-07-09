@extends('layouts.admin')

@section('title', 'New Category - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Categories / New')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Create category</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.categories.store') }}">
                @include('inventory.categories.form', ['category' => null])
            </form>
        </div>
    </div>
@endsection

