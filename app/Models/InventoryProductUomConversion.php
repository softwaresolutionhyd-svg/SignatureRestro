<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryProductUomConversion extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'uom',
        'factor_to_base',
        'active',
    ];

    protected $casts = [
        'factor_to_base' => 'decimal:6',
        'active' => 'bool',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
