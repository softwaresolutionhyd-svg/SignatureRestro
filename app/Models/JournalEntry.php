<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'entry_number',
        'entry_date',
        'reference',
        'description',
        'status',
        'source',
        'source_id',
        'posted_at',
        'posted_by',
        'total_debit',
        'total_credit',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';

    /** @return array<string, array{label: string, color: string}> */
    public static function statusLabel(): array
    {
        return [
            self::STATUS_DRAFT => ['label' => 'Draft', 'color' => 'secondary'],
            self::STATUS_POSTED => ['label' => 'Posted', 'color' => 'success'],
        ];
    }

    /** Display order for source groups on the journal index. */
    public static function sourceOrder(): array
    {
        return ['expense', 'purchase', 'pos', 'payroll', 'credit_book', 'manual'];
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            'expense' => 'Expense',
            'purchase' => 'Purchase',
            'pos' => 'POS',
            'payroll' => 'Payroll',
            'credit_book' => 'Credit Book',
            'manual' => 'Manual',
        ];
    }

    public static function sourceLabel(?string $source): string
    {
        $key = strtolower(trim((string) $source));
        if ($key === '') {
            return 'Other';
        }

        return self::sourceLabels()[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('sort_order');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function recalculateTotals(): void
    {
        $this->total_debit = round((float) $this->lines()->sum('debit'), 2);
        $this->total_credit = round((float) $this->lines()->sum('credit'), 2);
    }

    public function isBalanced(): bool
    {
        return round((float) $this->total_debit, 2) === round((float) $this->total_credit, 2)
            && (float) $this->total_debit > 0;
    }
}
