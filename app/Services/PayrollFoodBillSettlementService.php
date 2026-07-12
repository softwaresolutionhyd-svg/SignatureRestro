<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\PayrollEntry;
use App\Support\EnsuresPayrollSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PayrollFoodBillSettlementService
{
    use EnsuresPayrollSchema;

    public function __construct(
        private readonly EmployeeContactSyncService $contactSync,
    ) {}

    /** Record food bill as credit payment when deducted in payroll. */
    public function settle(PayrollEntry $entry, ?int $userId = null): void
    {
        $this->ensurePayrollSchema();
        $this->ensureCreditLedgerPayrollColumn();

        $amount = round((float) ($entry->food_bill ?? 0), 2);
        if ($amount <= 0) {
            $this->removeSettlement($entry);

            return;
        }

        $entry->loadMissing('employee');
        $employee = $entry->employee;
        if ($employee === null) {
            return;
        }

        $this->contactSync->ensureContactForEmployee($employee);
        $employee->refresh();

        if (! $employee->contact_id) {
            return;
        }

        $contact = Contact::query()->find($employee->contact_id);
        if ($contact === null) {
            return;
        }

        $entryDate = $entry->paid_at
            ? $entry->paid_at->toDateString()
            : Carbon::createFromFormat('Y-m-d', $entry->period.'-01')->endOfMonth()->toDateString();

        $running = (float) $contact->creditLedger()->where('type', 'credit')->sum('amount')
            - (float) $contact->creditLedger()->where('type', 'payment')->sum('amount');

        $existing = CreditLedger::query()
            ->where('payroll_entry_id', $entry->id)
            ->where('type', 'payment')
            ->first();

        if ($existing !== null) {
            if ((float) $existing->amount === $amount) {
                return;
            }

            $running += (float) $existing->amount;
        }

        $balanceAfter = round($running - $amount, 2);
        $createdBy = $this->resolveCreatedBy($userId, $entry);

        CreditLedger::withoutGlobalScopes()->updateOrCreate(
            [
                'payroll_entry_id' => $entry->id,
                'type' => 'payment',
            ],
            [
                'company_id' => (int) ($contact->company_id ?? $employee->company_id ?? 0),
                'contact_id' => (int) $employee->contact_id,
                'description' => 'Deduct From Salary',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'entry_date' => $entryDate,
                'notes' => 'Payroll '.$entry->period,
                'created_by' => $createdBy,
            ]
        );
    }

    private function resolveCreatedBy(?int $userId, PayrollEntry $entry): int
    {
        $users = CreditLedger::query()->getConnection()->table('users');

        foreach (array_filter([$userId, Auth::id(), $entry->created_by]) as $candidate) {
            if ($candidate && $users->where('id', $candidate)->exists()) {
                return (int) $candidate;
            }
        }

        return (int) ($users->orderBy('id')->value('id') ?? 0);
    }

    /** Backfill credit payments for payroll rows that already have food bill deducted. */
    public function syncUnsettledForPeriod(string $period, ?int $userId = null): void
    {
        PayrollEntry::query()
            ->where('period', $period)
            ->where('food_bill', '>', 0)
            ->orderBy('id')
            ->each(fn (PayrollEntry $entry) => $this->settle($entry, $userId));
    }

    private function removeSettlement(PayrollEntry $entry): void
    {
        $this->ensureCreditLedgerPayrollColumn();

        if (! Schema::connection('tenant')->hasColumn('credit_ledger', 'payroll_entry_id')) {
            return;
        }

        CreditLedger::query()
            ->where('payroll_entry_id', $entry->id)
            ->where('type', 'payment')
            ->delete();
    }

    private function ensureCreditLedgerPayrollColumn(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('credit_ledger') || ! $schema->hasTable('payroll_entries')) {
            return;
        }

        if ($schema->hasColumn('credit_ledger', 'payroll_entry_id')) {
            return;
        }

        $schema->table('credit_ledger', function ($table) {
            $table->unsignedBigInteger('payroll_entry_id')->nullable()->after('pos_order_id');
            $table->index('payroll_entry_id');
        });
    }
}
