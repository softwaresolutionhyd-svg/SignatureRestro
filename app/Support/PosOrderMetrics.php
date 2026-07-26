<?php

namespace App\Support;

use App\Models\InventoryMove;
use App\Models\PosOrder;

final class PosOrderMetrics
{
    /** Σ (qty × factor × cost) per line; refunds subtract. */
    public static function cogsFromLoaded(PosOrder $order): float
    {
        $sign = $order->type === 'refund' ? -1.0 : 1.0;
        $sum = 0.0;

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $factor = $product->factorToBaseForUom((string) $item->uom);
            if ($factor === null || $factor <= 0) {
                continue;
            }

            $extCost = (float) $item->qty * $factor * (float) $product->cost;
            $sum += $sign * $extCost;
        }

        return round($sum, 2);
    }

    /**
     * Gross profit (pre-tax): order net sale (subtotal − discount) − COGS.
     * Uses order totals (not Σ line subtotals) so duplicate/orphan lines cannot inflate profit above income.
     */
    public static function grossProfitFromLoaded(PosOrder $order): float
    {
        $sign = $order->type === 'refund' ? -1.0 : 1.0;
        $netSale = (float) $order->subtotal - (float) $order->discount_total;
        $cogs = self::cogsFromLoaded($order); // already signed for refunds

        return round(($sign * $netSale) - $cogs, 2);
    }

    /** Signed POS revenue (grand total, refunds negative). */
    public static function signedGrandTotal(PosOrder $order): float
    {
        $sign = $order->type === 'refund' ? -1.0 : 1.0;

        return round($sign * (float) $order->grand_total, 2);
    }

    /**
     * Net FIFO component cost consumed via BoM on POS sales (out minus refund returns).
     *
     * @param  list<string>  $orderNos
     */
    public static function bomConsumptionCostForOrderNos(array $orderNos): float
    {
        if ($orderNos === []) {
            return 0.0;
        }

        $net = (float) InventoryMove::query()
            ->whereIn('reference', $orderNos)
            ->where('note', 'like', '%(BoM)%')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN COALESCE(total_cost, 0) WHEN type = ? THEN -ABS(COALESCE(total_cost, 0)) ELSE 0 END), 0) as net',
                ['out', 'in']
            )
            ->value('net');

        return round($net, 2);
    }
}
