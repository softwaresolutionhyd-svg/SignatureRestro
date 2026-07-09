<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'description', 'active'];

    protected $casts = ['active' => 'bool'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }
}
