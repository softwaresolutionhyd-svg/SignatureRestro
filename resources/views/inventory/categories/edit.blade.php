@extends('layouts.admin')

@section('title', 'Edit Category - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Categories / Edit')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Edit category</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.categories.update', $category) }}">
                @method('PUT')
                @include('inventory.categories.form')
            </form>
        </div>
    </div>
@endsection

