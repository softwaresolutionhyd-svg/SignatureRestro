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

    /** Match employee ID (employee_no) or name — partial, case-insensitive on name. */
    public function scopeMatchingSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $sub) use ($like, $term) {
            $sub->where('employee_no', 'like', $like)
                ->orWhere('name', 'like', $like);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function filterRowsByEmployeeSearch(array $rows, string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return $rows;
        }

        $needle = mb_strtolower($term, 'UTF-8');

        return array_values(array_filter($rows, function (array $row) use ($needle) {
            $id = mb_strtolower((string) ($row['employee_no'] ?? ''), 'UTF-8');
            $name = mb_strtolower((string) ($row['name'] ?? ''), 'UTF-8');

            return str_contains($id, $needle) || str_contains($name, $needle);
        }));
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
