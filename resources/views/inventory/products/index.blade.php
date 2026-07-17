@extends('layouts.admin')

@section('title', 'Products - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Products')

@section('content')
    @include('inventory.partials.subnav')

    @if(($showLowStockBanner ?? true) && $lowStockCount > 0)
    <div class="alert border-0 mb-3 d-flex align-items-center gap-3 py-2 px-3"
         style="background:#fffbeb;border-left:4px solid #f59e0b!important;">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" style="color:#f59e0b;flex-shrink:0;">
            <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div class="flex-grow-1">
            <strong>{{ $lowStockCount }} product{{ $lowStockCount > 1 ? 's' : '' }} below reorder level</strong>
            @if($outOfStockCount > 0)
            &nbsp;·&nbsp; <span class="text-danger fw-semibold">{{ $outOfStockCount }} out of stock</span>
            @endif
        </div>
        <a href="{{ route('inventory.low-stock') }}" class="btn btn-warning btn-sm">View Low Stock Report</a>
    </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('inventory.products.index') }}">
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search SKU, barcode or name..." style="max-width: 260px;">
                <select name="department_id" class="form-select" style="max-width:200px;">
                    <option value="">All Departments</option>
                    @foreach($departments as $dep)
                        <option value="{{ $dep->id }}" {{ (string) $departmentId === (string) $dep->id ? 'selected' : '' }}>
                            {{ $dep->name }}{{ $dep->active ? '' : ' (inactive)' }}
                        </option>
                    @endforeach
                </select>
                <select name="category_id" class="form-select" style="max-width:220px;">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>
                            @if($cat->parent_id)
                                {{ $cat->parent?->name }} › {{ $cat->name }}
                            @else
                                {{ $cat->name }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <select name="stock_filter" class="form-select" style="max-width:160px;">
                    <option value="" {{ !request('stock_filter') ? 'selected' : '' }}>All Products</option>
                    <option value="low"  {{ request('stock_filter')==='low'  ? 'selected' : '' }}>Low Stock</option>
                    <option value="zero" {{ request('stock_filter')==='zero' ? 'selected' : '' }}>Out of Stock</option>
                    <option value="ok"   {{ request('stock_filter')==='ok'   ? 'selected' : '' }}>Stock OK</option>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i> Filter</button>
                @if($q !== '' || request('stock_filter') || !empty($categoryId) || !empty($departmentId))
                    <a class="btn btn-outline-secondary" href="{{ route('inventory.products.index') }}">Clear</a>
                @endif
            </form>

            <a href="{{ route('inventory.products.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New Product
            </a>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:120px;">SKU</th>
                            <th style="min-width:220px;">Product</th>
                            <th>Department</th>
                            <th>Category</th>
                            <th class="text-end">Effective Cost</th>
                            <th class="text-end">Sale Price</th>
                            <th class="text-end">On Hand</th>
                            <th>Status</th>
                            <th class="text-end" style="min-width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $p)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $p->sku }}</div>
                                <div class="small text-secondary">{{ $p->barcode ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $p->name }}</div>
                                <div class="small text-secondary">{{ $p->uom }}</div>
                            </td>
                            <td>
                                @php
                                    $deptNames = $p->departments->pluck('name')->filter()->values();
                                    if ($deptNames->isEmpty() && $p->department) {
                                        $deptNames = collect([$p->department->name]);
                                    }
                                @endphp
                                {{ $deptNames->isNotEmpty() ? $deptNames->join(', ') : '—' }}
                            </td>
                            <td>{{ $p->category ? $p->category->breadcrumbLabel() : '—' }}</td>
                            <td class="text-end">{{ fmt_num((float) $p->total, 2) }}</td>
                            <td class="text-end">{{ fmt_num((float) $p->price, 2) }}</td>
                            <td class="text-end">
                                <div>
                                    <span class="fw-semibold {{ ($p->for_purchase ?? true) && $p->isLowStock() ? 'text-warning' : '' }}">
                                        {{ fmt_num((float) $p->qty_on_hand, 3) }}
                                    </span>
                                    <span class="text-secondary">{{ $p->uom }}</span>
                                </div>
                                @if($p->hasPackageContents())
                                    @php $innerQty = $p->qtyOnHandAsPackageContents(); @endphp
                                    @if($innerQty !== null)
                                        <div class="small text-secondary mt-1">
                                            ≈ {{ fmt_num((float) $innerQty, 3) }} {{ trim((string) $p->package_contents_uom) }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $p->active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $p->active ? 'Active' : 'Inactive' }}
                                </span>
                                @if($p->for_pos ?? true)
                                    <span class="badge text-bg-primary text-white">POS</span>
                                @endif
                                @if($p->for_purchase ?? true)
                                    <span class="badge text-bg-secondary text-white">Purchase</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('inventory.products.edit', ['product' => $p, 'return' => request()->getRequestUri()]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('inventory.products.destroy', $p) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete product?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">No products yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white">
            {{ $products->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

