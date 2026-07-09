<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBookingVehicle extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'room_booking_id',
        'sort_order',
        'vehicle_no',
        'driver_accompanying',
        'driver_name',
        'driver_cnic',
        'driver_phone',
    ];

    protected $casts = [
        'sort_order' => 'int',
        'driver_accompanying' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }
}
