@php
    $active = fn (...$routes) => collect($routes)->contains(fn ($r) => request()->routeIs($r)) ? 'active' : '';
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('guest-rooms.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.index') }}">Reception</a>
    <a href="{{ route('guest-rooms.rates.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.rates.*', 'guest-rooms.categories.*') }}">Categories & Rates</a>
    <a href="{{ route('guest-rooms.rooms.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.rooms.*') }}">Rooms</a>
    <a href="{{ route('guest-rooms.housekeeping.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.cleaning.*', 'guest-rooms.housekeeping.*', 'guest-rooms.room-maintenance.*') }}">Housekeeping</a>
    <a href="{{ route('guest-rooms.bookings.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.bookings.*') }}">Bookings</a>
    <a href="{{ route('guest-rooms.checkout-counter.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.checkout-counter.*') }}">Checkout Counter</a>
    <a href="{{ route('guest-rooms.billing.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.billing.*') }}">Billing</a>
    <a href="{{ route('guest-rooms.reports.index') }}" class="btn btn-outline-primary btn-sm {{ $active('guest-rooms.reports.*') }}">Reports</a>
    <a href="{{ route('guest-rooms.bookings.create') }}" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg me-1"></i>New Booking</a>
</div>
