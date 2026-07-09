<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplate extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'report_type',
        'preset',
        'cols',
        'filters',
        'created_by',
    ];

    protected $casts = [
        'cols'    => 'array',
        'filters' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Human-readable type label */
    public function typeLabel(): string
    {
        return match($this->report_type) {
            'sales'     => 'Sales',
            'purchases' => 'Purchases',
            'inventory' => 'Inventory',
            'employees' => 'Employees',
            'expenses'  => 'Expenses',
            'credit'    => 'Credit Book',
            default     => ucfirst($this->report_type),
        };
    }

    /** Badge color for type */
    public function typeColor(): string
    {
        return match($this->report_type) {
            'sales'     => '#f97316',
            'purchases' => '#22c55e',
            'inventory' => '#0ea5e9',
            'employees' => '#ec4899',
            'expenses'  => '#14b8a6',
            'credit'    => '#ef4444',
            default     => '#7c3aed',
        };
    }
}
