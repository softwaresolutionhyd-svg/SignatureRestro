<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#212121">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Odoo Dark Theme CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #000000;
            color: #FFFFFF;
            overflow-x: hidden;
        }

        .odoo-dark-layout {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #000000;
        }

        /* Top Navigation */
        .odoo-dark-navbar {
            background: #212121;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 1px solid #333333;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .odoo-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 500;
            color: #FFFFFF;
            text-decoration: none;
        }

        .odoo-brand i {
            font-size: 24px;
            color: #875A7B;
        }

        .odoo-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .odoo-company-selector {
            background: #2A2A2A;
            border: 1px solid #444444;
            color: #FFFFFF;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .odoo-company-selector:hover {
            background: #333333;
        }

        .odoo-nav-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2A2A2A;
            border: 1px solid #444444;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .odoo-nav-icon:hover {
            background: #333333;
        }

        .odoo-nav-icon.active {
            background: #875A7B;
            border-color: #875A7B;
        }

        .odoo-notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            background: #FF4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .odoo-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #875A7B, #6C528B);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid #875A7B;
        }

        /* Main Content */
        .odoo-dark-main {
            flex: 1;
            padding: 40px;
            background: #000000;
        }

        .odoo-dark-header {
            margin-bottom: 40px;
        }

        .odoo-dark-title {
            font-size: 32px;
            font-weight: 300;
            color: #FFFFFF;
            margin-bottom: 8px;
        }

        .odoo-dark-subtitle {
            font-size: 16px;
            color: #CCCCCC;
        }

        /* App Grid */
        .odoo-app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }

        .odoo-app-card {
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            text-decoration: none;
            color: #FFFFFF;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .odoo-app-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(135, 90, 123, 0.1), rgba(108, 82, 139, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .odoo-app-card:hover {
            transform: translateY(-4px);
            border-color: #875A7B;
            box-shadow: 0 8px 25px rgba(135, 90, 123, 0.3);
        }

        .odoo-app-card:hover::before {
            opacity: 1;
        }

        .odoo-app-icon-container {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .odoo-app-icon {
            font-size: 32px;
            color: #FFFFFF;
            position: relative;
            z-index: 1;
        }

        .odoo-app-label {
            font-size: 14px;
            font-weight: 500;
            color: #FFFFFF;
            position: relative;
            z-index: 1;
        }

        /* App Icon Colors */
        .app-dashboard { background: linear-gradient(135deg, #FF6B6B, #FF8787); }
        .app-discuss { background: linear-gradient(135deg, #4ECDC4, #44A08D); }
        .app-calendar { background: linear-gradient(135deg, #45B7D1, #2196F3); }
        .app-pos { background: linear-gradient(135deg, #F7B731, #F5A623); }
        .app-employees { background: linear-gradient(135deg, #875A7B, #6C528B); }
        .app-inventory { background: linear-gradient(135deg, #5CB85C, #449D44); }
        .app-purchase { background: linear-gradient(135deg, #F0AD4E, #EC971F); }
        .app-accounting { background: linear-gradient(135deg, #5BC0DE, #31B0D5); }
        .app-projects { background: linear-gradient(135deg, #D9534F, #C9302C); }
        .app-reports { background: linear-gradient(135deg, #777777, #555555); }

        /* Responsive */
        @media (max-width: 768px) {
            .odoo-dark-main {
                padding: 20px;
            }

            .odoo-app-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 16px;
            }

            .odoo-app-card {
                padding: 16px;
            }

            .odoo-app-icon-container {
                width: 60px;
                height: 60px;
                margin: 0 auto 12px;
            }

            .odoo-app-icon {
                font-size: 24px;
            }

            .odoo-dark-title {
                font-size: 24px;
            }
        }

        /* Dropdown Menu */
        .odoo-dropdown {
            background: #2A2A2A;
            border: 1px solid #444444;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }

        .odoo-dropdown-item {
            color: #FFFFFF;
            padding: 12px 16px;
            transition: background 0.3s ease;
        }

        .odoo-dropdown-item:hover {
            background: #333333;
        }

        .odoo-dropdown-header {
            background: #333333;
            color: #CCCCCC;
            padding: 8px 16px;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="odoo-dark-layout">
        <!-- Top Navigation -->
        <nav class="odoo-dark-navbar">
            <div class="odoo-brand">
                <i class="bi bi-box-seam"></i>
                <span>{{ config('app.name') }}</span>
            </div>

            <div class="odoo-nav-right">
                <!-- Company Selector -->
                <div class="odoo-company-selector dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-building"></i>
                    <span>My Company</span>
                    <i class="bi bi-chevron-down small"></i>
                </div>

                <!-- Discuss Icon -->
                <div class="odoo-nav-icon">
                    <i class="bi bi-chat-dots"></i>
                </div>

                <!-- Notifications -->
                <div class="odoo-nav-icon">
                    <i class="bi bi-bell"></i>
                    <div class="odoo-notification-badge">3</div>
                </div>

                <!-- User Avatar -->
                <div class="odoo-user-avatar dropdown-toggle" data-bs-toggle="dropdown">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>

                <!-- User Dropdown -->
                <ul class="dropdown-menu odoo-dropdown dropdown-menu-end">
                    <li class="odoo-dropdown-header">{{ auth()->user()->email }}</li>
                    <li><a class="dropdown-item odoo-dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item odoo-dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider" style="border-color: #444444;"></li>
                    <li>
                        <a class="dropdown-item odoo-dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="odoo-dark-main">
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @yield('scripts')
</body>
</html>
