<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUpdate extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'company_id',
        'title',
        'body',
        'version',
        'feature_key',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopePublishedForTenant($query)
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
