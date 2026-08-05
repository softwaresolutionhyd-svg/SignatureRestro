<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::connection('tenant')->table('activity_logs')
    ->where(function ($q) {
        $q->where('subject_id', 405)
            ->orWhere('description', 'like', '%050826-049%')
            ->orWhere('properties', 'like', '%050826-049%');
    })
    ->orderBy('id')
    ->get(['id', 'action', 'description', 'subject_id', 'created_at', 'user_id', 'properties']);

foreach ($rows as $r) {
    echo "{$r->id} | {$r->created_at} | {$r->action} | sub={$r->subject_id} | u={$r->user_id} | {$r->description}\n";
}

echo "--- props for place/cancel/paid ---\n";
foreach ($rows as $r) {
    if (! in_array($r->action, ['pos.order_placed', 'pos.order_updated', 'pos.order_cancelled', 'pos.order_paid', 'pos.payment'], true)
        && ! str_contains((string) $r->action, 'discard')
        && ! str_contains((string) $r->action, 'cancel')) {
        continue;
    }
    echo "ID {$r->id} action={$r->action}:\n";
    echo substr((string) $r->properties, 0, 2500) . "\n\n";
}

$order406 = DB::connection('tenant')->table('pos_orders')->where('id', 406)->first();
echo "--- order 406 ---\n";
print_r($order406);

$gap = DB::connection('tenant')->table('pos_orders')->whereBetween('id', [400, 410])->orderBy('id')->get(['id', 'order_no', 'status', 'guest_name', 'order_type', 'grand_total', 'created_at', 'updated_at']);
echo "--- orders 400-410 ---\n";
foreach ($gap as $o) {
    echo "{$o->id} {$o->order_no} {$o->status} {$o->guest_name} {$o->order_type} {$o->grand_total} {$o->created_at}\n";
}
