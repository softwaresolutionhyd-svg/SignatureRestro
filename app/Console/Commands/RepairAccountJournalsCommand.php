<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CreditLedger;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollEntry;
use App\Models\PosOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVendor;
use App\Models\User;
use App\Services\AutoJournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepairAccountJournalsCommand extends Command
{
    protected $signature = 'accounts:repair-journals {--dry-run : Show fixes without writing} {--company= : Company id (default: first)}';

    protected $description = 'Backfill missing auto-journals and link Credit Book payments to AR/AP.';

    public function handle(AutoJournalService $journals): int
    {
        $companyId = $this->option('company')
            ? (int) $this->option('company')
            : (int) (Company::query()->orderBy('id')->value('id') ?? 0);

        if ($companyId <= 0) {
            $this->error('No company found.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', ['company_admin', 'admin', 'super_admin'])
            ->orderBy('id')
            ->first()
            ?? User::query()->where('company_id', $companyId)->orderBy('id')->first();

        if (! $user) {
            $this->error("No user for company {$companyId}.");

            return self::FAILURE;
        }

        Auth::login($user);
        session(['active_company_id' => $companyId]);
        $this->info("Company #{$companyId} as {$user->name} ({$user->role})");

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN — no changes' : 'Repairing account journals…');

        $fixed = 0;

        $paidOrders = PosOrder::query()->where('status', 'paid')->where('grand_total', '>', 0)->orderBy('id')->get();
        foreach ($paidOrders as $order) {
            $exists = JournalEntry::query()->where('source', 'pos')->where('source_id', $order->id)->exists();
            if ($exists) {
                continue;
            }
            $this->line("POS missing JE: {$order->order_no} ({$order->grand_total})");
            if (! $dry && $journals->backfillPosSale($order)) {
                $fixed++;
                $this->info("  + posted {$order->order_no}");
            } elseif (! $dry) {
                $this->warn("  ! failed {$order->order_no}");
            }
        }

        $received = PurchaseOrder::query()->where('status', 'received')->where('grand_total', '>', 0)->orderBy('id')->get();
        foreach ($received as $po) {
            $ref = $po->number.'-RECV';
            $exists = JournalEntry::query()->where('source', 'purchase')->where('source_id', $po->id)->where('reference', $ref)->exists();
            if ($exists) {
                continue;
            }
            $this->line("Purchase RECV missing JE: {$po->number} ({$po->grand_total})");
            if (! $dry && $journals->backfillPurchaseReceived($po)) {
                $fixed++;
                $this->info("  + posted {$ref}");
            } elseif (! $dry) {
                $this->warn("  ! failed {$ref}");
            }
        }

        $orphanPays = CreditLedger::query()
            ->where('type', 'payment')
            ->whereNull('purchase_order_id')
            ->whereNull('pos_order_id')
            ->whereNull('payroll_entry_id')
            ->orderBy('id')
            ->get();

        foreach ($orphanPays as $pay) {
            $vendorIds = PurchaseVendor::query()->where('contact_id', $pay->contact_id)->pluck('id');
            if ($vendorIds->isEmpty()) {
                continue;
            }

            $po = PurchaseOrder::query()
                ->whereIn('vendor_id', $vendorIds)
                ->where('purchase_type', 'credit')
                ->where('status', 'received')
                ->where(function ($q) {
                    $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
                })
                ->whereRaw('ABS(grand_total - ?) < 0.02', [(float) $pay->amount])
                ->orderBy('id')
                ->first();

            if (! $po) {
                continue;
            }

            $this->line("Link CB payment #{$pay->id} ({$pay->amount}) → {$po->number}");
            if ($dry) {
                continue;
            }

            DB::connection('tenant')->transaction(function () use ($pay, $po, $journals, &$fixed) {
                $pay->purchase_order_id = $po->id;
                $pay->save();

                $po->payment_status = 'paid';
                $po->paid_at = $pay->entry_date?->startOfDay() ?? now();
                $po->save();

                if ($journals->backfillPurchasePaid($po->fresh())) {
                    $fixed++;
                    $this->info("  + posted {$po->number}-PAY");
                } else {
                    $this->warn("  ! AP pay JE failed {$po->number}");
                }
            });
        }

        $payrollPays = CreditLedger::query()
            ->where('type', 'payment')
            ->whereNotNull('payroll_entry_id')
            ->orderBy('id')
            ->get();

        foreach ($payrollPays as $pay) {
            $ref = 'CB-'.$pay->id.'-AR-PAYROLL';
            if (JournalEntry::query()->where('reference', $ref)->exists()) {
                continue;
            }

            $payroll = PayrollEntry::query()->find($pay->payroll_entry_id);
            if (! $payroll) {
                continue;
            }
            $payrollRef = 'PAYROLL-'.$payroll->period.'-'.$payroll->employee_id;
            $alreadyCleared = JournalEntryLine::query()
                ->whereHas('journalEntry', fn ($q) => $q->where('reference', $payrollRef)->where('status', 'posted'))
                ->whereHas('account', fn ($q) => $q->where('code', '1200'))
                ->where('credit', '>', 0)
                ->exists();
            if ($alreadyCleared) {
                continue;
            }

            $amount = round((float) $pay->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $this->line("Payroll food bill AR clear: CB#{$pay->id} amount={$amount}");
            if ($dry) {
                continue;
            }

            $je = $journals->createPostedEntryPublic(
                source: 'credit_book',
                sourceId: (int) $pay->id,
                reference: $ref,
                description: 'Salary deduction clears receivable — '.$pay->description,
                entryDate: $pay->entry_date?->toDateString() ?? now()->toDateString(),
                lines: [
                    ['code' => '5200', 'debit' => $amount, 'description' => 'Food bill via payroll'],
                    ['code' => '1200', 'credit' => $amount, 'description' => 'Clear accounts receivable'],
                ],
            );
            if ($je) {
                $fixed++;
                $this->info("  + posted {$ref}");
            } else {
                $this->warn("  ! failed {$ref}");
            }
        }

        $linkedPays = CreditLedger::query()
            ->where('type', 'payment')
            ->whereNotNull('purchase_order_id')
            ->orderBy('id')
            ->get();
        foreach ($linkedPays as $pay) {
            $po = PurchaseOrder::query()->find($pay->purchase_order_id);
            if (! $po || $po->purchase_type !== 'credit') {
                continue;
            }
            if ($po->payment_status !== 'paid') {
                if (! $dry) {
                    $po->payment_status = 'paid';
                    $po->paid_at = $po->paid_at ?? $pay->entry_date?->startOfDay() ?? now();
                    $po->save();
                }
                $this->line("Mark {$po->number} paid from CB#{$pay->id}");
            }
            $payRef = $po->number.'-PAY';
            $exists = JournalEntry::query()->where('source', 'purchase')->where('source_id', $po->id)->where('reference', $payRef)->exists();
            if ($exists) {
                continue;
            }
            if (! $dry && $journals->backfillPurchasePaid($po->fresh())) {
                $fixed++;
                $this->info("AP payment JE: {$payRef}");
            }
        }

        $this->info($dry ? 'Dry run complete.' : "Done. New journals created: {$fixed}");

        return self::SUCCESS;
    }
}
