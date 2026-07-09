<?php

namespace App\Console\Commands;

use App\Services\PosCreditLedgerSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncPosCreditLedgerCommand extends Command
{
    protected $signature = 'pos:sync-credit-ledger';

    protected $description = 'Create missing credit_ledger rows for paid POS credit sales.';

    public function handle(PosCreditLedgerSync $sync): int
    {
        if (! Schema::hasTable('credit_ledger')) {
            $this->error('Table credit_ledger does not exist. Run migrations.');

            return self::FAILURE;
        }

        $sync->syncMissing();
        $this->info('Done. Check Credit Book or contact ledgers.');

        return self::SUCCESS;
    }
}
