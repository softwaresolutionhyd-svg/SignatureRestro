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

    /** Record food bill as credit payment when payroll is marked paid. */
    public function settle(PayrollEntry $entry, ?int $userId = null): void
    {
        $this->ensurePayrollSchema();
        $this->ensureCreditLedgerPayrollColumn();

        $amount = round((float) ($entry->food_bill ?? 0), 2);
        if ($amount <= 0) {
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

        CreditLedger::updateOrCreate(
            [
                'payroll_entry_id' => $entry->id,
                'type' => 'payment',
            ],
            [
                'contact_id' => (int) $employee->contact_id,
                'description' => 'Deduct From Salary',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'entry_date' => $entryDate,
                'notes' => 'Payroll '.$entry->period,
                'created_by' => $userId ?? Auth::id() ?? 0,
            ]
        );
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
