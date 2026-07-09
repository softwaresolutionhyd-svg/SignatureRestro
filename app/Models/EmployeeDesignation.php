<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeDesignation extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'active'];

    protected $casts = [
        'active' => 'bool',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }
}
