<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('maintenance.index') }}" class="btn btn-outline-primary {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
            <i class="bi bi-tools me-1"></i> Maintenance
        </a>
        <a href="{{ route('inventory.products.index', ['category_id' => optional(\App\Models\InventoryCategory::query()->whereRaw('LOWER(name)=?', ['maintenance'])->first())->id]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i> Maintenance Items (Inventory)
        </a>
    </div>
    <div class="small text-secondary">Manage items, demands and issue logs.</div>
</div>

