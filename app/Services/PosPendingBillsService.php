<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PosPendingBillsService
{
    /**
     * @return list<int>
     */
    public function billSessionIdsForSession(PosSession $session): array
    {
        $date = $session->business_date instanceof \Illuminate\Support\Carbon
            ? $session->business_date->toDateString()
            : (string) ($session->business_date ?: ($session->opened_at?->toDateString() ?? now()->toDateString()));

        return PosSession::query()
            ->where(function ($q) use ($date) {
                $q->where('business_date', $date)
                    ->orWhereDate('opened_at', $date);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function countHeldDrafts(array $billSessionIds): int
    {
        return $this->queryHeldDrafts($billSessionIds, true)->count();
    }

    /**
     * @param  list<int>  $billSessionIds
     * @return Collection<int, PosOrder>
     */
    public function queryHeldDrafts(array $billSessionIds, bool $dueOnly = true): Collection
    {
        if ($billSessionIds === []) {
            return collect();
        }

        $hasOrderTakerColumns = Schema::connection('tenant')->hasColumn('pos_orders', 'order_source')
            && Schema::connection('tenant')->hasColumn('pos_orders', 'ready_for_pos_at');

        $heldOrders = PosOrder::query()
            ->where('status', 'draft')
            ->when($hasOrderTakerColumns, function ($q) use ($billSessionIds) {
                $q->where(function ($outer) use ($billSessionIds) {
                    $outer->where(function ($w) use ($billSessionIds) {
                        $w->whereIn('session_id', $billSessionIds)
                            ->where(function ($inner) {
                                $inner->whereNull('order_source')
                                    ->orWhere('order_source', 'pos');
                            });
                    })->orWhere(function ($w) use ($billSessionIds) {
                        $w->where('order_source', OrderTakerService::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at')
                            ->where(function ($inner) use ($billSessionIds) {
                                $inner->whereNull('session_id')
                                    ->orWhereIn('session_id', $billSessionIds);
                            });
                    });
                });
            }, function ($q) use ($billSessionIds) {
                $q->whereIn('session_id', $billSessionIds);
            })
            ->get();

        if (! $dueOnly) {
            return $heldOrders->values();
        }

        return $heldOrders
            ->filter(fn (PosOrder $order) => $order->isDueForServeDay())
            ->values();
    }
}
