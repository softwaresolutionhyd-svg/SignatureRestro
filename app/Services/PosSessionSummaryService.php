<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\PosCashMovement;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Support\PosRuntimeSchema;

final class PosSessionSummaryService
{
    public function heldCount(PosSession $session): int
    {
        $pending = app(PosPendingBillsService::class);

        return $pending->countHeldDrafts($pending->billSessionIdsForSession($session));
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
     *   credit_sales_other_count:int,
     *   credit_sales_other_total:float,
     *   credit_sales_visitor_expense_count:int,
     *   credit_sales_visitor_expense_total:float,
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
        $creditSplit = $this->creditSalesSplitByVisitorExpense($sessionId);

        $saleOrders = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'paid')
            ->where('type', 'sale');

        $discountTotal = (float) (clone $saleOrders)->sum('discount_total');
        $serviceChargeTotal = $this->sumOrderColumn($saleOrders, 'service_charge_total');
        $taxTotal = (float) (clone $saleOrders)->sum('tax_total');

        $salePayTotals = $this->paymentTotalsByMethod($sessionId, 'sale');

        $refundPayTotals = $this->paymentTotalsByMethod($sessionId, 'refund');

        $net = static function (string $m) use ($salePayTotals, $refundPayTotals): float {
            return (float) (($salePayTotals[$m] ?? 0) - ($refundPayTotals[$m] ?? 0));
        };

        // Payments are grand_total (incl. service). Net sales excludes service — align Cash/Card/Bank.
        $payments = $this->excludeAmountFromPaymentMethods([
            'cash' => $net('cash'),
            'card' => $net('card'),
            'bank' => $net('bank'),
        ], $this->nonCreditSaleServiceChargeTotal($sessionId));

