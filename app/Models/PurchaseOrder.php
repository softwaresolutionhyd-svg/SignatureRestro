<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'number',
        'vendor_id',
        'created_by',
        'status',
        'purchase_type',
        'payment_status',
        'order_date',
        'expected_date',
        'subtotal',
        'tax_total',
        'grand_total',
        'confirmed_at',
        'received_at',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'received_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(PurchaseVendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    /** Header totals follow SUM(line.total). */
    public function computedSubtotal(): float
    {
        return round((float) $this->subtotal, 2);
    }

    public function computedTaxTotal(): float
    {
        return round((float) $this->tax_total, 2);
    }

    public function computedGrandTotal(): float
    {
        return round((float) $this->grand_total, 2);
    }

    public function isLineEditable(): bool
    {
        return in_array((string) $this->status, ['rfq', 'confirmed'], true);
    }
}
