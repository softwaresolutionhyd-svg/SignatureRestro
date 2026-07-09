<?php

namespace App\Services;

use App\Models\ManufacturingBom;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class KitchenService
{
    /**
     * @return Collection<int, PosOrder>
     */
    public function activeOrders(): Collection
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_completed_at')) {
            return collect();
        }

        $session = PosSession::query()
            ->where('status', 'open')
            ->latest('id')
            ->first();

        $hasOrderTakerColumns = Schema::hasColumn('pos_orders', 'order_source')
            && Schema::hasColumn('pos_orders', 'ready_for_pos_at');
        $hasKitchenSort = Schema::hasColumn('pos_orders', 'kitchen_sort');

        $query = PosOrder::query()
            ->whereNull('kitchen_completed_at')
            ->whereHas('items')
            ->where(function ($q) {
                $q->where('status', 'draft')
                    ->orWhere(function ($w) {
                        $w->where('status', 'paid')
                            ->where(function ($inner) {
                                $inner->where('customer_type', 'mess_use')
                                    ->orWhere('customer_type', 'ast_offr')
                                    ->orWhereNull('customer_type');
                            })
                            ->where(function ($inner) {
                                $inner->whereNull('room_no')
                                    ->orWhere('room_no', '');
                            });
                    });
            })
            ->when($hasOrderTakerColumns, function ($q) use ($session) {
                $q->where(function ($outer) use ($session) {
                    if ($session) {
                        $outer->where(function ($w) use ($session) {
                            $w->where('session_id', $session->id)
                                ->where(function ($inner) {
                                    $inner->whereNull('order_source')
                                        ->orWhere('order_source', 'pos');
                                });
                        });
                    }
                    $outer->orWhere(function ($w) use ($session) {
                        $w->where('order_source', OrderTakerService::SOURCE_ORDER_TAKER)
                            ->whereNotNull('ready_for_pos_at');
                        if ($session) {
                            $w->where(function ($inner) use ($session) {
                                $inner->whereNull('session_id')
                                    ->orWhere('session_id', $session->id);
                            });
                        }
                    });
                });
            }, function ($q) use ($session) {
                if ($session) {
                    $q->where('session_id', $session->id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with(['items.product:id,name', 'table:id,name', 'user:id,name']);

        if ($hasKitchenSort) {
            $query->orderByRaw('kitchen_sort is null')
                ->orderBy('kitchen_sort');
        }

        return $query->orderBy('ready_for_pos_at')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (PosOrder $order) => $this->orderBelongsOnKitchenBoard($order))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosOrderItem>
     */
    public function itemsForKitchenDisplay(PosOrder $order): Collection
    {
        $items = $order->items;

        if (Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            $items = $items
                ->filter(fn (PosOrderItem $item) => $item->kitchen_served_at === null)
                ->values();
        }

        if ($items->isEmpty()) {
            return $items;
        }

        if (! Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            return $items;
        }

        $pending = $items->where('kitchen_pending', true)->values();
        if ($pending->isNotEmpty()) {
            return $pending;
        }

        return $items;
    }

    /**
     * Match lines by product + UOM + notes (qty excluded — qty changes create new kitchen work).
     *
     * @param  array<string, mixed>|PosOrderItem  $item
     */
    public function baseItemFingerprint(array|PosOrderItem $item): string
    {
        if ($item instanceof PosOrderItem) {
            $productId = (int) $item->product_id;
            $uom = (string) $item->uom;
            $notes = trim((string) ($item->notes ?? ''));
        } else {
            $productId = (int) ($item['product_id'] ?? 0);
            $uom = (string) ($item['uom'] ?? '');
            $notes = trim((string) ($item['notes'] ?? ''));
        }

        return implode('|', [$productId, $uom, $notes]);
    }

    /**
     * @param  array<string, mixed>|PosOrderItem  $item
     */
    public function itemFingerprint(array|PosOrderItem $item): string
    {
        if ($item instanceof PosOrderItem) {
            $qty = number_format((float) $item->qty, 3, '.', '');
        } else {
            $qty = number_format((float) ($item['qty'] ?? 0), 3, '.', '');
        }

        return $this->baseItemFingerprint($item).'|'.$qty;
    }

    /**
     * @param  list<PosOrderItem>  $oldItems
     * @return array<string, array{served_qty: float, served_at: mixed}>
     */
    private function servedQtyPoolByBaseFingerprint(array $oldItems): array
    {
        $pool = [];
        foreach ($oldItems as $oldItem) {
            if ($oldItem->kitchen_served_at === null) {
                continue;
            }
            $base = $this->baseItemFingerprint($oldItem);
            if (! isset($pool[$base])) {
                $pool[$base] = ['served_qty' => 0.0, 'served_at' => $oldItem->kitchen_served_at];
            }
            $pool[$base]['served_qty'] += (float) $oldItem->qty;
        }

        return $pool;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function splitItemLineByQty(array $item, float $qty): array
    {
        $originalQty = (float) ($item['qty'] ?? 0);
        if ($originalQty <= 0 || abs($originalQty - $qty) < 0.0005) {
            $line = $item;
            $line['qty'] = $qty;

            return $line;
        }

        $ratio = $qty / $originalQty;
        $line = $item;
        $line['qty'] = $qty;
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'total'] as $field) {
            if (array_key_exists($field, $line)) {
                $line[$field] = round((float) $line[$field] * $ratio, 2);
            }
        }

        return $line;
    }

    /**
     * @param  list<PosOrderItem>  $oldItems
     * @param  list<array<string, mixed>>  $newItemsData
     * @return list<array<string, mixed>>
     */
    public function applyKitchenPendingFlags(array $oldItems, array $newItemsData): array
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            return $newItemsData;
        }

        $hasServedAt = Schema::hasColumn('pos_order_items', 'kitchen_served_at');
        $servedPool = $hasServedAt ? $this->servedQtyPoolByBaseFingerprint($oldItems) : [];

        $out = [];
        foreach ($newItemsData as $item) {
            $newQty = (float) ($item['qty'] ?? 0);
            if ($newQty <= 0) {
                continue;
            }

            $baseFp = $this->baseItemFingerprint($item);
            $servedQty = 0.0;
            $servedAt = null;
            if ($hasServedAt && isset($servedPool[$baseFp])) {
                $servedQty = min((float) $servedPool[$baseFp]['served_qty'], $newQty);
                $servedAt = $servedPool[$baseFp]['served_at'];
                $servedPool[$baseFp]['served_qty'] = max(0.0, (float) $servedPool[$baseFp]['served_qty'] - $servedQty);
            }

            $pendingQty = round($newQty - $servedQty, 3);

            if ($servedQty > 0.0005) {
                $servedLine = $this->splitItemLineByQty($item, $servedQty);
                $servedLine['kitchen_pending'] = false;
                if ($hasServedAt && $servedAt !== null) {
                    $servedLine['kitchen_served_at'] = $servedAt;
                }
                $out[] = $servedLine;
            }

            if ($pendingQty > 0.0005) {
                $pendingLine = $this->splitItemLineByQty($item, $pendingQty);
                $pendingLine['kitchen_pending'] = true;
                unset($pendingLine['kitchen_served_at']);
                $out[] = $pendingLine;
            }
        }

        return $out;
    }

    /**
     * @return array{order: PosOrder, removed: bool}
     */
    public function markItemServed(PosOrder $order, PosOrderItem $item): array
    {
        if ((int) $item->order_id !== (int) $order->id) {
            throw new RuntimeException('Item is order se match nahi karta.');
        }

        if (! $order->needsKitchenQueue()) {
            throw new RuntimeException('Yeh order ab kitchen mein nahi hai.');
        }

        if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            throw new RuntimeException('Item served ke liye migration chalayein: php artisan migrate');
        }

        if (! in_array($order->kitchenStatusKey(), [
            PosOrder::KITCHEN_STATUS_PREPARING,
            PosOrder::KITCHEN_STATUS_READY,
        ], true)) {
            throw new RuntimeException('Pehle Preparing par click karein.');
        }

        if ($item->kitchen_served_at !== null) {
            throw new RuntimeException('Yeh item pehle hi served hai.');
        }

        $item->kitchen_served_at = now();
        if (Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            $item->kitchen_pending = false;
        }
        $item->save();

        $order->refresh();
        $unservedCount = $order->items()->whereNull('kitchen_served_at')->count();

        if ($unservedCount === 0) {
            if (Schema::hasColumn($order->getTable(), 'kitchen_status')) {
                $order->kitchen_status = PosOrder::KITCHEN_STATUS_SERVED;
            }
            if (Schema::hasColumn($order->getTable(), 'kitchen_completed_at')) {
                $order->kitchen_completed_at = now();
            }
            $order->save();

            return [
                'order' => $order->fresh(['items.product']),
                'removed' => true,
            ];
        }

        return [
            'order' => $order->fresh(['items.product']),
            'removed' => false,
        ];
    }

    public function orderBelongsOnKitchenBoard(PosOrder $order): bool
    {
        if (! $order->isDueForServeDay()) {
            return false;
        }

        if (! $order->needsKitchenQueue()) {
            return false;
        }

        if (! Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            return true;
        }

        if (Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            $unserved = $order->items->filter(fn (PosOrderItem $item) => $item->kitchen_served_at === null);
            if ($unserved->isEmpty()) {
                return false;
            }
        }

        if ($order->items->contains(fn (PosOrderItem $item) => (bool) $item->kitchen_pending)) {
            return true;
        }

        if ($order->kitchenStatusKey() !== PosOrder::KITCHEN_STATUS_QUEUED) {
            return true;
        }

        return false;
    }

    /**
     * @return list<array{key: string, label: string, sort: string, orders: Collection<int, PosOrder>}>
     */
    public function ordersGroupedByTable(): array
    {
        $bucket = [];

        foreach ($this->activeOrders() as $order) {
            $meta = $this->tableGroupMeta($order);
            $key = $meta['key'];

            if (! isset($bucket[$key])) {
                $bucket[$key] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'sort' => $meta['sort'],
                    'orders' => collect(),
                ];
            }

            $bucket[$key]['orders']->push($order);
        }

        $groups = array_values($bucket);
        usort($groups, fn (array $a, array $b) => strnatcasecmp($a['sort'], $b['sort']));

        foreach ($groups as &$group) {
            $group['orders'] = $group['orders']
                ->sortBy(fn (PosOrder $order) => [
                    $order->kitchen_sort ?? PHP_INT_MAX,
                    $order->ready_for_pos_at?->timestamp ?? $order->created_at?->timestamp ?? 0,
                ])
                ->values();
        }
        unset($group);

        return $groups;
    }

    /**
     * Flat list for free-position kitchen board.
     *
     * @return Collection<int, PosOrder>
     */
    public function ordersForFreeBoard(): Collection
    {
        return $this->activeOrders();
    }

    /**
     * Dishes still to prepare on the kitchen board (unserved lines only).
     *
     * @return list<array{product_id: int, name: string, uom: string, qty: float}>
     */
    public function pendingDishSummary(): array
    {
        $totals = [];

        foreach ($this->activeOrders() as $order) {
            foreach ($this->itemsForKitchenDisplay($order) as $item) {
                $productId = (int) $item->product_id;
                $uom = (string) $item->uom;
                $key = $productId.'|'.$uom;

                if (! isset($totals[$key])) {
                    $totals[$key] = [
                        'product_id' => $productId,
                        'name' => (string) ($item->product?->name ?? 'Item'),
                        'uom' => $uom,
                        'qty' => 0.0,
                    ];
                }

                $totals[$key]['qty'] += (float) $item->qty;
            }
        }

        $rows = array_values($totals);
        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Recipe ingredients required for dishes still on active kitchen orders.
     *
     * @return list<array{product_id: int, name: string, uom: string, qty: float}>
     */
    public function pendingRecipeConsumption(): array
    {
        $rows = [];

        foreach ($this->activeOrders() as $order) {
            foreach ($this->itemsForKitchenDisplay($order) as $item) {
                $rows[] = $item;
            }
        }

        if ($rows === []) {
            return [];
        }

        $items = new EloquentCollection($rows);
        $items->load(['product.uomConversions']);

        return $this->recipeConsumptionFromItems($items);
    }

    /**
     * Recipe / ingredient consumption for items kitchen ne aaj served mark kiye.
     *
     * @return list<array{product_id: int, name: string, uom: string, qty: float}>
     */
    public function todayRecipeConsumption(): array
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            return [];
        }

        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();

        $items = PosOrderItem::query()
            ->whereNotNull('kitchen_served_at')
            ->whereBetween('kitchen_served_at', [$start, $end])
            ->with(['product.uomConversions'])
            ->get();

        return $this->recipeConsumptionFromItems($items);
    }

    /**
     * @param  Collection<int, PosOrderItem>|iterable<int, PosOrderItem>  $items
     * @return list<array{product_id: int, name: string, uom: string, qty: float}>
     */
    private function recipeConsumptionFromItems(iterable $items): array
    {
        $itemList = $items instanceof Collection ? $items : collect($items);
        if ($itemList->isEmpty()) {
            return [];
        }

        $finishedIds = $itemList->pluck('product_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        $bomsByFinished = ManufacturingBom::query()
            ->whereIn('finished_product_id', $finishedIds)
            ->where('active', true)
            ->with(['lines.component.uomConversions'])
            ->orderBy('id')
            ->get()
            ->groupBy('finished_product_id')
            ->map(fn (Collection $group) => $group->first());

        $totals = [];

        foreach ($itemList as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            $factor = $product->factorToBaseForUom((string) $item->uom);
            if ($factor === null || $factor <= 0) {
                continue;
            }

            $qtyFinishedBase = (float) $item->qty * $factor;
            if ($qtyFinishedBase <= 0) {
                continue;
            }

            /** @var ManufacturingBom|null $bom */
            $bom = $bomsByFinished->get((int) $product->id);
            if ($bom !== null && (float) $bom->batch_qty > 0) {
                $mult = $qtyFinishedBase / (float) $bom->batch_qty;
                foreach ($bom->lines as $line) {
                    $component = $line->component;
                    if ($component === null) {
                        continue;
                    }

                    $lineUom = $line->effectiveUom();
                    $qtyInLineUom = (float) $line->qty * $mult;
                    if ($qtyInLineUom <= 0) {
                        continue;
                    }

                    $this->addConsumptionRow(
                        $totals,
                        (int) $component->id,
                        (string) $component->name,
                        $lineUom,
                        $qtyInLineUom
                    );
                }

                continue;
            }

            if ($product->for_purchase) {
                $this->addConsumptionRow(
                    $totals,
                    (int) $product->id,
                    (string) $product->name,
                    (string) $product->uom,
                    $qtyFinishedBase
                );
            }
        }

        $rows = array_values($totals);
        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }

    public function saveCardPosition(PosOrder $order, float $xPx, float $yPx): void
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_pos_x') || ! Schema::hasColumn('pos_orders', 'kitchen_pos_y')) {
            throw new RuntimeException('Kitchen position ke liye migration chalayein: php artisan migrate');
        }

        if (! $order->needsKitchenQueue() || $order->kitchen_completed_at !== null) {
            throw new RuntimeException('Yeh order ab kitchen mein nahi hai.');
        }

        $x = max(0, min(8000, (int) round($xPx)));
        $y = max(0, min(8000, (int) round($yPx)));

        PosOrder::query()->whereKey($order->id)->update([
            'kitchen_pos_x' => $x,
            'kitchen_pos_y' => $y,
        ]);
    }

    /**
     * @return array{key: string, label: string, sort: string}
     */
    public function tableLabelFor(PosOrder $order): array
    {
        return $this->tableGroupMeta($order);
    }

    /**
     * @param  list<int>  $orderIds
     */
    public function reorderStack(string $groupKey, array $orderIds): void
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_sort')) {
            throw new RuntimeException('Kitchen drag ke liye pehle migration chalayein: php artisan migrate');
        }

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            throw new RuntimeException('Koi order select nahi hua.');
        }

        $orders = PosOrder::query()
            ->whereIn('id', $orderIds)
            ->whereNull('kitchen_completed_at')
            ->get()
            ->keyBy('id');

        if ($orders->count() !== count($orderIds)) {
            throw new RuntimeException('Kuch orders ab kitchen mein nahi hain.');
        }

        foreach ($orderIds as $id) {
            $order = $orders->get($id);
            if (! $order || ! $order->needsKitchenQueue()) {
                throw new RuntimeException('Invalid kitchen order.');
            }
            if ($this->tableGroupMeta($order)['key'] !== $groupKey) {
                throw new RuntimeException('Order is table/group se match nahi karta.');
            }
        }

        $sort = 10;
        foreach ($orderIds as $id) {
            PosOrder::query()->whereKey($id)->update(['kitchen_sort' => $sort]);
            $sort += 10;
        }
    }

    public function completeOrder(PosOrder $order): PosOrder
    {
        return $this->advanceKitchenStatus($order, PosOrder::KITCHEN_STATUS_READY);
    }

    public function advanceKitchenStatus(PosOrder $order, string $nextStatus): PosOrder
    {
        if (! $order->needsKitchenQueue()) {
            throw new RuntimeException('Yeh order ab kitchen mein nahi hai.');
        }

        if (! Schema::hasColumn($order->getTable(), 'kitchen_status')) {
            throw new RuntimeException('Kitchen status ke liye migration chalayein: php artisan migrate');
        }

        $current = $order->kitchenStatusKey();
        $allowed = match ($nextStatus) {
            PosOrder::KITCHEN_STATUS_PREPARING => $current === PosOrder::KITCHEN_STATUS_QUEUED,
            PosOrder::KITCHEN_STATUS_READY => $current === PosOrder::KITCHEN_STATUS_PREPARING,
            PosOrder::KITCHEN_STATUS_SERVED => $current === PosOrder::KITCHEN_STATUS_READY,
            default => false,
        };

        if (! $allowed) {
            throw new RuntimeException('Yeh action ab allowed nahi hai.');
        }

        $order->kitchen_status = $nextStatus;

        if ($nextStatus === PosOrder::KITCHEN_STATUS_PREPARING) {
            if (Schema::hasColumn($order->getTable(), 'kitchen_preparing_at') && $order->kitchen_preparing_at === null) {
                $order->kitchen_preparing_at = now();
            }
            if (Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
                PosOrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('kitchen_pending', true)
                    ->update(['kitchen_pending' => false]);
            }
        }

        if ($nextStatus === PosOrder::KITCHEN_STATUS_READY
            && Schema::hasColumn($order->getTable(), 'kitchen_ready_at')
            && $order->kitchen_ready_at === null) {
            $order->kitchen_ready_at = now();
        }

        if ($nextStatus === PosOrder::KITCHEN_STATUS_SERVED) {
            if (Schema::hasColumn($order->getTable(), 'kitchen_completed_at')) {
                $order->kitchen_completed_at = now();
            }
            if (Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
                PosOrderItem::query()
                    ->where('order_id', $order->id)
                    ->update(['kitchen_pending' => false]);
            }
            if (Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
                PosOrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereNull('kitchen_served_at')
                    ->update(['kitchen_served_at' => now()]);
            }
        }

        $order->save();

        return $order->fresh();
    }

    /**
     * Orders visible on cafe Order Status screen.
     *
     * @return Collection<int, PosOrder>
     */
    public function ordersForCafeStatusScreen(): Collection
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_status')) {
            return collect();
        }

        return $this->activeOrders()
            ->filter(fn (PosOrder $order) => $order->showsOnCafeStatusScreen())
            ->sortBy(fn (PosOrder $order) => [
                $order->kitchenStatusKey() === PosOrder::KITCHEN_STATUS_READY ? 0 : 1,
                $order->ready_for_pos_at?->timestamp ?? $order->created_at?->timestamp ?? 0,
            ])
            ->values();
    }

    public function clearKitchenCompletion(PosOrder $order): void
    {
        if (! Schema::hasColumn($order->getTable(), 'kitchen_completed_at')) {
            return;
        }

        if ($order->kitchen_completed_at === null) {
            return;
        }

        $order->kitchen_completed_at = null;
        if (Schema::hasColumn($order->getTable(), 'kitchen_preparing_at')) {
            $order->kitchen_preparing_at = null;
        }
        if (Schema::hasColumn($order->getTable(), 'kitchen_ready_at')) {
            $order->kitchen_ready_at = null;
        }
        if (Schema::hasColumn($order->getTable(), 'kitchen_status')) {
            $order->kitchen_status = null;
        }
        $order->save();
    }

    /**
     * @param  array<string, array{product_id: int, name: string, uom: string, qty: float}>  $bucket
     */
    private function addConsumptionRow(array &$bucket, int $productId, string $name, string $uom, float $qty): void
    {
        $key = $productId.'|'.$uom;
        if (! isset($bucket[$key])) {
            $bucket[$key] = [
                'product_id' => $productId,
                'name' => $name,
                'uom' => $uom,
                'qty' => 0.0,
            ];
        }

        $bucket[$key]['qty'] += $qty;
    }

    /**
     * @return array{key: string, label: string, sort: string}
     */
    private function tableGroupMeta(PosOrder $order): array
    {
        if ($order->table) {
            $name = (string) $order->table->name;

            return [
                'key' => 'table:'.$order->table_id,
                'label' => $name,
                'sort' => '1-'.$name,
            ];
        }

        $roomNo = trim((string) ($order->room_no ?? ''));
        if ($roomNo !== '') {
            return [
                'key' => 'room:'.$roomNo,
                'label' => 'Room '.$roomNo,
                'sort' => '2-'.$roomNo,
            ];
        }

        $guest = trim((string) ($order->guest_name ?? ''));

        return [
            'key' => 'walk-in:'.($guest !== '' ? $guest : (string) $order->id),
            'label' => $guest !== '' ? $guest : 'Walk-in',
            'sort' => '3-'.$guest,
        ];
    }
}
