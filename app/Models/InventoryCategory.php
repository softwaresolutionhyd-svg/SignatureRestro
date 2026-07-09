<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(InventoryProduct::class, 'category_id');
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @return array{parent_id: int|null, sub_category_id: int|null}
     */
    public static function selectionForProduct(?InventoryProduct $product = null): array
    {
        $parentId = null;
        $subId = null;

        if ($product?->category_id) {
            $product->loadMissing('category.parent');
            $cat = $product->category;
            if ($cat) {
                if ($cat->parent_id) {
                    $parentId = (int) $cat->parent_id;
                    $subId = (int) $cat->id;
                } else {
                    $parentId = (int) $cat->id;
                }
            }
        }

        return [
            'parent_id' => $parentId,
            'sub_category_id' => $subId,
        ];
    }

    /**
     * @return array<int, list<array{id: int, name: string}>>
     */
    public static function subcategoriesGroupedByParent(): array
    {
        return static::query()
            ->whereNotNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($rows) => $rows->map(fn (self $row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])->values()->all())
            ->all();
    }

    public function breadcrumbLabel(): string
    {
        if ($this->relationLoaded('parent') && $this->parent) {
            return $this->parent->name.' › '.$this->name;
        }

        if ($this->parent_id) {
            $parent = $this->parent()->first(['id', 'name']);

            return $parent ? $parent->name.' › '.$this->name : $this->name;
        }

        return $this->name;
    }
}
