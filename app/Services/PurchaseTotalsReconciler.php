<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Setting;
use App\Support\EnsuresPurchaseOrderLinePriceSchema;
use Illuminate\Support\Facades\Cache;

/**
 * Line TOTAL (invoice amount) is source of truth.
 * Unit price is derived as total ÷ qty with 6 decimals so qty × unit matches the bill.
 */
final class PurchaseTotalsReconciler
{
    use EnsuresPurchaseOrderLinePriceSchema;

    public const SETTING_KEY = 'purchase_totals_repaired_v2';

    public function __construct(
        private readonly PurchaseCreditLedgerService $purchaseCreditLedger
    ) {}

    public function ensureSchema(?string $connection = 'tenant'): void
    {
        $this->ensurePurchaseOrderLinePriceSchema($connection);
    }

    public function repairOnce(?string $connection = 'tenant'): void
    {
        $cacheKey = self::SETTING_KEY.':'.(string) $connection;
        if (Cache::get($cacheKey) === '1') {
            return;
        }

        try {
            if ((string) Setting::get(self::SETTING_KEY, '') === '1') {
                Cache::forever($cacheKey, '1');

                return;
            }
        } catch (\Throwable) {
        }

        try {
            $this->repairAll($connection);
            Cache::forever($cacheKey, '1');
            Setting::set(self::SETTING_KEY, '1');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{orders:int,lines:int,headers:int}
     */
    public function repairAll(?string $connection = 'tenant'): array
    {
        $this->ensureSchema($connection);

        $ordersFixed = 0;
        $linesFixed = 0;
        $headersFixed = 0;

        PurchaseOrder::withoutGlobalScope('company')
            ->with(['lines' => fn ($q) => $q->withoutGlobalScope('company')])
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$ordersFixed, &$linesFixed, &$headersFixed) {
                foreach ($orders as $order) {
                    $result = $this->repairOrder($order);
                    if ($result['lines'] > 0 || $result['header']) {
                        $ordersFixed++;
                    }
                    $linesFixed += $result['lines'];
                    if ($result['header']) {
                        $headersFixed++;
                    }
                }
            });

        return [
            'orders' => $ordersFixed,
            'lines' => $linesFixed,
            'headers' => $headersFixed,
        ];
    }

    /**
     * @return array{lines:int,header:bool}
     */
    public function repairOrder(PurchaseOrder $order): array
    {
        $order->loadMissing('lines');

        $linesTouched = 0;
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $grand = 0.0;

        foreach ($order->lines as $line) {
            $qty = round((float) $line->qty, 3);
            $taxPercent = round((float) $line->tax_percent, 3);
            $storedTotal = round((float) $line->total, 2);
            $storedSub = round((float) $line->subtotal, 2);
            $invoiceTotal = ($storedTotal > 0 || $storedSub > 0)
                ? ($storedTotal > 0 ? $storedTotal : $storedSub)
                : null;

            $amounts = PurchaseOrderLine::amountsFromInput(
                $qty,
                (float) $line->unit_price,
                $taxPercent,
                $invoiceTotal
            );

            $dirty = abs((float) $line->unit_price - $amounts['unit_price']) > 0.0000005
                || abs((float) $line->subtotal - $amounts['subtotal']) > 0.009
                || abs((float) $line->tax_amount - $amounts['tax_amount']) > 0.009
                || abs((float) $line->total - $amounts['total']) > 0.009;

            if ($dirty) {
                $line->update([
                    'unit_price' => $amounts['unit_price'],
                    'subtotal' => $amounts['subtotal'],
                    'tax_amount' => $amounts['tax_amount'],
                    'total' => $amounts['total'],
                ]);
                $linesTouched++;
            }

            $subtotal = round($subtotal + $amounts['subtotal'], 2);
            $taxTotal = round($taxTotal + $amounts['tax_amount'], 2);
            $grand = round($grand + $amounts['total'], 2);
        }

        $oldGrand = round((float) $order->grand_total, 2);
        $headerDirty = abs((float) $order->subtotal - $subtotal) > 0.009
            || abs((float) $order->tax_total - $taxTotal) > 0.009
            || abs($oldGrand - $grand) > 0.009;

        if ($headerDirty) {
            $order->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grand,
            ]);

            try {
                $this->purchaseCreditLedger->syncForOrder($order->fresh('vendor'));
            } catch (\Throwable) {
            }
        }

        return ['lines' => $linesTouched, 'header' => $headerDirty];
    }
}
