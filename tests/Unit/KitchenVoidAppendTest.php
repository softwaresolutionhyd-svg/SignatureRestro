<?php

namespace Tests\Unit;

use App\Models\PosOrderItem;
use App\Services\KitchenService;
use Tests\TestCase;

class KitchenVoidAppendTest extends TestCase
{
    public function test_void_fingerprint_ignores_item_name_for_non_custom(): void
    {
        $kitchen = new KitchenService;

        $fromDb = $kitchen->voidMatchFingerprint([
            'product_id' => 42,
            'uom' => 'Nos',
            'is_custom' => false,
            'item_name' => 'Fresh Green Salad',
        ]);
        $fromClientVoid = $kitchen->voidMatchFingerprint([
            'product_id' => 42,
            'uom' => 'nos',
            'is_custom' => false,
            'item_name' => '',
            'name' => 'Fresh Green Salad',
        ]);

        $this->assertSame($fromDb, $fromClientVoid);
    }

    public function test_cancelled_kitchen_line_is_not_reappended(): void
    {
        $kitchen = new KitchenService;

        $old = new PosOrderItem;
        $old->forceFill([
            'id' => 501,
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'unit_price' => 280,
            'is_custom' => false,
            'item_name' => null,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'notes' => null,
            'kitchen_printed_at' => now(),
        ]);
        $old->syncOriginal();

        $voids = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'reason' => 'REPLACE WITH RUSSIAN SALAD',
            'item_name' => '',
            'is_custom' => false,
            'order_item_id' => 501,
            'name' => 'Fresh Green Salad',
        ]];

        $incoming = [[
            'product_id' => 99,
            'uom' => 'nos',
            'qty' => 1,
            'unit_price' => 100,
            'is_custom' => false,
            'item_name' => null,
        ]];

        $out = $kitchen->appendMissingKitchenLockedNormalized([$old], $incoming, $voids);

        $this->assertCount(1, $out);
        $this->assertSame(99, (int) $out[0]['product_id']);
        $this->assertFalse(
            collect($out)->contains(fn ($row) => (int) ($row['product_id'] ?? 0) === 42),
            'Cancelled kitchen item must not be re-appended into the cart payload'
        );
    }

    public function test_void_by_fingerprint_without_order_item_id_also_blocks_reappend(): void
    {
        $kitchen = new KitchenService;

        $old = new PosOrderItem;
        $old->forceFill([
            'id' => 777,
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'unit_price' => 280,
            'is_custom' => false,
            'item_name' => 'Fresh Green Salad',
            'discount_percent' => 0,
            'tax_percent' => 0,
            'notes' => 'extra dressing',
            'kitchen_printed_at' => now(),
        ]);
        $old->syncOriginal();

        $voids = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'reason' => 'Wrong item',
            'item_name' => '',
            'is_custom' => false,
            'order_item_id' => null,
            'name' => 'Fresh Green Salad',
        ]];

        $out = $kitchen->appendMissingKitchenLockedNormalized([$old], [], $voids);

        $this->assertSame([], $out);
    }

    public function test_reduce_normalized_by_voids_removes_cancelled_line(): void
    {
        $kitchen = new KitchenService;

        $normalized = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'order_item_id' => 501,
        ], [
            'product_id' => 99,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
        ]];

        $voids = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'order_item_id' => 501,
        ]];

        $out = $kitchen->reduceNormalizedByVoids($normalized, $voids);

        $this->assertCount(1, $out);
        $this->assertSame(99, (int) $out[0]['product_id']);
    }

    public function test_reduce_normalized_by_voids_keeps_fresh_readd_after_item_specific_void(): void
    {
        $kitchen = new KitchenService;

        $normalized = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'kitchen_locked_qty' => 0,
        ]];

        $voids = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'order_item_id' => 501,
        ]];

        $out = $kitchen->reduceNormalizedByVoids($normalized, $voids);

        $this->assertCount(1, $out);
        $this->assertSame(42, (int) $out[0]['product_id']);
        $this->assertSame(1.0, (float) $out[0]['qty']);
    }

    public function test_reduce_normalized_by_fingerprint_void_skips_unlocked_readd(): void
    {
        $kitchen = new KitchenService;

        $normalized = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'kitchen_locked_qty' => 0,
        ], [
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'kitchen_locked_qty' => 1,
        ]];

        $voids = [[
            'product_id' => 42,
            'uom' => 'nos',
            'qty' => 1,
            'is_custom' => false,
            'order_item_id' => null,
        ]];

        $out = $kitchen->reduceNormalizedByVoids($normalized, $voids);

        $this->assertCount(1, $out);
        $this->assertSame(0.0, (float) $out[0]['kitchen_locked_qty']);
        $this->assertSame(1.0, (float) $out[0]['qty']);
    }
}
