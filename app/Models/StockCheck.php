<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCheck extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'number',
        'title',
        'status',
        'created_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(StockCheckLine::class, 'stock_check_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }
}
