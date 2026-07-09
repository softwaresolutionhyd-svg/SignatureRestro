@extends('layouts.admin')

@section('title', 'New Vendor - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / Vendors / New')

@section('content')
    @include('purchase.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Create vendor</div>
        <div class="card-body">
            <form method="POST" action="{{ route('purchase.vendors.store') }}">
                @include('purchase.vendors.form', ['vendor' => null])
            </form>
        </div>
    </div>
@endsection

