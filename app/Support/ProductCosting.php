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
    ): array {
        $cost = max($cost, 0.0);
        $extraCosts = [];
        $computedAmounts = [];
        $effectiveCost = $cost;
        $runningPrice = $recipeDriven ? $cost : max($seedPrice, 0.0);
        $priceTouchedByRules = false;

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
     * Apply recipe-driven cost + derived selling price to a product model (unsaved fields).
     */
    public static function applyRecipeCostToProduct(\App\Models\InventoryProduct $product, float $recipeCost): bool
    {
        $recipeCost = round(max($recipeCost, 0), 2);
        $previousEffective = (float) $product->total;
        $costing = self::computeFromCost(
            $recipeCost,
            (float) $product->price,
            recipeDriven: true,
            previousEffectiveCost: $previousEffective,
        );

        $changed = abs((float) $product->cost - $recipeCost) >= 0.0000001
            || abs((float) $product->price - $costing['price']) >= 0.0000001
            || $product->extra_costs != $costing['extra_costs'];

        if (! $changed) {
            return false;
        }

        $product->cost = $recipeCost;
        $product->extra_costs = $costing['extra_costs'];
        $product->gas_charges = $costing['gas_charges'];
        $product->price = $costing['price'];
        $product->profit = $costing['profit'];
        $product->service_charges = 0;

        return true;
    }
}
