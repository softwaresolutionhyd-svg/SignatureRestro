@extends('layouts.admin')
@section('title', 'Bookings')
@section('content')
@include('guest-rooms.partials.subnav')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        @if(!empty($reservedToday))
            <h4 class="fw-bold mb-0">Today's reserved guests</h4>
            <p class="text-secondary small mb-0">Reserved bookings for stay including {{ fmt_date(now()) }}</p>
        @else
            <h4 class="fw-bold mb-0">Bookings</h4>
        @endif
    </div>
    <div class="d-flex gap-2">
        @if(!empty($reservedToday))
            <a href="{{ route('guest-rooms.bookings.index') }}" class="btn btn-outline-secondary btn-sm">All bookings</a>
        @endif
        <a href="{{ route('guest-rooms.bookings.create') }}" class="btn btn-primary btn-sm">+ New Booking</a>
    </div>
</div>
<form class="row g-2 mb-3" method="GET">
@if(!empty($reservedToday))<input type="hidden" name="reserved_today" value="1">@endif
<div class="col-auto"><input name="q" class="form-control form-control-sm" placeholder="PA No / C/O / Name / booking #" value="{{ request('q') }}"></div>
<div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">All status</option>@foreach(\App\Models\RoomBooking::statusLabels() as $k=>$v)<option value="{{ $k }}" @selected(request('status')==$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-auto"><select name="booking_type" class="form-select form-select-sm"><option value="">All types</option>@foreach(\App\Models\RoomBooking::bookingTypeLabels() as $k=>$v)<option value="{{ $k }}" @selected(request('booking_type')==$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-auto"><select name="guest_category" class="form-select form-select-sm"><option value="">All categories</option>@foreach(\App\Models\RoomBooking::guestCategoryLabels() as $k=>$v)<option value="{{ $k }}" @selected(request('guest_category')==$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Search</button></div></form>
<div class="card border-0 shadow-sm"><div class="card-body p-0">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th class="ps-3">Booking #</th><th>Type</th><th>Room type</th><th># Rooms</th><th>Guest cat.</th><th>PA No / C/O</th><th>Rank</th><th>Name</th><th>Room(s)</th><th>Check-in</th><th>Check-out</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead>
<tbody>@forelse($bookings as $b)<tr><td class="ps-3"><a href="{{ route('guest-rooms.bookings.show', $b) }}">{{ $b->booking_no }}</a></td>
<td><span class="badge bg-{{ ($b->booking_type ?? 'manual') === 'online' ? 'info' : 'dark' }}">{{ \App\Models\RoomBooking::bookingTypeLabels()[$b->booking_type ?? 'manual'] ?? 'Manual' }}</span></td>
<td>{{ $b->category?->name ?? '—' }}</td>
<td>{{ (int) ($b->rooms_count ?? 1) }}</td>
<td>{{ $b->guestCategoryLabel() ?? '—' }}</td>
<td>@if($b->isCivilianPersonType()){{ $b->care_of ?? '—' }}@else{{ $b->pa_no ?? '—' }}@endif</td>
<td>@if($b->isCivilianPersonType())—@else{{ $b->guest_rank ?? '—' }}@endif</td>
<td>{{ $b->guest_name }}</td>
<td>{{ $b->roomNumbersLabel() }}</td><td>{{ fmt_date($b->check_in_date) }}</td><td>{{ fmt_date($b->check_out_date) }}</td>
<td><span class="badge bg-secondary">{{ \App\Models\RoomBooking::statusLabels()[$b->status] ?? $b->status }}</span></td>
<td class="text-end pe-3">
    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
        <a href="{{ route('guest-rooms.bookings.show', $b) }}" class="btn btn-sm btn-outline-primary">View</a>
        @if($b->status === \App\Models\RoomBooking::STATUS_RESERVED)
        <form method="POST" action="{{ route('guest-rooms.bookings.cancel', $b) }}" class="d-inline m-0"
              onsubmit="return confirm('Cancel booking {{ $b->booking_no }}? Assigned rooms will be freed.')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
        </form>
        @endif
    </div>
</td></tr>
@empty<tr><td colspan="13" class="text-center py-4">@if(!empty($reservedToday))No reserved guests for today.@else No bookings.@endif</td></tr>@endforelse</tbody></table>
@if($bookings->hasPages())<div class="p-2">{{ $bookings->links() }}</div>@endif</div></div>
@endsection