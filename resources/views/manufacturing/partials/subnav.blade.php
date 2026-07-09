<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('manufacturing.index') }}" class="btn btn-outline-primary {{ request()->routeIs('manufacturing.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('manufacturing.boms.index') }}" class="btn btn-outline-primary {{ request()->routeIs('manufacturing.boms.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3 me-1"></i> Bills of Materials
        </a>
        <a href="{{ route('manufacturing.orders.index') }}" class="btn btn-outline-primary {{ request()->routeIs('manufacturing.orders.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-check me-1"></i> Production Orders
        </a>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-seam me-1"></i> Inventory
        </a>
        <a href="{{ route('manufacturing.orders.create') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Order
        </a>
    </div>
</div>
