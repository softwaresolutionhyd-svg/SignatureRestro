<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'product_id',
        'description',
        'uom',
        'qty',
        'unit_price',
        'tax_percent',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tax_percent' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
