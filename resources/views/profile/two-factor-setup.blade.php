@extends('layouts.admin')

@section('title', 'Enable 2FA — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">Google Authenticator Setup</h4>
    <div class="text-secondary small">QR code scan karein aur code verify karein</div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card shadow-sm" style="max-width: 520px;">
    <div class="card-body">
        <p class="small text-secondary mb-2">
            Google Authenticator, Microsoft Authenticator, ya koi bhi TOTP app install karein.
            Neeche QR code scan karein ya manual key enter karein.
        </p>
        <ul class="small text-secondary mb-0">
            <li>Phone ki <strong>date/time automatic</strong> honi chahiye.</li>
            <li>Code enter karte waqt app ka <strong>latest</strong> 6-digit code use karein.</li>
            <li>Agar pehle scan kiya tha aur code galat aa raha hai, Authenticator se purani entry delete karke <a href="{{ route('profile.two-factor.reset') }}">naya QR code</a> scan karein.</li>
        </ul>

        <div class="text-center my-4">
            <div class="d-inline-block border rounded p-2 bg-white">
                {!! $qrCodeSvg !!}
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small text-secondary">Manual setup key</label>
            <div class="font-monospace user-select-all bg-light border rounded p-2 small">{{ $secret }}</div>
        </div>

        <form method="POST" action="{{ route('profile.two-factor.confirm') }}" autocomplete="off">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="code">Authenticator se 6-digit code</label>
                <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       value="{{ old('code') }}"
                       class="form-control text-center @error('code') is-invalid @enderror"
                       placeholder="000000" required autofocus autocomplete="one-time-code">
                @error('code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Enable 2FA</button>
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
