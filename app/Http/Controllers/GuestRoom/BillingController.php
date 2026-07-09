<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\RoomBill;
use App\Models\Setting;
use App\Support\GuestRoomReceiptBreakdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $q = RoomBill::query()->with(['booking:id,booking_no,guest_name,guest_room_id', 'booking.guestRoom:id,room_number', 'booking.assignedRooms:id,room_number']);

        if ($request->filled('payment_status')) {
            $q->where('payment_status', $request->string('payment_status'));
        }

        $bills = $q->latest('id')->paginate(25)->withQueryString();

        return view('guest-rooms.billing.index', compact('bills'));
    }

    public function show(RoomBill $bill)
    {
        $bill->load(['booking.guestRoom', 'booking.assignedRooms', 'booking.category', 'booking.charges']);

        return view('guest-rooms.billing.show', compact('bill'));
    }

    public function pay(Request $request, RoomBill $bill)
    {
        $data = $request->validate([
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank'],
        ]);

        $advance = (float) ($data['advance_amount'] ?? $bill->paid_amount);
        $amount = (float) $data['amount_received'];
        $balanceDue = max(0, (float) $bill->total - $advance);

        if ($amount > $balanceDue + 0.009) {
            return back()->with('error', 'Received amount cannot exceed balance.');
        }

        if ($amount <= 0) {
            return back()->with('error', 'Enter the balance amount to receive.');
        }

        DB::connection('tenant')->transaction(function () use ($bill, $data, $amount) {
            $newPaid = $advance + $amount;
            $balance = max(0, (float) $bill->total - $newPaid);
            $status = $balance <= 0 ? 'paid' : 'partial';

            $bill->update([
                'paid_amount' => $newPaid,
                'balance' => $balance,
                'payment_method' => $data['payment_method'],
                'payment_status' => $status,
            ]);

            $booking = $bill->booking;
            if ($booking) {
                $booking->update([
                    'paid_amount' => $newPaid,
                    'balance' => $balance,
                ]);
            }
        });

        $bill->refresh();

        if ($request->boolean('print')) {
            return redirect()
                ->route('guest-rooms.billing.receipt', ['bill' => $bill, 'print' => 1])
                ->with('success', 'Payment recorded successfully.');
        }

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function receipt(Request $request, RoomBill $bill): View
    {
        $bill->load([
            'booking.guestRoom',
            'booking.assignedRooms',
            'booking.category',
            'booking.charges',
        ]);

        $settings = array_merge([
            'company_name' => config('app.name'),
            'company_address' => '',
            'company_phone' => '',
            'currency_symbol' => 'Rs.',
        ], Setting::all_map());

        $autoPrint = $request->boolean('print', false) && ! $request->boolean('noprint', false);
        $breakdown = GuestRoomReceiptBreakdown::forBill($bill);
        $isUnpaidPreview = false;

        return view('guest-rooms.billing.receipt', compact('bill', 'settings', 'autoPrint', 'breakdown', 'isUnpaidPreview'));
    }
}
