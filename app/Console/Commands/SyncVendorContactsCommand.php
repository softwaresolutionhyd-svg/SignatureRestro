<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\PurchaseCreditLedgerService;
use App\Services\VendorContactSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncVendorContactsCommand extends Command
{
    protected $signature = 'purchase:sync-vendor-contacts';

    protected $description = 'Add all vendors to contacts and backfill credit-book ledger for credit purchases.';

    public function handle(VendorContactSyncService $vendorContacts, PurchaseCreditLedgerService $creditLedger): int
    {
        if (! Schema::connection('tenant')->hasTable('purchase_vendors')) {
            $this->error('Table purchase_vendors does not exist.');

            return self::FAILURE;
        }

        $synced = $vendorContacts->syncAllVendors();
        $this->info("Vendors synced to contacts: {$synced}");

        $orders = PurchaseOrder::query()->orderBy('id')->get();
        $ledgerCount = 0;
        foreach ($orders as $order) {
            if ($order->purchase_type === 'credit' && $order->payment_status === 'paid') {
                $creditLedger->registerPayment($order);
                $ledgerCount++;
            } else {
                $creditLedger->syncForOrder($order);
                if ($order->purchase_type === 'credit') {
                    $ledgerCount++;
                }
            }
        }

        $this->info("Credit purchases reflected in credit book: {$ledgerCount}");
        $this->info('Done.');

        return self::SUCCESS;
    }
}
