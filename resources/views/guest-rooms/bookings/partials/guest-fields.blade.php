@php
    $booking = $booking ?? null;
    $selectedPersonType = old('person_type', $booking?->person_type ?? '');
    $isCivilian = strcasecmp($selectedPersonType, 'Civilian') === 0;
@endphp
<div class="col-12">
    <div class="row g-3" id="guest-identity-row">
        <div class="col-md-3 guest-field-pa-wrap {{ $isCivilian ? 'd-none' : '' }}">
            <label class="form-label">PA No</label>
            <input type="text" name="pa_no" id="field_pa_no" class="form-control" value="{{ old('pa_no', $booking?->pa_no) }}" placeholder="e.g. 12345">
        </div>
        <div class="col-md-3 guest-field-rank-wrap {{ $isCivilian ? 'd-none' : '' }}">
            <label class="form-label">Rank</label>
            <input type="text" name="guest_rank" id="field_guest_rank" class="form-control" value="{{ old('guest_rank', $booking?->guest_rank) }}" placeholder="e.g. Captain">
        </div>
        <div class="col-md-6 guest-field-co-wrap {{ $isCivilian ? '' : 'd-none' }}">
            <label class="form-label">C/O</label>
            <input type="text" name="care_of" id="field_care_of" class="form-control" value="{{ old('care_of', $booking?->care_of) }}" placeholder="Care of">
        </div>
        <div class="col-md-3 guest-field-name-wrap">
            <label class="form-label" id="field_guest_name_label">Name *</label>
            <input type="text" name="guest_name" id="field_guest_name" class="form-control" value="{{ old('guest_name', $booking?->guest_name) }}" required placeholder="Full name">
        </div>
        <div class="col-md-3 guest-field-cnic-wrap" id="guest-field-cnic-wrap">
            <label class="form-label">CNIC</label>
            <input type="text" name="guest_cnic" id="field_guest_cnic" class="form-control" value="{{ old('guest_cnic', $booking?->guest_cnic) }}" placeholder="e.g. 35202-1234567-1">
        </div>
        @if($includePhone ?? false)
        <div class="col-md-3 guest-field-phone-wrap">
            <label class="form-label">Phone</label>
            <input type="text" name="guest_phone" id="field_guest_phone" class="form-control" value="{{ old('guest_phone', $booking?->guest_phone) }}" placeholder="Contact number">
        </div>
        @endif
    </div>
</div>

