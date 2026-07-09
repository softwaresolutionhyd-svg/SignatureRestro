<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    protected $fillable = [
        'company_id', 'room_category_id', 'name', 'code', 'max_occupancy',
        'bed_count', 'description', 'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'max_occupancy' => 'int',
        'bed_count' => 'int',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function guestRooms(): HasMany
    {
        return $this->hasMany(GuestRoom::class, 'room_type_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class, 'room_type_id');
    }

    public function defaultRate(): ?RoomRate
    {
        return $this->rates()->where('active', true)->where('is_default', true)->first()
            ?? $this->rates()->where('active', true)->orderByDesc('id')->first();
    }
}
