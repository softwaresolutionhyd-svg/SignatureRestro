<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\GuestRoom;
use App\Models\RoomBill;
use App\Models\RoomBooking;
use App\Models\RoomBookingCharge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestRoomReportController extends Controller
{
    public function index(Request $request)
    {
        normalize_request_dates($request, ['from', 'to']);

        $from = $request->filled('from')
            ? (parse_display_date($request->input('from')) ?? Carbon::parse($request->string('from')))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? (parse_display_date($request->input('to')) ?? Carbon::parse($request->string('to')))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $bookingsQuery = RoomBooking::query()
            ->with(['guestRoom:id,room_number', 'assignedRooms:id,room_number', 'category:id,name'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('check_out_date', [$from->toDateString(), $to->toDateString()]);
            });

        $bookings = (clone $bookingsQuery)->latest('check_in_date')->get();

        $statusCounts = (clone $bookingsQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $typeCounts = (clone $bookingsQuery)
            ->select('booking_type', DB::raw('count(*) as total'))
            ->groupBy('booking_type')
            ->pluck('total', 'booking_type');

        $guestCountsRow = (clone $bookingsQuery)
            ->where('status', '!=', RoomBooking::STATUS_CANCELLED)
            ->selectRaw('COALESCE(SUM(adults), 0) as total_adults, COALESCE(SUM(children), 0) as total_children')
            ->first();

        $guestCounts = [
            'adults' => (int) ($guestCountsRow->total_adults ?? 0),
            'children' => (int) ($guestCountsRow->total_children ?? 0),
            'total' => (int) ($guestCountsRow->total_adults ?? 0) + (int) ($guestCountsRow->total_children ?? 0),
        ];

        $billsQuery = RoomBill::query()
            ->whereBetween('billed_at', [$from, $to]);

        $revenue = [
            'bills' => (clone $billsQuery)->count(),
            'total' => (float) (clone $billsQuery)->sum('total'),
            'collected' => (float) (clone $billsQuery)->sum('paid_amount'),
            'balance' => (float) (clone $billsQuery)->sum('balance'),
            'room_charges' => (float) (clone $billsQuery)->sum('room_charges'),
            'extra_charges' => (float) (clone $billsQuery)->sum('extra_charges'),
            'discount' => (float) (clone $billsQuery)->sum('discount'),
            'tax' => (float) (clone $billsQuery)->sum('tax_amount'),
        ];

        $revenueByCategory = RoomBill::query()
            ->join('room_bookings', 'room_bills.room_booking_id', '=', 'room_bookings.id')
            ->leftJoin('room_categories', 'room_bookings.room_category_id', '=', 'room_categories.id')
            ->whereBetween('room_bills.billed_at', [$from, $to])
            ->select(
                'room_categories.name as category_name',
                DB::raw('count(room_bills.id) as bill_count'),
                DB::raw('sum(room_bills.total) as total_amount'),
                DB::raw('sum(room_bills.paid_amount) as paid_amount'),
                DB::raw('sum(room_bills.balance) as balance_amount')
            )
            ->groupBy('room_categories.id', 'room_categories.name')
            ->orderByDesc('total_amount')
            ->get();

        $extraChargesTotal = RoomBookingCharge::query()
            ->whereHas('booking', function ($q) use ($from, $to) {
                $q->whereBetween('check_in_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->sum('amount');

        $roomStatus = GuestRoom::query()
            ->where('active', true)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $bills = RoomBill::query()
            ->with(['booking:id,booking_no,guest_name,guest_room_id', 'booking.guestRoom:id,room_number', 'booking.assignedRooms:id,room_number', 'booking.category:id,name'])
            ->whereBetween('billed_at', [$from, $to])
            ->latest('billed_at')
            ->limit(100)
            ->get();

        return view('guest-rooms.reports.index', compact(
            'from',
            'to',
            'bookings',
            'statusCounts',
            'typeCounts',
            'guestCounts',
            'revenue',
            'revenueByCategory',
            'extraChargesTotal',
            'roomStatus',
            'bills'
        ));
    }
}
