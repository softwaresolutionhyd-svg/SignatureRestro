<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDemand extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'demand_no',
        'department_id',
        'demand_date',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'demand_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(InventoryDepartment::class, 'department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDemandLine::class, 'demand_id');
    }
}
