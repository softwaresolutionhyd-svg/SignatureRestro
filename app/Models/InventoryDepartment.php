<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryDepartment extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'active',
        'is_warehouse',
    ];

    protected $casts = [
        'active' => 'bool',
        'is_warehouse' => 'bool',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(InventoryProduct::class, 'department_id');
    }

    public function catalogProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            InventoryProduct::class,
            'inventory_product_department',
            'department_id',
            'product_id'
        )->withPivot('company_id')->withTimestamps();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryProductStock::class, 'department_id');
    }
}
