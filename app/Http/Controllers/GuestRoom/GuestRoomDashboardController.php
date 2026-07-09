<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\GuestRoom;
use App\Models\RoomBill;
use App\Models\RoomBooking;
use App\Models\RoomCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestRoomDashboardController extends Controller
{
    public function index(Request $request)
    {
        $reservedTodayStats = $this->reservedTodayStats();
        $reservedTodayRoomIds = $this->roomIdsReservedToday();

        $rooms = GuestRoom::query()
            ->with(['category:id,name'])
            ->where('active', true)
            ->orderByCategoryThenRoom()
            ->get();

        $displayStatusCounts = [
            GuestRoom::STATUS_AVAILABLE => 0,
            GuestRoom::STATUS_OCCUPIED => 0,
            GuestRoom::STATUS_CLEANING => 0,
            GuestRoom::STATUS_RESERVED => 0,
        ];
        foreach ($rooms as $room) {
            $displayStatus = $room->dashboardDisplayStatus($reservedTodayRoomIds);
            if (isset($displayStatusCounts[$displayStatus])) {
                $displayStatusCounts[$displayStatus]++;
            }
        }

        $kpis = [
            'total_rooms' => $rooms->count(),
            'available' => $displayStatusCounts[GuestRoom::STATUS_AVAILABLE]
                + $displayStatusCounts[GuestRoom::STATUS_CLEANING],
            'occupied' => $displayStatusCounts[GuestRoom::STATUS_OCCUPIED],
            'cleaning' => $displayStatusCounts[GuestRoom::STATUS_CLEANING],
            'reserved_today_rooms' => $reservedTodayStats['rooms'],
            'reserved_today_bookings' => $reservedTodayStats['bookings'],
            'unpaid_bills' => RoomBill::query()
                ->where(function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'partial'])
                        ->orWhere('balance', '>', 0);
                })
                ->count(),
            'unpaid_bills_balance' => (float) RoomBill::query()
                ->where(function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'partial'])
                        ->orWhere('balance', '>', 0);
                })
                ->sum('balance'),
        ];

        $bookingEager = ['guestRoom:id,room_number', 'assignedRooms:id,room_number', 'category:id,name'];

        $todayArrivals = RoomBooking::query()
            ->with($bookingEager)
            ->where('status', RoomBooking::STATUS_RESERVED)
            ->whereDate('check_in_date', today())
            ->orderBy('check_in_date')
            ->orderBy('guest_name')
            ->get();

        $todayDepartures = RoomBooking::query()
            ->with($bookingEager)
            ->where('status', RoomBooking::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', today())
            ->orderBy('check_out_date')
            ->orderBy('guest_name')
            ->get();

        $inHouseGuests = RoomBooking::query()
            ->with($bookingEager)
            ->where('status', RoomBooking::STATUS_CHECKED_IN)
            ->orderBy('check_out_date')
            ->orderBy('guest_name')
            ->get();

        $totalRooms = max(1, $rooms->count());
        $occupancyPercent = round(($displayStatusCounts[GuestRoom::STATUS_OCCUPIED] / $totalRooms) * 100, 1);
        $roomBookingMap = $this->roomBookingMapForDashboard($rooms);

        $mobileUrls = mobile_app_urls();
        if ($mobileUrls !== []) {
            @file_put_contents(storage_path('app/local-server-ip.txt'), local_lan_ip() ?? '');
        }

        $roomCategories = RoomCategory::query()
            ->where('active', true)
            ->orderedForRoomList()
            ->get(['id', 'name']);

        $availability = null;
        $availabilityError = null;
        $availCheckIn = $request->input('avail_check_in', '');
        $availCheckOut = $request->input('avail_check_out', '');
        $availCategoryId = $request->filled('avail_category_id') ? (int) $request->input('avail_category_id') : null;

        if ($request->boolean('check_availability') || $request->filled('avail_check_in')) {
            $wantsJson = $request->expectsJson() || $request->boolean('ajax');

            normalize_request_dates($request, ['avail_check_in', 'avail_check_out']);
            if ($request->has('avail_category_id') && $request->input('avail_category_id') === '') {
                $request->merge(['avail_category_id' => null]);
            }
            $availCheckIn = (string) $request->input('avail_check_in', $availCheckIn);
            $availCheckOut = (string) $request->input('avail_check_out', $availCheckOut);

            try {
                $request->validate([
                    'avail_check_in' => ['required', 'date'],
                    'avail_check_out' => ['required', 'date', 'after:avail_check_in'],
                    'avail_category_id' => ['nullable', 'integer', 'exists:tenant.room_categories,id'],
                ]);
            } catch (ValidationException $e) {
                if ($wantsJson) {
                    return $this->availabilityJsonResponse(
                        null,
                        collect($e->errors())->flatten()->first() ?? 'Invalid dates.',
                        $roomCategories,
                        422
                    );
                }

                throw $e;
            }

            try {
                $availability = GuestRoom::searchAvailability(
                    $availCheckIn,
                    $availCheckOut,
                    $availCategoryId ?: null
                );
            } catch (\Throwable $e) {
                $availabilityError = 'Could not check availability. Please verify dates.';
            }

            if ($wantsJson) {
                return $this->availabilityJsonResponse(
                    $availability,
                    $availabilityError,
                    $roomCategories,
                    $availabilityError === null ? 200 : 422
                );
            }
        }

        return view('guest-rooms.index', compact(
            'kpis',
            'rooms',
            'mobileUrls',
            'reservedTodayRoomIds',
            'todayArrivals',
            'todayDepartures',
            'inHouseGuests',
            'occupancyPercent',
            'roomBookingMap',
            'roomCategories',
            'availability',
            'availabilityError',
            'availCheckIn',
            'availCheckOut',
            'availCategoryId',
        ));
    }

    /**
     * @param  array<string, mixed>|null  $availability
     * @param  \Illuminate\Support\Collection<int, RoomCategory>|null  $roomCategories
     */
    private function availabilityJsonResponse(?array $availability, ?string $availabilityError, $roomCategories, int $status = 200)
    {
        $roomCategories ??= RoomCategory::query()->where('active', true)->orderedForRoomList()->get(['id', 'name']);

        try {
            $html = view('guest-rooms.partials.availability-modal-content', [
                'availability' => $availability,
                'availabilityError' => $availabilityError,
                'roomCategories' => $roomCategories,
            ])->render();
            $footerHtml = view('guest-rooms.partials.availability-modal-footer', [
                'availability' => $availability,
            ])->render();
        } catch (\Throwable $e) {
            $html = '<div class="alert alert-danger mb-0">Could not display availability results. Please try again.</div>';
            $footerHtml = '<button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>';
        }

        if (trim(strip_tags($html)) === '') {
            $html = '<div class="alert alert-warning mb-0">No availability data returned. Please check dates and try again.</div>';
        }

        return response()->json([
            'ok' => $availability !== null && $availabilityError === null,
            'html' => $html,
            'footer_html' => $footerHtml,
            'free_ids' => $availability
                ? $availability['available']->pluck('id')->values()->all()
                : [],
            'blocked_ids' => $availability
                ? $availability['unavailable']->map(fn ($row) => $row['room']->id)->values()->all()
                : [],
        ], $status);
    }

    /**
     * Active guest per room for dashboard tiles (checked-in or reserved today).
     *
     * @param  \Illuminate\Support\Collection<int, GuestRoom>  $rooms
     * @return array<int, array{guest: string, status: string, booking_no: string, url: string}>
     */
    private function roomBookingMapForDashboard($rooms): array
    {
        if ($rooms->isEmpty()) {
            return [];
        }

        $today = now()->toDateString();
        $roomIds = $rooms->pluck('id')->map(fn ($id) => (int) $id)->all();

        $bookings = RoomBooking::query()
            ->where(function ($q) use ($today) {
                $q->where('status', RoomBooking::STATUS_CHECKED_IN)
                    ->orWhere(function ($q2) use ($today) {
                        $q2->where('status', RoomBooking::STATUS_RESERVED)
                            ->whereDate('check_in_date', '<=', $today)
                            ->whereDate('check_out_date', '>', $today);
                    });
            })
            ->with(['assignedRooms:id,room_number'])
            ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
            ->get();

        $map = [];
        foreach ($bookings as $booking) {
            foreach ($booking->activeAssignedRoomIds() as $roomId) {
                if (! in_array($roomId, $roomIds, true) || isset($map[$roomId])) {
                    continue;
                }
                $map[$roomId] = [
                    'guest' => $booking->guestDisplayName(),
                    'status' => $booking->status,
                    'booking_no' => $booking->booking_no,
                    'url' => route('guest-rooms.bookings.show', $booking),
                ];
            }
        }

        return $map;
    }

    /** @param \Illuminate\Support\Collection<int, GuestRoom> $rooms */
    private function reservationChartData($rooms): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $today = now()->toDateString();

        $dates = [];
        for ($cursor = $monthStart->copy(); $cursor->lte($monthEnd); $cursor->addDay()) {
            $dates[] = [
                'iso' => $cursor->toDateString(),
                'day' => $cursor->day,
                'dow' => $cursor->format('D'),
                'is_today' => $cursor->toDateString() === $today,
                'is_weekend' => $cursor->isWeekend(),
            ];
        }

        $cells = [];
        if ($rooms->isNotEmpty()) {
            $bookings = RoomBooking::query()
                ->whereIn('status', [RoomBooking::STATUS_RESERVED, RoomBooking::STATUS_CHECKED_IN])
                ->stayOverlaps($monthStart->toDateString(), $monthEnd->copy()->addDay()->toDateString())
                ->with(['assignedRooms'])
                ->get();

            $dateIsos = array_column($dates, 'iso');

            foreach ($bookings as $booking) {
                $roomIds = $booking->activeAssignedRoomIds();
                if ($roomIds === []) {
                    continue;
                }

                $statusLabel = RoomBooking::statusLabels()[$booking->status] ?? $booking->status;

                $statusPhrase = $booking->status === RoomBooking::STATUS_CHECKED_IN
                    ? 'guest is checked in'
                    : 'booking is reserved';

                $cellPayload = [
                    'booking_id' => $booking->id,
                    'guest' => $booking->guestDisplayName(),
                    'booking_no' => $booking->booking_no,
                    'status' => $booking->status,
                    'status_label' => $statusLabel,
                    'url' => route('guest-rooms.bookings.show', $booking),
                    'check_in' => fmt_date($booking->check_in_date),
                    'check_out' => fmt_date($booking->check_out_date),
                    'check_in_iso' => Carbon::parse($booking->check_in_date)->toDateString(),
                    'check_out_iso' => Carbon::parse($booking->check_out_date)->toDateString(),
                    'tooltip' => $booking->guestDisplayName().' — '.$statusPhrase.' ('.$booking->booking_no.') · '
                        .fmt_date($booking->check_in_date).' → '.fmt_date($booking->check_out_date),
                ];

                foreach ($roomIds as $roomId) {
                    foreach ($dateIsos as $iso) {
                        if (! $booking->coversStayDate($iso)) {
                            continue;
                        }
                        $cells[(int) $roomId][$iso] = $cellPayload;
                    }
                }
            }
        }

        return [
            'month_label' => $monthStart->format('F Y'),
            'dates' => $dates,
            'cells' => $cells,
            'segments' => $this->reservationChartSegments($rooms, $dates, $cells),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GuestRoom>  $rooms
     * @param  list<array{iso: string, day: int, dow: string, is_today: bool, is_weekend: bool}>  $dates
     * @param  array<int, array<string, array<string, mixed>>>  $cells
     * @return array<int, list<array<string, mixed>>>
     */
    private function reservationChartSegments($rooms, array $dates, array $cells): array
    {
        $segments = [];
        $dateIsos = array_column($dates, 'iso');
        $dateCount = count($dateIsos);

        foreach ($rooms as $room) {
            $roomSegments = [];
            $roomCells = $cells[$room->id] ?? [];
            $di = 0;

            while ($di < $dateCount) {
                $iso = $dateIsos[$di];
                $cell = $roomCells[$iso] ?? null;

                if ($cell === null) {
                    $emptySpan = 1;
                    while ($di + $emptySpan < $dateCount && ! isset($roomCells[$dateIsos[$di + $emptySpan]])) {
                        $emptySpan++;
                    }
                    $emptyDates = array_slice($dates, $di, $emptySpan);
                    $roomSegments[] = [
                        'type' => 'empty',
                        'colspan' => $emptySpan,
                        'has_today' => collect($emptyDates)->contains(fn ($d) => $d['is_today']),
                        'has_weekend' => collect($emptyDates)->contains(fn ($d) => $d['is_weekend']),
                    ];
                    $di += $emptySpan;

                    continue;
                }

                $bookingId = $cell['booking_id'];
                $colspan = 1;
                while ($di + $colspan < $dateCount) {
                    $nextCell = $roomCells[$dateIsos[$di + $colspan]] ?? null;
                    if (($nextCell['booking_id'] ?? null) !== $bookingId) {
                        break;
                    }
                    $colspan++;
                }

                $segmentDates = array_slice($dates, $di, $colspan);
                $prevIso = $di > 0 ? $dateIsos[$di - 1] : null;
                $prevCell = $prevIso ? ($roomCells[$prevIso] ?? null) : null;
                $checkInInSegment = collect($segmentDates)->contains(
                    fn ($d) => ($cell['check_in_iso'] ?? '') === $d['iso']
                );

                $roomSegments[] = [
                    'type' => 'booking',
                    'colspan' => $colspan,
                    'cell' => $cell,
                    'is_new' => $prevCell !== null && ($prevCell['booking_id'] ?? null) !== $bookingId,
                    'show_marker' => true,
                    'has_today' => collect($segmentDates)->contains(fn ($d) => $d['is_today']),
                    'has_weekend' => collect($segmentDates)->contains(fn ($d) => $d['is_weekend']),
                    'tooltip' => $cell['tooltip']
                        .($checkInInSegment ? ' · Starts here' : '')
                        .($prevCell !== null && ($prevCell['booking_id'] ?? null) !== $bookingId ? ' · New booking after previous guest' : ''),
                ];

                $di += $colspan;
            }

            $segments[$room->id] = $roomSegments;
        }

        return $segments;
    }

    /** @return list<int> */
    private function roomIdsReservedToday(): array
    {
        $today = now()->toDateString();

        $fromPivot = DB::connection('tenant')
            ->table('room_booking_guest_room as p')
            ->join('room_bookings as b', 'b.id', '=', 'p.room_booking_id')
            ->whereNull('p.released_at')
            ->where('b.status', RoomBooking::STATUS_RESERVED)
            ->whereDate('b.check_in_date', '<=', $today)
            ->whereDate('b.check_out_date', '>=', $today)
            ->pluck('p.guest_room_id');

        $fromPrimary = RoomBooking::query()
            ->reservedStayingToday()
            ->whereNotNull('guest_room_id')
            ->pluck('guest_room_id');

        $fromReservedFlag = DB::connection('tenant')
            ->table('guest_rooms as gr')
            ->join('room_bookings as b', 'b.guest_room_id', '=', 'gr.id')
            ->where('gr.status', GuestRoom::STATUS_RESERVED)
            ->where('gr.active', true)
            ->where('b.status', RoomBooking::STATUS_RESERVED)
            ->whereDate('b.check_in_date', '<=', $today)
            ->whereDate('b.check_out_date', '>=', $today)
            ->pluck('gr.id');

        return $fromPivot->merge($fromPrimary)->merge($fromReservedFlag)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array{rooms: int, bookings: int} */
    private function reservedTodayStats(): array
    {
        $bookings = RoomBooking::query()
            ->reservedStayingToday()
            ->get(['id', 'rooms_count', 'guest_room_id']);

        $bookingCount = $bookings->count();
        $roomCount = 0;

        if ($bookingCount > 0) {
            $assignedCounts = DB::connection('tenant')
                ->table('room_booking_guest_room')
                ->whereIn('room_booking_id', $bookings->pluck('id'))
                ->whereNull('released_at')
                ->selectRaw('room_booking_id, COUNT(*) as c')
                ->groupBy('room_booking_id')
                ->pluck('c', 'room_booking_id');

            foreach ($bookings as $booking) {
                $assigned = (int) ($assignedCounts[$booking->id] ?? 0);
                if ($assigned > 0) {
                    $roomCount += $assigned;
                } else {
                    $roomCount += max(1, (int) ($booking->rooms_count ?? 1));
                }
            }
        }

        return ['rooms' => $roomCount, 'bookings' => $bookingCount];
    }
}
