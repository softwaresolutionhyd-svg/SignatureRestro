<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'amount',
        'balance',
        'start_date',
        'status',
        'notes',
        'created_by',
        'settled_payroll_entry_id',
        'settled_period',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'start_date' => 'date',
        'settled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settledPayrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'settled_payroll_entry_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (float) $this->balance > 0;
    }
}
