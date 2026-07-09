@if(in_array($booking->status, ['reserved', 'checked_in'], true))
@php
    $rateCategoryId = old('room_category_id', $booking->room_category_id);
    if (! $rateCategoryId && $booking->assignedRooms?->isNotEmpty()) {
        $rateCategoryId = $booking->assignedRooms->first()->room_category_id;
    }
    $isManualBooking = ($booking->booking_type ?? \App\Models\RoomBooking::TYPE_MANUAL) === \App\Models\RoomBooking::TYPE_MANUAL;
    $split = config('app.manual_rate_split', ['electric' => 300, 'gas' => 400, 'media' => 100]);
    $gtPerNight = old('gt_per_night', max(0, (float) $booking->rate_per_night));
@endphp
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Guest type &amp; rates</div>
    <div class="card-body">
        <form method="POST" action="{{ route('guest-rooms.bookings.guest-type.update', $booking) }}" id="guest-type-form">
            @csrf
            @method('PATCH')
            <input type="hidden" name="room_rate_id" id="gt-room_rate_id" value="{{ old('room_rate_id', $booking->room_rate_id) }}">
            <input type="hidden" name="room_category_id" id="gt-room_category_id" value="{{ $rateCategoryId }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Guest type *</label>
                    <select name="person_type" id="person_type" class="form-select" required>
                        <option value="">Select</option>
                        @foreach($personTypes as $pt)
                            <option value="{{ $pt }}" @selected(old('person_type', $booking->person_type) === $pt)>{{ $pt }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($booking->booking_type ?? \App\Models\RoomBooking::TYPE_MANUAL) === \App\Models\RoomBooking::TYPE_MANUAL)
                    @include('guest-rooms.bookings.partials.guest-category-field', ['booking' => $booking])
                @endif
                @include('guest-rooms.bookings.partials.guest-fields', ['booking' => $booking])
            </div>
            <div class="border rounded p-3 bg-light mt-2">
                <div class="small fw-semibold text-secondary mb-2">
                    @if($isManualBooking)
                        Rates — per night total or breakdown
                    @else
                        Rates (auto from category &amp; guest type)
                    @endif
                </div>
                @if($isManualBooking)
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small mb-0 fw-semibold" for="gt-per-night-quick">Per night rate</label>
                        <input type="number" step="0.01" min="0" id="gt-per-night-quick" class="form-control form-control-sm"
                               value="{{ $gtPerNight > 0 ? number_format($gtPerNight, 2, '.', '') : '' }}" placeholder="e.g. 5400">
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <p class="small text-secondary mb-2">Auto split: Electric {{ $split['electric'] ?? 300 }}, Gas {{ $split['gas'] ?? 400 }}, Media {{ $split['media'] ?? 100 }} — room rent = balance</p>
                    </div>
                </div>
                @endif
                <div class="row g-2" id="gt-breakdown-row">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Room rent</label>
                        <input type="number" step="0.01" min="0" name="room_rent" id="gt-room_rent" class="form-control form-control-sm gt-rate-field" value="{{ old('room_rent', $booking->room_rent) }}" @readonly(!$isManualBooking)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Electric</label>
                        <input type="number" step="0.01" min="0" name="electric_charges" id="gt-electric_charges" class="form-control form-control-sm gt-rate-field" value="{{ old('electric_charges', $booking->electric_charges) }}" @readonly(!$isManualBooking)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Gas</label>
                        <input type="number" step="0.01" min="0" name="gas_charges" id="gt-gas_charges" class="form-control form-control-sm gt-rate-field" value="{{ old('gas_charges', $booking->gas_charges) }}" @readonly(!$isManualBooking)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Media</label>
                        <input type="number" step="0.01" min="0" name="media_charges" id="gt-media_charges" class="form-control form-control-sm gt-rate-field" value="{{ old('media_charges', $booking->media_charges) }}" @readonly(!$isManualBooking)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0 fw-semibold">Total / night</label>
                        <input type="number" step="0.01" name="rate_per_night" id="gt-rate_per_night" class="form-control form-control-sm fw-bold" value="{{ old('rate_per_night', $booking->rate_per_night) }}" readonly>
                    </div>
                </div>
                <p id="gt-rate-lookup-msg" class="small text-secondary mb-0 mt-2"></p>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3">Update guest type &amp; rates</button>
        </form>
    </div>
