@php
    $selectedIds = old('guest_room_ids', $booking->assignedRoomIds());
    if (! is_array($selectedIds)) {
        $selectedIds = [];
    }
    $selectedIds = array_map('intval', $selectedIds);
    $maxRooms = max(1, (int) ($booking->rooms_count ?? 1));
    $checkInToday = now()->startOfDay();
    $flashedCheckIn = old('check_in_date');
    $checkInDisplay = ($flashedCheckIn !== null && $flashedCheckIn !== '')
        ? fmt_date(parse_display_date($flashedCheckIn) ?? $flashedCheckIn, (string) $flashedCheckIn)
        : fmt_date($checkInToday);
    $checkInMin = now()->subYears(3)->startOfDay();
    $plannedCheckIn = $booking->check_in_date?->startOfDay();
    if ($plannedCheckIn && $plannedCheckIn->lessThan($checkInMin)) {
        $checkInMin = $plannedCheckIn->copy();
    }
    $assignedIds = array_map('intval', $booking->assignedRoomIds());
    $roomsByCategory = $assignableRooms
        ->groupBy(fn ($room) => $room->category?->name ?? 'Other')
        ->sortBy(fn ($_, $categoryName) => \App\Models\RoomCategory::categorySortIndex($categoryName));
@endphp
@push('head')
<style>
.checkin-room-picker__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
    gap: 0.5rem;
}
.checkin-room-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 4.25rem;
    padding: 0.45rem 0.35rem;
    border-radius: 0.5rem;
    border: 2px solid #bbf7d0;
    background: linear-gradient(165deg, #f0fdf4 0%, #dcfce7 100%);
    cursor: pointer;
    text-align: center;
    transition: border-color 0.12s ease, box-shadow 0.12s ease, transform 0.12s ease, opacity 0.12s ease;
    user-select: none;
}
.checkin-room-card:hover {
    border-color: #22c55e;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
    transform: translateY(-1px);
}
.checkin-room-card.is-selected {
    border-color: #15803d;
    background: linear-gradient(165deg, #22c55e 0%, #15803d 100%);
    box-shadow: 0 4px 14px rgba(21, 128, 61, 0.35);
    color: #fff;
}
.checkin-room-card.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.checkin-room-card__input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.checkin-room-card__no {
    font-size: 0.9375rem;
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}
.checkin-room-card__meta {
    font-size: 0.5625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    opacity: 0.75;
    margin-top: 0.15rem;
    line-height: 1.1;
}
.checkin-room-card.is-selected .checkin-room-card__meta {
    color: #dcfce7;
    opacity: 1;
}
.checkin-room-card__tick {
    position: absolute;
    top: 0.2rem;
    right: 0.25rem;
    font-size: 0.75rem;
    opacity: 0;
    color: #fff;
}
.checkin-room-card.is-selected .checkin-room-card__tick {
    opacity: 1;
}
.checkin-room-picker__cat-title {
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #475569;
    margin-bottom: 0.35rem;
}
</style>
@endpush
<div class="card border-0 shadow-sm" id="check-in-form">
    <div class="card-header bg-white fw-semibold py-2">Check in</div>
    <div class="card-body py-3">
        <form method="POST" action="{{ route('guest-rooms.bookings.check-in', $booking) }}" class="m-0" name="check-in-form" id="check-in-form-el">
            @csrf
            <div class="mb-3" data-max-rooms="{{ $maxRooms }}" id="check-in-room-picker">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <div class="fw-semibold small">Available rooms</div>
                        <div class="text-secondary small">
                            {{ fmt_date($booking->check_in_date) }} – {{ fmt_date($booking->check_out_date) }}
                            · <strong>{{ $maxRooms }}</strong> room(s) select karein
                        </div>
                    </div>
                    <span class="badge bg-primary" id="check-in-room-count">0 / {{ $maxRooms }} selected</span>
                </div>
                @if($assignableRooms->isEmpty())
                    <div class="alert alert-warning py-2 mb-0 small">Is stay ke liye koi free room nahi.</div>
                @else
                    @foreach($roomsByCategory as $categoryName => $categoryRooms)
                        <section class="mb-3">
                            <div class="checkin-room-picker__cat-title">{{ $categoryName }}</div>
                            <div class="checkin-room-picker__grid">
                                @foreach($categoryRooms as $r)
                                    @php $wasAssigned = in_array((int) $r->id, $assignedIds, true); @endphp
                                    <label class="checkin-room-card @if(in_array((int) $r->id, $selectedIds, true)) is-selected @endif"
                                           for="checkin_room_{{ $r->id }}" data-room-card>
                                        <input type="checkbox" class="checkin-room-card__input js-checkin-room"
                                               name="guest_room_ids[]" value="{{ $r->id }}"
                                               id="checkin_room_{{ $r->id }}"
                                               @checked(in_array((int) $r->id, $selectedIds, true))>
                                        <i class="bi bi-check-circle-fill checkin-room-card__tick"></i>
                                        <span class="checkin-room-card__no">{{ $r->room_number }}</span>
                                        <span class="checkin-room-card__meta">{{ $wasAssigned ? 'Reserved' : 'Free' }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2 pt-1 border-top">
                <div style="min-width: 9rem;">
                    @include('partials.form-date-dmy', [
                        'name' => 'check_in_date',
                        'label' => 'Check-in date',
                        'value' => $checkInDisplay,
                        'useOld' => false,
                        'required' => true,
                        'class' => 'form-control form-control-sm',
                        'min' => $checkInMin,
                        'max' => now(),
                    ])
                </div>
                <button type="submit" class="btn btn-success btn-sm px-3" id="check-in-submit-btn"
                        @if($assignableRooms->isEmpty()) disabled @endif>
                    <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                </button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var picker = document.getElementById('check-in-room-picker');
    var form = document.getElementById('check-in-form-el');
    var submitBtn = document.getElementById('check-in-submit-btn');
    var countBadge = document.getElementById('check-in-room-count');
    if (!picker || !form) return;

    var max = parseInt(picker.dataset.maxRooms || '1', 10) || 1;
    var boxes = picker.querySelectorAll('.js-checkin-room');

    function selectedCount() {
        var n = 0;
        boxes.forEach(function (b) { if (b.checked) n++; });
        return n;
    }

    function syncCards() {
        var count = selectedCount();
        var atMax = count >= max;

        if (countBadge) {
            countBadge.textContent = count + ' / ' + max + ' selected';
            countBadge.className = 'badge ' + (count === max ? 'bg-success' : (count > 0 ? 'bg-primary' : 'bg-secondary'));
        }

        boxes.forEach(function (box) {
            var card = box.closest('[data-room-card]');
            if (!card) return;
            card.classList.toggle('is-selected', box.checked);
            card.classList.toggle('is-disabled', atMax && !box.checked);
        });

        if (submitBtn) {
            submitBtn.disabled = count === 0 || boxes.length === 0;
        }
    }

    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            if (selectedCount() > max) {
                box.checked = false;
            }
            syncCards();
        });
    });

    picker.querySelectorAll('[data-room-card]').forEach(function (card) {
        card.addEventListener('mousedown', function (e) {
            if (card.classList.contains('is-disabled')) {
                e.preventDefault();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        var count = selectedCount();
        if (count === 0) {
            e.preventDefault();
            alert('Pehle kam az kam 1 room select karein.');
            return;
        }
        if (count !== max) {
            e.preventDefault();
            alert('Is booking ke liye ' + max + ' room(s) select karein.');
        }
    });

    syncCards();
})();
</script>
@endpush
