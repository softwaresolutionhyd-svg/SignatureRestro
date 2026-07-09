@extends('layouts.admin')
@section('title', 'Contacts — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Contacts</h4>
        <div class="text-secondary small">Customers, clients & vendors address book</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('credit-book.index') }}" class="btn btn-outline-secondary btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><rect x="3" y="2" width="14" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 7h6M7 11h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Credit Book
        </a>
        <a href="{{ route('contacts.create') }}" class="btn btn-primary btn-sm">+ New Contact</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Search name, phone, email…" value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="active" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="1" @selected(request('active')==='1')>Active</option>
                    <option value="0" @selected(request('active')==='0')>Inactive</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="contact-categories">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-2">Contact Categories</div>
        <form method="POST" action="{{ route('contacts.categories.store') }}" class="d-flex flex-wrap gap-2 align-items-center mb-2">
            @csrf
            <input type="text" name="label" class="form-control form-control-sm" style="max-width:14rem;" placeholder="New category name" maxlength="60" required>
            <button type="submit" class="btn btn-sm btn-outline-primary">+ Add Category</button>
        </form>
        <div class="d-flex flex-wrap gap-1">
            @foreach($categoryRows ?? [] as $cat)
                <span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 py-1 px-2">
                    {{ $cat['label'] }}
                    <form method="POST" action="{{ route('contacts.categories.destroy', $cat['slug']) }}" onsubmit="return confirm('Delete category {{ $cat['label'] }}?');" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 lh-1" title="Remove category">×</button>
                    </form>
                </span>
            @endforeach
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Category</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th class="text-center">Orders</th>
                    <th class="text-end">Outstanding Credit</th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $c)
                @php
                    $balance = round(((float)($c->total_credit ?? 0)) - ((float)($c->total_paid ?? 0)), 2);
                @endphp
                <tr>
                    <td class="ps-3">
                        <a href="{{ route('contacts.show', $c) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $c->name }}
                        </a>
                        @if($c->email)
                            <div class="text-secondary" style="font-size:11px;">{{ $c->email }}</div>
                        @endif
                    </td>
                    <td class="small text-secondary">{{ $c->categoryLabel() }}</td>
                    <td class="small text-secondary">{{ $c->phone ?? '—' }}</td>
                    <td class="small text-secondary">{{ $c->city ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary bg-opacity-15 text-secondary">{{ $c->pos_orders_count }}</span>
                    </td>
                    <td class="text-end fw-semibold small">
                        @if($balance > 0)
                            <span class="text-danger">{{ fmt_num($balance, 2) }}</span>
                        @elseif($balance < 0)
                            <span class="text-success">{{ fmt_num(abs($balance), 2) }} <small class="fw-normal">(overpaid)</small></span>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($c->active)
                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-15 text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="{{ route('contacts.show', $c) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">View</a>
                            <a href="{{ route('contacts.edit', $c) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Edit</a>
                            <form method="POST" action="{{ route('contacts.destroy', $c) }}"
                                onsubmit="return confirm('Delete this contact?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0 px-2">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-5 text-secondary">No contacts found. <a href="{{ route('contacts.create') }}">Add one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($contacts->hasPages())
        <div class="px-3 py-2 border-top">{{ $contacts->links() }}</div>
        @endif
    </div>
</div>
@endsection
