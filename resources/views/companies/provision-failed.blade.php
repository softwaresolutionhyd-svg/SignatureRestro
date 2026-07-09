@extends('layouts.admin')

@section('title', 'Database setup failed — ' . config('app.name'))
@section('page_title', 'Database setup failed')

@section('content')
    <div class="alert alert-danger">
        <strong>{{ $company->name }}</strong> ke database ki setup mukammal nahi ho saki.
        @if($company->tenant_provision_error)
            <div class="small mt-2 font-monospace">{{ $company->tenant_provision_error }}</div>
        @endif
    </div>
    <p class="text-muted">Logs check karein (<code>storage/logs/laravel.log</code>) ya super admin se database / company record theek karwayein.</p>
    @if(auth()->user()?->isPlatformSuperAdmin())
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
    @else
        <p class="small mb-0">Apne administrator se rabta karein.</p>
    @endif
@endsection
