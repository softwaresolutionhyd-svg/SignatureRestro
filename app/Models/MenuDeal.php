<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\MenuCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MenuDeal extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'name',
        'sku',
        'price',
        'is_permanent',
        'starts_at',
        'ends_at',
        'active',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_permanent' => 'bool',
        'active' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** @var array<int, self>|null */
    private static ?array $byProductCache = null;

    public function items(): HasMany
    {
        return $this->hasMany(MenuDealItem::class, 'deal_id')->orderBy('sort_order')->orderBy('id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function isOnSale(?Carbon $at = null): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->is_permanent) {
            return true;
        }

        $at ??= now();
        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $at->gt($this->ends_at)) {
            return false;
        }

        return $this->starts_at !== null || $this->ends_at !== null;
    }

    public function durationLabel(): string
    {
        if ($this->is_permanent) {
            return 'Permanent';
        }

        $from = $this->starts_at?->format('d M Y') ?? '—';
        $to = $this->ends_at?->format('d M Y') ?? '—';

        return $from.' → '.$to;
    }

    public static function ensureSchema(): void
    {
        try {
            $schema = Schema::connection('tenant');
        } catch (\Throwable $e) {
            return;
        }

        if (! $schema->hasTable('inventory_products')) {
            return;
        }

        if (! $schema->hasTable('menu_deals')) {
            try {
                $schema->create('menu_deals', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('company_id')->index();
                    $table->unsignedBigInteger('product_id')->nullable()->index();
                    $table->string('name');
                    $table->string('sku', 40)->nullable();
                    $table->decimal('price', 12, 2)->default(0);
                    $table->boolean('is_permanent')->default(true);
                    $table->timestamp('starts_at')->nullable();
                    $table->timestamp('ends_at')->nullable();
                    $table->boolean('active')->default(true)->index();
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                    $table->timestamps();
                });
            } catch (\Throwable $e) {
                report($e);

                return;
            }
        }

        if (! $schema->hasTable('menu_deal_items')) {
            try {
                $schema->create('menu_deal_items', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('company_id')->index();
                    $table->unsignedBigInteger('deal_id')->index();
                    $table->unsignedBigInteger('product_id')->index();
                    $table->decimal('qty', 12, 3)->default(1);
                    $table->unsignedSmallInteger('sort_order')->default(0);
                    $table->timestamps();
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public static function flushProductCache(): void
    {
        self::$byProductCache = null;
    }

    public static function forPosProduct(int $productId): ?self
    {
        if ($productId <= 0) {
            return null;
        }

        self::ensureSchema();
        if (! Schema::connection('tenant')->hasTable('menu_deals')) {
            return null;
        }

        if (self::$byProductCache === null) {
            self::$byProductCache = [];
            foreach (self::query()->with([
                'items.product:id,sku,name,uom,cost,price,department_id,for_purchase,for_pos',
                'items.product.departments:id,name,printer_ip,printer_port',
                'items.product.department:id,name,printer_ip,printer_port',
            ])->get() as $deal) {
                $pid = (int) ($deal->product_id ?? 0);
                if ($pid > 0) {
                    self::$byProductCache[$pid] = $deal;
                }
            }
        }

        return self::$byProductCache[$productId] ?? null;
    }

    /**
     * POS product ids that must stay hidden (inactive / expired deals).
     *
     * @return list<int>
     */
    public static function hiddenPosProductIds(): array
    {
        self::ensureSchema();
        if (! Schema::connection('tenant')->hasTable('menu_deals')) {
            return [];
        }

        $now = now();
        $ids = [];
        foreach (self::query()->whereNotNull('product_id')->get() as $deal) {
            if (! $deal->isOnSale($now)) {
                $ids[] = (int) $deal->product_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InventoryProduct>  $products
     * @param  list<int>  $keepIds
     * @return \Illuminate\Support\Collection<int, InventoryProduct>
     */
    public static function rejectHiddenFrom(\Illuminate\Support\Collection $products, array $keepIds = []): \Illuminate\Support\Collection
    {
        $hidden = self::hiddenPosProductIds();
        if ($hidden === []) {
            return $products;
        }

        $keep = array_flip(array_map('intval', $keepIds));

        return $products->reject(function ($p) use ($hidden, $keep) {
            $id = (int) $p->id;
            if (isset($keep[$id])) {
                return false;
            }

            return in_array($id, $hidden, true);
        })->values();
    }

    /**
     * @return list<array{source: PosOrderItem, product: ?InventoryProduct, print: object}>
     */
    public static function kitchenPrintRowsFor(PosOrderItem $item): array
    {
        $deal = self::forPosProduct((int) $item->product_id);
        if ($deal === null || $deal->items->isEmpty()) {
            return [[
                'source' => $item,
                'product' => $item->product,
                'print' => $item,
            ]];
        }

        $rows = [];
        foreach ($deal->items as $line) {
            $component = $line->product;
            $qty = round((float) $item->qty * (float) $line->qty, 3);
            $notes = trim('Deal: '.$deal->name);
            $itemNotes = trim((string) ($item->notes ?? ''));
            if ($itemNotes !== '') {
                $notes .= ' — '.$itemNotes;
            }
            $rows[] = [
                'source' => $item,
                'product' => $component,
                'print' => (object) [
                    'qty' => $qty,
                    'notes' => $notes,
                    'product' => $component,
                    'name' => $component?->name ?? 'Item',
                    'uom' => $component?->uom ?? (string) ($item->uom ?? ''),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, qty: float, uom: string}>
     */
    public static function componentLinesForDisplay(PosOrderItem $item): array
    {
        $deal = self::forPosProduct((int) $item->product_id);
        if ($deal === null || $deal->items->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($deal->items as $line) {
            $component = $line->product;
            $out[] = [
                'name' => $component?->name ?? 'Item',
                'qty' => round((float) $item->qty * (float) $line->qty, 3),
                'uom' => (string) ($component?->uom ?? ''),
            ];
        }

        return $out;
    }

    public static function dealsCategory(): InventoryCategory
    {
        $menu = MenuCategory::ensure();
        $existing = InventoryCategory::query()
            ->where('parent_id', $menu->id)
            ->whereRaw('LOWER(name) = ?', ['deals'])
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InventoryCategory::query()->create([
            'name' => 'Deals',
            'parent_id' => $menu->id,
        ]);
    }
}
