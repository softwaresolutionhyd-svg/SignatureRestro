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
        'staff_category_id',
        'contact_id',
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

    public function staffCategory(): BelongsTo
    {
        return $this->belongsTo(EmployeeStaffCategory::class, 'staff_category_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
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

    public static function generateNextEmployeeNo(int $companyId): string
    {
        $max = 0;

        static::query()
            ->where('company_id', $companyId)
            ->pluck('employee_no')
            ->each(function (string $no) use (&$max) {
                if (preg_match('/^EMP-(\d+)$/i', trim($no), $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return sprintf('EMP-%05d', $max + 1);
    }
}
