<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosPurgeBillsCommand extends Command
{
    protected $signature = 'pos:purge-bills {--force : Skip confirmation}';

    protected $description = 'Delete all POS orders (pending + paid), items, payments, cash movements, sessions, and POS journals. Keeps products/tables.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm('Delete ALL POS bills, payments, order lines, cash movements, sessions, and POS journals? Products/tables stay.')) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive: run with --force');

                return self::FAILURE;
            }
        }

        if (! Schema::connection('tenant')->hasTable('pos_orders')
            && ! Schema::connection('tenant')->hasTable('pos_sessions')) {
            $this->warn('No POS tables — nothing to delete.');

            return self::SUCCESS;
        }

        try {
            DB::connection('tenant')->transaction(function () {
                Schema::connection('tenant')->withoutForeignKeyConstraints(function () {
                    if (Schema::connection('tenant')->hasTable('credit_ledger')) {
                        $n = DB::connection('tenant')->table('credit_ledger')->whereNotNull('pos_order_id')->delete();
                        $this->line("Removed {$n} credit ledger row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('journal_entries')
                        && Schema::connection('tenant')->hasTable('journal_entry_lines')) {
                        $posEntryIds = DB::connection('tenant')->table('journal_entries')
                            ->where('source', 'pos')
                            ->pluck('id')
                            ->all();
                        if ($posEntryIds !== []) {
                            $nl = DB::connection('tenant')->table('journal_entry_lines')
                                ->whereIn('journal_entry_id', $posEntryIds)
                                ->delete();
                            $ne = DB::connection('tenant')->table('journal_entries')
                                ->whereIn('id', $posEntryIds)
                                ->delete();
                            $this->line("Removed {$ne} POS journal entr(y/ies), {$nl} line(s).");
                        }
                    }

                    if (Schema::connection('tenant')->hasTable('pos_payments')) {
                        $n = DB::connection('tenant')->table('pos_payments')->delete();
                        $this->line("Removed {$n} payment row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('pos_order_items')) {
                        $n = DB::connection('tenant')->table('pos_order_items')->delete();
                        $this->line("Removed {$n} order line row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('pos_orders')) {
                        $n = DB::connection('tenant')->table('pos_orders')->delete();
                        $this->line("Removed {$n} bill/order row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('pos_cash_movements')) {
                        $n = DB::connection('tenant')->table('pos_cash_movements')->delete();
                        $this->line("Removed {$n} cash movement row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('pos_sessions')) {
                        $n = DB::connection('tenant')->table('pos_sessions')->delete();
                        $this->line("Removed {$n} register session row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('sync_queue')) {
                        $n = DB::connection('tenant')->table('sync_queue')
                            ->whereIn('table_name', [
                                'pos_orders',
                                'pos_order_items',
                                'pos_payments',
                                'pos_sessions',
                                'pos_cash_movements',
                            ])
                            ->delete();
                        $this->line("Removed {$n} sync queue row(s) for POS tables.");
                    }
                });
            });
        } catch (\Throwable $e) {
            $this->error('Delete failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('POS fresh: all bills/sessions cleared. Products and tables kept.');

        return self::SUCCESS;
    }
}
