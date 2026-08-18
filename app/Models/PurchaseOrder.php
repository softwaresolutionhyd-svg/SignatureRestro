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
        'discount_mode',
        'discount_value',
        'discount_total',
        'tax_mode',
        'tax_value',
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
        'discount_value' => 'decimal:3',
        'discount_total' => 'decimal:2',
        'tax_value' => 'decimal:3',
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

    public static function normalizeAdjustMode(?string $mode): string
    {
        return $mode === 'amount' ? 'amount' : 'percent';
    }

    /**
     * Bill-level discount then tax on (subtotal − discount).
     *
     * @return array{
     *   subtotal: float,
     *   discount_mode: string,
     *   discount_value: float,
     *   discount_total: float,
     *   tax_mode: string,
     *   tax_value: float,
     *   tax_total: float,
     *   grand_total: float
     * }
     */
    public static function computeBillTotals(
        float $goodsSubtotal,
        ?string $discountMode,
        float $discountValue,
        ?string $taxMode,
        float $taxValue
    ): array {
        $subtotal = round(max(0.0, $goodsSubtotal), 2);
        $discountMode = self::normalizeAdjustMode($discountMode);
        $taxMode = self::normalizeAdjustMode($taxMode);
        $discountValue = max(0.0, $discountValue);
        $taxValue = max(0.0, $taxValue);

        if ($discountMode === 'percent') {
            $discountTotal = round($subtotal * (min($discountValue, 100.0) / 100.0), 2);
        } else {
            $discountTotal = round(min($discountValue, $subtotal), 2);
        }

        $afterDiscount = round(max(0.0, $subtotal - $discountTotal), 2);

        if ($taxMode === 'percent') {
            $taxTotal = round($afterDiscount * (min($taxValue, 100.0) / 100.0), 2);
        } else {
            $taxTotal = round($taxValue, 2);
        }

        return [
            'subtotal' => $subtotal,
            'discount_mode' => $discountMode,
            'discount_value' => round($discountValue, 3),
            'discount_total' => $discountTotal,
            'tax_mode' => $taxMode,
            'tax_value' => round($taxValue, 3),
            'tax_total' => $taxTotal,
            'grand_total' => round($afterDiscount + $taxTotal, 2),
        ];
    }

    /** Header totals follow SUM(line.total) then bill discount + tax. */
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
