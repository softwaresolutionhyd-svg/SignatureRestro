<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'slug',
        'active',
        'database_name',
        'tenant_ready_at',
        'tenant_provision_failed_at',
        'tenant_provision_error',
    ];

    protected $casts = [
        'active' => 'boolean',
        'tenant_ready_at' => 'datetime',
        'tenant_provision_failed_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public function companyUpdates(): HasMany
    {
        return $this->hasMany(CompanyUpdate::class);
    }
}
