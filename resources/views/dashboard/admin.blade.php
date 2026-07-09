@extends('layouts.admin')

@section('title', 'Admin - ' . config('app.name'))
@section('page_title', 'Admin area')

@section('content')
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-1">Admin-only page</h5>
            <p class="card-text text-secondary mb-0">
                If you can see this, your <span class="fw-semibold">role middleware</span> is working.
            </p>
        </div>
    </div>
@endsection

