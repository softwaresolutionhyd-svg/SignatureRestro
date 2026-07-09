@extends('layouts.admin')
@section('title', 'Booking ' . $booking->booking_no)
@section('content')
@include('guest-rooms.partials.subnav')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@include('guest-rooms.bookings.partials.pending-pos-bills-alert')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
<div><h4 class="fw-bold mb-0">{{ $booking->booking_no }}</h4>
<span class="badge bg-secondary">{{ \App\Models\RoomBooking::statusLabels()[$booking->status] ?? $booking->status }}</span>
<span class="badge bg-{{ ($booking->booking_type ?? 'manual') === 'online' ? 'info' : 'dark' }}">{{ \App\Models\RoomBooking::bookingTypeLabels()[$booking->booking_type ?? 'manual'] ?? 'Manual' }}</span></div>
<div class="d-flex flex-wrap gap-2 align-items-center admin-action-btns">
@if($booking->status==='reserved')
<a href="{{ route('guest-rooms.bookings.edit', $booking) }}" class="btn btn-outline-primary btn-sm">Edit Booking</a>
<form method="POST" action="{{ route('guest-rooms.bookings.cancel', $booking) }}" class="m-0" onsubmit="return confirm('Cancel booking?')">@csrf<button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button></form>
@endif
@if($booking->status==='checked_in')
@if($booking->canUndoCheckIn())
<form method="POST" action="{{ route('guest-rooms.bookings.undo-check-in', $booking) }}" class="m-0" onsubmit="return confirm('Undo check-in for {{ $booking->booking_no }}? Booking will return to Reserved.')">@csrf
<button type="submit" class="btn btn-outline-danger btn-sm">Undo Check-in</button></form>
@endif
<a href="{{ route('guest-rooms.bookings.change-rooms', $booking) }}" class="btn btn-outline-primary btn-sm">Change Rooms</a>
<a href="{{ route('guest-rooms.bookings.bill-receipt', ['booking' => $booking, 'print' => 1]) }}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print Bill</a>
@if($booking->bill)
    <a href="{{ route('guest-rooms.billing.show', $booking->bill) }}" class="btn btn-outline-secondary btn-sm">View Bill</a>
@endif
<a href="{{ route('guest-rooms.checkout-counter.show', $booking) }}" class="btn btn-outline-warning btn-sm">Checkout Counter</a>
@endif
@if($booking->status==='checked_out' && $booking->bill)
    <a href="{{ route('guest-rooms.billing.show', $booking->bill) }}" class="btn btn-outline-secondary btn-sm">View Bill</a>
    <a href="{{ route('guest-rooms.billing.receipt', ['bill' => $booking->bill, 'print' => 1]) }}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
@endif
</div></div>
@if($booking->status==='reserved')
<div class="mb-3">
    @include('guest-rooms.bookings.partials.check-in-form', ['booking' => $booking, 'assignableRooms' => $assignableRooms])
