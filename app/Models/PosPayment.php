<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'method',
        'amount',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }
}
