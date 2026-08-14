<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuDealItem extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'deal_id',
        'product_id',
        'qty',
        'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(MenuDeal::class, 'deal_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
