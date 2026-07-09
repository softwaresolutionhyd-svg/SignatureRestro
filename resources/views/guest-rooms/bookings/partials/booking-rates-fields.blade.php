@php
    $booking = $booking ?? null;
    $isOnline = old('booking_type', $booking?->booking_type ?? 'manual') === \App\Models\RoomBooking::TYPE_ONLINE;
@endphp
<div class="col-12">
    <div class="border rounded p-3 bg-light" id="rates-panel">
        <div class="small fw-semibold text-secondary mb-2" id="rates-panel-title">
            @if($isOnline)
                Rates (auto from Categories & Rates)
            @else
                Rates — per night total or breakdown
            @endif
        </div>
        <div id="rates-blocks-container"></div>
        <div id="rates-combined-total" class="small fw-semibold text-secondary mt-2 d-none"></div>
        <p id="rate-lookup-msg" class="small text-secondary mb-0 mt-2"></p>
        <input type="hidden" name="room_rate_id" id="room_rate_id" value="{{ old('room_rate_id', $booking?->room_rate_id) }}">
        <input type="hidden" name="room_category_id" id="room_category_id" value="{{ old('room_category_id', $booking?->room_category_id) }}">
    </div>
</div>
