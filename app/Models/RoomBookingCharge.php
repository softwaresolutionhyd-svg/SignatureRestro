<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBookingCharge extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    public const TYPE_MATTRESS = 'mattress';

    public const TYPE_LAUNDRY = 'laundry';

    public const TYPE_OTHER = 'other';

    public const TYPE_LATE_CHECKOUT = 'late_checkout';

    /** @return array<string, string> */
    public static function damageChargeTypes(): array
    {
        return [
            self::TYPE_MATTRESS => 'Mattress Charges',
            self::TYPE_LAUNDRY => 'Laundry Charges',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function checkoutChargeTypes(): array
    {
        return [
            self::TYPE_LATE_CHECKOUT => 'Late Checkout Charges',
        ];
    }

    /** @return array<string, string> */
    public static function allChargeTypes(): array
    {
        return array_merge(self::damageChargeTypes(), self::checkoutChargeTypes());
    }

    public static function syncLateCheckout(RoomBooking $booking, float $amount, ?string $notes = null): void
    {
        $existing = $booking->charges()
            ->where('charge_type', self::TYPE_LATE_CHECKOUT)
            ->first();

        if ($amount <= 0) {
            $existing?->delete();

            return;
        }

        $description = self::checkoutChargeTypes()[self::TYPE_LATE_CHECKOUT];
        $notes = trim((string) $notes);
        if ($notes !== '') {
            $description .= ' — '.$notes;
        }

        if ($existing) {
            $existing->update([
                'description' => $description,
                'amount' => round($amount, 2),
                'charge_date' => now()->toDateString(),
            ]);

            return;
        }

        self::query()->create([
            'room_booking_id' => $booking->id,
            'charge_type' => self::TYPE_LATE_CHECKOUT,
            'description' => $description,
            'amount' => round($amount, 2),
            'charge_date' => now()->toDateString(),
        ]);
    }

    protected $fillable = [
        'company_id', 'room_booking_id', 'charge_type', 'description', 'amount', 'unit_amount', 'charge_date',
    ];

    protected $casts = [
        'amount' => 'float',
        'unit_amount' => 'float',
        'charge_date' => 'date',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }

    public function isDailyMattress(): bool
    {
        if ($this->charge_type === self::TYPE_MATTRESS) {
            return true;
        }

        return $this->charge_type === null
            && str_starts_with(strtolower(trim((string) $this->description)), 'mattress');
    }

    public function dailyUnitAmount(): float
    {
        if ($this->unit_amount !== null && (float) $this->unit_amount > 0) {
            return (float) $this->unit_amount;
        }

        return (float) $this->amount;
    }

    /** Inclusive days from issue date through stay end (today while checked in, checkout date when checked out). */
    public function billableDays(RoomBooking $booking): int
    {
        $issue = Carbon::parse($this->charge_date ?? $booking->stayCheckInAt())->startOfDay();
        $end = $booking->mattressChargeThroughDate()->startOfDay();

        if ($end->lt($issue)) {
            return 0;
        }

        return (int) $issue->diffInDays($end) + 1;
    }

    public function calculatedAmount(RoomBooking $booking): float
    {
        if (! $this->isDailyMattress()) {
            return round((float) $this->amount, 2);
        }

        $days = max(1, $this->billableDays($booking));

        return round($this->dailyUnitAmount() * $days, 2);
    }

    public function syncCalculatedAmount(RoomBooking $booking): void
    {
        $computed = $this->calculatedAmount($booking);
        if ((float) $this->amount !== $computed) {
            $this->amount = $computed;
            $this->saveQuietly();
        }
    }

    public function amountBreakdownLabel(RoomBooking $booking): ?string
    {
        if (! $this->isDailyMattress()) {
            return null;
        }

        $days = $this->billableDays($booking);

        return number_format($this->dailyUnitAmount(), 2).'/day × '.$days.' day'.($days === 1 ? '' : 's');
    }
}
