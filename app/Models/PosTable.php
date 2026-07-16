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
     * Natural ascending compare: GR1, GR2 … GR10 (not GR1, GR10, GR2).
     */
    public static function naturalNameCompare(string $a, string $b): int
    {
        return strnatcasecmp(trim($a), trim($b));
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
