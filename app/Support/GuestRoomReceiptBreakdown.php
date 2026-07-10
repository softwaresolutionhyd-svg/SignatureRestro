<?php

namespace App\Support;

use App\Models\PosOrder;
use App\Models\RoomBill;
use App\Models\RoomBooking;
use App\Models\RoomBookingCharge;
use Illuminate\Support\Collection;

final class GuestRoomReceiptBreakdown
{
    /**
     * @return array{
     *   room_rent: float,
     *   mattress_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   mattress_total: float,
     *   laundry_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   laundry_total: float,
     *   other_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   other_total: float,
     *   room_discount: float,
     *   room_tax: float,
     *   room_tax_percent: float,
     *   room_total: float,
     *   cafe_orders: list<array{order_no: string, room_no: string, items: list<array{name: string, qty: string, amount: float}>, total: float}>,
     *   cafe_total: float,
     *   grand_total: float,
     *   paid_total: float,
     *   balance_due: float,
     * }
     */
    public static function forBill(RoomBill $bill): array
    {
        $bill->loadMissing([
            'booking.charges',
            'booking.assignedRooms',
            'booking.guestRoom',
        ]);

        $booking = $bill->booking;
        $chargeBreakdown = $booking ? static::chargeBreakdown($booking) : static::emptyChargeBreakdown();

        $cafeOrders = collect();
        if ($booking) {
            $cafeOrders = static::cafeOrdersWithItems($booking->activeRoomNumbers(), ['paid']);
        }

        return static::assembleBreakdown(
            roomRent: round((float) $bill->room_charges, 2),
            chargeBreakdown: $chargeBreakdown,
            roomDiscount: round((float) $bill->discount, 2),
            roomTax: round((float) $bill->tax_amount, 2),
            roomTaxPercent: $booking ? (float) $booking->tax_percent : 0.0,
            roomTotal: round((float) $bill->total, 2),
            roomPaid: round((float) $bill->paid_amount, 2),
            cafeOrders: $cafeOrders,
            isUnpaidPreview: false,
        );
    }

    /**
     * Complete bill preview before checkout (room + paid & pending cafe).
     *
     * @return array<string, mixed>
     */
    public static function forUnpaidCheckout(RoomBooking $booking): array
    {
        $booking->loadMissing(['charges', 'assignedRooms', 'guestRoom']);
        $booking->recalculateTotals();

        $chargeBreakdown = static::chargeBreakdown($booking);
        $cafeOrders = static::cafeOrdersWithItems($booking->activeRoomNumbers(), ['draft', 'paid']);

        return static::assembleBreakdown(
            roomRent: round((float) $booking->room_charges, 2),
            chargeBreakdown: $chargeBreakdown,
            roomDiscount: round((float) $booking->discount, 2),
            roomTax: round((float) $booking->tax_amount, 2),
            roomTaxPercent: (float) $booking->tax_percent,
            roomTotal: round((float) $booking->total_amount, 2),
            roomPaid: round((float) $booking->paid_amount, 2),
            cafeOrders: $cafeOrders,
            isUnpaidPreview: true,
        );
    }

    /**
     * @return array{
     *   mattress_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   mattress_total: float,
     *   laundry_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   laundry_total: float,
     *   other_lines: list<array{label: string, amount: float, detail: ?string}>,
     *   other_total: float,
     * }
     */
    private static function chargeBreakdown(RoomBooking $booking): array
    {
        $mattressLines = [];
        $laundryLines = [];
        $lateCheckoutLines = [];
        $otherLines = [];
        $mattressTotal = 0.0;
        $laundryTotal = 0.0;
        $lateCheckoutTotal = 0.0;
        $otherTotal = 0.0;

        foreach ($booking->charges as $charge) {
            $amount = (float) $charge->calculatedAmount($booking);
            $line = [
                'label' => trim((string) $charge->description) !== ''
                    ? (string) $charge->description
                    : (RoomBookingCharge::allChargeTypes()[$charge->charge_type] ?? 'Extra charge'),
                'amount' => $amount,
                'detail' => $charge->amountBreakdownLabel($booking),
            ];

            if ($charge->charge_type === RoomBookingCharge::TYPE_MATTRESS || $charge->isDailyMattress()) {
                $mattressLines[] = $line;
                $mattressTotal += $amount;
            } elseif ($charge->charge_type === RoomBookingCharge::TYPE_LAUNDRY) {
                $laundryLines[] = $line;
                $laundryTotal += $amount;
            } elseif ($charge->charge_type === RoomBookingCharge::TYPE_LATE_CHECKOUT) {
                $lateCheckoutLines[] = $line;
                $lateCheckoutTotal += $amount;
            } else {
                $otherLines[] = $line;
                $otherTotal += $amount;
            }
        }

        return [
            'mattress_lines' => $mattressLines,
            'mattress_total' => round($mattressTotal, 2),
            'laundry_lines' => $laundryLines,
            'laundry_total' => round($laundryTotal, 2),
            'late_checkout_lines' => $lateCheckoutLines,
            'late_checkout_total' => round($lateCheckoutTotal, 2),
            'other_lines' => $otherLines,
            'other_total' => round($otherTotal, 2),
        ];
    }

