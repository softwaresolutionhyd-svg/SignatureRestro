<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class ServeMealSchedule
{
    /** @var array<string, array{label: string, time: string}> */
    public const MEALS = [
        'breakfast' => ['label' => 'Breakfast', 'time' => '08:00'],
        'brunch' => ['label' => 'Brunch', 'time' => '11:00'],
        'lunch' => ['label' => 'Lunch', 'time' => '13:00'],
        'tea_break' => ['label' => 'Tea Break', 'time' => '16:00'],
        'dinner' => ['label' => 'Dinner', 'time' => '20:00'],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::MEALS);
    }

    public static function isValid(?string $meal): bool
    {
        return is_string($meal) && $meal !== '' && isset(self::MEALS[$meal]);
    }

    public static function label(?string $meal): ?string
    {
        if (! self::isValid($meal)) {
            return null;
        }

        return self::MEALS[$meal]['label'];
    }

    /**
     * @return array{serve_meal: string, serve_date: string, serve_time: string}
     */
    public static function resolveNext(?string $meal, ?Carbon $now = null): array
    {
        if (! self::isValid($meal)) {
            throw new \InvalidArgumentException('Invalid serve meal.');
        }

        $now = ($now ?? now())->timezone(config('app.timezone'));
        $time = self::MEALS[$meal]['time'];
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $candidate = $now->copy()->startOfDay()->setTime($hour, $minute);
        if ($now->gte($candidate)) {
            $candidate = $candidate->addDay();
        }

        return [
            'serve_meal' => $meal,
            'serve_date' => $candidate->format('Y-m-d'),
            'serve_time' => $time,
        ];
    }

    /**
     * @return list<array{key: string, label: string, time: string}>
     */
    public static function optionsForUi(): array
    {
        return collect(self::MEALS)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'time' => $meta['time'],
            ])
            ->values()
            ->all();
    }
}
