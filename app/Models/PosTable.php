<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTable extends Model
{
    protected $connection = 'tenant';

    use HasFactory;

    protected $fillable = [
        'sitting_area_id',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
    ];

    public function sittingArea(): BelongsTo
    {
        return $this->belongsTo(PosSittingArea::class, 'sitting_area_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'table_id');
    }

    /**
     * Number-wise ascending compare: LG36 … LG45, L46 … L55; GR1 … GR10 (not GR1, GR10, GR2).
     * Compares the numeric part first, then the letter prefix.
     */
    public static function naturalNameCompare(string $a, string $b): int
    {
        $a = trim($a);
        $b = trim($b);

        preg_match('/^(.*?)(\d+)\s*$/', $a, $ma);
        preg_match('/^(.*?)(\d+)\s*$/', $b, $mb);

        $numA = isset($ma[2]) ? (int) $ma[2] : null;
        $numB = isset($mb[2]) ? (int) $mb[2] : null;

        if ($numA !== null && $numB !== null && $numA !== $numB) {
            return $numA <=> $numB;
        }

        $prefixA = isset($ma[1]) ? strtoupper($ma[1]) : strtoupper($a);
        $prefixB = isset($mb[1]) ? strtoupper($mb[1]) : strtoupper($b);
        $prefixCmp = strcmp($prefixA, $prefixB);
        if ($prefixCmp !== 0) {
            return $prefixCmp;
        }

        return strnatcasecmp($a, $b);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>|iterable<self>  $tables
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function sortByNaturalName(iterable $tables): \Illuminate\Support\Collection
    {
        return collect($tables)
            ->sort(fn (self $x, self $y) => self::naturalNameCompare((string) $x->name, (string) $y->name))
            ->values();
    }
}
