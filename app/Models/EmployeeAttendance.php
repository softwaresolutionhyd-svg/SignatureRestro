<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendance extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'user_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'status',
        'source',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
