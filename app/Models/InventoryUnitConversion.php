<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryUnitConversion extends Model
{
    protected $connection = 'tenant';

    protected $table = 'inventory_unit_conversions';

    protected $fillable = [
        'from_unit_id',
        'to_unit_id',
        'factor',
        'note',
    ];

    protected $casts = [
        'factor' => 'decimal:12',
    ];

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'to_unit_id');
    }

    /**
     * qty in "to" UOM = qty in "from" UOM × factor (e.g. g → kg: 1000 × 0.001 = 1).
     */
    public function explainFactor(): string
    {
        $from = $this->fromUnit?->code ?? '?';
        $to = $this->toUnit?->code ?? '?';

        return '1 '.$from.' = '.fmt_num($this->factor, 8).' '.$to;
    }
}
