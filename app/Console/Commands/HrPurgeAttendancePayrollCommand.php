<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes attendance marks and payroll (including paid) sample/transaction data.
 * Keeps employees, designations, staff categories, leave types, and loan master records.
 */
class HrPurgeAttendancePayrollCommand extends Command
{
    protected $signature = 'hr:purge-attendance-payroll
        {--force : Skip confirmation (required in non-interactive mode)}
        {--company= : If set, only rows for this company_id}';

    protected $description = 'Delete all attendance + payroll entries (and related salary journals/expenses). Employees kept.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm(
                    'This will DELETE employee_attendances, payroll_entries, payroll salary expenses, and payroll journals. Employees stay. Continue?'
                )) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive: run with --force to confirm.');

                return self::FAILURE;
            }
        }

        $conn = DB::connection('tenant');
        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;

        try {
            $conn->transaction(function () use ($conn, $companyId) {
                Schema::connection('tenant')->withoutForeignKeyConstraints(function () use ($conn, $companyId) {
                    $scopeCompany = function ($q, string $table) use ($companyId) {
                        if ($companyId !== null && Schema::connection('tenant')->hasColumn($table, 'company_id')) {
                            $q->where('company_id', $companyId);
                        }
                    };

                    $payrollIds = [];
                    if (Schema::connection('tenant')->hasTable('payroll_entries')) {
                        $q = $conn->table('payroll_entries');
                        $scopeCompany($q, 'payroll_entries');
                        $payrollIds = $q->pluck('id')->map(fn ($id) => (int) $id)->all();
                    }

                    if (Schema::connection('tenant')->hasTable('credit_ledger')
                        && Schema::connection('tenant')->hasColumn('credit_ledger', 'payroll_entry_id')) {
                        $q = $conn->table('credit_ledger')->whereNotNull('payroll_entry_id');
                        if ($payrollIds !== []) {
                            $q->whereIn('payroll_entry_id', $payrollIds);
                        } elseif ($companyId !== null) {
                            $scopeCompany($q, 'credit_ledger');
                        }
                        $n = $q->delete();
                        $this->line("Removed {$n} payroll-linked credit ledger row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('expenses')) {
                        $q = $conn->table('expenses')->where(function ($inner) {
                            $inner->where('notes', 'like', 'payroll_entry:%')
                                ->orWhere('description', 'like', '%salary paid%');
                        });
                        $scopeCompany($q, 'expenses');
                        $ne = $q->delete();
                        $this->line("Removed {$ne} salary expense row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('journal_entries')
                        && Schema::connection('tenant')->hasTable('journal_entry_lines')) {
                        $ids = $conn->table('journal_entries')
                            ->whereIn('source', ['payroll', 'payroll_paid', 'salary'])
                            ->pluck('id')
                            ->all();
                        if ($ids !== []) {
                            $nl = $conn->table('journal_entry_lines')->whereIn('journal_entry_id', $ids)->delete();
                            $nj = $conn->table('journal_entries')->whereIn('id', $ids)->delete();
                            $this->line("Removed {$nj} payroll journal entr(y/ies), {$nl} line(s).");
                        }
                    }

                    if (Schema::connection('tenant')->hasTable('employee_attendances')) {
                        $q = $conn->table('employee_attendances');
                        $scopeCompany($q, 'employee_attendances');
                        $na = $q->delete();
                        $this->line("Removed {$na} attendance row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('payroll_entries')) {
                        $q = $conn->table('payroll_entries');
                        $scopeCompany($q, 'payroll_entries');
                        $np = $q->delete();
                        $this->line("Removed {$np} payroll entr(y/ies).");
                    }

                    if (Schema::connection('tenant')->hasTable('sync_queue')) {
                        $nsq = $conn->table('sync_queue')
                            ->whereIn('table_name', [
                                'employee_attendances',
                                'payroll_entries',
                                'expenses',
                                'journal_entries',
                                'journal_entry_lines',
                                'credit_ledger',
                            ])
                            ->delete();
                        $this->line("Removed {$nsq} sync queue row(s).");
                    }
                });
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Attendance + payroll/salary-paid data deleted. Employees kept.');

        return self::SUCCESS;
    }
}
