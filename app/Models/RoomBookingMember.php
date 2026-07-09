<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBookingMember extends Model
{
    public const TYPE_ADULT = 'adult';

    public const TYPE_CHILD = 'child';

    protected $connection = 'tenant';

    protected $fillable = [
        'room_booking_id',
        'member_type',
        'sort_order',
        'name',
        'cnic',
        'relation',
    ];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }
}
