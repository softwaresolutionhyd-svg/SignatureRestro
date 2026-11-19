@extends('layouts.admin')

@section('title', 'New Purchase - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / New')

@section('content')
    @include('partials.super-admin-reason-modal')
    @include('purchase.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->has('admin_reason'))
        <div class="alert alert-danger">{{ $errors->first('admin_reason') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">New Purchase</div>
        <div class="card-body">
            <form method="POST" action="{{ route('purchase.orders.store') }}"
                  @if(auth()->user()?->isSuperAdmin()) data-sa-require-reason="1" @endif>
                @include('purchase.orders.form', ['order' => null])
            </form>
        </div>
    </div>
@endsection

