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
        'unit_price' => 'decimal:6',
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

    /**
     * Invoice line total is source of truth when $lineTotal is provided.
     * Unit price is stored at 6 dp so qty × unit matches that total.
     *
     * @return array{qty: float, unit_price: float, tax_percent: float, subtotal: float, tax_amount: float, total: float}
     */
    public static function amountsFromInput(float $qty, float $unitPrice, float $taxPercent, ?float $lineTotal): array
    {
        $qty = round($qty, 3);
        $taxPercent = round(max(0.0, $taxPercent), 3);

        if ($lineTotal !== null) {
            $total = round($lineTotal, 2);
            if ($taxPercent > 0) {
                $subtotal = round($total / (1 + ($taxPercent / 100.0)), 2);
                $taxAmount = round($total - $subtotal, 2);
            } else {
                $subtotal = $total;
                $taxAmount = 0.0;
            }
            $unit = $qty > 0 ? round($subtotal / $qty, 6) : 0.0;

            return [
                'qty' => $qty,
                'unit_price' => $unit,
                'tax_percent' => $taxPercent,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ];
        }

        $unit = round($unitPrice, 6);
        $subtotal = round($qty * $unit, 2);
        $taxAmount = round($subtotal * ($taxPercent / 100.0), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'qty' => $qty,
            'unit_price' => $unit,
            'tax_percent' => $taxPercent,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /** Stored invoice amounts — not qty × 2 dp unit. */
    public function computedSubtotal(): float
    {
        return round((float) $this->subtotal, 2);
    }

    public function computedTaxAmount(): float
    {
        return round((float) $this->tax_amount, 2);
    }

    public function computedTotal(): float
    {
        return round((float) $this->total, 2);
    }
}
