<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeStaffSubCategory extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'staff_category_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeStaffCategory::class, 'staff_category_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'staff_sub_category_id');
    }
}