</div>
<script>
(function () {
    const lookupUrl = @json(route('guest-rooms.rates.lookup'));
    const catEl = document.getElementById('gt-room_category_id');
    const personEl = document.getElementById('person_type');
    const guestCategoryEl = document.getElementById('guest_category');
    const complimentaryCategories = @json([\App\Models\RoomBooking::GUEST_CATEGORY_A, \App\Models\RoomBooking::GUEST_CATEGORY_B]);
    const isManualBooking = @json($isManualBooking);
    const manualSplit = @json($split);
    const UTIL = {
        electric: Number(manualSplit.electric) || 300,
        gas: Number(manualSplit.gas) || 400,
        media: Number(manualSplit.media) || 100,
    };
    UTIL.total = UTIL.electric + UTIL.gas + UTIL.media;
    const perNightQuickEl = document.getElementById('gt-per-night-quick');
    const breakdownRow = document.getElementById('gt-breakdown-row');
    const msgEl = document.getElementById('gt-rate-lookup-msg');
    const fields = {
        room_rate_id: document.getElementById('gt-room_rate_id'),
        room_rent: document.getElementById('gt-room_rent'),
        electric_charges: document.getElementById('gt-electric_charges'),
        gas_charges: document.getElementById('gt-gas_charges'),
        media_charges: document.getElementById('gt-media_charges'),
        rate_per_night: document.getElementById('gt-rate_per_night'),
    };
    const base = { room_rent: 0, electric_charges: 0, gas_charges: 0, media_charges: 0 };

    function isComplimentaryGuestCategory() {
        const cat = guestCategoryEl?.value || '';
        return complimentaryCategories.includes(cat);
    }

    function applyGuestCategoryToAmount(amount) {
        return isComplimentaryGuestCategory() ? 0 : (parseFloat(amount) || 0);
    }

    function syncChargesFromBase(skipQuickSync) {
        if (fields.room_rent) fields.room_rent.value = applyGuestCategoryToAmount(base.room_rent).toFixed(2);
        if (fields.electric_charges) fields.electric_charges.value = applyGuestCategoryToAmount(base.electric_charges).toFixed(2);
        if (fields.gas_charges) fields.gas_charges.value = applyGuestCategoryToAmount(base.gas_charges).toFixed(2);
        if (fields.media_charges) fields.media_charges.value = applyGuestCategoryToAmount(base.media_charges).toFixed(2);
        recalcTotal(skipQuickSync);
    }

    function splitPerNightGross(perNight) {
        const t = parseFloat(perNight) || 0;
        return {
            room_rent: Math.max(0, Math.round((t - UTIL.total) * 100) / 100),
            electric_charges: UTIL.electric,
            gas_charges: UTIL.gas,
            media_charges: UTIL.media,
        };
    }

    function applyQuickSplit(perNight) {
        const split = splitPerNightGross(perNight);
        base.room_rent = split.room_rent;
        base.electric_charges = split.electric_charges;
        base.gas_charges = split.gas_charges;
        base.media_charges = split.media_charges;
        syncChargesFromBase(true);
        recalcTotal(true);
    }

    function recalcTotal(skipQuickSync) {
        const rent = parseFloat(fields.room_rent?.value) || 0;
        const electric = parseFloat(fields.electric_charges?.value) || 0;
        const gas = parseFloat(fields.gas_charges?.value) || 0;
        const media = parseFloat(fields.media_charges?.value) || 0;
        const total = rent + electric + gas + media;
        if (fields.rate_per_night) {
            fields.rate_per_night.value = total.toFixed(2);
        }
        if (!skipQuickSync && perNightQuickEl && isManualBooking
            && document.activeElement?.classList?.contains('gt-rate-field')) {
            perNightQuickEl.value = total > 0 ? total.toFixed(2) : '';
        }
    }

    function commitPerNightQuick() {
        if (!perNightQuickEl || !isManualBooking) return;
        if (isComplimentaryGuestCategory()) {
            perNightQuickEl.value = '0.00';
            applyQuickSplit(0);
            return;
        }
        const raw = String(perNightQuickEl.value || '').trim();
        if (raw === '') {
            base.room_rent = 0;
            base.electric_charges = 0;
            base.gas_charges = 0;
            base.media_charges = 0;
            syncChargesFromBase(true);
            recalcTotal(true);
            return;
        }
        applyQuickSplit(raw);
    }

    function setBreakdownReadonly(readonly) {
        document.querySelectorAll('.gt-rate-field').forEach(function (el) {
            el.readOnly = readonly;
        });
    }

    function syncGtRateModeUi() {
        if (!isManualBooking) return;
        setBreakdownReadonly(isComplimentaryGuestCategory());
        if (isComplimentaryGuestCategory()) {
            syncChargesFromBase();
        }
    }

    function setCharges(data) {
        fields.room_rate_id.value = data?.id || '';
        base.room_rent = data?.room_rent ?? 0;
        base.electric_charges = data?.electric_charges ?? 0;
        base.gas_charges = data?.gas_charges ?? 0;
        base.media_charges = data?.media_charges ?? 0;
        syncChargesFromBase();
        const total = (parseFloat(base.room_rent) || 0) + (parseFloat(base.electric_charges) || 0)
            + (parseFloat(base.gas_charges) || 0) + (parseFloat(base.media_charges) || 0);
        if (perNightQuickEl && total > 0) {
            perNightQuickEl.value = total.toFixed(2);
        }
    }

    async function fetchRate() {
        const categoryId = catEl?.value;
        const personType = personEl?.value;
        if (!categoryId || !personType) {
            if (msgEl) msgEl.textContent = categoryId ? 'Select guest type to load rates.' : 'Room category not set on this booking.';
            return;
        }
        if (msgEl) {
            msgEl.textContent = 'Loading rates…';
            msgEl.className = 'small text-secondary mb-0 mt-2';
        }
        try {
            const url = lookupUrl + '?room_category_id=' + encodeURIComponent(categoryId) + '&person_type=' + encodeURIComponent(personType);
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (json.found) {
                setCharges(json);
                if (msgEl) {
                    msgEl.textContent = 'Rate loaded for ' + personType + '. Save to apply to bill.';
                    msgEl.className = 'small text-success mb-0 mt-2';
                }
            } else {
                setCharges(null);
                if (msgEl) {
                    msgEl.textContent = json.message || 'No rate found for this guest type.';
                    msgEl.className = 'small text-warning mb-0 mt-2';
                }
            }
        } catch (e) {
            if (msgEl) {
                msgEl.textContent = 'Could not load rate.';
                msgEl.className = 'small text-danger mb-0 mt-2';
            }
        }
    }

    personEl?.addEventListener('change', fetchRate);
    guestCategoryEl?.addEventListener('change', function () {
        syncChargesFromBase();
        syncGtRateModeUi();
        if (isComplimentaryGuestCategory() && perNightQuickEl) {
            perNightQuickEl.value = '0.00';
        }
    });
    perNightQuickEl?.addEventListener('change', commitPerNightQuick);
    perNightQuickEl?.addEventListener('blur', commitPerNightQuick);
    document.querySelectorAll('.gt-rate-field').forEach(function (el) {
        el.addEventListener('input', function () {
            if (!isManualBooking) return;
            const key = el.id.replace('gt-', '');
            if (base[key] !== undefined) {
                base[key] = parseFloat(el.value) || 0;
            }
            recalcTotal();
        });
    });
    if (isManualBooking) {
        if (isComplimentaryGuestCategory()) {
            base.room_rent = 0;
            base.electric_charges = 0;
            base.gas_charges = 0;
            base.media_charges = 0;
            syncChargesFromBase();
        }
        syncGtRateModeUi();
        if (!isComplimentaryGuestCategory() && perNightQuickEl?.value) {
            applyQuickSplit(perNightQuickEl.value);
        } else if (!isComplimentaryGuestCategory()) {
            recalcTotal();
        }
    } else if (catEl?.value && personEl?.value) {
        fetchRate();
    } else {
        recalcTotal();
    }
})();
</script>
@include('guest-rooms.bookings.partials.guest-fields-script')
@endif
