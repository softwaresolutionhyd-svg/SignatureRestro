<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomCategory extends Model
{
    use BelongsToCompany;

    /** Room lists: Barian Cottage → Executive Lodges → Hut → Family Suites. */
    public static function categoryOrderGroups(): array
    {
        return [
            ['Barian Cottage', 'BARIAN COTTAGE'],
            ['Barian Executive Lodges', 'BARIAN EXECUTIVE LODGES'],
            ['Barian Hut', 'Barian Huts', 'BARIAN HUT', 'BARIAN HUTS'],
            [
                'Barian Family Suite',
                'Barian Family Suites',
                'BARIAN FAMILY SUITE',
                'BARIAN FAMILY SUITES',
                'BFS',
            ],
            ['MT Hut', 'MT HUT'],
            ['BOQs', 'BOQS'],
        ];
    }

    /** Default room type/category for online bookings. */
    public const DEFAULT_ONLINE_CATEGORY_NAME = 'Barian Hut';

    protected $connection = 'tenant';

    protected $fillable = ['company_id', 'name', 'description', 'sort_order', 'active'];

    protected $casts = ['active' => 'bool', 'sort_order' => 'int'];

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class, 'room_category_id');
    }

    public function defaultRate(): ?RoomRate
    {
        return $this->rates()->where('active', true)->where('is_default', true)->first()
            ?? $this->rates()->where('active', true)->orderBy('person_type')->first();
    }

    public function rateForPersonType(?string $personType): ?RoomRate
    {
        if (! $personType) {
            return $this->defaultRate();
        }

        return $this->rates()
            ->where('active', true)
            ->where('person_type', $personType)
            ->first();
    }

    public function guestRooms(): HasMany
    {
        return $this->hasMany(GuestRoom::class, 'room_category_id');
    }

    public function scopeOrderedForRoomList(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::categoryNameOrderSql($query->getModel()->getTable().'.name'))
            ->orderBy('name');
    }

    /** Barian Hut rooms for manual booking check-in only. */
    public const MANUAL_BARIAN_HUT_ROOM_NUMBERS = ['B-7', 'B-8'];

    /** @return list<string> */
    public static function barianHutCategoryNames(): array
    {
        return self::categoryOrderGroups()[2] ?? [
            self::DEFAULT_ONLINE_CATEGORY_NAME,
            'Barian Huts',
            'BARIAN HUT',
            'BARIAN HUTS',
        ];
    }

    public static function defaultOnlineCategory(): ?self
    {
        $hutNames = self::categoryOrderGroups()[2] ?? [
            self::DEFAULT_ONLINE_CATEGORY_NAME,
            'Barian Huts',
            'BARIAN HUT',
            'BARIAN HUTS',
        ];

        return static::query()
            ->where('active', true)
            ->whereIn('name', $hutNames)
            ->orderByRaw(self::categoryNameOrderSql('name'))
            ->first();
    }

    public static function categorySortIndex(?string $name): int
    {
        $normalized = strtolower(trim((string) $name));
        if ($normalized === '' || $normalized === 'other') {
            return 9999;
        }

        foreach (self::categoryOrderGroups() as $i => $aliases) {
            foreach ($aliases as $alias) {
                $aliasNorm = strtolower($alias);
                if ($normalized === $aliasNorm) {
                    return $i;
                }
            }
        }

        foreach (self::categoryOrderGroups() as $i => $aliases) {
            foreach ($aliases as $alias) {
                $aliasNorm = strtolower($alias);
                if (str_contains($normalized, $aliasNorm) || str_contains($aliasNorm, $normalized)) {
                    return $i;
                }
            }
        }

        return 5000;
    }

    /** Dashboard room-tile color palette slug (one scheme per category group). */
    public static function dashboardColorScheme(?string $name): string
    {
        return match (self::categorySortIndex($name)) {
            0 => 'cottage',
            1 => 'lodge',
            2 => 'hut',
            3 => 'suite',
            4 => 'mthut',
            5 => 'boq',
            default => 'default',
        };
    }

    public static function categoryNameOrderSql(string $column): string
    {
        $parts = [];
        foreach (self::categoryOrderGroups() as $i => $aliases) {
            $priority = $i + 1;
            foreach ($aliases as $name) {
                $escaped = str_replace("'", "''", $name);
                $parts[] = "WHEN '{$escaped}' THEN {$priority}";
            }
        }
        $else = count(self::categoryOrderGroups()) + 1;

        return 'CASE '.$column.' '.implode(' ', $parts).' ELSE '.$else.' END';
    }
}
