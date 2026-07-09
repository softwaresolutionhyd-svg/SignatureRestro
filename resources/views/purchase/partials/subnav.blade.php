<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('purchase.index') }}" class="btn btn-outline-primary {{ request()->routeIs('purchase.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('purchase.orders.index') }}" class="btn btn-outline-primary {{ request()->routeIs('purchase.orders.*') ? 'active' : '' }}">
            <i class="bi bi-receipt me-1"></i> RFQs / POs
        </a>
        <a href="{{ route('purchase.vendors.index') }}" class="btn btn-outline-primary {{ request()->routeIs('purchase.vendors.*') ? 'active' : '' }}">
            <i class="bi bi-building me-1"></i> Vendors
        </a>
    </div>
    <a href="{{ route('purchase.orders.create') }}" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> New Purchase
    </a>
</div>

