<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceIssue extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'issued_to',
        'issued_location',
        'qty_uom',
        'uom',
        'qty_base',
        'reference',
        'note',
        'issued_by',
    ];

    protected $casts = [
        'qty_uom' => 'decimal:3',
        'qty_base' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

