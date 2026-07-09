<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\ProductCosting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingBom extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'finished_product_id',
        'name',
        'batch_qty',
        'active',
        'notes',
    ];

    protected $casts = [
        'batch_qty' => 'decimal:3',
        'active' => 'bool',
    ];

    public function finishedProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'finished_product_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ManufacturingBomLine::class, 'bom_id')->orderBy('sort_order');
    }

    /** Sum of component costs for one batch (uses each component’s cost per base UOM). */
    public function materialCostPerBatch(): float
    {
        $this->loadMissing('lines.component.uomConversions');
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $line->lineMaterialCostPerBatch();
        }

        return round($total, 6);
    }

    /** Standard cost for one unit of finished output (material only). */
    public function standardCostPerFinishedUnit(): float
    {
        $batch = (float) $this->batch_qty;
        if ($batch <= 0) {
            return 0.0;
        }

        return round($this->materialCostPerBatch() / $batch, 6);
    }

    /** Writes finished product cost + selling price from BoM rollup and settings costing rules. */
    public function syncFinishedProductStandardCost(): void
    {
        if (!$this->active) {
            return;
        }

        $this->loadMissing('lines.component.uomConversions');
        if ($this->lines->isEmpty()) {
            return;
        }

        $finishedId = (int) $this->finished_product_id;
        if ($finishedId <= 0) {
            return;
        }

        $finished = InventoryProduct::query()->find($finishedId);
        if (!$finished) {
            return;
        }

        $newCost = $this->standardCostPerFinishedUnit();
        if (! ProductCosting::applyRecipeCostToProduct($finished, $newCost)) {
            return;
        }

        $finished->save();
    }

    /**
     * Re-apply BoM standard cost to every finished product whose BoM lists this item as a component
     * (e.g. after FIFO refreshed the component’s unit cost).
     */
    public static function syncFinishedProductsUsingComponent(int $componentProductId): void
    {
        if ($componentProductId <= 0) {
            return;
        }

        $bomIds = ManufacturingBomLine::query()
            ->where('component_product_id', $componentProductId)
            ->distinct()
            ->pluck('bom_id');

        foreach ($bomIds as $bomId) {
            $bom = static::query()->find($bomId);
            if ($bom && $bom->active) {
                $bom->syncFinishedProductStandardCost();
            }
        }
    }
}
