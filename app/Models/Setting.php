<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['company_id', 'key', 'value'];

    private static function cacheSuffix(): string
    {
        $cid = current_company_id();

        return 'c'.($cid !== null ? (string) $cid : '0');
    }

    private static function scopedQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $q = static::query();
        $cid = current_company_id();
        if ($cid !== null) {
            $q->where('company_id', $cid);
        }

        return $q;
    }

    /** Get a setting value (with optional default). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::settingCacheKey($key);

        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $row = static::scopedQuery()->where('key', $key)->first();

            return $row ? $row->value : $default;
        });
    }

    private static function settingCacheKey(string $key): string
    {
        return 'setting:'.$key.':'.self::cacheSuffix();
    }

    private static function allMapCacheKey(): string
    {
        return 'settings:all:'.self::cacheSuffix();
    }

    /** Forget the cached settings map (must run after any write). */
    public static function forgetAllMapCache(): void
    {
        Cache::forget(self::allMapCacheKey());
    }

    /** Set (upsert) a setting and clear its cache. */
    public static function set(string $key, mixed $value): void
    {
        $cid = current_company_id();
        if ($cid === null) {
            return;
        }
        static::updateOrCreate(
            ['company_id' => $cid, 'key' => $key],
            ['value' => $value]
        );
        Cache::forget(self::settingCacheKey($key));
        self::forgetAllMapCache();
    }

    /** Bulk-set many settings and clear all their caches. */
    public static function setMany(array $data): void
    {
        $cid = current_company_id();
        if ($cid === null) {
            return;
        }
        foreach ($data as $key => $value) {
            static::updateOrCreate(
                ['company_id' => $cid, 'key' => $key],
                ['value' => $value]
            );
            Cache::forget(self::settingCacheKey((string) $key));
        }
        self::forgetAllMapCache();
    }

    /** Forget cached value (called after update). */
    public static function clearCache(string $key): void
    {
        $cid = current_company_id();
        if ($cid === null) {
            return;
        }
        Cache::forget(self::settingCacheKey($key));
        self::forgetAllMapCache();
    }

    /**
     * Clear setting caches after cloud sync apply (Query Builder upserts skip Eloquent).
     * Must clear both company-scoped forever caches or cloud UI keeps stale empty values.
     */
    public static function forgetCachesAfterSync(?int $companyId, ?string $key = null): void
    {
        if ($companyId !== null && $companyId > 0) {
            if ($key !== null && $key !== '') {
                Cache::forget('setting:'.$key.':c'.$companyId);
            }
            Cache::forget('settings:all:c'.$companyId);
        }

        // Also clear "no company" / current-context caches used by some pages.
        if ($key !== null && $key !== '') {
            Cache::forget('setting:'.$key.':c0');
            try {
                Cache::forget(self::settingCacheKey($key));
            } catch (\Throwable) {
                // ignore
            }
        }
        Cache::forget('settings:all:c0');
        try {
            self::forgetAllMapCache();
        } catch (\Throwable) {
            // ignore
        }
    }

    /** Return all settings as key→value array for the current company. */
    public static function all_map(): array
    {
        return Cache::rememberForever(self::allMapCacheKey(), function () {
            return static::scopedQuery()
                ->get(['key', 'value'])
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /** Paginator page size (global override: 20/50/150 via query or session). */
    public static function pageSize(string $key, int $default = 20): int
    {
        $allowed = [20, 50, 150];
        $request = request();

        $requested = (int) $request->query('per_page', 0);
        if (in_array($requested, $allowed, true)) {
            if ($request->hasSession()) {
                $request->session()->put('ui.per_page', $requested);
            }

            return $requested;
        }

        $sessionPerPage = $request->hasSession() ? (int) $request->session()->get('ui.per_page', 0) : 0;
        if (in_array($sessionPerPage, $allowed, true)) {
            return $sessionPerPage;
        }

        $stored = (int) static::get($key, (string) $default);
        if (in_array($stored, $allowed, true)) {
            return $stored;
        }

        return in_array($default, $allowed, true) ? $default : 20;
    }

    /** Default product cost markup rows when Settings has none configured. */
    public static function defaultProductExtraCostFields(): array
    {
        return [
            [
                'key' => 'gas_charges',
                'label' => 'Gas charges',
                'rate' => 20,
                'operator' => 'plus',
                'base' => 'cost',
                'target' => 'effective_cost',
            ],
        ];
    }

    /**
     * Normalized product extra-cost field definitions from Settings (with built-in defaults).
     *
     * @return list<array{key:string,label:string,rate:float,operator:string,base:string,target:string}>
     */
    public static function productExtraCostFieldDefinitions(): array
    {
        $raw = self::get('product_extra_cost_fields', '[]');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $rows = $raw;
        } else {
            $rows = [];
        }

        if ($rows === []) {
            $rows = self::defaultProductExtraCostFields();
        }

        $out = [];
        $used = [];
        $prevKeys = [];
        foreach ($rows as $row) {
            $label = trim((string) data_get($row, 'label', ''));
            if ($label === '') {
                continue;
            }

            $key = trim((string) data_get($row, 'key', ''));
            if ($key === '') {
                $key = strtolower((string) preg_replace('/[^a-z0-9]+/', '_', $label));
                $key = trim($key, '_');
            }
            if ($key === 'profit_50' || strcasecmp($label, 'Profit 50%') === 0) {
                continue;
            }
            if ($key === '' || isset($used[$key])) {
                continue;
            }
            $used[$key] = true;

            $operator = (string) data_get($row, 'operator', 'plus');
            if (! in_array($operator, ['plus', 'minus', 'multiply', 'divide'], true)) {
                $operator = 'plus';
            }
            $base = trim((string) data_get($row, 'base', 'cost'));
            if ($base === '') {
                $base = 'cost';
            }
            if (! in_array($base, ['cost', 'effective_cost', 'price'], true) && ! in_array($base, $prevKeys, true)) {
                $base = 'cost';
            }
            $target = trim((string) data_get($row, 'target', data_get($row, 'calculate_to', 'effective_cost')));
            if (! in_array($target, ['effective_cost', 'price'], true)) {
                $target = 'effective_cost';
            }

            $out[] = [
                'key' => $key,
                'label' => $label,
                'rate' => max((float) data_get($row, 'rate', 0), 0),
                'operator' => $operator,
                'base' => $base,
                'target' => $target,
            ];
            $prevKeys[] = $key;
        }

        return $out;
    }
}
