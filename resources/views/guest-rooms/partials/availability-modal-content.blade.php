@php
    $availByCategory = ($availability ?? null)
        ? $availability['available']
            ->groupBy(fn ($room) => $room->category?->name ?? 'Other')
            ->sortBy(fn ($_, $categoryName) => \App\Models\RoomCategory::categorySortIndex($categoryName))
        : collect();
@endphp

@if(!empty($availabilityError))
    <div class="alert alert-danger mb-0">{{ $availabilityError }}</div>
@elseif($availability)
    <p class="avail-modal__dates mb-3">
        <strong>{{ fmt_date($availability['check_in']) }}</strong>
        <span class="text-secondary mx-1">to</span>
        <strong>{{ fmt_date($availability['check_out']) }}</strong>
        <span class="text-secondary ms-2">· {{ $availability['nights'] }} night(s)</span>
        @if($availability['category_id'])
            <span class="badge bg-light text-dark border ms-1">{{ $roomCategories->firstWhere('id', $availability['category_id'])?->name }}</span>
        @endif
    </p>

    @if($availability['available']->isEmpty())
        <div class="text-center py-4">
            <i class="bi bi-x-circle text-danger display-6 d-block mb-2"></i>
            <p class="fw-semibold mb-1">No rooms available for these dates</p>
            <p class="text-secondary small mb-0">{{ $availability['unavailable']->count() }} room(s) not available for these dates.</p>
        </div>
    @else
        <p class="avail-modal__lead mb-3">
            <span class="badge bg-success">{{ $availability['available']->count() }} available</span>
            @if($availability['unavailable']->isNotEmpty())
                <span class="badge bg-danger ms-1">{{ $availability['unavailable']->count() }} unavailable</span>
            @endif
        </p>
        @foreach($availByCategory as $categoryName => $categoryRooms)
            <section class="avail-modal__cat mb-3">
                <h6 class="avail-modal__cat-title">{{ $categoryName }}</h6>
                <div class="avail-modal__rooms">
                    @foreach($categoryRooms as $room)
                        <a href="{{ route('guest-rooms.bookings.create', array_filter([
                            'check_in_date' => fmt_date($availability['check_in']),
                            'check_out_date' => fmt_date($availability['check_out']),
                            'room_category_id' => $room->room_category_id,
                        ])) }}" class="avail-modal__room" title="Book {{ $room->room_number }}">
                            {{ $room->room_number }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
@else
    <div class="alert alert-warning mb-0">Enter check-in and check-out dates, then search again.</div>
@endif
