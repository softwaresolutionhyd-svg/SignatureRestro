<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBookingGuestChange extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    protected $fillable = [
        'company_id',
        'room_booking_id',
        'field',
        'field_label',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function displayOldValue(): string
    {
        return self::formatValue($this->old_value);
    }

    public function displayNewValue(): string
    {
        return self::formatValue($this->new_value);
    }

    public static function formatValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $value;
    }
}
