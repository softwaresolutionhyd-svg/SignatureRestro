@extends('layouts.admin')
@section('title', 'Rooms')
@section('content')
@include('guest-rooms.partials.subnav')
<div class="d-flex justify-content-between mb-3"><h4 class="fw-bold mb-0">Rooms</h4><a href="{{ route('guest-rooms.rooms.create') }}" class="btn btn-primary btn-sm">+ Add Room</a></div>
<form class="row g-2 mb-3" method="GET">
<div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">All status</option>@foreach(\App\Models\GuestRoom::statusLabels() as $k=>$v)<option value="{{ $k }}" @selected(request('status')==$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Filter</button></div>
</form>
<div class="card border-0 shadow-sm"><div class="card-body p-0">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Room #</th><th>Category</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead>
<tbody>@forelse($rooms as $r)<tr><td class="ps-3 fw-semibold">{{ $r->room_number }}</td><td>{{ $r->category?->name ?? '—' }}</td><td><span class="badge bg-{{ $r->statusBadgeClass() }}">{{ \App\Models\GuestRoom::statusLabels()[$r->status] ?? $r->status }}</span></td>
<td class="text-end pe-3">
@if($r->status === 'cleaning')
<a href="{{ route('guest-rooms.cleaning.show', $r) }}" class="btn btn-sm btn-warning">Cleaning</a>
@endif
<a href="{{ route('guest-rooms.rooms.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
<form method="POST" action="{{ route('guest-rooms.rooms.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form></td></tr>
@empty<tr><td colspan="4" class="text-center py-4">No rooms.</td></tr>@endforelse</tbody></table>
@if($rooms->hasPages())<div class="p-2">{{ $rooms->links() }}</div>@endif</div></div>
@endsection
