<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_no',
        'name',
        'email',
        'phone',
        'department_id',
        'designation_id',
        'join_date',
        'salary',
        'address',
        'active',
    ];

    protected $casts = [
        'join_date' => 'date',
        'salary' => 'decimal:2',
        'active' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(EmployeeDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(EmployeeDesignation::class, 'designation_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeWaiters(Builder $query): Builder
    {
        return $query->whereHas('designation', function (Builder $designationQuery) {
            $designationQuery->whereRaw('LOWER(TRIM(name)) = ?', ['waiter']);
        });
    }
}
