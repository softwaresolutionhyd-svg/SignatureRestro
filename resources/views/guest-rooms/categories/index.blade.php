@extends('layouts.admin')
@section('title', 'Room Categories')
@section('content')
@include('guest-rooms.partials.subnav')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Room Categories</h4>
    <a href="{{ route('guest-rooms.categories.create') }}" class="btn btn-primary btn-sm">+ New Category</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card border-0 shadow-sm"><div class="card-body p-0">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Name</th><th>Description</th><th>Rates</th><th>Rooms</th><th>Active</th><th class="text-end pe-3">Actions</th></tr></thead>
<tbody>
@forelse($categories as $cat)
<tr>
<td class="ps-3 fw-semibold">{{ $cat->name }}</td>
<td class="small text-secondary">{{ $cat->description ?? '—' }}</td>
<td>{{ $cat->rates_count }}</td><td>{{ $cat->guest_rooms_count }}</td>
<td>@if($cat->active)<span class="badge bg-success">Yes</span>@else<span class="badge bg-secondary">No</span>@endif</td>
<td class="text-end pe-3">
<a href="{{ route('guest-rooms.categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary">Edit</a>
<form method="POST" action="{{ route('guest-rooms.categories.destroy', $cat) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
</td></tr>
@empty<tr><td colspan="6" class="text-center py-4">No categories.</td></tr>@endforelse
</tbody></table>
@if($categories->hasPages())<div class="p-2">{{ $categories->links() }}</div>@endif
</div></div>
@endsection
