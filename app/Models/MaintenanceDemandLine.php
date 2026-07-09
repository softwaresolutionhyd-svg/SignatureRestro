<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceDemandLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'demand_id',
        'product_id',
        'item_name',
        'is_custom',
        'line_location',
        'line_category',
        'qty_uom',
        'uom',
        'qty_base',
        'expected_rate',
        'expected_total',
        'received_qty_uom',
        'received_qty_base',
        'actual_rate',
        'actual_total',
    ];

    protected $casts = [
        'qty_uom' => 'decimal:3',
        'qty_base' => 'decimal:3',
        'is_custom' => 'bool',
        'expected_rate' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'received_qty_uom' => 'decimal:3',
        'received_qty_base' => 'decimal:3',
        'actual_rate' => 'decimal:2',
        'actual_total' => 'decimal:2',
    ];

    public function demand(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDemand::class, 'demand_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function displayName(): string
    {
        $custom = trim((string) ($this->item_name ?? ''));
        if ($this->is_custom && $custom !== '') {
            return $custom;
        }

        return (string) ($this->product?->name ?? $custom ?: '—');
    }

    public function locationLabel(): string
    {
        $value = trim((string) ($this->line_location ?? ''));
        return $value !== '' ? $value : '—';
    }

    public function categoryLabel(): string
    {
        $value = trim((string) ($this->line_category ?? ''));
        return $value !== '' ? $value : '—';
    }
}

