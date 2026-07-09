<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingOrder extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'bom_id',
        'user_id',
        'qty_ordered',
        'status',
        'reference',
        'note',
        'completed_at',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:3',
        'completed_at' => 'datetime',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ManufacturingBom::class, 'bom_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
