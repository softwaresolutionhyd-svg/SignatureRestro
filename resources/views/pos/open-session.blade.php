@extends('layouts.admin')
@section('title', 'Open POS Session — ' . config('app.name'))

@push('head')
<style>
    body.admin-app-body .app-shell > main.container-fluid {
        padding: 0 !important;
        max-width: none;
    }
    body.admin-app-body .admin-topbar {
        display: none !important;
    }
    .pos-open-gate {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(160deg, #0f172a 0%, #1e293b 45%, #0f172a 100%);
        padding: 2rem 1.25rem;
    }
    .pos-open-card {
        width: 100%;
        max-width: 420px;
        text-align: center;
        color: #f8fafc;
    }
    .pos-open-icon {
        width: 88px;
        height: 88px;
        margin: 0 auto 1.5rem;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #f59e0b;
    }
    .pos-open-card h1 {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }
    .pos-open-card p {
        color: #94a3b8;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    .pos-open-form {
        margin-top: 0.5rem;
    }
    .btn-open-session {
        width: 100%;
        padding: 1rem 1.25rem;
        font-size: 1.125rem;
        font-weight: 700;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #111827;
        margin-top: 0;
    }
    .btn-open-session:hover {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #111827;
    }
    .pos-open-wait {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 14px;
        padding: 1.25rem;
        color: #cbd5e1;
    }
    .pos-open-back {
        display: inline-block;
        margin-top: 1.5rem;
        color: #94a3b8;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .pos-open-back:hover { color: #e2e8f0; }
</style>
@endpush

@section('content')
<div class="pos-open-gate">
    <div class="pos-open-card">
        <div class="pos-open-icon"><i class="bi bi-cash-register"></i></div>
        <h1>Restaurant POS</h1>
        <p>{{ now()->format('l, d M Y') }}</p>

        @if(session('success'))
            <div class="alert alert-success text-start mb-3">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning text-start mb-3">{{ session('warning') }}</div>
        @endif

        @if($canOpen)
            <p>Shift shuru karne ke liye pehle apni POS session open karein.<br>
            <span class="text-secondary small">Sirf <strong>CASHIER</strong> designation wale employee.</span></p>
            <form method="POST" action="{{ route('restaurant-pos.session.open') }}" class="pos-open-form">
                @csrf
                <button type="submit" class="btn btn-open-session">
                    <i class="bi bi-play-circle me-2"></i>Open POS Session
                </button>
            </form>
        @else
            <div class="pos-open-wait">
                <i class="bi bi-hourglass-split me-1"></i>
                Aaj ki POS session abhi open nahi hui.<br>
                <strong>Cashier</strong> se pehle session open karwaein, phir POS use karein.
            </div>
        @endif

        <a href="{{ route('dashboard') }}" class="pos-open-back">← Dashboard</a>
    </div>
</div>
@endsection
