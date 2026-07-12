<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>@yield('title', __('Login')) — {{ config('app.name', 'Stair') }}</title>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then((regs) => {
                regs.forEach((r) => r.unregister());
            }).catch(() => {});
        }
    </script>
    <meta name="theme-color" content="#1a1410">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:500,600,700|dm-sans:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/display-quality.css') }}?v=3">
    <style>
        :root {
            --auth-gold: #c9a84c;
            --auth-gold-light: #e8c872;
            --auth-gold-dark: #a8863a;
            --auth-wine: #6b2d3c;
            --auth-wine-deep: #4a1f2a;
            --auth-charcoal: #14100e;
            --auth-charcoal-soft: #1f1814;
            --auth-cream: #faf6ef;
            --auth-cream-dark: #f0e8da;
            --auth-ink: #2c2218;
            --auth-muted: #7a6f63;
            --auth-border: rgba(44, 34, 24, 0.12);
            --auth-font-display: 'Playfair Display', Georgia, 'Times New Roman', serif;
            --auth-font-body: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
        }

        body.auth-body {
            font-family: var(--auth-font-body);
            min-height: 100vh;
            margin: 0;
            background: var(--auth-charcoal);
            color: var(--auth-ink);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .auth-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 992px) {
            .auth-page {
                grid-template-columns: 1.05fr 0.95fr;
            }
        }

        /* Left hero panel */
        .auth-hero {
            display: none;
            position: relative;
            overflow: hidden;
            background: #14100e;
        }

        .auth-hero-collage {
            position: absolute;
            inset: 0;
            z-index: 0;
            display: grid;
            grid-template-columns: 1.15fr 0.95fr 1.1fr;
            grid-template-rows: repeat(3, 1fr);
            gap: 7px;
            padding: 10px;
        }

        .auth-hero-collage-item {
            overflow: hidden;
            border-radius: 14px;
            min-height: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
        }

        .auth-hero-collage-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.02);
            transition: transform 8s ease;
        }

        .auth-hero:hover .auth-hero-collage-item img {
            transform: scale(1.08);
        }

        .auth-hero-collage-item--1 { grid-column: 1; grid-row: 1 / 3; }
        .auth-hero-collage-item--2 { grid-column: 2; grid-row: 1; }
        .auth-hero-collage-item--3 { grid-column: 3; grid-row: 1 / 3; }
        .auth-hero-collage-item--4 { grid-column: 2; grid-row: 2; }
        .auth-hero-collage-item--5 { grid-column: 1; grid-row: 3; }
        .auth-hero-collage-item--6 { grid-column: 2 / 4; grid-row: 3; }

        .auth-hero-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(115deg, rgba(20, 16, 14, 0.92) 0%, rgba(74, 31, 42, 0.78) 42%, rgba(20, 16, 14, 0.88) 100%),
                linear-gradient(to top, rgba(20, 16, 14, 0.95) 0%, transparent 45%);
            pointer-events: none;
        }

        @media (min-width: 992px) {
            .auth-hero {
                display: flex;
                align-items: center;
                padding: 3rem;
            }
        }

        .auth-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 85% 15%, rgba(201, 168, 76, 0.12) 0%, transparent 35%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.9;
            pointer-events: none;
        }

        .auth-hero-content {
            position: relative;
            z-index: 2;
            max-width: 28rem;
            color: var(--auth-cream);
        }

        .auth-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(201, 168, 76, 0.35);
            background: rgba(201, 168, 76, 0.1);
            color: var(--auth-gold-light);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.75rem;
        }

        .auth-hero-title {
            font-family: var(--auth-font-display);
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin: 0 0 1rem;
            color: #fff;
        }

        .auth-hero-text {
            font-size: 1.05rem;
            line-height: 1.65;
            color: rgba(250, 246, 239, 0.78);
            margin-bottom: 2rem;
        }

        .auth-hero-features {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.85rem;
        }

        .auth-hero-features li {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 0.95rem;
            color: rgba(250, 246, 239, 0.88);
            font-weight: 500;
        }

        .auth-hero-features i {
            color: var(--auth-gold);
            font-size: 1.1rem;
        }

        /* Right login panel */
        .auth-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem 1.5rem;
            background:
                radial-gradient(ellipse 90% 60% at 50% -10%, rgba(201, 168, 76, 0.08), transparent),
                linear-gradient(180deg, #faf6ef 0%, #f3ece1 100%);
        }

        .auth-panel {
            width: 100%;
            max-width: 440px;
            background: var(--auth-cream);
            border-radius: 1.5rem;
            border: 1px solid var(--auth-border);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.65) inset,
                0 24px 48px -20px rgba(44, 34, 24, 0.22),
                0 8px 24px -8px rgba(107, 45, 60, 0.12);
            overflow: hidden;
        }

        .auth-panel::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, var(--auth-wine) 0%, var(--auth-gold) 50%, var(--auth-wine) 100%);
        }

        .auth-panel-inner {
            padding: 2rem 1.75rem 1.85rem;
        }

        @media (min-width: 576px) {
            .auth-panel-inner {
                padding: 2.35rem 2.1rem 2rem;
            }
        }

        .auth-brand-top {
            text-align: center;
            margin-bottom: 1.65rem;
        }

        .auth-logo-wrap {
            margin-bottom: 0.85rem;
        }

        .auth-brand-top img.company-logo {
            max-height: 110px;
            max-width: 240px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 1rem;
            padding: 0.65rem;
            background: #fff;
            border: 1px solid var(--auth-border);
            box-shadow: 0 8px 24px rgba(44, 34, 24, 0.1);
        }

        .auth-hero-logo img {
            max-height: 72px;
            max-width: 200px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 0.85rem;
            padding: 0.45rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .auth-footer-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 0.5rem;
            background: #fff;
            padding: 2px;
        }

        .auth-logo-fallback {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--auth-wine) 0%, var(--auth-wine-deep) 100%);
            color: var(--auth-gold-light);
            font-size: 1.65rem;
            box-shadow: 0 8px 24px rgba(107, 45, 60, 0.25);
        }

        .auth-welcome {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--auth-gold-dark);
            margin: 0 0 0.35rem;
        }

        .auth-company-name {
            font-family: var(--auth-font-display);
            font-size: clamp(1.5rem, 4vw, 1.85rem);
            font-weight: 600;
            color: var(--auth-ink);
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin: 0 0 0.35rem;
        }

        .auth-contact {
            font-size: 0.875rem;
            color: var(--auth-muted);
            margin: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .auth-contact a {
            color: var(--auth-wine);
            font-weight: 600;
            text-decoration: none;
        }

        .auth-contact a:hover {
            color: var(--auth-gold-dark);
        }

        .auth-heading {
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--auth-muted);
            margin-bottom: 1.35rem;
            text-align: center;
        }

        .auth-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--auth-ink);
            margin-bottom: 0.4rem;
            letter-spacing: 0.01em;
        }

        .auth-form .auth-label:not(:first-of-type) {
            margin-top: 0.15rem;
        }

        .auth-input-wrap {
            position: relative;
            margin-bottom: 1rem;
        }

        .auth-input-wrap .form-control {
            border-radius: 0.75rem;
            border: 1px solid var(--auth-border);
            background: #fff;
            padding: 0.72rem 0.9rem 0.72rem 2.75rem;
            font-family: var(--auth-font-body);
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--auth-ink);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .auth-input-wrap .form-control::placeholder {
            color: #a89f94;
            font-weight: 400;
        }

        .auth-input-wrap .form-control:focus {
            border-color: var(--auth-gold);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.18);
        }

        .auth-input-wrap .input-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a89f94;
            font-size: 1rem;
            pointer-events: none;
            z-index: 2;
        }

        .auth-input-wrap:focus-within .input-icon {
            color: var(--auth-gold-dark);
        }

        .auth-input-wrap .form-control.auth-no-autofill:-webkit-autofill,
        .auth-input-wrap .form-control.auth-no-autofill:-webkit-autofill:hover,
        .auth-input-wrap .form-control.auth-no-autofill:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #fff inset;
            box-shadow: 0 0 0 1000px #fff inset;
            -webkit-text-fill-color: var(--auth-ink);
            caret-color: var(--auth-ink);
            border: 1px solid var(--auth-border);
            transition: background-color 99999s ease-out;
        }

        .auth-input-wrap .form-control.auth-no-autofill:-webkit-autofill:focus {
            border-color: var(--auth-gold);
            -webkit-box-shadow: 0 0 0 1000px #fff inset, 0 0 0 4px rgba(201, 168, 76, 0.18);
            box-shadow: 0 0 0 1000px #fff inset, 0 0 0 4px rgba(201, 168, 76, 0.18);
        }

        .auth-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0.25rem 0 1.35rem;
            font-size: 0.875rem;
        }

        .auth-options .form-check-label {
            color: var(--auth-muted);
            font-weight: 500;
        }

        .auth-options .form-check-input {
            border-color: #c4b8a8;
        }

        .auth-options .form-check-input:checked {
            background-color: var(--auth-wine);
            border-color: var(--auth-wine);
        }

        .auth-options .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(107, 45, 60, 0.18);
        }

        .auth-btn-submit {
            width: 100%;
            border: none;
            border-radius: 0.75rem;
            padding: 0.82rem 1.25rem;
            font-family: var(--auth-font-body);
            font-weight: 700;
            font-size: 0.975rem;
            letter-spacing: 0.02em;
            color: #fff;
            background: linear-gradient(135deg, var(--auth-wine) 0%, var(--auth-wine-deep) 55%, #3d1820 100%);
            box-shadow: 0 6px 20px rgba(107, 45, 60, 0.32);
            transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .auth-btn-submit i {
            font-size: 1.35rem;
            line-height: 1;
            transition: transform 0.15s;
        }

        .auth-btn-submit:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(107, 45, 60, 0.38);
            filter: brightness(1.05);
        }

        .auth-btn-submit:hover i {
            transform: translateX(3px);
        }

        .auth-btn-submit:active {
            transform: translateY(0);
        }

        .auth-forgot {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            margin-top: 1.15rem;
            font-size: 0.875rem;
            color: var(--auth-wine);
            font-weight: 600;
        }

        .auth-forgot:hover {
            color: var(--auth-gold-dark);
        }

        .auth-footer-global {
            margin-top: 1.75rem;
            text-align: center;
        }

        .auth-footer-global img {
            opacity: 0.85;
            filter: grayscale(0.2);
        }

        .auth-footer-global span {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--auth-muted);
            letter-spacing: 0.02em;
        }

        /* Password reset page (same layout, single column) */
        .auth-page:not(:has(.auth-hero)) .auth-shell,
        .auth-shell.auth-shell--solo {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body class="auth-body">
    @yield('content')
</body>
</html>