</div>
@endif
<div class="row g-3"><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body">
<p><strong>Booking type:</strong> {{ \App\Models\RoomBooking::bookingTypeLabels()[$booking->booking_type ?? 'manual'] ?? 'Manual' }}</p>
@if($booking->isOnlineBooking() && $booking->voucher_no)
<p><strong>Voucher number:</strong> {{ $booking->voucher_no }}</p>
@endif
@if($booking->isCivilianPersonType())
<p><strong>C/O:</strong> {{ $booking->care_of ?? '—' }}</p>
@else
<p><strong>PA No:</strong> {{ $booking->pa_no ?? '—' }}</p>
<p><strong>Rank:</strong> {{ $booking->guest_rank ?? '—' }}</p>
@endif
<p><strong>{{ $booking->primaryGuestIsStaying() ? 'Name' : 'Contact / Booked by' }}:</strong> {{ $booking->guest_name }}
@if(! $booking->primaryGuestIsStaying())
<span class="badge bg-warning text-dark ms-1">Self nahi aa raha</span>
@endif
</p>
@if($booking->primaryGuestIsStaying() && $booking->guest_cnic)
<p><strong>CNIC:</strong> {{ $booking->guest_cnic }}</p>
@endif
<p><strong>Phone:</strong> {{ $booking->guest_phone ?? '—' }}</p>
<p><strong>{{ $booking->primaryGuestIsStaying() ? 'Members' : 'Staying guests' }}:</strong> {{ (int) ($booking->adults ?? 1) }} adult(s)@if((int) ($booking->children ?? 0) > 0), {{ (int) $booking->children }} child(ren)@endif</p>
@if($booking->members->isNotEmpty())
<div class="table-responsive mb-2">
    <table class="table table-sm table-bordered mb-0 small">
        <thead class="table-light">
            <tr><th>Type</th><th>Name</th><th>CNIC</th><th>Relation</th></tr>
        </thead>
        <tbody>
            @foreach($booking->members->where('member_type', \App\Models\RoomBookingMember::TYPE_ADULT)->sortBy('sort_order') as $m)
            <tr>
                <td>Adult {{ $m->sort_order + 1 }}</td>
                <td>{{ $m->name }}</td>
                <td>{{ $m->cnic ?? '—' }}</td>
                <td>{{ $m->relation ?? '—' }}</td>
            </tr>
            @endforeach
            @foreach($booking->members->where('member_type', \App\Models\RoomBookingMember::TYPE_CHILD)->sortBy('sort_order') as $m)
            <tr>
                <td>Child {{ $m->sort_order + 1 }}</td>
                <td>{{ $m->name }}</td>
                <td>—</td>
                <td>{{ $m->relation ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@if((int) ($booking->vehicles_count ?? 0) > 0 || $booking->vehicles->isNotEmpty())
<p><strong>Vehicles:</strong> {{ (int) ($booking->vehicles_count ?? $booking->vehicles->count()) }}</p>
@if($booking->vehicles->isNotEmpty())
<div class="table-responsive mb-2">
    <table class="table table-sm table-bordered mb-0 small">
        <thead class="table-light">
            <tr><th>#</th><th>Vehicle No</th><th>Driver</th><th>Driver CNIC</th><th>Driver Phone</th></tr>
        </thead>
        <tbody>
            @foreach($booking->vehicles->sortBy('sort_order') as $v)
            <tr>
                <td>{{ $v->sort_order + 1 }}</td>
                <td>{{ $v->vehicle_no }}</td>
                <td>{{ $v->driver_accompanying ? ($v->driver_name ?? '—') : '—' }}</td>
                <td>{{ $v->driver_accompanying ? ($v->driver_cnic ?? '—') : '—' }}</td>
                <td>{{ $v->driver_accompanying ? ($v->driver_phone ?? '—') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endif
<p><strong>Rooms required:</strong> {{ (int) ($booking->rooms_count ?? 1) }}</p>
<p><strong>Room type:</strong> {{ $booking->category?->name ?? '—' }}</p>
<p><strong>Room(s):</strong>
@if($booking->hasAssignedRooms())
    {{ $booking->roomNumbersLabel() }} @if($booking->billableRoomCount() > 1)<span class="badge bg-secondary">{{ $booking->billableRoomCount() }} rooms</span>@endif
@elseif($booking->status === 'reserved')
    <span class="text-secondary">Not assigned — use Assign rooms below</span>
@else
    —
@endif
@if($booking->category) <span class="text-secondary">({{ $booking->category->name }})</span>@endif
</p>
<p><strong>Stay:</strong> {{ fmt_date($booking->check_in_date) }} → {{ fmt_date($booking->check_out_date) }} ({{ $booking->nights }} nights)</p>
@if($booking->status === 'checked_in' || $booking->status === 'checked_out')
<p><strong>Check-in (billing):</strong> {{ $booking->checkInDisplayLabel() }}
@if($booking->actual_check_in && $booking->check_in_date && $booking->actual_check_in->format('Y-m-d') !== $booking->check_in_date->format('Y-m-d'))
<span class="text-secondary small">— system recorded {{ fmt_datetime($booking->actual_check_in) }}</span>
@endif
</p>
@endif
@if($booking->guest_category)
<p><strong>Guest category:</strong> {{ $booking->guestCategoryLabel() }}
    @if($booking->guestCategoryRentPolicy())<span class="text-secondary small">— {{ $booking->guestCategoryRentPolicy() }}</span>@endif
</p>
@endif
@if($booking->person_type)<p><strong>Guest type:</strong> {{ $booking->person_type }}</p>@endif
<p><strong>Rate / night (per room):</strong> {{ number_format($booking->rate_per_night, 2) }} × {{ $booking->billableRoomCount() }} active room(s)</p>
@if($booking->status === 'checked_in' || $booking->status === 'checked_out')
<p class="small text-secondary mb-0">Room charges use actual nights per room (partial release supported).</p>
@endif
<ul class="list-unstyled small text-secondary mb-0">
<li>Room rent: {{ number_format($booking->room_rent, 2) }}</li>
<li>Electric: {{ number_format($booking->electric_charges, 2) }} | Gas: {{ number_format($booking->gas_charges, 2) }} | Media: {{ number_format($booking->media_charges, 2) }}</li>
</ul>
</div></div></div>
<div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body">
<p><strong>Room charges:</strong> {{ number_format($booking->room_charges, 2) }}
    @if($booking->isOnlineBooking())<span class="badge bg-success ms-1">Prepaid (online)</span>@endif
</p>
<p><strong>Extra charges:</strong> {{ number_format($booking->extra_charges, 2) }}
    @if($booking->isOnlineBooking() && $booking->status === 'checked_in')<span class="text-secondary small">— due at checkout</span>@endif
</p>
<p><strong>Discount:</strong> {{ number_format($booking->discount, 2) }}@if($booking->isOnlineBooking()) <span class="text-secondary small">(applies to extras)</span>@endif</p>
<p><strong>Tax:</strong> {{ number_format($booking->tax_amount, 2) }}</p>
<p class="fs-5"><strong>Total:</strong> {{ number_format($booking->total_amount, 2) }} | <strong>Paid:</strong> {{ number_format($booking->paid_amount, 2) }} | <strong>Balance:</strong> {{ number_format($booking->balance, 2) }}</p>
</div></div></div></div>
@if(in_array($booking->status, ['checked_in', 'checked_out', 'reserved'], true) && $booking->hasAssignedRooms())
@include('guest-rooms.bookings.partials.booking-rooms-manage', ['booking' => $booking])
@endif
@if($booking->status==='checked_in')
@include('guest-rooms.bookings.partials.damage-charges', ['booking' => $booking, 'class' => 'mt-3'])
@endif
@include('guest-rooms.bookings.partials.guest-details-correction', ['booking' => $booking, 'personTypes' => $personTypes])
@include('guest-rooms.bookings.partials.guest-type-rates-form', ['booking' => $booking, 'personTypes' => $personTypes])
@endsection
@section('scripts')
@if($booking->status === 'checked_in')
@include('guest-rooms.bookings.partials.guest-fields-script')
@endif
@endsection
