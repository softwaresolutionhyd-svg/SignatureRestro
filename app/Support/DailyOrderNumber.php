<?php

namespace App\Support;

use App\Models\PosOrder;
use Illuminate\Support\Facades\DB;

final class DailyOrderNumber
{
    /**
     * Short daily order number, e.g. 200626-001 (resets sequence each calendar day).
     */
    public static function next(): string
    {
        $tz = config('app.timezone');
        $dayKey = now()->timezone($tz)->format('dmy');
        $prefix = $dayKey.'-';

        return DB::connection('tenant')->transaction(function () use ($prefix) {
            $maxSeq = PosOrder::query()
                ->where('order_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('order_no')
                ->map(fn (string $orderNo) => self::sequenceFromPrefixed($orderNo, $prefix))
                ->filter()
                ->max();

            $next = ($maxSeq ?? 0) + 1;

            return $prefix.($next > 999 ? (string) $next : sprintf('%03d', $next));
        });
    }

    private static function sequenceFromPrefixed(string $orderNo, string $prefix): ?int
    {
        if (! str_starts_with($orderNo, $prefix)) {
            return null;
        }

        $suffix = substr($orderNo, strlen($prefix));
        if ($suffix === '' || ! ctype_digit($suffix)) {
            return null;
        }

        return (int) $suffix;
    }
}
