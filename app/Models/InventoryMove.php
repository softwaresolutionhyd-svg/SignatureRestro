<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMove extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'from_department_id',
        'to_department_id',
        'user_id',
        'type',
        'qty',
        'uom',
        'qty_uom',
        'factor_to_base',
        'unit_cost',
        'total_cost',
        'qty_before',
        'qty_after',
        'reference',
        'note',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'qty_uom' => 'decimal:3',
        'qty_before' => 'decimal:3',
        'qty_after' => 'decimal:3',
        'factor_to_base' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(InventoryDepartment::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(InventoryDepartment::class, 'to_department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
