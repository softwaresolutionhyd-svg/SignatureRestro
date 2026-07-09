<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceDemand extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'requested_by',
        'qty_uom',
        'uom',
        'qty_base',
        'status',
        'demand_date',
        'needed_date',
        'location',
        'demand_category',
        'note',
        'created_by',
    ];

    protected $casts = [
        'qty_uom' => 'decimal:3',
        'qty_base' => 'decimal:3',
        'demand_date' => 'date',
        'needed_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaintenanceDemandLine::class, 'demand_id');
    }
}

