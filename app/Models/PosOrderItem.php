<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'product_id',
        'item_name',
        'is_custom',
        'uom',
        'qty',
        'unit_price',
        'discount_percent',
        'tax_percent',
        'notes',
        'kitchen_pending',
        'kitchen_served_at',
        'kitchen_printed_at',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'is_custom' => 'bool',
        'kitchen_pending' => 'bool',
        'kitchen_served_at' => 'datetime',
        'kitchen_printed_at' => 'datetime',
    ];

    public function displayName(): string
    {
        $custom = trim((string) ($this->item_name ?? ''));
        if ($this->is_custom && $custom !== '') {
            return $custom;
        }

        return (string) ($this->product?->name ?? ($custom !== '' ? $custom : 'Item'));
    }

    public function isKitchenServed(): bool
    {
        return $this->kitchen_served_at !== null;
    }

    public function isKitchenPrinted(): bool
    {
        return $this->kitchen_printed_at !== null;
    }

    /** Sent to kitchen print at least once (or marked pending / served). */
    public function isKitchenLocked(): bool
    {
        if ($this->isKitchenServed() || $this->isKitchenPrinted()) {
            return true;
        }

        return (bool) $this->kitchen_pending;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    /**
     * Top-selling lines for reports: inventory products by product_id,
     * On Demand customs grouped by item_name.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $orderConstraint
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function topSellingGrouped(callable $orderConstraint, int $limit = 10)
    {
        $query = static::query()->whereHas('order', $orderConstraint);

        $model = new static;
        $schema = \Illuminate\Support\Facades\Schema::connection($model->getConnectionName());

        if ($schema->hasColumn($model->getTable(), 'is_custom')) {
            $rows = $query
                ->selectRaw("
                    CASE WHEN COALESCE(is_custom, 0) = 1
                        THEN CONCAT('c:', COALESCE(item_name, ''))
                        ELSE CONCAT('p:', product_id)
                    END as grp_key,
                    MAX(product_id) as product_id,
                    MAX(CASE WHEN COALESCE(is_custom, 0) = 1 THEN 1 ELSE 0 END) as is_custom,
                    MAX(item_name) as item_name,
                    SUM(qty) as total_qty,
                    SUM(total) as total_revenue
                ")
                ->groupBy('grp_key')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get();
        } else {
            $rows = $query
                ->with('product')
                ->selectRaw('product_id, SUM(qty) as total_qty, SUM(total) as total_revenue')
                ->groupBy('product_id')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get();
        }

        $productIds = $rows
            ->filter(fn ($row) => ! (bool) ($row->is_custom ?? false))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $products = $productIds === []
            ? collect()
            : InventoryProduct::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($products) {
            if ((bool) ($row->is_custom ?? false)) {
                $name = trim((string) ($row->item_name ?? ''));
                $row->setAttribute('display_name', $name !== '' ? 'On Demand: '.$name : 'On Demand');
                $row->setRelation('product', null);
            } else {
                $product = $products->get((int) $row->product_id);
                $row->setRelation('product', $product);
                $row->setAttribute('display_name', (string) ($product?->name ?? '—'));
            }

            return $row;
        });
    }
}
