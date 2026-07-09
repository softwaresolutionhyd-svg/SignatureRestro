@php
    $chart = $reservationChart ?? ['month_label' => now()->format('F Y'), 'dates' => [], 'segments' => []];
    $dates = $chart['dates'] ?? [];
    $segments = $chart['segments'] ?? [];
@endphp
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-calendar3 me-1"></i>Reservation chart — {{ $chart['month_label'] ?? now()->format('F Y') }}</span>
        <div class="d-flex flex-wrap gap-3 small fw-normal text-secondary">
            <span><span class="res-legend res-legend--reserved"></span> Reserved block</span>
            <span><span class="res-legend res-legend--checked-in"></span> Checked in</span>
            <span><span class="res-legend res-legend--start"></span> ▶ Booking starts</span>
            <span><span class="res-legend res-legend--today"></span> Today</span>
        </div>
    </div>
    <div class="card-body p-0">
        @if(($rooms ?? collect())->isEmpty())
            <p class="text-secondary text-center py-4 mb-0">Add rooms to see the reservation chart.</p>
        @elseif($dates === [])
            <p class="text-secondary text-center py-4 mb-0">No dates for this month.</p>
        @else
        <div class="res-chart-scroll">
            <table class="res-chart mb-0">
                <thead>
                    <tr>
                        <th class="res-chart-room-head res-sticky-left">Room</th>
                        @foreach($dates as $date)
                            <th class="res-chart-date-head @if($date['is_today']) res-col-today @endif @if($date['is_weekend']) res-col-weekend @endif"
                                title="{{ $date['iso'] }}">
                                <span class="res-date-dow">{{ $date['dow'] }}</span>
                                <span class="res-date-day">{{ $date['day'] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rooms as $room)
                        <tr>
                            <th class="res-chart-room res-sticky-left" scope="row">
                                <span class="fw-semibold">{{ $room->room_number }}</span>
                                @if($room->category?->name)
                                    <span class="d-block text-secondary res-room-cat">{{ $room->category->name }}</span>
                                @endif
                            </th>
                            @foreach($segments[$room->id] ?? [] as $segment)
                                @if($segment['type'] === 'empty')
                                    <td colspan="{{ $segment['colspan'] }}"
                                        class="res-chart-cell res-chart-cell--empty @if($segment['has_today']) res-col-today @endif @if($segment['has_weekend']) res-col-weekend @endif"></td>
                                @else
                                    @php
                                        $cell = $segment['cell'];
                                        $status = $cell['status'] ?? null;
                                    @endphp
                                    <td colspan="{{ $segment['colspan'] }}"
                                        class="res-chart-cell res-chart-block @if($segment['has_today']) res-col-today @endif @if($segment['has_weekend']) res-col-weekend @endif @if($status === \App\Models\RoomBooking::STATUS_RESERVED) res-cell--reserved @elseif($status === \App\Models\RoomBooking::STATUS_CHECKED_IN) res-cell--checked-in @endif @if($segment['is_new']) res-block-new @endif">
                                        <a href="{{ $cell['url'] }}"
                                           class="res-merged-block"
                                           title="{{ $segment['tooltip'] ?? $cell['tooltip'] ?? '' }}"
                                           aria-label="{{ $cell['guest'] }} — {{ $cell['status_label'] }}">
                                            @if($segment['show_marker'])
                                                <span class="res-block-marker" aria-hidden="true">▶</span>
                                            @endif
                                            <span class="visually-hidden">{{ $cell['guest'] }}</span>
                                        </a>
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-secondary px-3 py-2 mb-0 border-top">
            Merged box = full stay for one guest. <strong>▶</strong> = booking starts. Hover for name &amp; dates, click to open.
        </p>
        @endif
    </div>
</div>
