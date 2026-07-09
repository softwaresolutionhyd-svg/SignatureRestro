<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#714B67">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --odoo-primary: #714B67;
            --odoo-primary-dark: #5A3A52;
            --odoo-secondary: #00A09D;
            --odoo-light: #F8F9FA;
            --odoo-sidebar-width: 250px;
            --odoo-topbar-height: 60px;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--odoo-light);
            margin: 0;
            padding: 0;
        }

        .hybrid-layout {
            display: flex;
            min-height: 100vh;
        }

        .hybrid-sidebar {
            width: var(--odoo-sidebar-width);
            background: var(--odoo-primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .hybrid-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .hybrid-logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .hybrid-nav {
            padding: 20px 0;
        }

        .hybrid-nav-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin: 2px 0;
        }

        .hybrid-nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .hybrid-nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: white;
        }

        .hybrid-nav-item i {
            width: 20px;
            margin-right: 12px;
        }

        .hybrid-main {
            flex: 1;
            margin-left: var(--odoo-sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .hybrid-topbar {
            height: var(--odoo-topbar-height);
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .hybrid-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .hybrid-user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .hybrid-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: var(--odoo-light);
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .hybrid-user-info:hover {
            background: #e9ecef;
        }

        .hybrid-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--odoo-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .hybrid-content {
            flex: 1;
            padding: 30px;
            background: var(--odoo-light);
        }

        .mobile-menu-toggle {
            display: none;
            background: var(--odoo-primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .hybrid-sidebar {
                transform: translateX(-100%);
            }

            .hybrid-sidebar.show {
                transform: translateX(0);
            }

            .hybrid-main {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .hybrid-content {
                padding: 20px;
            }
        }

        /* Keep original launcher styles but adapt to new layout */
        .launcher-page {
            background: var(--odoo-light);
        }

        .launcher-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--odoo-primary);
            margin-bottom: 0.5rem;
        }

        .launcher-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
        }

        .launcher-badge {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .launcher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .launcher-app {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid transparent;
        }

        .launcher-app:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--odoo-primary);
            color: inherit;
        }

        .launcher-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 24px;
            color: white;
        }

        .launcher-label {
            font-weight: 600;
            font-size: 1rem;
        }

        .bg-grad-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-grad-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-grad-3 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-grad-4 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-grad-6 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    </style>
</head>
<body>
    <div class="hybrid-layout">
        <!-- Sidebar -->
        <div class="hybrid-sidebar" id="hybridSidebar">
            <div class="hybrid-sidebar-header">
                <a href="{{ route('dashboard') }}" class="hybrid-logo">
                    <i class="bi bi-box-seam"></i>
                    <span>{{ config('app.name') }}</span>
                </a>
            </div>
            
            <nav class="hybrid-nav">
                <a href="{{ route('dashboard') }}" class="hybrid-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('restaurant-pos.index') }}" class="hybrid-nav-item {{ request()->routeIs('restaurant-pos.*') ? 'active' : '' }}">
                    <i class="bi bi-shop"></i> Restaurant POS
                </a>
                <a href="{{ route('inventory.index') }}" class="hybrid-nav-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a href="{{ route('purchase.index') }}" class="hybrid-nav-item {{ request()->routeIs('purchase.*') ? 'active' : '' }}">
                    <i class="bi bi-cart3"></i> Purchase
                </a>
                <a href="{{ route('employees.index') }}" class="hybrid-nav-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> Employees
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="hybrid-main">
            <!-- Topbar -->
            <div class="hybrid-topbar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="hybrid-breadcrumb">
                        @foreach (\App\Support\AdminBreadcrumbs::items() as $crumb)
                            @if ($crumb['url'])
                                <a href="{{ $crumb['url'] }}" style="color: inherit; text-decoration: none;">{{ $crumb['label'] }}</a>
                            @else
                                <span>{{ $crumb['label'] }}</span>
                            @endif
                            @if (!$loop->last) <i class="bi bi-chevron-right" style="font-size: 12px;"></i> @endif
                        @endforeach
                    </div>
                </div>

                <div class="hybrid-user-menu">
                    <div class="dropdown">
                        <button class="btn btn-sm position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                0
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">No new notifications</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <div class="hybrid-user-info" data-bs-toggle="dropdown">
                            <div class="hybrid-avatar">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <div>
                                <div style="font-weight: 500;">{{ auth()->user()->name }}</div>
                                <div style="font-size: 12px; color: #666;">{{ auth()->user()->role }}</div>
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="hybrid-content">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('hybridSidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('hybridSidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>

    @yield('scripts')
</body>
</html>
