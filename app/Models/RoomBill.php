<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBill extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    protected $fillable = [
        'company_id', 'room_booking_id', 'bill_no', 'room_charges', 'extra_charges',
        'discount', 'tax_amount', 'total', 'paid_amount', 'balance',
        'payment_method', 'payment_status', 'billed_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'room_charges' => 'float',
        'extra_charges' => 'float',
        'discount' => 'float',
        'tax_amount' => 'float',
        'total' => 'float',
        'paid_amount' => 'float',
        'balance' => 'float',
        'billed_at' => 'datetime',
    ];

    public static function generateBillNo(): string
    {
        $prefix = 'RB-'.now()->format('Ymd').'-';
        $last = static::query()
            ->where('bill_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('bill_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }
}
