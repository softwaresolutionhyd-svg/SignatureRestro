<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\InventoryMove;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollEntry;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoJournalService
{
    /** @var array<string, string> */
    private const ACCOUNT_CODES = [
        'cash' => '1000',
        'bank' => '1100',
        'ar' => '1200',
        'inventory' => '1300',
        'ap' => '2000',
        'sales' => '4000',
        'cogs' => '5000',
        'operating_expense' => '5100',
        'payroll' => '5200',
    ];

    public function __construct(
        private readonly DefaultChartOfAccounts $defaultChart
    ) {}

    public function postPosSale(PosOrder $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $order->loadMissing(['payments']);

        DB::afterCommit(function () use ($order) {
            $fresh = PosOrder::query()->with('payments')->find($order->id);
            if (! $fresh || $fresh->status !== 'paid') {
                return;
            }

            $this->postPosSaleEntry($fresh);
        });
    }

    /** Immediate post (no afterCommit) — backfill / repair. */
    public function backfillPosSale(PosOrder $order): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $order->loadMissing(['payments']);
        if ($order->status !== 'paid') {
            return false;
        }

        $before = JournalEntry::query()->where('source', 'pos')->where('source_id', $order->id)->exists();
        $this->postPosSaleEntry($order);

        return ! $before && JournalEntry::query()->where('source', 'pos')->where('source_id', $order->id)->exists();
    }

    public function postExpensePaid(Expense $expense): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($expense) {
            $fresh = Expense::query()->find($expense->id);
            if (! $fresh || $fresh->status !== Expense::STATUS_PAID) {
                return;
            }

            $amount = round((float) $fresh->grand_total, 2);
            if ($amount <= 0) {
                return;
            }

            $this->createPostedEntry(
                source: 'expense',
                sourceId: (int) $fresh->id,
                reference: 'EXP-'.$fresh->id.'-PAY',
                description: 'Expense paid — '.$fresh->description,
                entryDate: $fresh->paid_at?->toDateString() ?? now()->toDateString(),
                lines: [
                    ['code' => self::ACCOUNT_CODES['operating_expense'], 'debit' => $amount, 'description' => $fresh->description],
                    ['code' => self::ACCOUNT_CODES['cash'], 'credit' => $amount, 'description' => 'Cash payment'],
                ],
            );
        });
    }

    public function postPurchaseReceived(PurchaseOrder $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($order) {
            $fresh = PurchaseOrder::query()->find($order->id);
            if (! $fresh || $fresh->status !== 'received') {
                return;
            }

            $this->postPurchaseReceivedEntry($fresh);
        });
    }

    public function backfillPurchaseReceived(PurchaseOrder $order): bool
    {
        if (! $this->isEnabled() || $order->status !== 'received') {
            return false;
        }

        $ref = $order->number.'-RECV';
        $before = $this->entryExists('purchase', (int) $order->id, $ref);
        $this->postPurchaseReceivedEntry($order);

        return ! $before && $this->entryExists('purchase', (int) $order->id, $ref);
    }

    public function postPurchasePaid(PurchaseOrder $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($order) {
            $fresh = PurchaseOrder::query()->find($order->id);
            if (! $fresh || $fresh->purchase_type !== 'credit' || $fresh->payment_status !== 'paid') {
                return;
            }

            $this->postPurchasePaidEntry($fresh);
        });
    }

    public function backfillPurchasePaid(PurchaseOrder $order): bool
    {
        if (! $this->isEnabled() || $order->purchase_type !== 'credit' || $order->payment_status !== 'paid') {
            return false;
        }

        $ref = $order->number.'-PAY';
        $before = $this->entryExists('purchase', (int) $order->id, $ref);
        $this->postPurchasePaidEntry($order);

        return ! $before && $this->entryExists('purchase', (int) $order->id, $ref);
    }

    public function postPayrollPaid(PayrollEntry $entry): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::afterCommit(function () use ($entry) {
            $fresh = PayrollEntry::query()->with('employee:id,name')->find($entry->id);
            if (! $fresh || $fresh->status !== 'paid') {
                return;
            }

            $this->postPayrollPaidEntry($fresh);
        });
    }

    /**
     * Credit Book payment → clear AR (customer) or AP (vendor purchase).
     * @return list<string> journal references created
     */
    public function postCreditBookPayment(\App\Models\CreditLedger $entry): array
    {
        if (! $this->isEnabled() || $entry->type !== 'payment') {
            return [];
        }

        $amount = round((float) $entry->amount, 2);
        if ($amount <= 0) {
            return [];
        }

        $created = [];
        $entryDate = $entry->entry_date?->toDateString() ?? now()->toDateString();

        // Payroll salary deduction: AR clear is posted inside postPayrollPaidEntry (with net pay).
        // Historical rows without that link are repaired by accounts:repair-journals.
        if ($entry->payroll_entry_id) {
            return [];
        }

        // Purchase-linked payment clears AP
        if ($entry->purchase_order_id) {
            $ref = 'CB-'.$entry->id.'-AP-PAY';
            // Prefer standard purchase PAY reference if PO already marked paid
            $po = PurchaseOrder::query()->find($entry->purchase_order_id);
            if ($po && $po->payment_status === 'paid') {
                $payRef = $po->number.'-PAY';
                if (! $this->entryExists('purchase', (int) $po->id, $payRef)) {
                    $this->postPurchasePaidEntry($po);
                    if ($this->entryExists('purchase', (int) $po->id, $payRef)) {
                        $created[] = $payRef;
                    }
                }

                return $created;
            }

            $je = $this->createPostedEntry(
                source: 'credit_book',
                sourceId: (int) $entry->id,
                reference: $ref,
                description: 'Vendor payment — '.$entry->description,
                entryDate: $entryDate,
                lines: [
                    ['code' => self::ACCOUNT_CODES['ap'], 'debit' => $amount, 'description' => 'Clear accounts payable'],
                    ['code' => self::ACCOUNT_CODES['cash'], 'credit' => $amount, 'description' => 'Cash to vendor'],
                ],
            );
            if ($je) {
                $created[] = $ref;
            }

            return $created;
        }

        // Manual / POS-linked customer payment clears AR
        // Vendor contacts without a matched PO: still clear AP (manual vendor pay)
        $isVendorContact = \App\Models\PurchaseVendor::query()->where('contact_id', $entry->contact_id)->exists();
        if ($isVendorContact) {
            $ref = 'CB-'.$entry->id.'-AP-PAY';
            $je = $this->createPostedEntry(
                source: 'credit_book',
                sourceId: (int) $entry->id,
                reference: $ref,
                description: 'Vendor payment — '.$entry->description,
                entryDate: $entryDate,
                lines: [
                    ['code' => self::ACCOUNT_CODES['ap'], 'debit' => $amount, 'description' => 'Clear accounts payable'],
                    ['code' => self::ACCOUNT_CODES['cash'], 'credit' => $amount, 'description' => 'Cash to vendor'],
                ],
            );
            if ($je) {
                $created[] = $ref;
            }

            return $created;
        }

        $ref = 'CB-'.$entry->id.'-AR-PAY';
        $je = $this->createPostedEntry(
            source: 'credit_book',
            sourceId: (int) $entry->id,
            reference: $ref,
            description: 'Customer credit payment — '.$entry->description,
            entryDate: $entryDate,
            lines: [
                ['code' => self::ACCOUNT_CODES['cash'], 'debit' => $amount, 'description' => 'Cash received'],
                ['code' => self::ACCOUNT_CODES['ar'], 'credit' => $amount, 'description' => 'Clear accounts receivable'],
            ],
        );
        if ($je) {
            $created[] = $ref;
        }

        return $created;
    }

    private function postPurchaseReceivedEntry(PurchaseOrder $order): void
    {
        $amount = round((float) $order->grand_total, 2);
        if ($amount <= 0) {
            return;
        }

        $creditAccount = $order->purchase_type === 'credit'
            ? self::ACCOUNT_CODES['ap']
            : self::ACCOUNT_CODES['cash'];

        $this->createPostedEntry(
            source: 'purchase',
            sourceId: (int) $order->id,
            reference: $order->number.'-RECV',
            description: 'Purchase received — '.$order->number,
            entryDate: $order->received_at?->toDateString() ?? now()->toDateString(),
            lines: [
                ['code' => self::ACCOUNT_CODES['inventory'], 'debit' => $amount, 'description' => 'Inventory received'],
                ['code' => $creditAccount, 'credit' => $amount, 'description' => $order->purchase_type === 'credit' ? 'Accounts payable' : 'Cash purchase'],
            ],
        );
    }

    private function postPurchasePaidEntry(PurchaseOrder $order): void
    {
        $amount = round((float) $order->grand_total, 2);
        if ($amount <= 0) {
            return;
        }

        $this->createPostedEntry(
            source: 'purchase',
            sourceId: (int) $order->id,
            reference: $order->number.'-PAY',
            description: 'Purchase payment — '.$order->number,
            entryDate: $order->paid_at?->toDateString() ?? now()->toDateString(),
            lines: [
                ['code' => self::ACCOUNT_CODES['ap'], 'debit' => $amount, 'description' => 'Clear accounts payable'],
                ['code' => self::ACCOUNT_CODES['cash'], 'credit' => $amount, 'description' => 'Vendor payment'],
            ],
        );
    }

    private function postPayrollPaidEntry(PayrollEntry $fresh): void
    {
        $netPay = round((float) $fresh->net_pay, 2);
        $foodBill = round((float) ($fresh->food_bill ?? 0), 2);
        if ($netPay <= 0 && $foodBill <= 0) {
            return;
        }

        $employeeName = $fresh->employee?->name ?? 'Employee';
        $lines = [];

        // Payroll expense = cash paid + food bill settled against AR
        $payrollExpense = round($netPay + max(0, $foodBill), 2);
        if ($payrollExpense > 0) {
            $lines[] = [
                'code' => self::ACCOUNT_CODES['payroll'],
                'debit' => $payrollExpense,
                'description' => $employeeName,
            ];
        }
        if ($netPay > 0) {
            $lines[] = [
                'code' => self::ACCOUNT_CODES['cash'],
                'credit' => $netPay,
                'description' => 'Salary payment',
            ];
        }
        if ($foodBill > 0) {
            $lines[] = [
                'code' => self::ACCOUNT_CODES['ar'],
                'credit' => $foodBill,
                'description' => 'Food bill deducted from salary',
            ];
        }

        $this->createPostedEntry(
            source: 'payroll',
            sourceId: (int) $fresh->id,
            reference: 'PAYROLL-'.$fresh->period.'-'.$fresh->employee_id,
            description: 'Payroll paid — '.$employeeName.' ('.$fresh->period.')',
            entryDate: $fresh->paid_at?->toDateString() ?? now()->toDateString(),
            lines: $lines,
        );
    }

    private function postPosSaleEntry(PosOrder $order): void
    {
        if ($this->entryExists('pos', (int) $order->id, $order->order_no)) {
            return;
        }

        $amount = round(abs((float) $order->grand_total), 2);
        if ($amount <= 0) {
            return;
        }

        $isRefund = $order->type === 'refund';
        $lines = [];

        if ($order->is_credit) {
            $lines[] = [
                'code' => self::ACCOUNT_CODES['ar'],
                'debit' => $isRefund ? 0 : $amount,
                'credit' => $isRefund ? $amount : 0,
                'description' => 'Credit sale — '.$order->order_no,
            ];
        } else {
            foreach ($order->payments as $payment) {
                /** @var PosPayment $payment */
                $payAmount = round((float) $payment->amount, 2);
                if ($payAmount <= 0) {
                    continue;
                }

                $assetCode = $payment->method === 'cash'
                    ? self::ACCOUNT_CODES['cash']
                    : self::ACCOUNT_CODES['bank'];

                $lines[] = [
                    'code' => $assetCode,
                    'debit' => $isRefund ? 0 : $payAmount,
                    'credit' => $isRefund ? $payAmount : 0,
                    'description' => strtoupper((string) $payment->method).' payment',
                ];
            }
        }

        $lines[] = [
            'code' => self::ACCOUNT_CODES['sales'],
            'debit' => $isRefund ? $amount : 0,
            'credit' => $isRefund ? 0 : $amount,
            'description' => ($isRefund ? 'Sales refund' : 'Sales revenue').' — '.$order->order_no,
        ];

        $cogsAmount = round((float) InventoryMove::query()
            ->where('reference', $order->order_no)
            ->where('type', $isRefund ? 'in' : 'out')
            ->sum('total_cost'), 2);

        if ($cogsAmount > 0) {
            $lines[] = [
                'code' => self::ACCOUNT_CODES['cogs'],
                'debit' => $isRefund ? 0 : $cogsAmount,
                'credit' => $isRefund ? $cogsAmount : 0,
                'description' => 'Cost of goods sold',
            ];
            $lines[] = [
                'code' => self::ACCOUNT_CODES['inventory'],
                'debit' => $isRefund ? $cogsAmount : 0,
                'credit' => $isRefund ? 0 : $cogsAmount,
                'description' => 'Inventory movement',
            ];
        }

        $this->createPostedEntry(
            source: 'pos',
            sourceId: (int) $order->id,
            reference: $order->order_no,
            description: ($isRefund ? 'POS refund' : 'POS sale').' — '.$order->order_no,
            entryDate: $order->paid_at?->toDateString() ?? now()->toDateString(),
            lines: $lines,
        );
    }

    /**
     * @param  list<array{code: string, debit?: float, credit?: float, description?: string}>  $lines
     */
    public function createPostedEntryPublic(
        string $source,
        int $sourceId,
        string $reference,
        string $description,
        string $entryDate,
        array $lines,
    ): ?JournalEntry {
        return $this->createPostedEntry($source, $sourceId, $reference, $description, $entryDate, $lines);
    }

    /**
     * @param  list<array{code: string, debit?: float, credit?: float, description?: string}>  $lines
     */
    private function createPostedEntry(
        string $source,
        int $sourceId,
        string $reference,
        string $description,
        string $entryDate,
        array $lines,
    ): ?JournalEntry {
        $companyId = current_company_id();
        if ($companyId === null) {
            return null;
        }

        $this->defaultChart->ensureForCompany($companyId);

        if ($this->entryExists($source, $sourceId, $reference)) {
            return null;
        }

        $resolvedLines = [];
        foreach ($lines as $row) {
            $debit = round(max(0, (float) ($row['debit'] ?? 0)), 2);
            $credit = round(max(0, (float) ($row['credit'] ?? 0)), 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $account = Account::query()->where('code', $row['code'])->where('active', true)->first();
            if (! $account) {
                Log::warning('Auto journal skipped: account missing', ['code' => $row['code'], 'reference' => $reference]);

                return null;
            }

            $resolvedLines[] = [
                'account_id' => $account->id,
                'description' => $row['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($resolvedLines) < 2) {
            return null;
        }

        $totalDebit = round(array_sum(array_column($resolvedLines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($resolvedLines, 'credit')), 2);
        if ($totalDebit !== $totalCredit || $totalDebit <= 0) {
            Log::warning('Auto journal skipped: unbalanced entry', [
                'reference' => $reference,
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ]);

            return null;
        }

        return DB::connection('tenant')->transaction(function () use ($source, $sourceId, $reference, $description, $entryDate, $resolvedLines, $totalDebit, $totalCredit) {
            if ($this->entryExists($source, $sourceId, $reference)) {
                return null;
            }

            $entry = JournalEntry::create([
                'entry_number' => $this->nextEntryNumber(),
                'entry_date' => $entryDate,
                'reference' => $reference,
                'description' => $description,
                'status' => JournalEntry::STATUS_POSTED,
                'source' => $source,
                'source_id' => $sourceId,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            foreach ($resolvedLines as $i => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'sort_order' => $i,
                ]);
            }

            return $entry;
        });
    }

    private function entryExists(string $source, int $sourceId, string $reference): bool
    {
        return JournalEntry::query()
            ->where('source', $source)
            ->where('source_id', $sourceId)
            ->where('reference', $reference)
            ->exists();
    }

    private function nextEntryNumber(): string
    {
        $last = JournalEntry::query()
            ->where('entry_number', 'like', 'JE-%')
            ->orderByDesc('id')
            ->value('entry_number');

        $seq = 1;
        if ($last && preg_match('/JE-(\d+)/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'JE-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    private function isEnabled(): bool
    {
        return Setting::get('accounts_auto_journal', '1') === '1';
    }
}
