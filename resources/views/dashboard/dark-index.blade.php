@extends('layouts.odoo-dark')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <div class="odoo-dark-header">
        <h1 class="odoo-dark-title">Dashboard</h1>
        <p class="odoo-dark-subtitle">Welcome back, {{ auth()->user()->name }}! Select an app to get started.</p>
    </div>

    <div class="odoo-app-grid">
        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}" class="odoo-app-card">
            <div class="odoo-app-icon-container app-dashboard">
                <i class="bi bi-speedometer2 odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Dashboard</div>
        </a>

        <!-- Discuss -->
        <a href="#" class="odoo-app-card">
            <div class="odoo-app-icon-container app-discuss">
                <i class="bi bi-chat-dots odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Discuss</div>
        </a>

        <!-- Calendar -->
        <a href="#" class="odoo-app-card">
            <div class="odoo-app-icon-container app-calendar">
                <i class="bi bi-calendar3 odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Calendar</div>
        </a>

        <!-- Point of Sale -->
        <a href="{{ route('restaurant-pos.index') }}" class="odoo-app-card">
            <div class="odoo-app-icon-container app-pos">
                <i class="bi bi-shop odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Restaurant POS</div>
        </a>

        <!-- Employees -->
        <a href="{{ route('employees.index') }}" class="odoo-app-card">
            <div class="odoo-app-icon-container app-employees">
                <i class="bi bi-people-fill odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Employees</div>
        </a>

        <!-- Inventory -->
        <a href="{{ route('inventory.index') }}" class="odoo-app-card">
            <div class="odoo-app-icon-container app-inventory">
                <i class="bi bi-box-seam odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Inventory</div>
        </a>

        <!-- Purchase -->
        <a href="{{ route('purchase.index') }}" class="odoo-app-card">
            <div class="odoo-app-icon-container app-purchase">
                <i class="bi bi-cart3 odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Purchase</div>
        </a>

        <!-- Accounting -->
        <a href="#" class="odoo-app-card">
            <div class="odoo-app-icon-container app-accounting">
                <i class="bi bi-cash-coin odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Accounting</div>
        </a>

        <!-- Projects -->
        <a href="#" class="odoo-app-card">
            <div class="odoo-app-icon-container app-projects">
                <i class="bi bi-kanban odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Projects</div>
        </a>

        <!-- Reports -->
        <a href="#" class="odoo-app-card">
            <div class="odoo-app-icon-container app-reports">
                <i class="bi bi-graph-up odoo-app-icon"></i>
            </div>
            <div class="odoo-app-label">Reports</div>
        </a>
    </div>
@endsection
