<?php

namespace App\Services;

use App\Models\PosOrder;

/**
 * Pending bill cards: split child (guest label) and split source parent detection.
 */
final class PosOrderSplitIndicator
{
    /** @var array<int, list<int>> */
    private array $splitParentOrderIdsBySession = [];

    public function isSplit(PosOrder $order): bool
    {
        return $this->splitLabel($order) !== null;
    }

    public function splitLabel(PosOrder $order): ?string
    {
        $fromGuest = $this->splitLabelFromGuest($order);
        if ($fromGuest !== null) {
            return $fromGuest;
        }

        if ($this->isSplitSourceBill($order)) {
            return 'Source';
        }

        return null;
    }

    public function clearSessionCache(): void
    {
        $this->splitParentOrderIdsBySession = [];
    }

    private function splitLabelFromGuest(PosOrder $order): ?string
    {
        $guest = trim((string) ($order->guest_name ?? ''));
        if ($guest === '') {
            return null;
        }
        if (preg_match('/ · Split (.+)$/u', $guest, $m) === 1) {
            $label = trim((string) ($m[1] ?? ''));

            return $label !== '' ? $label : null;
        }

        return null;
    }

    private function isSplitSourceBill(PosOrder $order): bool
    {
        $sessionId = $order->session_id ? (int) $order->session_id : 0;
        if ($sessionId <= 0) {
            return false;
        }

        return in_array((int) $order->id, $this->splitParentOrderIdsForSession($sessionId), true);
    }

    /**
     * @return list<int>
     */
    private function splitParentOrderIdsForSession(int $sessionId): array
    {
        if (isset($this->splitParentOrderIdsBySession[$sessionId])) {
            return $this->splitParentOrderIdsBySession[$sessionId];
        }

        $drafts = PosOrder::query()
            ->where('status', 'draft')
            ->where('session_id', $sessionId)
            ->with('table:id,name')
            ->get(['id', 'guest_name', 'table_id', 'session_id']);

        $splitChildren = $drafts->filter(
            fn (PosOrder $o) => str_contains((string) $o->guest_name, ' · Split ')
        );

        $parentIds = [];
        foreach ($drafts as $parent) {
            if (str_contains((string) $parent->guest_name, ' · Split ')) {
                continue;
            }
            $tableName = strtolower(trim((string) ($parent->table?->name ?? '')));
            foreach ($splitChildren as $child) {
                if ((int) $child->id === (int) $parent->id) {
                    continue;
                }
                $guest = trim((string) $child->guest_name);
                if (preg_match('/^(.+?) · Split /u', $guest, $m) !== 1) {
                    continue;
                }
                $childBase = strtolower(trim((string) ($m[1] ?? '')));
                if ($tableName !== '' && $childBase === $tableName) {
                    $parentIds[] = (int) $parent->id;
                    break;
                }
            }
        }

        $this->splitParentOrderIdsBySession[$sessionId] = array_values(array_unique($parentIds));

        return $this->splitParentOrderIdsBySession[$sessionId];
    }
}
