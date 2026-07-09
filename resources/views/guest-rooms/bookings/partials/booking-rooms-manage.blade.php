@php
    $rooms = $booking->assignedRooms ?? $booking->assignedRooms()->get();
    $active = $rooms->filter(fn ($r) => empty($r->pivot->released_at));
    $released = $rooms->filter(fn ($r) => ! empty($r->pivot->released_at));
    $checkIn = $booking->stayCheckInAt();
@endphp
<div class="card border-0 shadow-sm {{ $class ?? 'mt-3' }}">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Rooms on booking</span>
        @if($booking->status === 'checked_in')
            <a href="{{ route('guest-rooms.bookings.change-rooms', $booking) }}" class="btn btn-sm btn-outline-primary">Change / Add rooms</a>
        @endif
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Room</th>
                    <th>Status</th>
                    <th>Nights billed</th>
                    <th class="text-end pe-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($active as $room)
                    @php
                        $nights = max(1, (int) \Carbon\Carbon::parse($checkIn)->startOfDay()->diffInDays(\Carbon\Carbon::parse($booking->check_out_date)->startOfDay()));
                    @endphp
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $room->room_number }}</td>
                        <td>
                            @if($booking->status === 'reserved')
                                <span class="badge bg-info">Reserved for this booking</span>
                            @else
                                <span class="badge bg-danger">Occupied</span>
                            @endif
                        </td>
                        <td>
                            @if($booking->status === 'reserved')
                                <span class="text-secondary">From check-in</span>
                            @else
                                {{ $nights }} <span class="text-secondary">(until checkout)</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if($booking->status === 'checked_in' && $active->count() > 1)
                                <form method="POST" action="{{ route('guest-rooms.bookings.rooms.release', [$booking, $room]) }}" class="d-inline" onsubmit="return confirm('Release room {{ $room->room_number }}? Guest keeps other rooms; bill will be adjusted.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Release room</button>
                                </form>
                            @elseif($booking->status === 'checked_in')
                                <span class="text-secondary small">Last room</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach($released as $room)
                    @php
                        $releasedAt = \Carbon\Carbon::parse($room->pivot->released_at);
                        $billedNights = max(1, (int) \Carbon\Carbon::parse($checkIn)->startOfDay()->diffInDays($releasedAt->copy()->startOfDay()));
                    @endphp
                    <tr class="table-secondary">
                        <td class="ps-3">{{ $room->room_number }}</td>
                        <td><span class="badge bg-warning text-dark">Released {{ fmt_date($releasedAt) }}</span></td>
                        <td>{{ $billedNights }} <span class="text-secondary">(partial stay)</span></td>
                        <td class="text-end pe-3 text-secondary small">Cleaning</td>
                    </tr>
                @endforeach
                @if($active->isEmpty() && $released->isEmpty())
                    <tr><td colspan="4" class="text-center py-3 text-secondary">No rooms assigned.</td></tr>
                @endif
            </tbody>
        </table>
        @if($booking->status === 'checked_in' && $active->count() > 1)
            <div class="px-3 py-2 small text-secondary border-top">
                Tip: Use <strong>Release room</strong> when guest vacates one room mid-stay but continues in others. Bill charges per room only for nights used.
            </div>
        @endif
    </div>
</div>
