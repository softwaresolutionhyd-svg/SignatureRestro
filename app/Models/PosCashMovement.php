<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCashMovement extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'session_id',
        'user_id',
        'type',
        'amount',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
