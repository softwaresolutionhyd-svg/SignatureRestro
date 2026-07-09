@extends('layouts.admin')
@section('title', 'Guest Rooms — ' . config('app.name'))

@push('head')
<link href="https://fonts.bunny.net/css?family=Inter:400,500,600,700,800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="guest-rooms-dash-page fd-dashboard">
@include('guest-rooms.partials.subnav')

@php
    $availFreeIds = $availability
        ? $availability['available']->pluck('id')->flip()->all()
        : [];
    $availBlocked = $availability
        ? $availability['unavailable']->keyBy(fn ($row) => $row['room']->id)
        : collect();
    $showAvailModal = $availability !== null || filled($availabilityError);
@endphp

<div class="hotel-dash">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-1 align-items-stretch hotel-main-layout">
        <div class="col-lg-9 d-flex flex-column gap-1 hotel-main-col">
    {{-- Front desk + availability --}}
    <div class="card border-0 hotel-head-block">
        <div class="hotel-head-block__banner">
            <div class="hotel-head-block__brand">
                <span class="hotel-head-block__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                <div class="hotel-head-block__titles">
                    <span class="hotel-hero__title">Front Desk</span>
                    <span class="hotel-hero__sep">·</span>
                    <span class="hotel-hero__date">{{ now()->format('D, d M Y') }}</span>
                </div>
            </div>
            <div class="hotel-head-block__meta">
                <span class="hotel-head-block__pill"><i class="bi bi-clock me-1"></i><span id="fd-live-clock">{{ now()->format('h:i A') }}</span></span>
                <span class="hotel-head-block__pill hotel-head-block__pill--muted">{{ $kpis['occupied'] }}/{{ $kpis['total_rooms'] }}</span>
            </div>
        </div>
        <div class="hotel-head-block__body">
            <div class="row g-1 hotel-kpi-row row-cols-2 row-cols-md-4">
                        @foreach([
                            ['Total Rooms', $kpis['total_rooms'], 'primary', route('guest-rooms.rooms.index')],
                            ['Available', $kpis['available'], 'success', null],
                            ['Occupied', $kpis['occupied'], 'danger', null],
                            ['Reserved', $kpis['reserved_today_rooms'], 'info', route('guest-rooms.bookings.index', ['reserved_today' => 1])],
                        ] as [$label, $val, $color, $link])
                        <div class="col">
                            @if($link)<a href="{{ $link }}" class="text-decoration-none text-body d-block h-100">@endif
                            <div class="hotel-stat hotel-stat--{{ $color }}">
                                <div class="hotel-stat__val">{{ $val }}</div>
                                <div class="hotel-stat__lbl">{{ $label }}</div>
                            </div>
                            @if($link)</a>@endif
                        </div>
                        @endforeach
            </div>

            <form method="GET" action="{{ route('guest-rooms.index') }}" class="hotel-search" id="hotel-avail-form">
                <input type="hidden" name="check_availability" value="1">
            <div class="row g-1 align-items-end hotel-search__row">
                <div class="col-auto hotel-search__label-wrap">
                    <span class="hotel-search__label"><i class="bi bi-calendar2-check"></i></span>
                </div>
                <div class="col-6 col-md-auto">
                    @include('partials.form-date-dmy', [
                        'name' => 'avail_check_in',
                        'label' => 'In',
                        'value' => $availCheckIn ?: now(),
                        'required' => true,
                        'class' => 'form-control form-control-sm',
                        'id' => 'avail_check_in',
                        'useOld' => false,
                    ])
                </div>
                <div class="col-6 col-md-auto">
                    @include('partials.form-date-dmy', [
                        'name' => 'avail_check_out',
                        'label' => 'Out',
                        'value' => $availCheckOut ?: now()->addDay(),
                        'required' => true,
                        'class' => 'form-control form-control-sm',
                        'id' => 'avail_check_out',
                        'useOld' => false,
                    ])
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label" for="avail_category_id">Type</label>
                    <select name="avail_category_id" id="avail_category_id" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach($roomCategories as $cat)
                            <option value="{{ $cat->id }}" @selected((int) ($availCategoryId ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-sm hotel-search__btn px-2">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            @if($errors->has('avail_check_in') || $errors->has('avail_check_out'))
                <div class="text-danger small mt-1">{{ $errors->first('avail_check_in') ?: $errors->first('avail_check_out') }}</div>
            @endif
            </form>
        </div>
    </div>

        {{-- Room rack — grows to fill column height --}}
        <div class="hotel-rack card border-0 flex-grow-1">
            @php
                $roomsByCategory = $rooms
                    ->groupBy(fn ($room) => $room->category?->name ?? 'Other')
                    ->sortBy(fn ($_, $categoryName) => \App\Models\RoomCategory::categorySortIndex($categoryName));
            @endphp
            <div class="card-header hotel-rack__header">
                <span class="hotel-rack__title"><i class="bi bi-grid-3x3-gap me-1"></i>Room status</span>
                @if($rooms->isNotEmpty())
                    <div class="hotel-rack__types">
                        @foreach($roomsByCategory as $categoryName => $categoryRooms)
                            @php $rackScheme = \App\Models\RoomCategory::dashboardColorScheme($categoryName); @endphp
                            <span class="hotel-rack__type hotel-rack__type--{{ $rackScheme }}">
                                <span class="hotel-rack__type-name">{{ $categoryName }}</span>
                                <span class="badge bg-light text-dark border">{{ $categoryRooms->count() }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
                <div class="hotel-legend hotel-rack__legend">
                    <span><i class="hotel-dot hotel-dot--available"></i>Available</span>
                    <span><i class="hotel-dot hotel-dot--occupied"></i>Occupied</span>
                    <span><i class="hotel-dot hotel-dot--reserved"></i>Reserved</span>
                    <span><i class="hotel-dot hotel-dot--cleaning"></i>Cleaning</span>
                </div>
            </div>
            <div class="card-body p-2 hotel-rack__body">
                @if($rooms->isEmpty())
                    <p class="text-secondary text-center py-5 mb-0">No rooms yet. <a href="{{ route('guest-rooms.rooms.create') }}">Add rooms</a></p>
                @else
                    @foreach($roomsByCategory as $categoryName => $categoryRooms)
                        @php $colorScheme = \App\Models\RoomCategory::dashboardColorScheme($categoryName); @endphp
                        <section class="hotel-cat-section hotel-cat-section--{{ $colorScheme }}">
                            <div class="hotel-cat-section__label">{{ $categoryName }}</div>
                            <div class="hotel-room-grid">
                                @foreach($categoryRooms as $room)
                                    @php
                                        $dashStatus = $room->dashboardDisplayStatus($reservedTodayRoomIds ?? []);
                                        $info = $roomBookingMap[$room->id] ?? null;
                                        $tileClass = match ($dashStatus) {
                                            \App\Models\GuestRoom::STATUS_OCCUPIED => 'hotel-room--occupied',
                                            \App\Models\GuestRoom::STATUS_AVAILABLE => 'hotel-room--available',
                                            \App\Models\GuestRoom::STATUS_RESERVED => 'hotel-room--reserved',
                                            \App\Models\GuestRoom::STATUS_CLEANING => 'hotel-room--cleaning',
                                            default => '',
                                        };
                                        if ($availability) {
                                            if (isset($availFreeIds[$room->id])) {
                                                $tileClass .= ' hotel-room--search-free';
                                            } elseif ($availBlocked->has($room->id)) {
                                                $tileClass .= ' hotel-room--search-blocked';
                                            }
                                        }
                                        $statusLabel = $room->dashboardStatusLabel($reservedTodayRoomIds ?? []);
                                        $block = $availBlocked->get($room->id);
                                        $bookingCreateParams = array_filter([
                                            'check_in_date' => $availability
                                                ? fmt_date($availability['check_in'])
                                                : fmt_date(now()),
                                            'check_out_date' => $availability
                                                ? fmt_date($availability['check_out'])
                                                : fmt_date(now()->addDay()),
                                            'room_category_id' => $room->room_category_id,
                                        ]);
                                        $href = match ($dashStatus) {
                                            \App\Models\GuestRoom::STATUS_AVAILABLE => route('guest-rooms.bookings.create', $bookingCreateParams),
                                            \App\Models\GuestRoom::STATUS_CLEANING => route('guest-rooms.cleaning.show', ['room' => $room, 'from' => 'dashboard']),
                                            \App\Models\GuestRoom::STATUS_OCCUPIED => $info['url'] ?? route('guest-rooms.bookings.index'),
                                            \App\Models\GuestRoom::STATUS_RESERVED => ($info['url'] ?? route('guest-rooms.bookings.index')).'#check-in-form',
                                            default => route('guest-rooms.rooms.edit', $room),
                                        };
                                        if ($availability) {
                                            if (isset($availFreeIds[$room->id])) {
                                                $href = route('guest-rooms.bookings.create', $bookingCreateParams);
                                            } elseif ($block && ! empty($block['block']['url'])) {
                                                $href = $block['block']['url'];
                                            }
                                        }
                                    @endphp
                                    @php
                                        $guestTip = $info['guest'] ?? (($block && ! empty($block['block']['guest'])) ? $block['block']['guest'] : null);
                                        $roomTip = match ($dashStatus) {
                                            \App\Models\GuestRoom::STATUS_AVAILABLE => $room->room_number.' — New booking',
                                            \App\Models\GuestRoom::STATUS_CLEANING => $room->room_number.' — Cleaning checklist',
                                            \App\Models\GuestRoom::STATUS_OCCUPIED => $room->room_number.' — '.($guestTip ?? 'Guest in house'),
                                            \App\Models\GuestRoom::STATUS_RESERVED => $room->room_number.' — '.($guestTip ?? 'Reserved guest'),
                                            default => $room->room_number.' — '.$statusLabel,
                                        };
                                    @endphp
                                    <a href="{{ $href }}" class="hotel-room hotel-room--scheme-{{ $colorScheme }} {{ $tileClass }}" data-room-id="{{ $room->id }}" title="{{ $roomTip }}">
                                        <span class="hotel-room__no">{{ $room->room_number }}</span>
                                        <span class="hotel-room__status">{{ $statusLabel }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif
            </div>
        </div>
        </div>

        {{-- Right: today's ops --}}
        <div class="col-lg-3 hotel-ops-side">
            <div class="card border-0 hotel-ops-card hotel-ops-card--arrivals mb-1">
                <div class="hotel-ops-card__head">
                    <span class="hotel-ops-card__title"><i class="bi bi-box-arrow-in-right me-1"></i>Arrivals</span>
                    <span class="badge rounded-pill">{{ $todayArrivals->count() }}</span>
                </div>
                <div class="list-group list-group-flush hotel-ops-side-list">
                    @forelse($todayArrivals as $b)
                        <a href="{{ route('guest-rooms.bookings.show', $b) }}#check-in-form" class="list-group-item list-group-item-action hotel-ops-line">
                            <span class="hotel-ops-name">{{ $b->guestDisplayName() }}</span>
                            <span class="hotel-ops-detail">{{ $b->roomNumbersLabel() }} · {{ $b->adults }}A{{ $b->children > 0 ? $b->children.'C' : '' }}</span>
                        </a>
                    @empty
                        <div class="list-group-item py-2 px-2 text-secondary small">None today</div>
                    @endforelse
                </div>
            </div>
            <div class="card border-0 hotel-ops-card hotel-ops-card--departures mb-1">
                <div class="hotel-ops-card__head">
                    <span class="hotel-ops-card__title"><i class="bi bi-box-arrow-right me-1"></i>Departures</span>
                    <span class="badge rounded-pill">{{ $todayDepartures->count() }}</span>
                </div>
                <div class="list-group list-group-flush hotel-ops-side-list">
                    @forelse($todayDepartures as $b)
                        <a href="{{ route('guest-rooms.checkout-counter.show', $b) }}" class="list-group-item list-group-item-action hotel-ops-line">
                            <span class="hotel-ops-name">{{ $b->guestDisplayName() }}</span>
                            <span class="hotel-ops-detail">{{ $b->roomNumbersLabel() }} · {{ number_format($b->balance, 0) }}</span>
                        </a>
                    @empty
                        <div class="list-group-item py-2 px-2 text-secondary small">None today</div>
                    @endforelse
                </div>
            </div>
            <div class="card border-0 hotel-ops-card hotel-ops-card--inhouse">
                <div class="hotel-ops-card__head">
                    <span class="hotel-ops-card__title"><i class="bi bi-people me-1"></i>In-house</span>
                    <span class="badge rounded-pill">{{ $inHouseGuests->count() }}</span>
                </div>
                <div class="list-group list-group-flush hotel-ops-side-list hotel-ops-side-list--grow">
                    @forelse($inHouseGuests as $b)
                        <a href="{{ route('guest-rooms.bookings.show', $b) }}" class="list-group-item list-group-item-action hotel-ops-line">
                            <span class="hotel-ops-name">{{ $b->guestDisplayName() }}</span>
                            <span class="hotel-ops-detail">{{ $b->roomNumbersLabel() }} · {{ fmt_date($b->check_out_date) }}</span>
                        </a>
                    @empty
                        <div class="list-group-item py-2 px-2 text-secondary small">None</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="availResultModal" tabindex="-1" aria-labelledby="availResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg avail-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold mb-0" id="availResultModalLabel">
                    <i class="bi bi-calendar2-check text-primary me-1"></i>Room availability
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="availModalBody">
                @if($showAvailModal)
                    @include('guest-rooms.partials.availability-modal-content', [
                        'availability' => $availability,
                        'availabilityError' => $availabilityError,
                        'roomCategories' => $roomCategories,
                    ])
                @endif
            </div>
            <div class="modal-footer border-0 pt-0" id="availModalFooter">
                @if($showAvailModal)
                    @include('guest-rooms.partials.availability-modal-footer', ['availability' => $availability])
                @else
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
                @endif
            </div>
        </div>
    </div>
</div>

</div>

<style>
.fd-dashboard {
    --fd-font: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    --fd-ink: #0f172a;
    --fd-ink-muted: #64748b;
    --fd-surface: #ffffff;
    --fd-border: rgba(15, 23, 42, 0.08);
    --fd-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px rgba(15, 23, 42, 0.06);
    --fd-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06);
    --fd-radius: 0.75rem;
    --fd-radius-sm: 0.5rem;
    --fd-navy: #0f172a;
    --fd-indigo: #312e81;
    --fd-violet: #6d28d9;
    font-family: var(--fd-font);
    font-size: 0.8125rem;
    line-height: 1.35;
    color: var(--fd-ink);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.fd-dashboard > .d-flex.flex-wrap.gap-2.mb-3 {
    margin-bottom: 0.5rem !important;
    gap: 0.35rem !important;
}
.fd-dashboard .guest-rooms-subnav-dash .btn {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    min-height: 1.75rem;
    border-radius: 999px;
    box-shadow: var(--fd-shadow-sm);
}

.hotel-dash {
    max-width: 100%;
    min-height: calc(100dvh - 5.75rem);
    display: flex;
    flex-direction: column;
    background: linear-gradient(165deg, #f8fafc 0%, #eef2f7 45%, #f1f5f9 100%);
    border-radius: var(--fd-radius);
    padding: 0.35rem;
}

.hotel-main-layout {
    align-items: stretch;
    flex: 1 1 auto;
    min-height: 0;
    --bs-gutter-y: 0.5rem;
    --bs-gutter-x: 0.5rem;
}
.hotel-main-col {
    min-height: 0;
    flex: 1 1 auto;
}

/* —— Front desk hero —— */
.hotel-head-block {
    border-radius: var(--fd-radius);
    box-shadow: var(--fd-shadow);
    overflow: hidden;
    background: var(--fd-surface);
    flex-shrink: 0;
    border: 1px solid var(--fd-border);
}
.hotel-head-block__banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
    padding: 0.55rem 0.85rem;
    background: linear-gradient(125deg, #0f172a 0%, #312e81 48%, #6d28d9 100%);
    color: #fff;
    position: relative;
    overflow: hidden;
}
.hotel-head-block__banner::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 120% at 100% 0%, rgba(255,255,255,0.12) 0%, transparent 55%);
    pointer-events: none;
}
.hotel-head-block__brand,
.hotel-head-block__meta {
    position: relative;
    z-index: 1;
}
.hotel-head-block__brand {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}
.hotel-head-block__titles {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
}
.hotel-head-block__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.55rem;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.28);
    font-size: 0.95rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.hotel-hero__title {
    font-size: 0.9375rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
    color: #fff;
}
.hotel-hero__sep {
    color: rgba(255, 255, 255, 0.45);
    font-weight: 400;
}
.hotel-hero__date {
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.85);
}
.hotel-head-block__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    flex-shrink: 0;
}
.hotel-head-block__pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #fff;
    backdrop-filter: blur(4px);
}
.hotel-head-block__pill--muted {
    background: rgba(0, 0, 0, 0.2);
}
.hotel-head-block__body {
    padding: 0.55rem 0.65rem 0.65rem;
    background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
}

/* —— KPI tiles —— */
.hotel-kpi-row {
    margin-bottom: 0.45rem;
    --bs-gutter-x: 0.45rem;
}
.hotel-kpi-row > .col { min-width: 0; }
.hotel-kpi-row a {
    transition: transform 0.18s ease;
}
.hotel-kpi-row a:hover {
    transform: translateY(-2px);
}
.hotel-stat {
    text-align: center;
    padding: 0.65rem 0.4rem 0.6rem;
    border-radius: var(--fd-radius-sm);
    background: #fff;
    border: 1px solid var(--fd-border);
    height: 100%;
    width: 100%;
    box-shadow: var(--fd-shadow-sm);
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.18s ease, border-color 0.18s ease;
}
.hotel-kpi-row a:hover .hotel-stat,
.hotel-stat:hover {
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.1);
}
.hotel-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    border-radius: var(--fd-radius-sm) var(--fd-radius-sm) 0 0;
}
.hotel-stat__val {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    font-variant-numeric: tabular-nums;
}
.hotel-stat__lbl {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--fd-ink-muted);
    margin-top: 0.2rem;
    line-height: 1.2;
}
.hotel-stat--primary::before { background: linear-gradient(90deg, #6366f1, #4f46e5); }
.hotel-stat--success::before { background: linear-gradient(90deg, #34d399, #16a34a); }
.hotel-stat--danger::before { background: linear-gradient(90deg, #f87171, #dc2626); }
.hotel-stat--warning::before { background: linear-gradient(90deg, #fbbf24, #d97706); }
.hotel-stat--info::before { background: linear-gradient(90deg, #60a5fa, #2563eb); }
.hotel-stat--secondary::before { background: linear-gradient(90deg, #94a3b8, #64748b); }
.hotel-stat--primary .hotel-stat__val { color: #4f46e5; }
.hotel-stat--success .hotel-stat__val { color: #16a34a; }
.hotel-stat--danger .hotel-stat__val { color: #dc2626; }
.hotel-stat--warning .hotel-stat__val { color: #b45309; }
.hotel-stat--info .hotel-stat__val { color: #2563eb; }
.hotel-stat--secondary .hotel-stat__val { color: #64748b; }

/* —— Availability search —— */
.hotel-search {
    padding: 0.5rem 0.55rem;
    border-radius: var(--fd-radius-sm);
    background: #f8fafc;
    border: 1px solid var(--fd-border);
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.03);
}
.hotel-search__label-wrap { padding-bottom: 0.1rem; }
.hotel-search__label {
    font-size: 0.95rem;
    color: #4f46e5;
    line-height: 1.8rem;
}
.hotel-search .form-label {
    font-size: 0.625rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 0.05rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.1;
}
.hotel-search .form-control,
.hotel-search .form-select {
    font-size: 0.75rem;
    font-weight: 500;
    min-height: 1.75rem !important;
    padding: 0.2rem 0.45rem !important;
    border-color: rgba(15, 23, 42, 0.1);
    background: #fff;
    border-radius: 0.4rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.hotel-search__btn {
    min-height: 1.75rem !important;
    line-height: 1;
    border-radius: 0.4rem;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}
/* —— Availability modal —— */
.avail-modal__dates {
    font-size: 0.875rem;
}
.avail-modal__lead .badge {
    font-size: 0.75rem;
    font-weight: 600;
}
.avail-modal__cat-title {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #3730a3;
    margin-bottom: 0.5rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--fd-border);
}
.avail-modal__rooms {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.avail-modal__room {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 3.5rem;
    padding: 0.4rem 0.65rem;
    border-radius: 0.4rem;
    background: linear-gradient(165deg, #22c55e 0%, #15803d 100%);
    border: 1px solid #14532d;
    color: #fff;
    font-size: 0.8125rem;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(21, 128, 61, 0.25);
    transition: filter 0.12s ease, transform 0.12s ease;
}
.avail-modal__room:hover {
    color: #fff;
    filter: brightness(1.08);
    transform: translateY(-1px);
}

/* —— Room rack —— */
.hotel-rack {
    min-width: 0;
    min-height: 0;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    border-radius: var(--fd-radius);
    box-shadow: var(--fd-shadow);
    background: var(--fd-surface);
    overflow: hidden;
    border: 1px solid var(--fd-border);
}
.hotel-rack__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.55rem;
    padding: 0.45rem 0.65rem;
    background: linear-gradient(180deg, #fafbfc 0%, #f1f5f9 100%);
    border-bottom: 1px solid var(--fd-border);
}
.hotel-rack__title {
    flex-shrink: 0;
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--fd-ink);
    white-space: nowrap;
}
.hotel-rack__types {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
    min-width: 0;
    flex: 1 1 auto;
}
.hotel-rack__type {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #e0e7ff;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(49, 46, 129, 0.06);
}
.hotel-rack__type-name {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4338ca;
}
.hotel-rack__type--cottage { background: #ccfbf1; border-color: #5eead4; }
.hotel-rack__type--cottage .hotel-rack__type-name { color: #0f766e; }
.hotel-rack__type--lodge { background: #e0e7ff; border-color: #a5b4fc; }
.hotel-rack__type--lodge .hotel-rack__type-name { color: #4338ca; }
.hotel-rack__type--hut { background: #dcfce7; border-color: #86efac; }
.hotel-rack__type--hut .hotel-rack__type-name { color: #15803d; }
.hotel-rack__type--suite { background: #fce7f3; border-color: #f9a8d4; }
.hotel-rack__type--suite .hotel-rack__type-name { color: #be185d; }
.hotel-rack__type--mthut { background: #ede9fe; border-color: #c4b5fd; }
.hotel-rack__type--mthut .hotel-rack__type-name { color: #6d28d9; }
.hotel-rack__type--boq,
.hotel-rack__type--default { background: #f1f5f9; border-color: #cbd5e1; }
.hotel-rack__type--boq .hotel-rack__type-name,
.hotel-rack__type--default .hotel-rack__type-name { color: #475569; }
.hotel-rack__type .badge {
    font-size: 0.5625rem;
    font-weight: 700;
    padding: 0.12em 0.4em;
    background: #eef2ff !important;
    color: #3730a3 !important;
    border: none !important;
    border-radius: 999px;
}
.hotel-rack__legend { flex-shrink: 0; }
.hotel-rack__body {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: visible;
    padding: 0.55rem 0.65rem 0.65rem !important;
    background: #fff;
}

.hotel-legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem 0.55rem;
    font-size: 0.625rem;
    font-weight: 600;
    color: var(--fd-ink-muted);
}
.hotel-dot {
    display: inline-block;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    margin-right: 0.2rem;
    vertical-align: middle;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.8);
}
.hotel-dot--available { background: #16a34a; }
.hotel-dot--occupied { background: #dc2626; }
.hotel-dot--reserved { background: #2563eb; }
.hotel-dot--cleaning { background: #ea580c; }

.hotel-cat-section {
    margin-bottom: 0.5rem;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 3.25rem;
}
.hotel-cat-section:last-child { margin-bottom: 0; }
.hotel-cat-section__label {
    font-size: 0.625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.3rem;
    padding-left: 0.1rem;
}
.hotel-cat-section--cottage .hotel-cat-section__label { color: #0f766e; }
.hotel-cat-section--lodge .hotel-cat-section__label { color: #4338ca; }
.hotel-cat-section--hut .hotel-cat-section__label { color: #15803d; }
.hotel-cat-section--suite .hotel-cat-section__label { color: #be185d; }
.hotel-cat-section--mthut .hotel-cat-section__label { color: #6d28d9; }
.hotel-cat-section--boq .hotel-cat-section__label { color: #475569; }
.hotel-cat-section--default .hotel-cat-section__label { color: #64748b; }

.hotel-room-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(4.85rem, 1fr));
    grid-auto-rows: minmax(3.1rem, 1fr);
    gap: 0.4rem;
    flex: 1 1 auto;
    align-content: center;
}
.hotel-room {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.3rem;
    min-height: 3.1rem;
    max-height: 4.75rem;
    height: 100%;
    border-radius: 0.55rem;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: #fff;
    text-decoration: none;
    color: var(--fd-ink);
    text-align: center;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
}
.hotel-room:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.14);
    filter: brightness(1.03);
}
.hotel-room--available:hover,
.hotel-room--occupied:hover,
.hotel-room--reserved:hover,
.hotel-room--cleaning:hover {
    color: #fff;
}
.hotel-room__no {
    font-size: 0.8125rem;
    font-weight: 800;
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
}
.hotel-room__status {
    font-size: 0.5rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-top: 0.12rem;
    line-height: 1.1;
    white-space: normal;
    word-break: break-word;
    max-width: 100%;
    opacity: 0.92;
}
.hotel-room--available {
    background: linear-gradient(155deg, #4ade80 0%, #16a34a 55%, #15803d 100%);
    border-color: #166534;
    color: #fff;
    box-shadow: 0 3px 10px rgba(22, 163, 74, 0.35);
}
.hotel-room--scheme-cottage.hotel-room--available {
    background: linear-gradient(155deg, #5eead4 0%, #14b8a6 50%, #0f766e 100%);
    border-color: #0f766e;
    box-shadow: 0 3px 10px rgba(15, 118, 110, 0.38);
}
.hotel-room--scheme-cottage.hotel-room--available .hotel-room__status { color: #ccfbf1; }
.hotel-room--scheme-lodge.hotel-room--available {
    background: linear-gradient(155deg, #a5b4fc 0%, #6366f1 50%, #4338ca 100%);
    border-color: #3730a3;
    box-shadow: 0 3px 10px rgba(67, 56, 202, 0.38);
}
.hotel-room--scheme-lodge.hotel-room--available .hotel-room__status { color: #e0e7ff; }
.hotel-room--scheme-hut.hotel-room--available {
    background: linear-gradient(155deg, #4ade80 0%, #16a34a 55%, #15803d 100%);
    border-color: #166534;
    box-shadow: 0 3px 10px rgba(22, 163, 74, 0.35);
}
.hotel-room--scheme-hut.hotel-room--available .hotel-room__status { color: #ecfdf5; }
.hotel-room--scheme-suite.hotel-room--available {
    background: linear-gradient(155deg, #f9a8d4 0%, #ec4899 50%, #be185d 100%);
    border-color: #9d174d;
    box-shadow: 0 3px 10px rgba(190, 24, 93, 0.38);
}
.hotel-room--scheme-suite.hotel-room--available .hotel-room__status { color: #fce7f3; }
.hotel-room--scheme-mthut.hotel-room--available {
    background: linear-gradient(155deg, #c4b5fd 0%, #8b5cf6 50%, #6d28d9 100%);
    border-color: #5b21b6;
    box-shadow: 0 3px 10px rgba(109, 40, 217, 0.38);
}
.hotel-room--scheme-mthut.hotel-room--available .hotel-room__status { color: #ede9fe; }
.hotel-room--scheme-boq.hotel-room--available,
.hotel-room--scheme-default.hotel-room--available {
    background: linear-gradient(155deg, #cbd5e1 0%, #64748b 50%, #475569 100%);
    border-color: #334155;
    box-shadow: 0 3px 10px rgba(71, 85, 105, 0.35);
}
.hotel-room--scheme-boq.hotel-room--available .hotel-room__status,
.hotel-room--scheme-default.hotel-room--available .hotel-room__status { color: #f1f5f9; }
.hotel-room--available .hotel-room__no { color: #fff; }
.hotel-room--available .hotel-room__status { color: #ecfdf5; }
.hotel-room--occupied {
    background: linear-gradient(155deg, #f87171 0%, #dc2626 55%, #b91c1c 100%);
    border-color: #991b1b;
    color: #fff;
    box-shadow: 0 3px 10px rgba(220, 38, 38, 0.35);
}
.hotel-room--occupied .hotel-room__no { color: #fff; }
.hotel-room--occupied .hotel-room__status { color: #fee2e2; }
.hotel-room--reserved {
    background: linear-gradient(155deg, #60a5fa 0%, #2563eb 55%, #1d4ed8 100%);
    border-color: #1e40af;
    color: #fff;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.35);
}
.hotel-room--reserved .hotel-room__no { color: #fff; }
.hotel-room--reserved .hotel-room__status { color: #dbeafe; }
.hotel-room--cleaning {
    background: linear-gradient(155deg, #fbbf24 0%, #ea580c 55%, #c2410c 100%);
    border-color: #9a3412;
    color: #fff;
    box-shadow: 0 3px 10px rgba(234, 88, 12, 0.35);
}
.hotel-room--cleaning .hotel-room__no { color: #fff; }
.hotel-room--cleaning .hotel-room__status { color: #ffedd5; }
.hotel-room--search-free {
    outline: 2px solid #4ade80;
    outline-offset: 2px;
    box-shadow: 0 0 0 2px #fff, 0 6px 20px rgba(34, 197, 94, 0.45);
}
.hotel-room--search-blocked {
    opacity: 0.45;
    border-style: dashed;
    filter: grayscale(0.3);
}

/* —— Ops sidebar —— */
.hotel-ops-side {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-height: calc(100dvh - 5.75rem);
}
.hotel-ops-card {
    border-radius: var(--fd-radius);
    box-shadow: var(--fd-shadow);
    overflow: hidden;
    background: var(--fd-surface);
    flex: 1 1 auto;
    min-height: 0;
    border: 1px solid var(--fd-border);
}
.hotel-ops-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding: 0.4rem 0.55rem;
    font-weight: 700;
}
.hotel-ops-card__title {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.02em;
}
.hotel-ops-card__head .badge {
    font-size: 0.6875rem;
    font-weight: 700;
    min-width: 1.35rem;
    padding: 0.25em 0.5em;
    border-radius: 999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.hotel-ops-card--arrivals .hotel-ops-card__head {
    background: linear-gradient(90deg, #ecfdf5 0%, #fff 70%);
    border-bottom: 2px solid #22c55e;
    color: #15803d;
}
.hotel-ops-card--arrivals .badge { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.hotel-ops-card--departures .hotel-ops-card__head {
    background: linear-gradient(90deg, #fffbeb 0%, #fff 70%);
    border-bottom: 2px solid #f59e0b;
    color: #b45309;
}
.hotel-ops-card--departures .badge { background: linear-gradient(135deg, #fbbf24, #d97706); color: #fff; }
.hotel-ops-card--inhouse .hotel-ops-card__head {
    background: linear-gradient(90deg, #fef2f2 0%, #fff 70%);
    border-bottom: 2px solid #ef4444;
    color: #b91c1c;
}
.hotel-ops-card--inhouse .badge { background: linear-gradient(135deg, #f87171, #dc2626); color: #fff; }

.hotel-ops-side-list {
    max-height: none;
    overflow: visible;
    background: #fff;
}
.hotel-ops-side-list--grow { max-height: none; }
.hotel-ops-line {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.12rem;
    padding: 0.45rem 0.55rem !important;
    border-color: var(--fd-border);
    font-size: 0.6875rem;
    line-height: 1.25;
    transition: background 0.15s ease;
}
.hotel-ops-line:hover {
    background: #f8fafc;
}
.hotel-ops-name {
    font-weight: 700;
    color: var(--fd-ink);
    white-space: normal;
    word-break: break-word;
    width: 100%;
    line-height: 1.35;
}
.hotel-ops-detail {
    font-weight: 500;
    color: var(--fd-ink-muted);
    width: 100%;
    white-space: nowrap;
    flex-shrink: 0;
    font-size: 0.625rem;
}

@media (max-width: 991.98px) {
    .hotel-dash,
    .hotel-ops-side { min-height: 0; }
    .hotel-rack__body { justify-content: flex-start; }
    .hotel-cat-section { flex: 0 0 auto; min-height: 0; }
    .hotel-room-grid { grid-template-columns: repeat(auto-fill, minmax(3.75rem, 1fr)); }
}
@media (max-width: 575.98px) {
    .hotel-stat__val { font-size: 1.25rem; }
    .hotel-stat__lbl { font-size: 0.6875rem; }
}

body.admin-app-body:has(.fd-dashboard) main.admin-main {
    padding-top: 0.4rem !important;
    padding-bottom: 0.25rem !important;
    background: #e8eef4;
}
.fd-dashboard .alert {
    margin-bottom: 0.45rem !important;
    padding: 0.45rem 0.75rem;
    font-size: 0.8125rem;
    border-radius: var(--fd-radius-sm);
    border: none;
    box-shadow: var(--fd-shadow-sm);
}
</style>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelector('.guest-rooms-dash-page .d-flex.flex-wrap.gap-2.mb-3')?.classList.add('guest-rooms-subnav-dash');

    var clockEl = document.getElementById('fd-live-clock');
    if (clockEl) {
        var tick = function () {
            var d = new Date();
            var h = d.getHours();
            var m = String(d.getMinutes()).padStart(2, '0');
            var ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            clockEl.textContent = h + ':' + m + ' ' + ap;
        };
        tick();
        setInterval(tick, 30000);
    }

    var availModalEl = document.getElementById('availResultModal');
    var availModalInstance = null;

    function getAvailModal() {
        if (!availModalEl) return null;
        if (!window.bootstrap || !window.bootstrap.Modal) return null;
        if (!availModalInstance) {
            availModalInstance = new window.bootstrap.Modal(availModalEl);
        }
        return availModalInstance;
    }

    function showAvailModal() {
        var modal = getAvailModal();
        if (modal) modal.show();
    }

    function whenBootstrapReady(fn) {
        if (getAvailModal()) {
            fn();
            return;
        }
        setTimeout(function () { whenBootstrapReady(fn); }, 50);
    }

    function clearAvailHighlights() {
        document.querySelectorAll('.hotel-room[data-room-id]').forEach(function (tile) {
            tile.classList.remove('hotel-room--search-free', 'hotel-room--search-blocked');
        });
    }

    function applyAvailHighlights(freeIds, blockedIds) {
        clearAvailHighlights();
        (freeIds || []).forEach(function (id) {
            document.querySelector('.hotel-room[data-room-id="' + id + '"]')
                ?.classList.add('hotel-room--search-free');
        });
        (blockedIds || []).forEach(function (id) {
            document.querySelector('.hotel-room[data-room-id="' + id + '"]')
                ?.classList.add('hotel-room--search-blocked');
        });
    }

    function availSearchParams(form) {
        var params = new URLSearchParams(new FormData(form));
        params.set('ajax', '1');
        params.set('check_availability', '1');
        var checkIn = document.getElementById('avail_check_in');
        var checkOut = document.getElementById('avail_check_out');
        if (checkIn && checkIn.value) {
            params.set('avail_check_in', checkIn.value);
        }
        if (checkOut && checkOut.value) {
            params.set('avail_check_out', checkOut.value);
        }
        return params;
    }

    function parseAvailResponse(res, text) {
        var contentType = (res.headers.get('content-type') || '').toLowerCase();
        if (contentType.indexOf('application/json') !== -1) {
            try {
                return JSON.parse(text);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    var availForm = document.getElementById('hotel-avail-form');
    if (availForm) {
        availForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var bodyEl = document.getElementById('availModalBody');
            var footerEl = document.getElementById('availModalFooter');
            var params = availSearchParams(availForm);

            if (bodyEl) {
                bodyEl.innerHTML = '<div class="text-center py-4 text-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Checking…</div>';
            }
            if (footerEl) {
                footerEl.innerHTML = '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>';
            }

            whenBootstrapReady(function () {
                showAvailModal();

                fetch(availForm.action + '?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (res) {
                        return res.text().then(function (text) {
                            return { ok: res.ok, data: parseAvailResponse(res, text), text: text };
                        });
                    })
                    .then(function (result) {
                        var data = result.data || {};
                        var html = data.html || '';
                        if (!html) {
                            html = '<div class="alert alert-danger mb-0">Could not load availability. Please refresh and try again.</div>';
                        }
                        if (bodyEl) {
                            bodyEl.innerHTML = html;
                        }
                        if (footerEl) {
                            footerEl.innerHTML = data.footer_html || '<button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>';
                        }
                        applyAvailHighlights(data.free_ids || [], data.blocked_ids || []);

                        var url = new URL(window.location.href);
                        params.delete('ajax');
                        window.history.replaceState({}, '', url.pathname + '?' + params.toString());
                    })
                    .catch(function () {
                        if (bodyEl) {
                            bodyEl.innerHTML = '<div class="alert alert-danger mb-0">Could not check availability. Please try again.</div>';
                        }
                    });
            });
        });
    }

    @if($showAvailModal)
    whenBootstrapReady(showAvailModal);
    @endif
})();
</script>
@endpush
