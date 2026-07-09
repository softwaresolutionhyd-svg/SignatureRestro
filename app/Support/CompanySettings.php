<?php

namespace App\Support;

use App\Models\Setting;

class CompanySettings
{
    public static function get(?int $companyId, string $key, mixed $default = null): mixed
    {
        if ($companyId === null) {
            return $default;
        }

        $value = Setting::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->value('value');

        return $value !== null ? $value : $default;
    }
}
