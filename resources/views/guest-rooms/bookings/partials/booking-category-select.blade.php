@php
    $booking = $booking ?? null;
@endphp
<div class="col-md-3">
    <label class="form-label">Phone</label>
    <input type="text" name="guest_phone" id="field_guest_phone" class="form-control" value="{{ old('guest_phone', $booking?->guest_phone) }}" placeholder="Contact number">
</div>
<div class="col-md-2">
    <label class="form-label">Rooms required *</label>
    <input type="number" name="rooms_count" id="rooms_count" class="form-control" min="1" max="20" required
           value="{{ old('rooms_count', $booking?->rooms_count ?? 1) }}">
</div>
