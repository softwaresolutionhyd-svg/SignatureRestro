@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <img src="{{ asset('images/stair-logo.svg') }}" alt="" width="56" height="56" class="mb-3">
                    <h1 class="h4 fw-bold mb-2">No employee access</h1>
                    <p class="text-secondary mb-4">
                        This login is not linked to an active employee record. Only registered employees can use {{ config('app.name', 'Stair') }}.
                        Please contact your administrator.
                    </p>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
