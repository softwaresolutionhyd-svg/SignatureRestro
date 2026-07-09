@extends('layouts.admin')

@section('title', 'Setting up database — ' . config('app.name'))
@section('page_title', 'Setting up your company database')

@section('content')
    <div class="card shadow-sm" style="max-width: 640px;">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
            <h2 class="h5">Database tayar ho rahi hai</h2>
            <p class="text-muted mb-0">
                Company <strong>{{ $company->name }}</strong> ke liye tables ban rahi hain — aksar <strong>1 se 2 minute</strong> lagte hain.
                Neeche wala button ya auto-refresh use karein.
            </p>
            <p class="small text-muted mt-3 mb-0">Page har 8 second baad khud refresh ho jayega.</p>
            <div class="mt-4 d-flex flex-wrap justify-content-center gap-2">
                <a href="{{ url()->current() }}" class="btn btn-primary">Abhi refresh karein</a>
                @if(auth()->user()?->isPlatformSuperAdmin())
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    setTimeout(function () { window.location.reload(); }, 8000);
</script>
@endsection
