@extends('layouts.auth')

@section('title', 'Password reset request')

@php($loginBrand = login_page_branding())

@section('content')
<div class="auth-shell auth-shell--solo">
    <div class="auth-panel">
        <div class="auth-panel-inner">
            <div class="auth-brand-top">
                @if ($logoUrl = company_logo_url($loginBrand['company_logo'] ?? ''))
                    <div class="mb-2">
                        <img src="{{ $logoUrl }}"
                             alt="{{ $loginBrand['company_name'] }}"
                             class="company-logo">
                    </div>
                @endif
                <h1 class="auth-company-name">{{ $loginBrand['company_name'] }}</h1>
            </div>

            <p class="auth-heading">Password reset ki request</p>
            <p class="text-secondary small mb-3">Apna email likhein. Agar account mojood ho ga to admin ko request jaye gi. Admin reset ke baad naya password <strong>Abcd1234</strong> ho ga.</p>

            @if (session('status'))
                <div class="alert alert-success small">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password-reset-request.store') }}" novalidate autocomplete="off">
                @csrf
                <div class="auth-input-wrap">
                    <i class="bi bi-envelope input-icon" aria-hidden="true"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control auth-no-autofill @error('email') is-invalid @enderror"
                           placeholder="{{ __('Email Address') }}" required
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                           readonly
                           onfocus="this.removeAttribute('readonly')"
                           autofocus>
                    @error('email')
                        <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="auth-btn-submit">Request bhejein</button>
                <a class="auth-forgot text-decoration-none d-block text-center mt-2" href="{{ route('login') }}">Wapas login</a>
            </form>
        </div>
    </div>
</div>

<div class="auth-footer-global">
    <img src="{{ asset('images/stair-logo.svg') }}" alt="Stair" width="36" height="36">
    <span>Stair by Software Solutions</span>
</div>
@endsection
