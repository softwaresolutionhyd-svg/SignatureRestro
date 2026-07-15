<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'uom',
        'qty',
        'unit_price',
        'discount_percent',
        'tax_percent',
        'notes',
        'kitchen_pending',
        'kitchen_served_at',
        'kitchen_printed_at',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'kitchen_pending' => 'bool',
        'kitchen_served_at' => 'datetime',
        'kitchen_printed_at' => 'datetime',
    ];

    public function isKitchenServed(): bool
    {
        return $this->kitchen_served_at !== null;
    }

    public function isKitchenPrinted(): bool
    {
        return $this->kitchen_printed_at !== null;
    }

    /** Sent to kitchen print at least once (or marked pending / served). */
    public function isKitchenLocked(): bool
    {
        if ($this->isKitchenServed() || $this->isKitchenPrinted()) {
            return true;
        }

        return (bool) $this->kitchen_pending;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
