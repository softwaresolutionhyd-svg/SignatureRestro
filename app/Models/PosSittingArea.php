<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSittingArea extends Model
{
    protected $connection = 'tenant';

    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'sort_order' => 'int',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(PosTable::class, 'sitting_area_id')->orderBy('name');
    }
}
