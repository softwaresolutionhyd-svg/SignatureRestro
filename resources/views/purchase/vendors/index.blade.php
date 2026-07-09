@extends('layouts.admin')

@section('title', 'Vendors - Purchase - ' . config('app.name'))
@section('page_title', 'Purchase / Vendors')

@section('content')
    @include('purchase.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Vendors</div>
            <a href="{{ route('purchase.vendors.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New Vendor
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($vendors as $v)
                    <tr>
                        <td class="fw-semibold">{{ $v->name }}</td>
                        <td class="text-secondary">{{ $v->email ?? '—' }}</td>
                        <td class="text-secondary">{{ $v->phone ?? '—' }}</td>
                        <td>
                            @if($v->active)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('purchase.vendors.edit', $v) }}">Edit</a>
                            <form class="d-inline" method="POST" action="{{ route('purchase.vendors.destroy', $v) }}"
                                  onsubmit="return confirm('Delete vendor?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No vendors yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $vendors->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

