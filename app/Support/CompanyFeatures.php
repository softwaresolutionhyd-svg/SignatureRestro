<?php

namespace App\Support;

use App\Models\CompanyInstalledFeature;

final class CompanyFeatures
{
    /**
     * @return list<string>
     */
    public static function packageKeys(): array
    {
        return array_keys(config('company_features.packages', []));
    }

    public static function label(string $featureKey): string
    {
        return (string) data_get(config('company_features.packages'), $featureKey.'.label', $featureKey);
    }

    public static function isInstalled(?int $companyId, string $featureKey): bool
    {
        if ($companyId === null) {
            return false;
        }

        return CompanyInstalledFeature::query()
            ->where('company_id', $companyId)
            ->where('feature_key', $featureKey)
            ->exists();
    }
}
