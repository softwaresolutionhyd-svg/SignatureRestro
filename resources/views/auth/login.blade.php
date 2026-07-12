@extends('layouts.auth')

@section('title', __('Login'))

@php($loginBrand = login_page_branding())
@php($foodCollage = login_page_food_collage(6))

@section('content')
<div class="auth-page">
    <div class="auth-hero" aria-hidden="true">
        @if (count($foodCollage) > 0)
            <div class="auth-hero-collage">
                @foreach ($foodCollage as $index => $imageUrl)
                    <div class="auth-hero-collage-item auth-hero-collage-item--{{ $index + 1 }}">
                        <img src="{{ $imageUrl }}" alt="" loading="lazy" decoding="async">
                    </div>
                @endforeach
            </div>
        @endif
        <div class="auth-hero-overlay"></div>
        <div class="auth-hero-content">
            @if ($logoUrl = company_logo_url($loginBrand['company_logo'] ?? ''))
                <div class="auth-hero-logo mb-3">
                    <img src="{{ $logoUrl }}" alt="{{ $loginBrand['company_name'] }}">
                </div>
            @endif
            <div class="auth-hero-badge">
                <i class="bi bi-stars"></i>
                <span>Management Portal</span>
            </div>
            <h2 class="auth-hero-title">Fine dining.<br>Flawless operations.</h2>
            <p class="auth-hero-text">Manage orders, staff, inventory and reports — all in one elegant workspace.</p>
            <ul class="auth-hero-features">
                <li><i class="bi bi-check2-circle"></i> Real-time order tracking</li>
                <li><i class="bi bi-check2-circle"></i> Kitchen &amp; floor coordination</li>
                <li><i class="bi bi-check2-circle"></i> Secure staff access</li>
            </ul>
        </div>
    </div>

    <div class="auth-shell">
        <div class="auth-panel">
            <div class="auth-panel-inner">
                <div class="auth-brand-top">
                    @if ($logoUrl = company_logo_url($loginBrand['company_logo'] ?? ''))
                        <div class="auth-logo-wrap">
                            <img src="{{ $logoUrl }}"
                                 alt="{{ $loginBrand['company_name'] }}"
                                 class="company-logo">
                        </div>
                    @else
                        <div class="auth-logo-fallback" aria-hidden="true">
                            <i class="bi bi-cup-hot-fill"></i>
                        </div>
                    @endif

                    <p class="auth-welcome">{{ __('Welcome back') }}</p>
                    @if (empty($loginBrand['company_logo'] ?? ''))
                        <h1 class="auth-company-name">{{ $loginBrand['company_name'] }}</h1>
                    @endif

                    @if (! empty(trim($loginBrand['company_phone'] ?? '')))
                        <p class="auth-contact mb-0">
                            <i class="bi bi-telephone-fill"></i>
                            <a href="tel:{{ preg_replace('/\s+/', '', $loginBrand['company_phone']) }}">{{ $loginBrand['company_phone'] }}</a>
                        </p>
                    @endif
                </div>

                <p class="auth-heading">{{ __('Sign in to your account') }}</p>

                @if(!empty($currentUser))
                    <div class="alert alert-info py-2 small mb-3">
                        Abhi signed in: <strong>{{ $currentUser->loginUsername() ?? $currentUser->email }}</strong>
                        ({{ $currentUser->name }}).
                        Doosra account kholne ke liye neeche username/password likhein — purana account replace ho jayega.
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning py-2 small">{{ session('warning') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate class="auth-form" autocomplete="off">
                    @csrf

                    <label class="auth-label" for="login">{{ __('Username') }}</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-person input-icon" aria-hidden="true"></i>
                        <input id="login" type="text" name="login" value="{{ old('login') }}"
                               class="form-control auth-no-autofill @error('login') is-invalid @enderror"
                               placeholder="ordertaker, rana_shahid, admin"
                               required
                               autocomplete="username" autocorrect="off" autocapitalize="off" spellcheck="false"
                               autofocus>
                        @error('login')
                            <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="auth-label" for="password">{{ __('Password') }}</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-key input-icon" aria-hidden="true"></i>
                        <input id="password" type="password" name="password"
                               class="form-control auth-no-autofill @error('password') is-invalid @enderror"
                               placeholder="{{ __('Enter your password') }}" required
                               autocomplete="current-password" autocorrect="off" spellcheck="false">
                        @error('password')
                            <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="auth-btn-submit">
                        <span>{{ __('Sign In') }}</span>
                        <i class="bi bi-arrow-right-short"></i>
                    </button>

                    <a class="auth-forgot text-decoration-none" href="{{ route('password-reset-request.create') }}">
                        <i class="bi bi-shield-lock"></i>
                        Password reset ki request (admin)
                    </a>
                </form>
            </div>
        </div>

        <div class="auth-footer-global">
            @if ($logoUrl = company_logo_url($loginBrand['company_logo'] ?? ''))
                <img src="{{ $logoUrl }}" alt="{{ $loginBrand['company_name'] }}" width="36" height="36" class="auth-footer-logo">
                <span>{{ $loginBrand['company_name'] }}</span>
            @else
                <img src="{{ asset('images/stair-logo.svg') }}" alt="Stair" width="32" height="32">
                <span>Stair by Software Solutions</span>
            @endif
        </div>
    </div>
</div>
@endsection
