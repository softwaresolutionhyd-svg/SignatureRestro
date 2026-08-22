<?php

namespace App\Support;

use App\Models\Setting;

final class ProductCosting
{
    /**
     * @return array{
     *     extra_costs: array<string, float>,
     *     gas_charges: float,
     *     effective_cost: float,
     *     price: float,
     *     profit: float,
     *     price_from_rules: bool
     * }
     */
    public static function computeFromCost(
        float $cost,
        float $seedPrice = 0.0,
        bool $recipeDriven = false,
        ?float $previousEffectiveCost = null,
        bool $applyExtraCostRules = true,
    ): array {
        $cost = max($cost, 0.0);
        $extraCosts = [];
        $computedAmounts = [];
        $effectiveCost = $cost;
        $runningPrice = $recipeDriven ? $cost : max($seedPrice, 0.0);
        $priceTouchedByRules = false;

        if ($applyExtraCostRules) {
            foreach (Setting::productExtraCostFieldDefinitions() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rate = max((float) ($field['rate'] ?? 0), 0);
            $operator = (string) ($field['operator'] ?? 'plus');
            if (! in_array($operator, ['plus', 'minus', 'multiply', 'divide'], true)) {
                $operator = 'plus';
            }

            $baseKey = (string) ($field['base'] ?? 'cost');
            $baseVal = match ($baseKey) {
                'effective_cost' => $effectiveCost,
                'price' => $runningPrice,
                'cost' => $cost,
                default => (float) ($computedAmounts[$baseKey] ?? 0.0),
            };

            $target = (string) ($field['target'] ?? 'effective_cost');
            if (! in_array($target, ['effective_cost', 'price'], true)) {
                $target = 'effective_cost';
            }

            $amount = match ($operator) {
                'minus' => -$baseVal * ($rate / 100),
                'multiply' => $baseVal * $rate,
                'divide' => $rate > 0 ? $baseVal / $rate : 0.0,
                default => $baseVal * ($rate / 100),
            };
            $amount = round($amount, 2);
            $computedAmounts[$key] = $amount;
            $extraCosts[$key] = $amount;

            if ($target === 'price') {
                $runningPrice += $amount;
                $priceTouchedByRules = true;
            } else {
                $effectiveCost += $amount;
            }
        }
        }

        $effectiveCost = round($effectiveCost, 2);

        if ($priceTouchedByRules) {
            $price = round(max($runningPrice, 0), 2);
        } elseif ($recipeDriven) {
            $markup = $previousEffectiveCost !== null
                ? max($seedPrice, 0.0) - $previousEffectiveCost
                : 0.0;
            $price = round(max($effectiveCost + $markup, $effectiveCost), 2);
        } else {
            $price = round(max($seedPrice, 0), 2);
        }

        return [
            'extra_costs' => $extraCosts,
            'gas_charges' => round((float) ($extraCosts['gas_charges'] ?? 0), 2),
            'effective_cost' => $effectiveCost,
            'price' => $price,
            'profit' => round($price - $effectiveCost, 2),
            'price_from_rules' => $priceTouchedByRules,
        ];
    }

    /**
     * Apply recipe-driven cost to a finished product.
     * Sale price (rate) is never changed — only cost / extras / profit update.
     */
    public static function applyRecipeCostToProduct(\App\Models\InventoryProduct $product, float $recipeCost): bool
    {
        $recipeCost = round(max($recipeCost, 0), 2);
        $existingPrice = round(max((float) $product->price, 0), 2);
        $costing = self::computeFromCost(
            $recipeCost,
            $existingPrice,
            recipeDriven: true,
            previousEffectiveCost: (float) $product->total,
            applyExtraCostRules: ! ($product->for_purchase ?? false),
        );

        $newProfit = round($existingPrice - $costing['effective_cost'], 2);
        $changed = abs((float) $product->cost - $recipeCost) >= 0.0000001
            || abs((float) $product->profit - $newProfit) >= 0.0000001
            || $product->extra_costs != $costing['extra_costs'];

        if (! $changed) {
            return false;
        }

        $product->cost = $recipeCost;
        $product->extra_costs = $costing['extra_costs'];
        $product->gas_charges = $costing['gas_charges'];
        // Keep menu / POS rate — recipe only drives cost
        $product->price = $existingPrice;
        $product->profit = $newProfit;
        $product->service_charges = 0;

        return true;
    }
}
