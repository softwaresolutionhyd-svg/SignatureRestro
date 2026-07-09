<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\RoomBooking;
use App\Models\RoomBookingCharge;
use App\Models\Setting;
use App\Services\CheckoutCounterService;
use App\Support\GuestRoomReceiptBreakdown;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutCounterController extends Controller
{
    public function __construct(
        private readonly CheckoutCounterService $checkoutCounter,
        private readonly BookingController $bookings,
    ) {}

    public function index(): View
    {
        $today = now()->toDateString();

        $departuresToday = RoomBooking::query()
            ->where('status', RoomBooking::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', $today)
            ->with(['assignedRooms:id,room_number', 'category:id,name'])
            ->orderBy('guest_name')
            ->get();

        $checkedIn = RoomBooking::query()
            ->where('status', RoomBooking::STATUS_CHECKED_IN)
            ->with(['assignedRooms:id,room_number', 'category:id,name'])
            ->orderBy('guest_name')
            ->get();

        $queue = $checkedIn->map(function (RoomBooking $booking) use ($today) {
            $summary = $this->buildBillSummary($booking);

            return array_merge($summary, [
                'booking' => $booking,
                'departure_today' => $booking->check_out_date
                    && $booking->check_out_date->toDateString() === $today,
            ]);
        });

        $walkInPendingBills = $this->walkInPendingPosOrders();

        return view('guest-rooms.checkout-counter.index', compact('departuresToday', 'queue', 'walkInPendingBills'));
    }

    public function settleCafeOrder(Request $request, PosOrder $order): RedirectResponse
    {
        if (! $this->isWalkInCafeDraft($order)) {
            return back()->with('error', 'Yeh bill checkout counter se settle nahi ho sakti.');
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
        ]);

        try {
            $paid = $this->checkoutCounter->settleCafeDraftOrder($order, (string) $data['payment_method']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Payment save nahi ho saki.');
        }

        return redirect()
            ->route('restaurant-pos.receipt', $paid)
            ->with('success', 'Cafe bill collect ho gayi.');
    }

    private function isWalkInCafeDraft(PosOrder $order): bool
    {
        if ($order->status !== 'draft') {
            return false;
        }

        if (in_array($order->customerTypeKey(), ['booking'], true)) {
            return false;
        }

        $roomNo = trim((string) ($order->room_no ?? ''));

        return $roomNo === '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosOrder>
     */
    private function walkInPendingPosOrders(): Collection
    {
        return PosOrder::query()
            ->where('status', 'draft')
            ->where(function ($q) {
                $q->whereNull('room_no')->orWhere('room_no', '');
            })
            ->where(function ($q) {
                $q->where('customer_type', 'mess_use')
                    ->orWhere('customer_type', 'ast_offr')
                    ->orWhereNull('customer_type');
            })
            ->with(['table:id,name'])
            ->withCount('items')
            ->latest('id')
            ->get();
    }

    public function show(RoomBooking $booking): View
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            abort(404);
        }

        $booking->load(['guestRoom', 'assignedRooms', 'category', 'charges']);

        $summary = $this->buildBillSummary($booking, recalculate: true);
        [$checkInMin, $checkoutMax] = $this->bookings->checkoutDateBounds($booking);
        $defaultCheckout = $booking->check_out_date
            ? Carbon::parse($booking->check_out_date)->startOfDay()
            : now()->startOfDay();
        if ($defaultCheckout->gt($checkoutMax)) {
            $defaultCheckout = $checkoutMax->copy();
        }
        if ($defaultCheckout->lt($checkInMin)) {
            $defaultCheckout = $checkInMin->copy();
        }
        if ($defaultCheckout->gt(now()->startOfDay())) {
            $defaultCheckout = now()->startOfDay();
        }

        $lateCheckoutCharge = $booking->charges
            ->first(fn (RoomBookingCharge $charge) => $charge->charge_type === RoomBookingCharge::TYPE_LATE_CHECKOUT);
        $lateCheckoutAmount = $lateCheckoutCharge ? (float) $lateCheckoutCharge->amount : 0.0;
        $lateCheckoutNotes = '';
        if ($lateCheckoutCharge && str_contains((string) $lateCheckoutCharge->description, ' — ')) {
            $lateCheckoutNotes = trim(substr((string) $lateCheckoutCharge->description, strpos((string) $lateCheckoutCharge->description, ' — ') + 3));
        }

        $scheduledCheckout = $booking->check_out_date
            ? Carbon::parse($booking->check_out_date)->startOfDay()
            : null;
        $isLateCheckout = $scheduledCheckout && $scheduledCheckout->lt(now()->startOfDay());

        return view('guest-rooms.checkout-counter.show', [
            'booking' => $booking,
            'pendingPosBills' => $summary['cafe_pending'],
            'paidCafeBills' => $summary['cafe_paid'],
            'allCafeBills' => $summary['cafe_all'],
            'summary' => $summary,
            'checkInMin' => $checkInMin,
            'checkoutMax' => $checkoutMax,
            'defaultCheckout' => $defaultCheckout,
            'lateCheckoutAmount' => $lateCheckoutAmount,
            'lateCheckoutNotes' => $lateCheckoutNotes,
            'isLateCheckout' => $isLateCheckout,
            'scheduledCheckout' => $scheduledCheckout,
        ]);
    }

    public function unpaidBill(Request $request, RoomBooking $booking): View
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            abort(404);
        }

        $bill = $booking->syncRunningBill();
        if (! $bill) {
            abort(404);
        }

        $bill->load(['booking.guestRoom', 'booking.assignedRooms', 'booking.category']);

        $settings = array_merge([
            'company_name' => config('app.name'),
            'company_address' => '',
            'company_phone' => '',
            'currency_symbol' => 'Rs.',
        ], Setting::all_map());

        $breakdown = GuestRoomReceiptBreakdown::forUnpaidCheckout($booking);
        $isUnpaidPreview = true;
        $autoPrint = $request->boolean('print', false) && ! $request->boolean('noprint', false);

        return view('guest-rooms.billing.receipt', compact(
            'bill',
            'settings',
            'autoPrint',
            'breakdown',
            'isUnpaidPreview',
        ));
    }

    public function settle(Request $request, RoomBooking $booking): RedirectResponse
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return back()->with('error', 'Sirf checked-in guest ka checkout ho sakta hai.');
        }

        normalize_request_dates($request, ['checkout_date']);
        [$checkInMin, $checkoutMax] = $this->bookings->checkoutDateBounds($booking);

        $data = $request->validate([
            'checkout_date' => [
                'required',
                'date',
                'after_or_equal:'.$checkInMin->toDateString(),
                'before_or_equal:'.$checkoutMax->toDateString(),
            ],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'bank'])],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'late_checkout_amount' => ['nullable', 'numeric', 'min:0'],
            'late_checkout_notes' => ['nullable', 'string', 'max:255'],
        ]);

        RoomBookingCharge::syncLateCheckout(
            $booking,
            (float) ($data['late_checkout_amount'] ?? 0),
            $data['late_checkout_notes'] ?? null,
        );
        $booking->refresh()->load('charges');

        $booking->discount = (float) ($data['discount'] ?? $booking->discount);
        $booking->tax_percent = (float) ($data['tax_percent'] ?? $booking->tax_percent);
        $summary = $this->buildBillSummary($booking, recalculate: true);
        $totalDue = (float) $summary['total_due_now'];
        $roomDue = (float) $summary['room_balance_due'];

        if ($totalDue > 0 && empty($data['payment_method'])) {
            return back()->with('error', 'Payment method select karein.')->withInput();
        }

        $received = (float) ($data['amount_received'] ?? 0);
        if ($totalDue > 0 && abs($received - $totalDue) > 0.02) {
            return back()->with('error', 'Received amount collect now total ('.fmt_num($totalDue, 2).') ke barabar honi chahiye.')->withInput();
        }

        $checkoutPayload = [
            'checkout_date' => $data['checkout_date'],
            'discount' => (float) ($data['discount'] ?? $booking->discount),
            'tax_percent' => (float) ($data['tax_percent'] ?? $booking->tax_percent),
            'advance_amount' => (float) $booking->paid_amount,
            'amount_received' => $roomDue,
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        try {
            $bill = $this->checkoutCounter->settleCompleteBill($booking, $checkoutPayload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Checkout complete nahi ho saka.')->withInput();
        }

        return redirect()
            ->route('guest-rooms.billing.receipt', ['bill' => $bill, 'print' => 1])
            ->with('success', 'Complete bill collect ho gayi. Room checkout, cafe bills POS par clear, housekeeping queue mein room bhej di gayi.');
    }

    /**
     * @return array{
     *   room_charges: float,
     *   extra_charges: float,
     *   late_checkout_total: float,
     *   discount: float,
     *   tax_amount: float,
     *   room_total: float,
     *   room_paid: float,
     *   room_balance_due: float,
     *   cafe_pending: Collection,
     *   cafe_paid: Collection,
     *   cafe_all: Collection,
     *   cafe_pending_total: float,
     *   cafe_paid_total: float,
     *   cafe_total: float,
     *   cafe_pending_count: int,
     *   complete_bill_total: float,
     *   total_due_now: float,
     * }
     */
    private function buildBillSummary(RoomBooking $booking, bool $recalculate = false): array
    {
        if ($recalculate) {
            $booking->loadMissing('charges');
            $booking->recalculateTotals();
            $booking->load('charges');
        }

        $roomNumbers = $booking->activeRoomNumbers();
        $cafeAll = PosOrder::inHouseCafeOrdersForRoomNumbers($roomNumbers);
        $cafePending = $cafeAll->where('status', 'draft')->values();
        $cafePaid = $cafeAll->where('status', 'paid')->values();

        $roomTotal = (float) $booking->total_amount;
        $roomPaid = (float) $booking->paid_amount;
        $roomBalance = max(0, (float) $booking->balance);
        $cafePendingTotal = (float) $cafePending->sum('grand_total');
        $cafePaidTotal = (float) $cafePaid->sum('grand_total');
        $cafeTotal = round($cafePendingTotal + $cafePaidTotal, 2);
        $lateCheckoutTotal = round((float) $booking->charges
            ->where('charge_type', RoomBookingCharge::TYPE_LATE_CHECKOUT)
            ->sum(fn (RoomBookingCharge $charge) => $charge->calculatedAmount($booking)), 2);

        return [
            'room_charges' => (float) $booking->room_charges,
            'extra_charges' => (float) $booking->extra_charges,
            'late_checkout_total' => $lateCheckoutTotal,
            'discount' => (float) $booking->discount,
            'tax_amount' => (float) $booking->tax_amount,
            'room_total' => $roomTotal,
            'room_paid' => $roomPaid,
            'room_balance_due' => $roomBalance,
            'cafe_pending' => $cafePending,
            'cafe_paid' => $cafePaid,
            'cafe_all' => $cafeAll,
            'cafe_pending_total' => $cafePendingTotal,
            'cafe_paid_total' => $cafePaidTotal,
            'cafe_total' => $cafeTotal,
            'cafe_pending_count' => $cafePending->count(),
            'complete_bill_total' => round($roomTotal + $cafeTotal, 2),
            'total_due_now' => round($roomBalance + $cafePendingTotal, 2),
        ];
    }
}
