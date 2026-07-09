@extends('layouts.admin')

@section('title', 'Recovery Codes — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">2FA Enabled</h4>
    <div class="text-secondary small">Recovery codes safe jagah save karein</div>
</div>

<div class="alert alert-warning">
    <strong>Important:</strong> Agar phone kho jaye to login ke liye yeh recovery codes use karein.
    Har code sirf ek dafa kaam karega. Is page ko dubara nahi dikhaya jayega.
</div>

<div class="card shadow-sm" style="max-width: 520px;">
    <div class="card-body">
        <div class="row g-2 font-monospace">
            @foreach ($recoveryCodes as $code)
                <div class="col-6">
                    <div class="border rounded px-3 py-2 bg-light user-select-all">{{ $code }}</div>
                </div>
            @endforeach
        </div>

        <a href="{{ route('profile.edit') }}" class="btn btn-primary mt-4">Profile par wapas jaein</a>
    </div>
</div>
@endsection
