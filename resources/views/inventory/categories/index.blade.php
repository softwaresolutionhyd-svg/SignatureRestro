@extends('layouts.admin')

@section('title', 'Categories - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Categories')

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Categories</div>
            <a href="{{ route('inventory.categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New Category
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Parent</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($categories as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->name }}</td>
                        <td class="text-secondary">{{ $c->parent?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('inventory.categories.edit', $c) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('inventory.categories.destroy', $c) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete category?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">No categories yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $categories->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

