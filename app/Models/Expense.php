<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id', 'category_id', 'description', 'expense_date',
        'qty', 'unit_amount', 'tax_percent', 'tax_amount',
        'total_amount', 'grand_total', 'notes', 'receipt_path',
        'status', 'submitted_at', 'approved_at', 'approved_by',
        'paid_at', 'refuse_reason',
    ];

    protected $casts = [
        'expense_date'  => 'date',
        'qty'           => 'decimal:3',
        'unit_amount'   => 'decimal:2',
        'tax_percent'   => 'decimal:3',
        'tax_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'grand_total'   => 'decimal:2',
        'submitted_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'paid_at'       => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_PAID      = 'paid';
    const STATUS_REFUSED   = 'refused';

    public static function statusLabel(): array
    {
        return [
            self::STATUS_DRAFT     => ['label' => 'Draft',     'color' => 'secondary'],
            self::STATUS_SUBMITTED => ['label' => 'Submitted', 'color' => 'info'],
            self::STATUS_APPROVED  => ['label' => 'Approved',  'color' => 'primary'],
            self::STATUS_PAID      => ['label' => 'Paid',      'color' => 'success'],
            self::STATUS_REFUSED   => ['label' => 'Refused',   'color' => 'danger'],
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Recalculate totals from qty × unit_amount + tax */
    public function recalculate(): void
    {
        $subtotal         = (float) $this->qty * (float) $this->unit_amount;
        $this->total_amount = round($subtotal, 2);
        $this->tax_amount   = round($subtotal * (float) $this->tax_percent / 100, 2);
        $this->grand_total  = round($subtotal + $this->tax_amount, 2);
    }
}
