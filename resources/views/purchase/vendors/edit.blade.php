@extends('layouts.admin')

@section('title', 'Edit Vendor - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / Vendors / Edit')

@section('content')
    @include('purchase.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Edit vendor</div>
        <div class="card-body">
            <form method="POST" action="{{ route('purchase.vendors.update', $vendor) }}">
                @method('PUT')
                @include('purchase.vendors.form')
            </form>
        </div>
    </div>
@endsection

