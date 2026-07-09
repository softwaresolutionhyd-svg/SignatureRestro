<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosPurgeBillsCommand extends Command
{
    protected $signature = 'pos:purge-bills {--force : Skip confirmation}';

    protected $description = 'Delete all POS orders (pending + paid bills), items, payments, and sessions';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm('Delete ALL POS bills, payments, order lines, and register sessions?')) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive: run with --force');

                return self::FAILURE;
            }
        }

        if (! Schema::connection('tenant')->hasTable('pos_orders')) {
            $this->warn('No pos_orders table — nothing to delete.');

            return self::SUCCESS;
        }

        try {
            DB::connection('tenant')->transaction(function () {
                Schema::connection('tenant')->withoutForeignKeyConstraints(function () {
                    if (Schema::connection('tenant')->hasTable('credit_ledger')) {
                        $n = DB::connection('tenant')->table('credit_ledger')->whereNotNull('pos_order_id')->delete();
                        $this->line("Removed {$n} credit ledger row(s).");
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
                });
            });
        } catch (\Throwable $e) {
            $this->error('Delete failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('All POS bills and sessions deleted.');

        return self::SUCCESS;
    }
}
