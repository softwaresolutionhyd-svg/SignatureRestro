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
    
    <!-- Custom Odoo-style CSS -->
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

        .odoo-layout {
            display: flex;
            min-height: 100vh;
        }

        .odoo-sidebar {
            width: var(--odoo-sidebar-width);
            background: var(--odoo-primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .odoo-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .odoo-logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .odoo-nav {
            padding: 20px 0;
        }

        .odoo-nav-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin: 2px 0;
        }

        .odoo-nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .odoo-nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: white;
        }

        .odoo-nav-item i {
            width: 20px;
            margin-right: 12px;
        }

        .odoo-main {
            flex: 1;
            margin-left: var(--odoo-sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .odoo-topbar {
            height: var(--odoo-topbar-height);
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .odoo-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .odoo-user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .odoo-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: var(--odoo-light);
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .odoo-user-info:hover {
            background: #e9ecef;
        }

        .odoo-avatar {
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

        .odoo-content {
            flex: 1;
            padding: 30px;
            background: var(--odoo-light);
        }

        .odoo-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }

        .odoo-card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #333;
        }

        .odoo-card-body {
            padding: 25px;
        }

        .odoo-btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .odoo-btn-primary {
            background: var(--odoo-primary);
            color: white;
        }

        .odoo-btn-primary:hover {
            background: var(--odoo-primary-dark);
            color: white;
        }

        .odoo-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .odoo-stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--odoo-primary);
        }

        .odoo-stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--odoo-primary);
            margin-bottom: 5px;
        }

        .odoo-stat-label {
            color: #666;
            font-size: 14px;
        }

        .odoo-app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .odoo-app-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 2px solid transparent;
        }

        .odoo-app-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--odoo-primary);
            color: inherit;
        }

        .odoo-app-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }

        .odoo-app-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .odoo-app-desc {
            font-size: 12px;
            color: #666;
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
            .odoo-sidebar {
                transform: translateX(-100%);
            }

            .odoo-sidebar.show {
                transform: translateX(0);
            }

            .odoo-main {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .odoo-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="odoo-layout">
        <!-- Sidebar -->
        <div class="odoo-sidebar" id="odooSidebar">
            <div class="odoo-sidebar-header">
                <a href="{{ route('dashboard') }}" class="odoo-logo">
                    <i class="bi bi-box-seam"></i>
                    <span>{{ config('app.name') }}</span>
                </a>
            </div>
            
            <nav class="odoo-nav">
                <a href="{{ route('dashboard') }}" class="odoo-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('restaurant-pos.index') }}" class="odoo-nav-item {{ request()->routeIs('restaurant-pos.*') ? 'active' : '' }}">
                    <i class="bi bi-shop"></i> Restaurant POS
                </a>
                <a href="{{ route('inventory.index') }}" class="odoo-nav-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a href="{{ route('purchase.index') }}" class="odoo-nav-item {{ request()->routeIs('purchase.*') ? 'active' : '' }}">
                    <i class="bi bi-cart3"></i> Purchase
                </a>
                <a href="{{ route('employees.index') }}" class="odoo-nav-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> Employees
                </a>
                <a href="{{ route('maintenance.index') }}" class="odoo-nav-item {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
                    <i class="bi bi-tools"></i> Maintenance
                </a>
                <a href="{{ route('custom-forms.index') }}" class="odoo-nav-item {{ request()->routeIs('custom-forms.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text"></i> Custom Forms
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="odoo-main">
            <!-- Topbar -->
            <div class="odoo-topbar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="odoo-breadcrumb">
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

                <div class="odoo-user-menu">
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
                        <div class="odoo-user-info" data-bs-toggle="dropdown">
                            <div class="odoo-avatar">
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
                                <a class="dropdown-item" href="#"
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
            <div class="odoo-content">
                @yield('content')
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('odooSidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('odooSidebar');
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
