<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTable extends Model
{
    protected $connection = 'tenant';

    use HasFactory;

    protected $fillable = [
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'table_id');
    }
}
