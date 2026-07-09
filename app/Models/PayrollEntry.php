<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'period',
        'base_salary',
        'bonus',
        'deduction',
        'net_pay',
        'status',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deduction' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateNet(): void
    {
        $this->net_pay = (float) $this->base_salary + (float) $this->bonus - (float) $this->deduction;
    }
}
