<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCheckLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'stock_check_id',
        'product_id',
        'expected_qty',
        'counted_qty',
        'note',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:6',
        'counted_qty' => 'decimal:6',
    ];

    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(StockCheck::class, 'stock_check_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
