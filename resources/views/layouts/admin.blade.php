<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#121212">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    {{-- Ensures header/theme without requiring `npm run build` (Vite CSS may be stale). --}}
    <link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}?v=11">
    <link rel="stylesheet" href="{{ asset('css/display-quality.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/admin-module-theme.css') }}?v=12">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @stack('head')
</head>
<body class="admin-app-body @if(request()->routeIs('dashboard')) admin-app-body--dashboard @endif">
    <div class="app-shell">
        @include('partials.admin.topbar')

        <main class="container-fluid @if(request()->routeIs('dashboard')) admin-main-dashboard @else py-4 admin-main @endif">
            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    @stack('scripts')
    @yield('scripts')
    @include('partials.sync-heartbeat')
    <script>
        // Disable service worker on admin panel to avoid stale/offline shell
        // replacing dynamic pages (e.g. BoMs index) in local development.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then((regs) => {
                regs.forEach((r) => r.unregister());
            }).catch(() => {});
        }
    </script>
</body>
</html>
