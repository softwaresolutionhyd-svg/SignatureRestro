@php
    $booking = $booking ?? null;
    $selfStaying = filter_var(old('primary_guest_staying', $booking?->primary_guest_staying ?? true), FILTER_VALIDATE_BOOLEAN);
    $adultCount = max(1, (int) old('adults', $booking?->adults ?? 1));
    $childCount = max(0, (int) old('children', $booking?->children ?? 0));
    $adultRows = old('members.adults', $booking?->membersFormAdults() ?? [['name' => '', 'cnic' => '', 'relation' => 'Self']]);
    $childRows = old('members.children', $booking?->membersFormChildren() ?? []);
    if (! is_array($adultRows)) {
        $adultRows = [];
    }
    if (! is_array($childRows)) {
        $childRows = [];
    }
@endphp
<div class="col-12">
    <input type="hidden" name="primary_guest_staying" value="0">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" name="primary_guest_staying" id="primary_guest_staying" value="1" @checked($selfStaying)>
        <label class="form-check-label fw-semibold" for="primary_guest_staying">Self aa raha hai (primary guest staying)</label>
    </div>
    <div class="form-text mb-2">Band karein agar booking kisi aur ke naam par ho lekin wo khud nahi aa raha — sirf unke mehman aa rahe hain.</div>
</div>
<div class="col-12" id="guest-members-section">
    <div class="card border bg-light-subtle">
        <div class="card-body py-3">
            <div class="fw-semibold small text-secondary mb-2" id="guest-members-heading">Other members</div>
            <div id="guest-members-adults"></div>
            <div id="guest-members-children" class="mt-2"></div>
        </div>
    </div>
</div>
<script type="application/json" id="guest-members-initial-data">
{!! json_encode([
    'adults' => array_values($adultRows),
    'children' => array_values($childRows),
    'primary_guest_staying' => $selfStaying,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
