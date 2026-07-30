@extends('layouts.auth')

@section('title', __('Verify 2FA'))

@section('content')
<div class="auth-shell auth-shell--solo">
    <div class="auth-panel">
        <div class="auth-panel-inner">
            <div class="auth-brand-top">
                <div class="auth-logo-fallback" aria-hidden="true">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <p class="auth-welcome">{{ __('Security check') }}</p>
                <h1 class="auth-company-name">{{ __('Google Authenticator') }}</h1>
                <p class="auth-contact mb-0">
                    <i class="bi bi-phone-fill"></i>
                    {{ __('Apne Authenticator app se 6-digit code enter karein') }}
                </p>
            </div>

            <p class="auth-heading">{{ __('Login complete karne ke liye verification code chahiye') }}</p>

            <form method="POST" action="{{ route('login.verify-totp.submit') }}" class="auth-form" autocomplete="off">
                @csrf

                <label class="auth-label" for="code">{{ __('Authenticator Code') }}</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-123 input-icon" aria-hidden="true"></i>
                    <input id="code" type="text" name="code" inputmode="text" maxlength="20"
                           class="form-control auth-no-autofill text-center @error('code') is-invalid @enderror"
                           placeholder="000000" required autofocus autocomplete="one-time-code">
                    @error('code')
                        <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="auth-btn-submit">
                    <span>{{ __('Verify & Sign In') }}</span>
                    <i class="bi bi-arrow-right-short"></i>
                </button>
            </form>

            <p class="text-secondary small text-center mt-3 mb-0">
                {{ __('Recovery code hai? Upar wale field mein woh bhi enter kar sakte hain.') }}
            </p>

            <a class="auth-forgot text-decoration-none mt-3" href="{{ route('login') }}">
                <i class="bi bi-arrow-left"></i>
                {{ __('Wapas login') }}
            </a>
        </div>
    </div>
</div>
@endsection
