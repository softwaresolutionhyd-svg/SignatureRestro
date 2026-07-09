<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'session_no',
        'business_date',
        'user_id',
        'status',
        'opening_cash',
        'closing_cash',
        'closing_bank',
        'closing_card',
        'amount_to_collect',
        'expected_cash',
        'cash_difference',
        'opened_at',
        'closed_at',
        'note',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'closing_bank' => 'decimal:2',
        'closing_card' => 'decimal:2',
        'amount_to_collect' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'session_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class, 'session_id');
    }
}
