@php
$pendingStockIn = \App\Models\PurchaseOrder::where('status', 'confirmed')->count();
$lowStockBadge = \App\Models\InventoryProduct::where('active', true)
    ->where('for_purchase', true)
    ->where('reorder_level', '>', 0)
    ->whereRaw('qty_on_hand <= reorder_level')
    ->excludingActiveBomFinishedProducts()
    ->count();
$outBadge = \App\Models\InventoryProduct::where('active', true)
    ->where('for_purchase', true)
    ->where('qty_on_hand', '<=', 0)
    ->count();
$alertBadge = $lowStockBadge + $outBadge;
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('inventory.products.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.products.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam me-1"></i> Products
        </a>
        <a href="{{ route('inventory.categories.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.categories.*') ? 'active' : '' }}">
            <i class="bi bi-tags me-1"></i> Categories
        </a>
        <a href="{{ route('inventory.departments.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.departments.*') ? 'active' : '' }}">
            <i class="bi bi-building me-1"></i> Departments
        </a>
        <a href="{{ route('inventory.issues.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.issues.*') ? 'active' : '' }}">
            <i class="bi bi-box-arrow-right me-1"></i> Issue Stock
        </a>
        <a href="{{ route('inventory.uom-library.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.uom-library.*') ? 'active' : '' }}">
            <i class="bi bi-rulers me-1"></i> Units
        </a>
        <a href="{{ route('inventory.moves.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.moves.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right me-1"></i> Stock Moves
        </a>
        <a href="{{ route('inventory.stock-in.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.stock-in.*') ? 'active' : '' }} position-relative">
            <i class="bi bi-box-arrow-in-down me-1"></i> Stock in
            @if($pendingStockIn > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:9px;">{{ $pendingStockIn }}</span>
            @endif
        </a>
        <a href="{{ route('inventory.stock-check.index') }}" class="btn btn-outline-primary {{ request()->routeIs('inventory.stock-check.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-check me-1"></i> Stock check
        </a>
        <a href="{{ route('manufacturing.index') }}" class="btn btn-outline-primary {{ request()->routeIs('manufacturing.*') ? 'active' : '' }}">
            <i class="bi bi-gear-wide-connected me-1"></i> Manufacturing
        </a>
        <a href="{{ route('maintenance.index') }}" class="btn btn-outline-primary {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
            <i class="bi bi-tools me-1"></i> Maintenance
        </a>
        <a href="{{ route('inventory.low-stock') }}" class="btn {{ request()->routeIs('inventory.low-stock') ? 'btn-warning' : 'btn-outline-warning' }} position-relative">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M10 2L2 17h16L10 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 7v4M10 13h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Low Stock
            @if($alertBadge > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;">{{ $alertBadge }}</span>
            @endif
        </a>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('inventory.moves.create', ['type' => 'wastage']) }}" class="btn btn-warning">
            <i class="bi bi-exclamation-triangle me-1"></i> Wastage
        </a>
        <a href="{{ route('inventory.moves.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Stock Adjustment
        </a>
    </div>
</div>