    /** @return array{mattress_lines: array, mattress_total: float, laundry_lines: array, laundry_total: float, late_checkout_lines: array, late_checkout_total: float, other_lines: array, other_total: float} */
    private static function emptyChargeBreakdown(): array
    {
        return [
            'mattress_lines' => [],
            'mattress_total' => 0.0,
            'laundry_lines' => [],
            'laundry_total' => 0.0,
            'late_checkout_lines' => [],
            'late_checkout_total' => 0.0,
            'other_lines' => [],
            'other_total' => 0.0,
        ];
    }

    /**
     * @param  array{mattress_lines: array, mattress_total: float, laundry_lines: array, laundry_total: float, other_lines: array, other_total: float}  $chargeBreakdown
     * @param  Collection<int, PosOrder>  $cafeOrders
     * @return array<string, mixed>
     */
    private static function assembleBreakdown(
        float $roomRent,
        array $chargeBreakdown,
        float $roomDiscount,
        float $roomTax,
        float $roomTaxPercent,
        float $roomTotal,
        float $roomPaid,
        Collection $cafeOrders,
        bool $isUnpaidPreview,
    ): array {
        $cafePaidOrders = $cafeOrders->where('status', 'paid');
        $cafePendingOrders = $cafeOrders->where('status', 'draft');

        $cafeTotal = round((float) $cafeOrders->sum('grand_total'), 2);
        $cafePaidTotal = round((float) $cafePaidOrders->sum('grand_total'), 2);
        $cafePendingTotal = round((float) $cafePendingOrders->sum('grand_total'), 2);
        $cafePaid = round((float) $cafePaidOrders->sum(fn (PosOrder $order) => (float) $order->payments->sum('amount')), 2);
        if ($cafePaid <= 0 && $cafePaidTotal > 0) {
            $cafePaid = $cafePaidTotal;
        }

        $grandTotal = round($roomTotal + $cafeTotal, 2);
        $roomBalanceDue = max(0, round($roomTotal - $roomPaid, 2));
        $paidTotal = round($roomPaid + $cafePaid, 2);
        $balanceDue = $isUnpaidPreview
            ? round($roomBalanceDue + $cafePendingTotal, 2)
            : max(0, round($grandTotal - $paidTotal, 2));

        return [
            'is_unpaid_preview' => $isUnpaidPreview,
            'room_rent' => $roomRent,
            'mattress_lines' => $chargeBreakdown['mattress_lines'],
            'mattress_total' => $chargeBreakdown['mattress_total'],
            'laundry_lines' => $chargeBreakdown['laundry_lines'],
            'laundry_total' => $chargeBreakdown['laundry_total'],
            'late_checkout_lines' => $chargeBreakdown['late_checkout_lines'],
            'late_checkout_total' => $chargeBreakdown['late_checkout_total'],
            'other_lines' => $chargeBreakdown['other_lines'],
            'other_total' => $chargeBreakdown['other_total'],
            'room_discount' => $roomDiscount,
            'room_tax' => $roomTax,
            'room_tax_percent' => $roomTaxPercent,
            'room_total' => $roomTotal,
            'room_paid' => $roomPaid,
            'room_balance_due' => $roomBalanceDue,
            'cafe_orders' => $cafeOrders->map(function (PosOrder $order) {
                return [
                    'order_no' => (string) $order->order_no,
                    'room_no' => (string) ($order->room_no ?? ''),
                    'status' => (string) $order->status,
                    'items' => $order->items->map(function ($item) {
                        $name = trim((string) ($item->product?->name ?? 'Item'));

                        return [
                            'name' => $name,
                            'qty' => fmt_num((float) $item->qty, 0),
                            'amount' => round((float) $item->total, 2),
                        ];
                    })->values()->all(),
                    'total' => round((float) $order->grand_total, 2),
                ];
            })->values()->all(),
            'cafe_total' => $cafeTotal,
            'cafe_paid_total' => $cafePaidTotal,
            'cafe_pending_total' => $cafePendingTotal,
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * @param  list<string>  $roomNumbers
     * @param  list<string>  $statuses
     * @return Collection<int, PosOrder>
     */
    private static function cafeOrdersWithItems(array $roomNumbers, array $statuses): Collection
    {
        if ($roomNumbers === []) {
            return collect();
        }

        return PosOrder::inHouseCafeOrdersForRoomNumbers($roomNumbers, $statuses)
            ->load(['items.product:id,name', 'payments'])
            ->sortBy(fn (PosOrder $order) => [
                $order->status === 'draft' ? 0 : 1,
                $order->created_at?->timestamp ?? 0,
            ])
            ->values();
    }
}
