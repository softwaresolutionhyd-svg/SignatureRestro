@extends('layouts.admin')
@section('title', 'Edit Booking')
@section('content')
@include('guest-rooms.partials.subnav')
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('guest-rooms.bookings.update', $booking) }}" id="booking-form">@csrf @method('PUT')
<div class="row g-3">
<div class="col-md-3">
    <label class="form-label">Booking type *</label>
    <select name="booking_type" id="booking_type" class="form-select" required>
        @foreach(\App\Models\RoomBooking::bookingTypeLabels() as $key => $label)
            <option value="{{ $key }}" @selected(old('booking_type', $booking->booking_type ?? 'manual') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
@include('guest-rooms.bookings.partials.voucher-number-field', ['booking' => $booking])
<div class="col-md-3"><label class="form-label">Guest type *</label><select name="person_type" id="person_type" class="form-select" required><option value="">Select</option>@foreach($personTypes as $pt)<option value="{{ $pt }}" @selected(old('person_type', $booking->person_type)==$pt)>{{ $pt }}</option>@endforeach</select></div>
@include('guest-rooms.bookings.partials.guest-category-field', ['booking' => $booking])
@include('guest-rooms.bookings.partials.room-type-field', ['booking' => $booking])
@include('guest-rooms.bookings.partials.guest-fields', ['booking' => $booking])
@include('guest-rooms.bookings.partials.booking-category-select', ['booking' => $booking])
@include('guest-rooms.bookings.partials.booking-rates-fields', ['booking' => $booking])
<div class="col-md-3">@include('partials.form-date-dmy', ['name' => 'check_in_date', 'label' => 'Check-in', 'value' => $booking->check_in_date, 'required' => true])</div>
<div class="col-md-3">@include('partials.form-date-dmy', ['name' => 'check_out_date', 'label' => 'Check-out', 'value' => $booking->check_out_date, 'required' => true])</div>
<div class="col-md-2"><label class="form-label">Adults</label><input type="number" name="adults" id="booking_adults" class="form-control" value="{{ old('adults', $booking->adults) }}" min="1" max="20"></div>
<div class="col-md-2"><label class="form-label">Children</label><input type="number" name="children" id="booking_children" class="form-control" value="{{ old('children', $booking->children) }}" min="0" max="20"></div>
@include('guest-rooms.bookings.partials.guest-members-fields', ['booking' => $booking])
@include('guest-rooms.bookings.partials.guest-vehicles-fields', ['booking' => $booking])
<div class="col-md-2">
    <label class="form-label">Advance paid</label>
    <input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control" value="{{ old('paid_amount', $booking->paid_amount) }}">
    <p class="small text-secondary mb-0 mt-1 d-none" id="paid-amount-online-hint">Auto: rooms × total / night × nights</p>
</div>
</div>
<button class="btn btn-primary mt-3">Update</button>
<a href="{{ route('guest-rooms.bookings.show', $booking) }}" class="btn btn-outline-secondary mt-3">Cancel</a>
</form></div></div>
@endsection
@section('scripts')
@include('guest-rooms.bookings.partials.booking-rates-script')
@include('guest-rooms.bookings.partials.guest-fields-script')
@include('guest-rooms.bookings.partials.guest-members-script')
@include('guest-rooms.bookings.partials.guest-vehicles-script')
@endsection
