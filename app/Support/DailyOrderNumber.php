<?php

namespace App\Support;

use App\Models\PosOrder;
use App\Models\PosSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DailyOrderNumber
{
    /**
     * Session-scoped order number, e.g. 270726-001.
     *
     * Sequence follows the earliest open POS session (shared by cashier /
     * manager / admin / order taker) and does NOT reset at midnight while
     * that session stays open.
     */
    public static function next(?PosSession $session = null): string
    {
        return DB::connection('tenant')->transaction(function () use ($session) {
            $anchor = self::resolveAnchorSession($session);
            $prefix = self::prefixForSession($anchor);

            $openSessionIds = PosSession::query()
                ->where('status', 'open')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $query = PosOrder::query()->lockForUpdate();

            // One continuous sequence for the open floor session:
            // same prefix OR any order already attached to open sessions.
            if ($openSessionIds !== []) {
                $query->where(function ($q) use ($prefix, $openSessionIds) {
                    $q->where('order_no', 'like', $prefix.'%')
                        ->orWhereIn('session_id', $openSessionIds);
                });
            } else {
                $query->where('order_no', 'like', $prefix.'%');
            }

            $maxSeq = $query
                ->pluck('order_no')
                ->map(fn (string $orderNo) => self::sequenceFromOrderNo($orderNo))
                ->filter()
                ->max();

            $next = ((int) ($maxSeq ?? 0)) + 1;

            return $prefix.($next > 999 ? (string) $next : sprintf('%03d', $next));
        });
    }

    private static function resolveAnchorSession(?PosSession $session): ?PosSession
    {
        $earliestOpen = PosSession::query()
            ->where('status', 'open')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->first();

        return $earliestOpen ?? $session;
    }

    private static function prefixForSession(?PosSession $session): string
    {
        $tz = (string) config('app.timezone', 'UTC');
        $moment = null;

        if ($session?->opened_at) {
            $moment = Carbon::parse($session->opened_at)->timezone($tz);
        } elseif ($session?->business_date) {
            $moment = Carbon::parse($session->business_date)->timezone($tz)->startOfDay();
        }

        if ($moment === null) {
            $moment = now()->timezone($tz);
        }

        return $moment->format('dmy').'-';
    }

    private static function sequenceFromOrderNo(string $orderNo): ?int
    {
        if (! preg_match('/-(\d+)$/', $orderNo, $m)) {
            return null;
        }

        return (int) $m[1];
    }
}
