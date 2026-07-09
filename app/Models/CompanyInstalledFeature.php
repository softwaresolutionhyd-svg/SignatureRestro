<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInstalledFeature extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'company_id',
        'feature_key',
        'installed_at',
        'installed_by',
        'source_company_update_id',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }
}
