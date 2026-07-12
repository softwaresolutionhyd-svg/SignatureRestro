<?php

namespace App\Services;

use App\Models\PosCashMovement;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Support\PosRuntimeSchema;

final class PosSessionSummaryService
{
    public function heldCount(PosSession $session): int
    {
        return (int) PosOrder::query()
            ->where('session_id', $session->id)
            ->where('status', 'draft')
            ->count();
    }

    /**
     * @return array{
     *   held_count:int,
     *   can_close_session:bool,
     *   sales_count:int,
     *   sales_total:float,
     *   refunds_count:int,
     *   refunds_total:float,
     *   credit_sales_count:int,
     *   credit_sales_total:float,
     *   discount_total:float,
     *   service_charge_total:float,
     *   tax_total:float,
     *   net_sales_total:float,
     *   payments_cash:float,
     *   payments_card:float,
     *   payments_bank:float
     * }
     */
    public function stats(PosSession $session): array
    {
        PosRuntimeSchema::ensureForSessionSummary();

        $heldCount = $this->heldCount($session);
        $sessionId = $session->id;
        $paid = PosOrder::query()->where('session_id', $sessionId)->where('status', 'paid');

        $salesCount = (int) (clone $paid)->where('type', 'sale')->count();
        $salesTotal = (float) (clone $paid)->where('type', 'sale')->sum('grand_total');

        $refundsCount = (int) (clone $paid)->where('type', 'refund')->count();
        $refundsTotal = (float) (clone $paid)->where('type', 'refund')->sum('grand_total');

        $creditCount = (int) (clone $paid)->where('type', 'sale')->where('is_credit', true)->count();
        $creditTotal = (float) (clone $paid)->where('type', 'sale')->where('is_credit', true)->sum('grand_total');

        $saleOrders = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'paid')
            ->where('type', 'sale');

        $discountTotal = (float) (clone $saleOrders)->sum('discount_total');
        $serviceChargeTotal = $this->sumOrderColumn($saleOrders, 'service_charge_total');
        $taxTotal = (float) (clone $saleOrders)->sum('tax_total');

        $salePayTotals = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $sessionId)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->selectRaw('pos_payments.method as payment_method, SUM(pos_payments.amount) as total')
            ->groupBy('pos_payments.method')
            ->pluck('total', 'payment_method');

        $refundPayTotals = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $sessionId)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'refund')
            ->selectRaw('pos_payments.method as payment_method, SUM(pos_payments.amount) as total')
            ->groupBy('pos_payments.method')
            ->pluck('total', 'payment_method');

        $net = static function (string $m) use ($salePayTotals, $refundPayTotals): float {
            return (float) (($salePayTotals[$m] ?? 0) - ($refundPayTotals[$m] ?? 0));
        };

        return [
            'held_count' => $heldCount,
            'can_close_session' => $heldCount === 0,
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'refunds_count' => $refundsCount,
            'refunds_total' => $refundsTotal,
            'credit_sales_count' => $creditCount,
            'credit_sales_total' => $creditTotal,
            'discount_total' => round($discountTotal, 2),
            'service_charge_total' => round($serviceChargeTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'net_sales_total' => round($salesTotal - $refundsTotal, 2),
            'payments_cash' => $net('cash'),
            'payments_card' => $net('card'),
            'payments_bank' => $net('bank'),
        ];
    }

    /**
     * @return array{opening_cash: float, cash_from_sales: float, cash_refunds_paid: float, cash_in: float, cash_out: float, expected_closing: float}
     */
    public function cashBreakdown(PosSession $session): array
    {
        $cashFromSales = (float) PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'sale')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashRefundsPaid = (float) PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', 'refund')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashIn = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'in')->sum('amount');
        $cashOut = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'out')->sum('amount');

        $opening = (float) $session->opening_cash;
        $expected = round($opening + $cashFromSales - $cashRefundsPaid + $cashIn - $cashOut, 2);

        return [
            'opening_cash' => $opening,
            'cash_from_sales' => $cashFromSales,
            'cash_refunds_paid' => $cashRefundsPaid,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_closing' => $expected,
        ];
    }

    /**
     * @return array{stats: array<string, mixed>, cash: array<string, mixed>, amount_to_collect: float}
     */
    public function summaryPayload(PosSession $session): array
    {
        $stats = $this->stats($session);
        $cash = $this->cashBreakdown($session);
        $amountToCollect = round(
            $stats['payments_cash'] + $cash['cash_in'] - $cash['cash_out'],
            2
        );

        return [
            'stats' => $stats,
            'cash' => $cash,
            'amount_to_collect' => $amountToCollect,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\PosOrder>  $query
     */
    private function sumOrderColumn($query, string $column): float
    {
        if (! PosRuntimeSchema::ordersHasColumn($column)) {
            PosRuntimeSchema::ensureServiceChargeColumns();
        }

        if (! PosRuntimeSchema::ordersHasColumn($column)) {
            return 0.0;
        }

        return (float) (clone $query)->sum($column);
    }
}
