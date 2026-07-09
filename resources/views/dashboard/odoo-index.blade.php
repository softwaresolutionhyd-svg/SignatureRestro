@extends('layouts.odoo')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <!-- Welcome Section -->
    <div class="mb-4">
        <h1 class="mb-2">Welcome back, {{ auth()->user()->name }}! 👋</h1>
        <p class="text-muted mb-0">Here's what's happening with your business today.</p>
    </div>

    <!-- Stats Cards -->
    <div class="odoo-stats-grid">
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">24</div>
            <div class="odoo-stat-label">Total Products</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">₹45,250</div>
            <div class="odoo-stat-label">Today's Sales</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">12</div>
            <div class="odoo-stat-label">Pending Orders</div>
        </div>
        <div class="odoo-stat-card">
            <div class="odoo-stat-value">8</div>
            <div class="odoo-stat-label">Total Employees</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="odoo-card">
        <div class="odoo-card-header">
            <i class="bi bi-lightning-charge me-2"></i> Quick Actions
        </div>
        <div class="odoo-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="{{ route('restaurant-pos.index') }}" class="btn btn-primary w-100">
                        <i class="bi bi-cash-stack me-2"></i> New Sale
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('purchase.orders.create') }}" class="btn btn-success w-100">
                        <i class="bi bi-cart-plus me-2"></i> New Purchase
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('inventory.products.create') }}" class="btn btn-info w-100">
                        <i class="bi bi-box-seam me-2"></i> Add Product
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('employees.create') }}" class="btn btn-warning w-100">
                        <i class="bi bi-person-plus me-2"></i> Add Employee
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Grid -->
    <div class="odoo-card">
        <div class="odoo-card-header">
            <i class="bi bi-grid-3x3-gap me-2"></i> Applications
        </div>
        <div class="odoo-card-body">
            <div class="odoo-app-grid">
                <a href="{{ route('dashboard') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div class="odoo-app-title">Dashboard</div>
                    <div class="odoo-app-desc">Business overview and analytics</div>
                </a>

                <a href="{{ route('restaurant-pos.index') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="odoo-app-title">Restaurant POS</div>
                    <div class="odoo-app-desc">Manage sales and transactions</div>
                </a>

                <a href="{{ route('inventory.index') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="odoo-app-title">Inventory</div>
                    <div class="odoo-app-desc">Track products and stock</div>
                </a>

                <a href="{{ route('purchase.index') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <div class="odoo-app-title">Purchase</div>
                    <div class="odoo-app-desc">Manage vendors and orders</div>
                </a>

                <a href="{{ route('employees.index') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="odoo-app-title">Employees</div>
                    <div class="odoo-app-desc">Manage staff and permissions</div>
                </a>

                <a href="{{ route('accounts.index') }}" class="odoo-app-card">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="odoo-app-title">Accounts</div>
                    <div class="odoo-app-desc">Chart of accounts & journal entries</div>
                </a>

                <a href="#" class="odoo-app-card" style="opacity: 0.6; cursor: not-allowed;">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                        <i class="bi bi-kanban"></i>
                    </div>
                    <div class="odoo-app-title">Projects</div>
                    <div class="odoo-app-desc">Project management (Coming Soon)</div>
                </a>

                <a href="#" class="odoo-app-card" style="opacity: 0.6; cursor: not-allowed;">
                    <div class="odoo-app-icon" style="background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="odoo-app-title">Reports</div>
                    <div class="odoo-app-desc">Business analytics (Coming Soon)</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="odoo-card">
        <div class="odoo-card-header">
            <i class="bi bi-clock-history me-2"></i> Recent Activity
        </div>
        <div class="odoo-card-body">
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-bag-check text-success me-2"></i>
                        <strong>New Sale</strong> - Order #1001 completed
                    </div>
                    <small class="text-muted">2 minutes ago</small>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-box-seam text-info me-2"></i>
                        <strong>Product Added</strong> - New item in inventory
                    </div>
                    <small class="text-muted">1 hour ago</small>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-person-plus text-warning me-2"></i>
                        <strong>New Employee</strong> - John Doe joined the team
                    </div>
                    <small class="text-muted">3 hours ago</small>
                </div>
            </div>
        </div>
    </div>
@endsection
