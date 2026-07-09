@php

    $booking = $booking ?? null;

    $selected = old('guest_category', $booking?->guest_category ?? '');

    $policies = \App\Models\RoomBooking::guestCategoryRentPolicies();

    $isManual = old('booking_type', $booking?->booking_type ?? 'manual') === \App\Models\RoomBooking::TYPE_MANUAL;

@endphp

<div class="col-md-3 {{ $isManual ? '' : 'd-none' }}" id="guest-category-wrap">

    <label class="form-label">Guest category *</label>

    <select name="guest_category" id="guest_category" class="form-select" @required($isManual) @disabled(!$isManual)>

        <option value="">Select</option>

        @foreach(\App\Models\RoomBooking::guestCategoryLabels() as $key => $label)

            <option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>

        @endforeach

    </select>

    <p id="guest-category-policy" class="small text-secondary mb-0 mt-1">

        @if($selected && isset($policies[$selected]))

            {{ $policies[$selected] }}

        @endif

    </p>

</div>

<script>

(function () {

    const sel = document.getElementById('guest_category');

    const policyEl = document.getElementById('guest-category-policy');

    const policies = @json($policies);

    if (!sel || !policyEl) return;

    function updatePolicy() {

        const v = sel.value;

        policyEl.textContent = v && policies[v] ? policies[v] : '';

    }

    sel.addEventListener('change', updatePolicy);

    updatePolicy();

})();

</script>

