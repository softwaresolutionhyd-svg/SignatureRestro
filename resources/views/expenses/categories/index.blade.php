@extends('layouts.admin')
@section('title', 'Expense Categories — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between">
    <div>
        <h4 class="fw-bold mb-0">Expense Categories</h4>
        <div class="text-secondary small">Manage expense classification categories</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm">← Expenses</a>
        <a href="{{ route('expenses.categories.create') }}" class="btn btn-primary btn-sm">+ New Category</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Category Name</th>
                    <th>Description</th>
                    <th class="text-center">Expenses</th>
                    <th class="text-center">Active</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $cat->name }}</td>
                    <td class="text-secondary small">{{ $cat->description ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary bg-opacity-15 text-secondary">{{ $cat->expenses_count }}</span>
                    </td>
                    <td class="text-center">
                        @if($cat->active)
                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-15 text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="{{ route('expenses.categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Edit</a>
                            <form method="POST" action="{{ route('expenses.categories.destroy', $cat) }}"
                                onsubmit="return confirm('Delete this category?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0 px-2">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-secondary">No categories yet. <a href="{{ route('expenses.categories.create') }}">Create one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($categories->hasPages())
        <div class="px-3 py-2 border-top">{{ $categories->links() }}</div>
        @endif
    </div>
</div>
@endsection
