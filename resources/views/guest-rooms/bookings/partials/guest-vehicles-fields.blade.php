@php
    $booking = $booking ?? null;
    $vehicleCount = max(0, (int) old('vehicles_count', $booking?->vehicles_count ?? 0));
    $vehicleRows = old('vehicles', $booking?->vehiclesFormData() ?? []);
    if (! is_array($vehicleRows)) {
        $vehicleRows = [];
    }
@endphp
<div class="col-12" id="guest-vehicles-section">
    <div class="card border bg-light-subtle">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-end gap-3 mb-2">
                <div class="fw-semibold small text-secondary">Vehicle information</div>
                <div style="width: 5rem;">
                    <label class="form-label small mb-0">Vehicles</label>
                    <input type="number" name="vehicles_count" id="booking_vehicles" class="form-control form-control-sm"
                           value="{{ $vehicleCount }}" min="0" max="10">
                </div>
            </div>
            <div id="guest-vehicles-rows"></div>
        </div>
    </div>
</div>
<script type="application/json" id="guest-vehicles-initial-data">
{!! json_encode(['vehicles' => array_values($vehicleRows)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
