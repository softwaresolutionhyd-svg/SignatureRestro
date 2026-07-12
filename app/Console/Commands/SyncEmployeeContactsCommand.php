<?php

namespace App\Console\Commands;

use App\Services\EmployeeContactSyncService;
use Illuminate\Console\Command;

class SyncEmployeeContactsCommand extends Command
{
    protected $signature = 'employees:sync-contacts';

    protected $description = 'Link all employees to Contacts (for credit / food bill payroll deduction).';

    public function handle(EmployeeContactSyncService $contactSync): int
    {
        $count = $contactSync->syncAllEmployees();
        $this->info("Synced {$count} employee(s) to Contacts.");

        return self::SUCCESS;
    }
}
