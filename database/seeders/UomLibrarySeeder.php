<?php

namespace Database\Seeders;

use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use Illuminate\Database\Seeder;

class UomLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'g', 'name' => 'Gram'],
            ['code' => 'ltr', 'name' => 'Litre'],
            ['code' => 'ml', 'name' => 'Millilitre'],
            ['code' => 'pcs', 'name' => 'Pieces'],
            ['code' => 'box', 'name' => 'Box'],
            ['code' => 'pkt', 'name' => 'Packet'],
        ];

        $ids = [];
        foreach ($defs as $d) {
            $u = InventoryUnit::query()->firstOrCreate(
                ['code' => InventoryUnit::normalizeCode($d['code'])],
                ['name' => $d['name']]
            );
            $ids[$u->code] = $u->id;
        }

        $rules = [
            ['from' => 'g', 'to' => 'kg', 'factor' => 0.001, 'note' => '1 g = 0.001 kg'],
            ['from' => 'ml', 'to' => 'ltr', 'factor' => 0.001, 'note' => '1 ml = 0.001 ltr'],
        ];

        foreach ($rules as $r) {
            if (! isset($ids[$r['from']], $ids[$r['to']])) {
                continue;
            }
            InventoryUnitConversion::query()->updateOrCreate(
                [
                    'from_unit_id' => $ids[$r['from']],
                    'to_unit_id' => $ids[$r['to']],
                ],
                [
                    'factor' => $r['factor'],
                    'note' => $r['note'],
                ]
            );
        }
    }
}
