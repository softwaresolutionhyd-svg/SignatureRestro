<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryUnit extends Model
{
    protected $connection = 'tenant';

    protected $table = 'inventory_units';

    protected $fillable = [
        'code',
        'name',
    ];

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(InventoryUnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(InventoryUnitConversion::class, 'to_unit_id');
    }

    public static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryUnit $unit) {
            $unit->code = self::normalizeCode($unit->code);
        });
    }
}
