<?php

namespace App\Support;

use App\Models\PosOrder;
use App\Models\Setting;

final class PosServiceCharge
{
    public static function percent(): float
    {
        if (Setting::get('pos_service_charge_enabled', '0') !== '1') {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) Setting::get('pos_service_charge_percent', 0)));
    }

    public static function appliesTo(?string $serviceType): bool
    {
        return $serviceType === PosOrder::SERVICE_DINE_IN;
    }

    public static function amountOnNet(float $netAfterDiscount, ?string $serviceType = null): float
    {
        if (! self::appliesTo($serviceType)) {
            return 0.0;
        }

        $pct = self::percent();
        if ($pct <= 0) {
            return 0.0;
        }

        $net = round($netAfterDiscount, 2);

        return round($net * ($pct / 100), 2);
    }
}
