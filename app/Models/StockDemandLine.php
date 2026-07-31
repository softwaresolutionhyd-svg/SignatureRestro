<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDemandLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'demand_id',
        'product_id',
        'qty_uom',
        'uom',
        'qty_base',
        'issued_qty_base',
        'status',
        'issued_at',
        'issued_by',
    ];

    protected $casts = [
        'qty_uom' => 'decimal:3',
        'qty_base' => 'decimal:3',
        'issued_qty_base' => 'decimal:3',
        'issued_at' => 'datetime',
    ];

    public function demand(): BelongsTo
    {
        return $this->belongsTo(StockDemand::class, 'demand_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function remainingQtyBase(): float
    {
        return max(0.0, round((float) $this->qty_base - (float) $this->issued_qty_base, 3));
    }

    public function isFullyIssued(): bool
    {
        return $this->remainingQtyBase() <= 0.0005;
    }
}
