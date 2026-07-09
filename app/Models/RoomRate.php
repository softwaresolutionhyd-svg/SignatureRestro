<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    protected $fillable = [
        'company_id', 'room_category_id', 'room_type_id', 'person_type',
        'room_rent', 'electric_charges', 'gas_charges', 'media_charges', 'total',
        'name', 'rate_type', 'amount', 'valid_from', 'valid_until', 'is_default', 'active',
    ];

    protected $casts = [
        'room_rent' => 'float',
        'electric_charges' => 'float',
        'gas_charges' => 'float',
        'media_charges' => 'float',
        'total' => 'float',
        'amount' => 'float',
        'is_default' => 'bool',
        'active' => 'bool',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (RoomRate $rate) {
            $rate->syncChargeTotals();
        });
    }

    public function syncChargeTotals(): void
    {
        $this->total = round(
            (float) $this->room_rent
            + (float) $this->electric_charges
            + (float) $this->gas_charges
            + (float) $this->media_charges,
            2
        );
        $this->amount = $this->total;
        $this->name = $this->person_type ?? $this->name;
        $this->rate_type = $this->rate_type ?: 'nightly';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public static function findForBooking(?int $categoryId, ?string $personType): ?self
    {
        if (! $categoryId || ! $personType) {
            return null;
        }

        return static::query()
            ->where('room_category_id', $categoryId)
            ->where('person_type', $personType)
            ->where('active', true)
            ->first();
    }
}
