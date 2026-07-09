<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InventoryProduct extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category_id',
        'department_id',
        'sku',
        'barcode',
        'name',
        'image_path',
        'uom',
        'package_contents_qty',
        'package_contents_uom',
        'cost',
        'gas_charges',
        'profit',
        'service_charges',
        'extra_costs',
        'price',
        'qty_on_hand',
        'reorder_level',
        'active',
        'for_pos',
        'for_purchase',
    ];

    protected $casts = [
        'cost'                  => 'decimal:2',
        'gas_charges'           => 'decimal:2',
        'profit'                => 'decimal:2',
        'service_charges'       => 'decimal:2',
        'extra_costs'           => 'array',
        'price'                 => 'decimal:2',
        'qty_on_hand'           => 'decimal:3',
        'reorder_level'         => 'decimal:3',
        'package_contents_qty'  => 'decimal:6',
        'active'                => 'bool',
        'for_pos'               => 'bool',
        'for_purchase'          => 'bool',
    ];

    /** @var array<string, string>|null */
    protected static ?array $extraCostTargetsCache = null;

    /**
     * Create a unique, human-readable SKU.
     */
    public static function generateNextSku(string $prefix = 'PRD'): string
    {
        $prefix = strtoupper(trim($prefix)) ?: 'PRD';

        for ($i = 0; $i < 20; $i++) {
            $seq = ((int) self::query()->max('id')) + 1 + $i;
            $sku = sprintf('%s-%s', $prefix, str_pad((string) $seq, 5, '0', STR_PAD_LEFT));
            if (! self::query()->where('sku', $sku)->exists()) {
                return $sku;
            }
        }

        return sprintf('%s-%s', $prefix, strtoupper(substr(sha1((string) microtime(true)), 0, 8)));
    }

    /** Total cost line = base cost + settings-based extra charges. */
    public function getTotalAttribute(): float
    {
        $extraCosts = (array) ($this->extra_costs ?? []);
        $targets = self::extraCostTargets();

        // Backward compatibility: if rules are unavailable, keep legacy behavior.
        if ($targets === []) {
            $extraCostTotal = 0.0;
            foreach ($extraCosts as $value) {
                $extraCostTotal += (float) $value;
            }

            return round(
                (float) $this->cost + $extraCostTotal,
                2
            );
        }

        $effectiveExtraTotal = 0.0;
        foreach ($extraCosts as $key => $value) {
            $target = $targets[(string) $key] ?? 'effective_cost';
            if ($target !== 'effective_cost') {
                continue;
            }
            $effectiveExtraTotal += (float) $value;
        }

        return round(
            (float) $this->cost
            + $effectiveExtraTotal,
            2
        );
    }

    /** Gas markup for POS staff sales (live from cost + settings). */
    public function gasChargesAmount(): float
    {
        $cost = (float) $this->cost;
        $effectiveCost = $cost;
        $computedAmounts = [];
        $gasFromSettings = 0.0;
        $foundGasField = false;

        foreach (Setting::productExtraCostFieldDefinitions() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rate = max((float) ($field['rate'] ?? 0), 0);
            $operator = (string) ($field['operator'] ?? 'plus');
            if (! in_array($operator, ['plus', 'minus', 'multiply', 'divide'], true)) {
                $operator = 'plus';
            }
            $baseKey = (string) ($field['base'] ?? 'cost');
            $baseVal = match ($baseKey) {
                'effective_cost' => $effectiveCost,
                'cost' => $cost,
                default => (float) ($computedAmounts[$baseKey] ?? 0.0),
            };

            $amount = round(match ($operator) {
                'minus' => -$baseVal * ($rate / 100),
                'multiply' => $baseVal * $rate,
                'divide' => $rate > 0 ? $baseVal / $rate : 0.0,
                default => $baseVal * ($rate / 100),
            }, 2);

            $computedAmounts[$key] = $amount;

            if (self::isGasCostField($field)) {
                $gasFromSettings = max($amount, 0.0);
                $foundGasField = true;
            }

            $target = (string) ($field['target'] ?? 'effective_cost');
            if ($target === 'effective_cost') {
                $effectiveCost += $amount;
            }
        }

        if ($foundGasField) {
            return $gasFromSettings;
        }

        $rate = self::gasChargesRatePercent();
        if ($rate > 0 && $cost > 0) {
            return round($cost * ($rate / 100), 2);
        }

        $stored = data_get($this->extra_costs, 'gas_charges');
        if ($stored !== null && (float) $stored > 0) {
            return round((float) $stored, 2);
        }

        return max(round((float) ($this->gas_charges ?? 0), 2), 0.0);
    }

    /** @return float Percent rate for the gas_charges settings line (0 if unset). */
    public static function gasChargesRatePercent(): float
    {
        foreach (Setting::productExtraCostFieldDefinitions() as $field) {
            if (self::isGasCostField($field)) {
                return max((float) ($field['rate'] ?? 0), 0);
            }
        }

        return 0.0;
    }

    /** @param  array{key?:string,label?:string}  $field */
    private static function isGasCostField(array $field): bool
    {
        $key = strtolower((string) ($field['key'] ?? ''));
        $label = strtolower((string) ($field['label'] ?? ''));

        return $key === 'gas_charges'
            || str_contains($key, 'gas')
            || str_contains($label, 'gas');
    }

    /**
     * @return array<string, string> key => target (effective_cost|price)
     */
    private static function extraCostTargets(): array
    {
        if (self::$extraCostTargetsCache !== null) {
            return self::$extraCostTargetsCache;
        }

        $out = [];
        foreach (Setting::productExtraCostFieldDefinitions() as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $target = (string) ($row['target'] ?? 'effective_cost');
            if (! in_array($target, ['effective_cost', 'price'], true)) {
                $target = 'effective_cost';
            }
            $out[$key] = $target;
        }

        self::$extraCostTargetsCache = $out;

        return self::$extraCostTargetsCache;
    }

    /**
     * Finished output of an active BoM: shelf qty is optional (production uses components).
     */
    public function scopeExcludingActiveBomFinishedProducts(Builder $query): Builder
    {
        return $query->whereDoesntHave('manufacturingBoms', fn ($q) => $q->where('active', true));
    }

    /** True when this SKU is the finished product on at least one active manufacturing BoM. */
    public function isManufacturedFinishedProduct(): bool
    {
        if (array_key_exists('active_manufacturing_boms_count', $this->attributes)) {
            return (int) $this->active_manufacturing_boms_count > 0;
        }

        return $this->manufacturingBoms()->where('active', true)->exists();
    }

    /** True when stock is at or below reorder level (and reorder level is set). */
    public function isLowStock(): bool
    {
        if (! ($this->for_purchase ?? true)) {
            return false;
        }

        if ($this->isManufacturedFinishedProduct()) {
            return false;
        }

        return (float) $this->reorder_level > 0
            && (float) $this->qty_on_hand <= (float) $this->reorder_level;
    }

    /** Public URL for square POS/inventory product image. */
    public function imageUrl(): ?string
    {
        $path = trim((string) ($this->image_path ?? ''));
        if ($path === '') {
            return null;
        }

        return asset('storage/'.$path);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(InventoryDepartment::class, 'department_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            InventoryDepartment::class,
            'inventory_product_department',
            'product_id',
            'department_id'
        )->withPivot('company_id')->withTimestamps();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryProductStock::class, 'product_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(InventoryMove::class, 'product_id');
    }

    public function uomConversions(): HasMany
    {
        return $this->hasMany(InventoryProductUomConversion::class, 'product_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(InventoryProductFavorite::class, 'product_id');
    }

    /** BoMs where this product is the manufactured output. */
    public function manufacturingBoms(): HasMany
    {
        return $this->hasMany(ManufacturingBom::class, 'finished_product_id');
    }

    /** True when “1 base UOM (e.g. pkt) = X inner UOM (e.g. g)” is configured. */
    public function hasPackageContents(): bool
    {
        return $this->package_contents_uom !== null
            && trim((string) $this->package_contents_uom) !== ''
            && (float) $this->package_contents_qty > 0;
    }

    /** Total inner quantity on hand (e.g. grams) = packets × contents per packet. */
    public function qtyOnHandAsPackageContents(): ?float
    {
        if (!$this->hasPackageContents()) {
            return null;
        }

        return round((float) $this->qty_on_hand * (float) $this->package_contents_qty, 6);
    }

    /** Human line for product cards, e.g. "≈ 62.500 g in packets". */
    public function packageContentsLine(): ?string
    {
        if (!$this->hasPackageContents()) {
            return null;
        }
        $inner = $this->qtyOnHandAsPackageContents();
        if ($inner === null) {
            return null;
        }

        return '≈ '.fmt_num($inner, 3).' '.trim((string) $this->package_contents_uom).' in stock (by packet size)';
    }

    /**
     * Base UOM + alternate units that can be used on PO, POS, BoM lines, etc.
     * Includes **Inventory → Units** global rules where rule “to” = this product’s base (e.g. g → kg).
     *
     * @return list<string>
     */
    public function allowedUomCodes(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (array $r) => trim((string) $r['uom']),
            $this->uomsForForms()
        ))));
    }

    /**
     * Rows for dropdowns: base + product conversions + matching global library rules (to = product base).
     * Product-specific factors override library when the same “from” code exists.
     *
     * @return list<array{uom: string, factor: float}>
     */
    public function uomsForForms(): array
    {
        $baseNorm = InventoryUnit::normalizeCode((string) $this->uom);
        $out = [['uom' => $this->uom, 'factor' => 1.0]];
        $seen = [$baseNorm => true];

        $this->loadMissing(['uomConversions' => fn ($q) => $q->where('active', true)]);
        foreach ($this->uomConversions as $c) {
            if (!$c->active) {
                continue;
            }
            $code = InventoryUnit::normalizeCode((string) $c->uom);
            if (isset($seen[$code])) {
                continue;
            }
            $out[] = ['uom' => $c->uom, 'factor' => (float) $c->factor_to_base];
            $seen[$code] = true;
        }

        // Auto-include package inner unit (e.g. g) when product defines packet contents.
        if ($this->hasPackageContents()) {
            $innerUom = trim((string) $this->package_contents_uom);
            $innerNorm = InventoryUnit::normalizeCode($innerUom);
            if ($innerNorm !== '' && !isset($seen[$innerNorm])) {
                $factor = $this->packageContentsInnerFactorToBase();
                if ($factor !== null && $factor > 0) {
                    $out[] = ['uom' => $innerUom, 'factor' => $factor];
                    $seen[$innerNorm] = true;
                }
            }
        }

        foreach (self::allLibraryUnitConversions() as $lib) {
            if (!$lib->fromUnit || !$lib->toUnit) {
                continue;
            }
            if (InventoryUnit::normalizeCode($lib->toUnit->code) !== $baseNorm) {
                continue;
            }
            $fn = InventoryUnit::normalizeCode($lib->fromUnit->code);
            if ($fn === $baseNorm || isset($seen[$fn])) {
                continue;
            }
            $out[] = ['uom' => $lib->fromUnit->code, 'factor' => (float) $lib->factor];
            $seen[$fn] = true;
        }

        return $out;
    }

    /**
     * How many base units = 1 of {@see $uomCode}, or null if not allowed.
     * Uses product conversions first, then global library rule from → to = product base.
     */
    public function factorToBaseForUom(string $uomCode): ?float
    {
        $uomCode = trim($uomCode);
        if ($uomCode === '') {
            return null;
        }
        if (strcasecmp($uomCode, (string) $this->uom) === 0) {
            return 1.0;
        }

        if (
            $this->hasPackageContents()
            && strcasecmp($uomCode, (string) $this->package_contents_uom) === 0
        ) {
            return $this->packageContentsInnerFactorToBase();
        }

        $this->loadMissing(['uomConversions' => fn ($q) => $q->where('active', true)]);
        foreach ($this->uomConversions as $c) {
            if (!$c->active) {
                continue;
            }
            if (strcasecmp((string) $c->uom, $uomCode) === 0) {
                return (float) $c->factor_to_base;
            }
        }

        $baseNorm = InventoryUnit::normalizeCode((string) $this->uom);
        $fromNorm = InventoryUnit::normalizeCode($uomCode);

        foreach (self::allLibraryUnitConversions() as $lib) {
            if (!$lib->fromUnit || !$lib->toUnit) {
                continue;
            }
            if (InventoryUnit::normalizeCode($lib->fromUnit->code) !== $fromNorm) {
                continue;
            }
            if (InventoryUnit::normalizeCode($lib->toUnit->code) !== $baseNorm) {
                continue;
            }

            return (float) $lib->factor;
        }

        return null;
    }

    /**
     * For inner package unit (e.g. g), returns how many base units make 1 inner unit.
     * Example: base=packet, package=250 g => 1 g = 0.004 packet.
     */
    private function packageContentsInnerFactorToBase(): ?float
    {
        $qty = (float) $this->package_contents_qty;
        if ($qty <= 0) {
            return null;
        }

        return 1 / $qty;
    }

    /**
     * Convert a quantity from any allowed UOM into inventory base UOM (e.g. 250 g → 0.25 kg).
     *
     * @throws \InvalidArgumentException
     */
    public function convertQtyToBaseUom(float $qty, string $uomCode): float
    {
        $f = $this->factorToBaseForUom($uomCode);
        if ($f === null) {
            throw new \InvalidArgumentException(
                "Unit \"".trim($uomCode)."\" is not set up for product \"{$this->sku}\". Add it on the product (Other units) or a matching rule under Inventory → Units (e.g. g → kg for base kg)."
            );
        }

        return $qty * $f;
    }

    /** @var Collection<int, InventoryUnitConversion>|null */
    private static ?Collection $libraryConversionsMemo = null;

    /** Cached per request: all rows from inventory_unit_conversions with from/to units. */
    private static function allLibraryUnitConversions(): Collection
    {
        if (self::$libraryConversionsMemo !== null) {
            return self::$libraryConversionsMemo;
        }
        if (!Schema::hasTable('inventory_unit_conversions')) {
            return self::$libraryConversionsMemo = collect();
        }

        return self::$libraryConversionsMemo = InventoryUnitConversion::query()
            ->with(['fromUnit:id,code', 'toUnit:id,code'])
            ->get();
    }
}
