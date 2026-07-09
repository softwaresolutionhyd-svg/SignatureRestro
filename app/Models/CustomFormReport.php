<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormReport extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'template_id',
        'month',
        'year',
        'values_json',
        'saved_by',
    ];

    protected $casts = [
        'values_json' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CustomFormTemplate::class, 'template_id');
    }
}

