@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <img src="{{ asset('images/stair-logo.svg') }}" alt="" width="56" height="56" class="mb-3">
                    <h1 class="h4 fw-bold mb-2">{{ __('No employee access') }}</h1>
                    <p class="text-secondary mb-4">
                        {{ __('This login is not linked to an active employee record. Only registered employees can use :app. Please contact your administrator.', ['app' => config('app.name', 'Stair')]) }}
                    </p>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
