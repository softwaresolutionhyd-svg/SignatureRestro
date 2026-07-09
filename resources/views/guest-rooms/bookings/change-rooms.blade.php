@extends('layouts.admin')
@section('title', 'Change Rooms — ' . $booking->booking_no)
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <a href="{{ route('guest-rooms.bookings.show', $booking) }}" class="text-secondary small">&larr; Back to booking</a>
    <h4 class="fw-bold mb-0 mt-1">Change Rooms</h4>
    <div class="text-secondary small">{{ $booking->booking_no }} · {{ $booking->guestDisplayName() }}</div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Select rooms</div>
            <div class="card-body">
                <p class="small text-secondary mb-3">
                    Active: <strong>{{ $booking->billableRoomCount() }}</strong> room(s).
                    To vacate one room mid-stay but keep others, use <strong>Release room</strong> on the booking page (bill adjusts by nights used).
                    Deselecting a room here also releases it with pro-rated charges.
                </p>
                @include('guest-rooms.bookings.partials.booking-rooms-manage', ['booking' => $booking, 'class' => 'mb-3 border'])
                <form method="POST" action="{{ route('guest-rooms.bookings.rooms.update', $booking) }}">
                    @csrf
                    @method('PUT')
                    @include('guest-rooms.bookings.partials.room-select', ['booking' => $booking])
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">Save room changes</button>
                        <a href="{{ route('guest-rooms.bookings.show', $booking) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Current charges</div>
            <div class="card-body small">
                <p class="mb-1">Rate / night (per room): <strong>{{ number_format($booking->rate_per_night, 2) }}</strong></p>
                <p class="mb-1">Nights: <strong>{{ $booking->nights }}</strong></p>
                <p class="mb-1">Rooms: <strong>{{ $booking->billableRoomCount() }}</strong></p>
                <p class="mb-1">Room charges: <strong>{{ number_format($booking->room_charges, 2) }}</strong></p>
                <p class="mb-0 text-secondary">Total will update automatically when you save.</p>
            </div>
        </div>
    </div>
</div>
@endsection
