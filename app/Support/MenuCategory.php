<?php

namespace App\Support;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * POS / menu catalog: top-level “Menu” with the same sub-categories
 * (Burger, Soup, Pizza, …) — products keep their subcategory, never the Menu root.
 */
final class MenuCategory
{
    public const NAME = 'Menu';

    public const LEGACY_ROOT_NAMES = ['All Products', 'All Product', 'Products'];

    public static function ensure(): InventoryCategory
    {
        $existing = InventoryCategory::query()
            ->whereNull('parent_id')
            ->whereRaw('LOWER(name) = ?', [strtolower(self::NAME)])
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InventoryCategory::query()->create([
            'name' => self::NAME,
            'parent_id' => null,
        ]);
    }

    /**
     * @return list<int>
     */
    public static function categoryIds(): array
    {
        $root = self::ensure();

        return InventoryCategory::query()
            ->where('id', $root->id)
            ->orWhere('parent_id', $root->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure Menu exists, adopt legacy sub-categories under it, and keep each
     * POS product on its subcategory (never force category_id = Menu).
     *
     * @return int Number of products updated
     */
    public static function assignPosProducts(): int
    {
        $menu = self::ensure();
        self::adoptLegacySubcategories($menu);

        return self::reclassifyPosProducts($menu, onlyMenuRoot: true, useHistory: true);
    }

    /**
     * Move old top-level children (e.g. under “All Products”) under Menu.
     * Same subcategory rows/ids — only parent_id changes.
     */
    public static function adoptLegacySubcategories(InventoryCategory $menu): int
    {
        $legacyRoots = InventoryCategory::query()
            ->whereNull('parent_id')
            ->where('id', '!=', $menu->id)
            ->where(function ($q) {
                foreach (self::LEGACY_ROOT_NAMES as $name) {
                    $q->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($legacyRoots === []) {
            return 0;
        }

        return InventoryCategory::query()
            ->whereIn('parent_id', $legacyRoots)
            ->update(['parent_id' => $menu->id]);
    }

    /**
     * Products stuck on Menu root → restore previous subcategory when possible.
     *
     * @return int Number of products updated
     */
    public static function repairProductsOnMenuRoot(?InventoryCategory $menu = null): int
    {
        return self::reclassifyPosProducts($menu, onlyMenuRoot: true, useHistory: true);
    }

    /**
     * Re-assign POS products to Menu sub-categories (history → aliases → name).
     *
     * @return int Number of products updated
     */
    public static function reclassifyPosProducts(
        ?InventoryCategory $menu = null,
        bool $onlyMenuRoot = false,
        bool $useHistory = true
    ): int {
        $menu ??= self::ensure();
        self::adoptLegacySubcategories($menu);

        $subcategories = InventoryCategory::query()
            ->where('parent_id', $menu->id)
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->get(['id', 'name']);

        if ($subcategories->isEmpty()) {
            return 0;
        }

        $subIds = $subcategories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fromHistory = $useHistory
            ? self::categoryIdsFromSyncHistory($subIds)
            : [];

        $updated = 0;
        $query = InventoryProduct::query()->where('for_pos', true);
        if ($onlyMenuRoot) {
            $query->where('category_id', $menu->id);
        } else {
            $query->where(function ($q) use ($menu, $subIds) {
                $q->where('category_id', $menu->id)
                    ->orWhereIn('category_id', $subIds)
                    ->orWhereNull('category_id');
            });
        }

        $query->orderBy('id')->chunkById(100, function (Collection $products) use ($subcategories, $fromHistory, &$updated) {
            foreach ($products as $product) {
                /** @var InventoryProduct $product */
                $nextId = $fromHistory[(int) $product->id]
                    ?? self::guessSubcategoryId((string) $product->name, $subcategories);

                if ($nextId === null) {
                    $starter = $subcategories->first(
                        fn ($c) => strcasecmp((string) $c->name, 'Starter') === 0
                    );
                    $nextId = $starter ? (int) $starter->id : null;
                }

                if ($nextId === null || $nextId === (int) $product->category_id) {
                    continue;
                }

                $product->category_id = $nextId;
                $product->save();
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Latest known subcategory from sync outbox (before Menu-root wipe).
     *
     * @param  list<int>  $allowedSubIds
     * @return array<int, int> product_id => category_id
     */
    private static function categoryIdsFromSyncHistory(array $allowedSubIds): array
    {
        if ($allowedSubIds === [] || ! DB::getSchemaBuilder()->hasTable('sync_queue')) {
            return [];
        }

        $allowed = array_fill_keys($allowedSubIds, true);
        $map = [];

        $rows = DB::table('sync_queue')
            ->where('table_name', 'inventory_products')
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->get(['record_key', 'payload']);

        foreach ($rows as $row) {
            $productId = (int) $row->record_key;
            if ($productId <= 0 || isset($map[$productId])) {
                continue;
            }

            $payload = is_string($row->payload)
                ? json_decode($row->payload, true)
                : (array) $row->payload;

            $catId = (int) ($payload['category_id'] ?? 0);
            if ($catId > 0 && isset($allowed[$catId])) {
                $map[$productId] = $catId;
            }
        }

        return $map;
    }

    /**
     * Keyword → subcategory name (longest / most specific checked first via sort).
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function subcategoryAliases(): array
    {
        return [
            // Dish-type keywords first (before protein words like tikka / chicken)
            ['pizza', 'Pizza'],
            ['fajita', 'Pizza'],
            ['soup', 'Soup'],
            ['steak', 'Steaks'],
            ['lasagna', 'Italian'],
            ['fettuccine', 'Pastas'],
            ['alfredo', 'Pastas'],
            ['penne', 'Pastas'],
            ['pasta', 'Pastas'],
            ['mac n cheese', 'Pastas'],
            ['chowmein', 'Chowmein'],
            ['biryani', 'Rise'],
            ['pulao', 'Rise'],
            ['mandi', 'Rise'],
            ['fried rice', 'Rise'],
            ['garlic rice', 'Rise'],
            ['steamed rice', 'Rise'],
            ['masalah rice', 'Rise'],
            ['with rice', 'Rise'],
            ['karahi', 'Handi'],
            ['handi', 'Handi'],
            ['kunna', 'Handi'],
            ['qeema', 'Handi'],
            ['daal', 'Handi'],
            ['jalfrezi', 'Handi'],
            ['bar b q', 'Bar BQ Hot Line'],
            ['bbq', 'Bar BQ Hot Line'],
            ['platter', 'Bar BQ Hot Line'],
            ['chops', 'Bar BQ Hot Line'],
            ['boti', 'Bar BQ Hot Line'],
            ['tikka', 'Bar BQ Hot Line'],
            ['kebab', 'Bar BQ Hot Line'],
            ['seekh', 'Bar BQ Hot Line'],
            ['noodle', 'Chinies'],
            ['chilli dry', 'Chinies'],
            ['mongolian', 'Chinies'],
            ['manchurian', 'Chinies'],
            ['szechuan', 'Chinies'],
            ['shashlik', 'Chinies'],
            ['cashew', 'Chinies'],
            ['teriyaki', 'Chinies'],
            ['dragon', 'Chinies'],
            ['sweet & sour', 'Chinies'],
            ['tempura', 'Starter'],
            ['dynamite', 'Starter'],
            ['nigri', 'Grand Sushi'],
            ['sushi', 'Grand Sushi'],
            ['salmon', 'Grand Sushi'],
            ['wings', 'Starter'],
            ['drum sticks', 'Starter'],
            ['garlic bread', 'Starter'],
            ['bites', 'Starter'],
            ['cheese roll', 'Starter'],
            ['hummus', 'Starter'],
            ['baba ganoush', 'Starter'],
            ['avocado', 'Starter'],
            ['strips', 'Fried Chicken'],
            ['n chips', 'Fried Chicken'],
            ['crisper', 'Fried Chicken'],
            ['finger fish', 'Fish Lover'],
            ['fried fish', 'Fish Lover'],
            ['fried prawn', 'Fish Lover'],
            ['grilled fish', 'Fish Lover'],
            ['grilled prawn', 'Fish Lover'],
            ['fish', 'Fish Lover'],
            ['prawn', 'Fish Lover'],
            ['twister', 'Wraps "n" Twister'],
            ['wrap', 'Wraps "n" Twister'],
            ['sandwich', 'Sandwich'],
            ['burger', 'Burger'],
            ['fries', 'Fries'],
            ['salad', 'Salad Bar'],
            ['rice', 'Rise'],
            ['naan', 'Tandoor'],
            ['roti', 'Tandoor'],
            ['ginger', 'Handi'],
            ['laal maas', 'Desi Delight Mutton'],
            ['moman mas', 'Desi Delight Mutton'],
            ['mutton', 'Desi Delight Mutton'],
            ['lamb', 'Desi Delight Mutton'],
            ['bakra', 'Desi Delight Mutton'],
            ['rosh', 'Desi Delight Mutton'],
            ['mix vegetable', 'Chinies'],
            ['tuna', 'Sandwich'],
            ['mayo roll', 'Sandwich'],
            ['roll', 'Sandwich'],
            ['gulab jamun', 'Deserts'],
            ['pudding', 'Deserts'],
            ['trifle', 'Deserts'],
            ['kheer', 'Deserts'],
            ['mineral water', 'Beverages'],
            ['soft drink', 'Beverages'],
            ['margarita', 'Beverages'],
            ['fresh lime', 'Beverages'],
            ['raita', 'Beverages'],
            ['mint sauce', 'Beverages'],
            ['signature', 'Our Signature'],
            ['deal', 'Deals'],
            ['dou box', 'Deals'],
            ['smart individual', 'Deals'],
            ['fast food', 'Deals'],
            ['on demand', 'Deals'],
        ];
    }

    /**
     * @param  Collection<int, InventoryCategory>  $subcategories  longest names first
     */
    private static function guessSubcategoryId(string $productName, Collection $subcategories): ?int
    {
        $hay = mb_strtolower($productName);
        $hayPlain = str_replace(['"', "'", '“', '”', '?', '–', '-'], ['', '', '', '', '', ' ', ' '], $hay);

        $byName = [];
        foreach ($subcategories as $cat) {
            $byName[mb_strtolower(trim((string) $cat->name))] = (int) $cat->id;
        }

        // Specific aliases first (avoids “Chicken …” stealing Soup / Pizza / Handi).
        foreach (self::subcategoryAliases() as [$keyword, $subName]) {
            $kw = mb_strtolower($keyword);
            if ($kw !== '' && str_contains($hayPlain, $kw)) {
                $key = mb_strtolower($subName);
                if (isset($byName[$key])) {
                    return $byName[$key];
                }
            }
        }

        foreach ($subcategories as $cat) {
            $needle = mb_strtolower(trim((string) $cat->name));
            if ($needle === '' || mb_strlen($needle) < 4) {
                continue;
            }
            $needlePlain = str_replace(['"', "'", '“', '”'], '', $needle);

            if (str_contains($hay, $needle) || ($needlePlain !== '' && str_contains($hayPlain, $needlePlain))) {
                return (int) $cat->id;
            }
        }

        return null;
    }
}
