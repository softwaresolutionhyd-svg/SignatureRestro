@php
    $booking = $booking ?? null;
    $selectedIds = old('guest_room_ids', $booking?->assignedRoomIds() ?? []);
    if (! is_array($selectedIds)) {
        $selectedIds = [];
    }
    $selectedIds = array_map('intval', $selectedIds);
    if ($selectedIds === [] && old('guest_room_id')) {
        $selectedIds = [(int) old('guest_room_id')];
    }
    if ($selectedIds === [] && $booking?->guest_room_id) {
        $selectedIds = [(int) $booking->guest_room_id];
    }
@endphp
<div class="col-12">
    <label class="form-label">
        Rooms
        <span class="text-secondary fw-normal">(multiple — Ctrl/⌘ click to select more than one)</span>
    </label>
    <select name="guest_room_ids[]" id="guest_room_ids" class="form-select" multiple size="8">
        @foreach($rooms as $r)
            <option value="{{ $r->id }}"
                    data-category="{{ $r->room_category_id }}"
                    data-category-name="{{ $r->category?->name ?? '' }}"
                    @selected(in_array((int) $r->id, $selectedIds, true))>
                {{ $r->room_number }} ({{ \App\Models\GuestRoom::statusLabels()[$r->status] ?? $r->status }})
            </option>
        @endforeach
    </select>
    <div class="form-text">Active rooms only. Mid-stay release: use <strong>Release room</strong> on the booking page.</div>
</div>
