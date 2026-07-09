<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_ANNUAL = 'annual';

    public const TYPE_SICK = 'sick';

    public const TYPE_UNPAID = 'unpaid';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'employee_id',
        'user_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => ['label' => 'Pending', 'color' => 'warning'],
            self::STATUS_APPROVED => ['label' => 'Approved', 'color' => 'success'],
            self::STATUS_REJECTED => ['label' => 'Rejected', 'color' => 'danger'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'secondary'],
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ANNUAL => 'Annual leave',
            self::TYPE_SICK => 'Sick leave',
            self::TYPE_UNPAID => 'Unpaid leave',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public static function countWeekdays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (! $date->isWeekend()) {
                $days++;
            }
        }

        return max(1, $days);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
