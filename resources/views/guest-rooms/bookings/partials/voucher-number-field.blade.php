@php
    $isOnline = old('booking_type', $booking?->booking_type ?? 'manual') === \App\Models\RoomBooking::TYPE_ONLINE;
@endphp
<div class="col-md-3 {{ $isOnline ? '' : 'd-none' }}" id="voucher-no-wrap">
    <label class="form-label">Voucher number *</label>
    <input type="text" name="voucher_no" id="voucher_no" class="form-control" maxlength="80"
           value="{{ old('voucher_no', $booking?->voucher_no ?? '') }}"
           placeholder="Online payment voucher #">
</div>
