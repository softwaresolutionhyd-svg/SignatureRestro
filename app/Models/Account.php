<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id',
        'description',
        'active',
        'is_system',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_ASSET => 'Asset',
            self::TYPE_LIABILITY => 'Liability',
            self::TYPE_EQUITY => 'Equity',
            self::TYPE_INCOME => 'Income',
            self::TYPE_EXPENSE => 'Expense',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** Normal balance side for trial balance display. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE], true);
    }

    /** Posted balance from journal lines (debit − credit for debit-normal accounts). */
    public function postedBalance(): float
    {
        $debit = (float) $this->journalLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', JournalEntry::STATUS_POSTED))
            ->sum('debit');

        $credit = (float) $this->journalLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', JournalEntry::STATUS_POSTED))
            ->sum('credit');

        $net = round($debit - $credit, 2);

        return $this->isDebitNormal() ? $net : round($credit - $debit, 2);
    }
}
