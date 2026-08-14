<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $ok ? (($already ?? false) ? 'Already punched' : 'Present') : 'QR Attendance' }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: {{ $ok ? ($already ?? false ? '#1e3a5f' : '#14532d') : '#7f1d1d' }};
            color: #fff;
        }
        .card {
            width: min(420px, 100%);
            text-align: center;
        }
        .icon { font-size: 3.5rem; line-height: 1; margin-bottom: .5rem; }
        h1 { font-size: 1.75rem; margin: 0 0 .35rem; }
        .name { font-size: 1.35rem; font-weight: 700; margin: .4rem 0; }
        .meta { opacity: .9; font-size: 1rem; }
        .msg { margin-top: 1rem; font-size: 1.05rem; line-height: 1.4; }
        .photo {
            width: 96px; height: 120px; object-fit: cover;
            border-radius: 8px; border: 2px solid rgba(255,255,255,.4);
            margin: .75rem auto; display: block; background: rgba(0,0,0,.15);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{{ $ok ? (($already ?? false) ? '✓' : '●') : '!' }}</div>
        <h1>{{ $title ?? ($ok ? (($already ?? false) ? 'Attendance already punched' : 'Present') : 'Not marked') }}</h1>
        @if($employee)
            @if($employee->photoUrl())
                <img class="photo" src="{{ $employee->photoUrl() }}" alt="">
            @endif
            <div class="name">{{ $employee->name }}</div>
            <div class="meta">{{ $employee->employee_no }}</div>
        @endif
        <div class="msg">{{ $message }}</div>
        <div class="meta" style="margin-top:1rem;">{{ $date }} · {{ $time }}</div>
    </div>
</body>
</html>
