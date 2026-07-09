@extends('layouts.admin')

@section('title', 'New Product - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Products / New')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Create product</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.products.store') }}" enctype="multipart/form-data">
                @include('inventory.products.form', ['product' => null, 'bomStandardCost' => null])
            </form>
        </div>
    </div>
@endsection

