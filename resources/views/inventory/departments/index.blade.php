@extends('layouts.admin')

@section('title', 'Departments - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Departments')

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Purchase receive hone par stock pehle <strong>Warehouse</strong> mein aata hai. Phir <a href="{{ route('inventory.issues.create') }}" class="alert-link">Issue to Department</a> se doosre departments ko bhejein.
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Departments</div>
            <div class="d-flex gap-2">
                <a href="{{ route('inventory.issues.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Issue Stock
                </a>
                <a href="{{ route('inventory.departments.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> New Department
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th class="text-end">Stock Qty</th>
                    <th>Products tagged</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($departments as $d)
                    <tr>
                        <td class="fw-semibold">
                            {{ $d->name }}
                            @if($d->is_warehouse)
                                <span class="badge text-bg-warning ms-1">Default Warehouse</span>
                            @endif
                        </td>
                        <td class="text-end">{{ fmt_num((float) ($d->stock_qty ?? 0), 3) }}</td>
                        <td>
                            @if($d->catalog_products_count > 0)
                                <a href="{{ route('inventory.products.index', ['department_id' => $d->id]) }}" class="text-decoration-none">
                                    {{ $d->catalog_products_count }}
                                </a>
                            @else
                                <span class="text-secondary">0</span>
                            @endif
                        </td>
                        <td>
                            @if($d->active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(! $d->is_warehouse)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory.departments.edit', $d) }}">Edit</a>
                                <form class="d-inline" method="POST" action="{{ route('inventory.departments.destroy', $d) }}"
                                      onsubmit="return confirm('Delete department? Issued stock history rahegi.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            @else
                                <span class="small text-secondary">System default</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No departments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $departments->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
