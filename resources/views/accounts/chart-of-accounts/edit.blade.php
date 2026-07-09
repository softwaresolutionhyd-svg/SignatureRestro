@extends('layouts.admin')
@section('title', 'Edit Account — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Edit Account — {{ $account->code }}</h4>
</div>

@include('accounts.partials.subnav')

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('accounts.chart-of-accounts.update', $account) }}">
            @csrf @method('PUT')
            @include('accounts.chart-of-accounts._form', ['account' => $account])
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Update Account</button>
                <a href="{{ route('accounts.chart-of-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