        return [
            'held_count' => $heldCount,
            'can_close_session' => $heldCount === 0,
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'refunds_count' => $refundsCount,
            'refunds_total' => $refundsTotal,
            'credit_sales_count' => $creditCount,
            'credit_sales_total' => $creditTotal,
            'credit_sales_other_count' => $creditSplit['other_count'],
            'credit_sales_other_total' => $creditSplit['other_total'],
            'credit_sales_visitor_expense_count' => $creditSplit['visitor_count'],
            'credit_sales_visitor_expense_total' => $creditSplit['visitor_total'],
            'discount_total' => round($discountTotal, 2),
            'service_charge_total' => round($serviceChargeTotal, 2),
            'tax_total' => round($taxTotal, 2),
            // Gross (grand_total) already net of discount; exclude service charges from net sales.
            'net_sales_total' => round($salesTotal - $refundsTotal - $serviceChargeTotal, 2),
            'payments_cash' => $payments['cash'],
            'payments_card' => $payments['card'],
            'payments_bank' => $payments['bank'],
        ];
    }

    /**
     * Split credit sales so closing can label only the Visitor Expense contact.
     *
     * @return array{other_count:int, other_total:float, visitor_count:int, visitor_total:float}
     */
    private function creditSalesSplitByVisitorExpense(int $sessionId): array
    {
        $empty = [
            'other_count' => 0,
            'other_total' => 0.0,
            'visitor_count' => 0,
            'visitor_total' => 0.0,
        ];

        if (! PosRuntimeSchema::ordersHasColumn('is_credit') || ! PosRuntimeSchema::ordersHasColumn('contact_id')) {
            return $empty;
        }

        $orders = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'paid')
            ->where('type', 'sale')
            ->where('is_credit', true)
            ->get(['id', 'contact_id', 'grand_total']);

        if ($orders->isEmpty()) {
            return $empty;
        }

        $contactNames = Contact::query()
            ->whereIn('id', $orders->pluck('contact_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $otherCount = 0;
        $otherTotal = 0.0;
        $visitorCount = 0;
        $visitorTotal = 0.0;

        foreach ($orders as $order) {
            $amount = round((float) $order->grand_total, 2);
            $name = $contactNames[(int) $order->contact_id] ?? null;
            if ($this->isVisitorExpenseContactName($name)) {
                $visitorCount++;
                $visitorTotal += $amount;
            } else {
                $otherCount++;
                $otherTotal += $amount;
            }
        }

        return [
            'other_count' => $otherCount,
            'other_total' => round($otherTotal, 2),
            'visitor_count' => $visitorCount,
            'visitor_total' => round($visitorTotal, 2),
        ];
    }

    private function isVisitorExpenseContactName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($name))) ?? '';

        return $normalized === 'visitor expense'
            || str_contains($normalized, 'visitor expense');
    }

    /**
     * @return array{cash_from_sales: float, cash_refunds_paid: float, cash_in: float, cash_out: float, expected_closing: float}
     */
    public function cashBreakdown(PosSession $session): array
    {
        $cashFromSalesRaw = (float) $this->paymentJoinQuery($session->id, 'sale')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashRefundsPaid = (float) $this->paymentJoinQuery($session->id, 'refund')
            ->where('pos_payments.method', 'cash')
            ->sum('pos_payments.amount');

        $cashIn = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'in')->sum('amount');
        $cashOut = (float) PosCashMovement::query()->where('session_id', $session->id)->where('type', 'out')->sum('amount');

        $salePayTotals = $this->paymentTotalsByMethod($session->id, 'sale');
        $refundPayTotals = $this->paymentTotalsByMethod($session->id, 'refund');
        $payments = $this->excludeAmountFromPaymentMethods([
            'cash' => (float) (($salePayTotals['cash'] ?? 0) - ($refundPayTotals['cash'] ?? 0)),
            'card' => (float) (($salePayTotals['card'] ?? 0) - ($refundPayTotals['card'] ?? 0)),
            'bank' => (float) (($salePayTotals['bank'] ?? 0) - ($refundPayTotals['bank'] ?? 0)),
        ], $this->nonCreditSaleServiceChargeTotal($session->id));

        $cashFromSales = $payments['cash'];
        $expected = round($cashFromSales + $cashIn - $cashOut, 2);

        return [
            'cash_from_sales' => $cashFromSales,
            'cash_refunds_paid' => $cashRefundsPaid,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_closing' => $expected,
            // Raw cash intake before net-sales adjustment (audit).
            'cash_from_sales_gross' => $cashFromSalesRaw,
        ];
    }

    /** Service charges on paid non-credit sales (these flow into Cash/Card/Bank payments). */
    private function nonCreditSaleServiceChargeTotal(int $sessionId): float
    {
        if (! PosRuntimeSchema::ordersHasColumn('service_charge_total')) {
            PosRuntimeSchema::ensureServiceChargeColumns();
        }
        if (! PosRuntimeSchema::ordersHasColumn('service_charge_total')) {
            return 0.0;
        }

        $query = PosOrder::query()
            ->where('session_id', $sessionId)
            ->where('status', 'paid')
            ->where('type', 'sale');

        if (PosRuntimeSchema::ordersHasColumn('is_credit')) {
            $query->where(function ($q) {
                $q->where('is_credit', false)->orWhereNull('is_credit');
            });
        }

        return round((float) $query->sum('service_charge_total'), 2);
    }

    /**
     * Reduce payment-method totals proportionally so they match net sales (excl. service charges).
     *
     * @param  array{cash:float, card:float, bank:float}  $payments
     * @return array{cash:float, card:float, bank:float}
     */
    private function excludeAmountFromPaymentMethods(array $payments, float $exclude): array
    {
        $cash = (float) ($payments['cash'] ?? 0);
        $card = (float) ($payments['card'] ?? 0);
        $bank = (float) ($payments['bank'] ?? 0);
        $sum = $cash + $card + $bank;

        if ($sum <= 0 || $exclude <= 0) {
            return [
                'cash' => round($cash, 2),
                'card' => round($card, 2),
                'bank' => round($bank, 2),
            ];
        }

        $exclude = min($exclude, $sum);
        $out = [
            'cash' => round($cash - ($exclude * ($cash / $sum)), 2),
            'card' => round($card - ($exclude * ($card / $sum)), 2),
            'bank' => round($bank - ($exclude * ($bank / $sum)), 2),
        ];

        // Absorb rounding drift on the largest method so Cash+Card+Bank stays exact.
        $drift = round(($sum - $exclude) - ($out['cash'] + $out['card'] + $out['bank']), 2);
        if (abs($drift) >= 0.01) {
            $largest = array_keys($out, max($out), true)[0] ?? 'cash';
            $out[$largest] = round($out[$largest] + $drift, 2);
        }

        return $out;
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

    /** @return \Illuminate\Support\Collection<string, float|int|string> */
    private function paymentTotalsByMethod(int $sessionId, string $orderType): \Illuminate\Support\Collection
    {
        return $this->paymentJoinQuery($sessionId, $orderType)
            ->selectRaw('pos_payments.method as payment_method, SUM(pos_payments.amount) as total')
            ->groupBy('pos_payments.method')
            ->pluck('total', 'payment_method');
    }

    private function paymentJoinQuery(int $sessionId, string $orderType): \Illuminate\Database\Eloquent\Builder
    {
        $query = PosPayment::query()
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $sessionId)
            ->where('pos_orders.status', 'paid')
            ->where('pos_orders.type', $orderType);

        if (PosRuntimeSchema::ordersHasColumn('is_credit')) {
            $query->where(function ($q) {
                $q->where('pos_orders.is_credit', false)
                    ->orWhereNull('pos_orders.is_credit');
            });
        }

        return $query;
    }
}
