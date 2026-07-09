<?php

namespace App\Services;

use App\Http\Controllers\GuestRoom\BookingController;
use App\Http\Controllers\Pos\PosController;
use App\Models\PosOrder;
use App\Models\RoomBill;
use App\Models\RoomBooking;
use Illuminate\Support\Facades\DB;

final class CheckoutCounterService
{
    public function __construct(
        private readonly PosController $pos,
        private readonly BookingController $bookings,
    ) {}

    /**
     * Pay pending cafe drafts and complete room checkout in one transaction.
     *
     * @param  array<string, mixed>  $checkoutData
     */
    public function settleCompleteBill(RoomBooking $booking, array $checkoutData): RoomBill
    {
        return DB::connection('tenant')->transaction(function () use ($booking, $checkoutData) {
            $paymentMethod = (string) ($checkoutData['payment_method'] ?? 'cash');
            $pending = PosOrder::pendingBookingDraftsForRoomNumbers($booking->activeRoomNumbers());

            foreach ($pending as $draft) {
                $this->pos->settleDraftOrderForCheckoutCounter($draft, $paymentMethod);
            }

            $booking->refresh();

            return $this->bookings->performCheckout($booking, $checkoutData);
        });
    }

    public function settleCafeDraftOrder(PosOrder $order, string $paymentMethod): PosOrder
    {
        return $this->pos->settleDraftOrderForCheckoutCounter($order, $paymentMethod);
    }
}
