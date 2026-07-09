<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryProductStock extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'department_id',
        'qty_on_hand',
    ];

    protected $casts = [
        'qty_on_hand' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(InventoryDepartment::class, 'department_id');
    }
}
