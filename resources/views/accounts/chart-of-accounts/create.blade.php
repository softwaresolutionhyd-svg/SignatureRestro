@extends('layouts.admin')
@section('title', 'New Account — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">New Account</h4>
</div>

@include('accounts.partials.subnav')

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('accounts.chart-of-accounts.store') }}">
            @csrf
            @include('accounts.chart-of-accounts._form')
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save Account</button>
                <a href="{{ route('accounts.chart-of-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
