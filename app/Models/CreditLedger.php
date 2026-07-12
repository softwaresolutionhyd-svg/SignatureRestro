<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedger extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'company_id',
        'contact_id', 'type', 'pos_order_id', 'payroll_entry_id', 'description',
        'amount', 'balance_after', 'entry_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'balance_after'=> 'decimal:2',
        'entry_date'   => 'date',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
