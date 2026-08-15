<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLine extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'expense_id',
        'description',
        'qty',
        'unit_amount',
        'tax_percent',
        'tax_amount',
        'total_amount',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_amount' => 'decimal:2',
        'tax_percent' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function recalculate(): void
    {
        $subtotal = (float) $this->qty * (float) $this->unit_amount;
        $this->total_amount = round($subtotal, 2);
        $this->tax_amount = round($subtotal * (float) $this->tax_percent / 100, 2);
        $this->line_total = round($subtotal + $this->tax_amount, 2);
    }
}
