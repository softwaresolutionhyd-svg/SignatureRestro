<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFormTemplate extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'heading',
        'rows_json',
        'show_remarks',
        'active',
    ];

    protected $casts = [
        'rows_json' => 'array',
        'show_remarks' => 'bool',
        'active' => 'bool',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(CustomFormReport::class, 'template_id');
    }
}

