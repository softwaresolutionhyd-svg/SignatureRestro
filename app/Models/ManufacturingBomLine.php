<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingBomLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'bom_id',
        'component_product_id',
        'qty',
        'uom',
        'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    /** UOM for qty on this line (null = component base UOM). */
    public function effectiveUom(): string
    {
        $u = $this->uom !== null && trim((string) $this->uom) !== ''
            ? trim((string) $this->uom)
            : null;

        if ($u !== null) {
            return $u;
        }

        $this->loadMissing('component');

        return (string) ($this->component?->uom ?? '');
    }

    /** Component qty consumed per BoM batch, in product base UOM (stock UOM). */
    public function qtyInBasePerBatch(): float
    {
        $this->loadMissing('component');
        $c = $this->component;
        if (!$c) {
            return 0.0;
        }
        $c->loadMissing('uomConversions');
        try {
            return $c->convertQtyToBaseUom((float) $this->qty, $this->effectiveUom());
        } catch (\InvalidArgumentException $e) {
            // Keep BoM screens usable even if one component has missing UOM mapping.
            return 0.0;
        }
    }

    /** Extended material cost for this line per batch (qty in base × component cost per base). */
    public function lineMaterialCostPerBatch(): float
    {
        $this->loadMissing('component');

        return round($this->qtyInBasePerBatch() * (float) ($this->component?->cost ?? 0), 6);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ManufacturingBom::class, 'bom_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'component_product_id');
    }
}
