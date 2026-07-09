@extends('layouts.admin')

@section('title', 'New Purchase - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / New')

@section('content')
    @include('purchase.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">New Purchase</div>
        <div class="card-body">
            <form method="POST" action="{{ route('purchase.orders.store') }}">
                @include('purchase.orders.form', ['order' => null])
            </form>
        </div>
    </div>
@endsection

