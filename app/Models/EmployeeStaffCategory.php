<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeStaffCategory extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'staff_category_id');
    }
}
