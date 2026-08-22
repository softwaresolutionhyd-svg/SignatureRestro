<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$targets = [];

foreach (App\Models\InventoryDepartment::query()
    ->whereNotNull('printer_ip')
    ->where('printer_ip', '!=', '')
    ->get(['name', 'printer_ip', 'printer_port']) as $dept) {
    $targets[] = [
        'label' => $dept->name,
        'ip' => trim((string) $dept->printer_ip),
        'port' => (int) ($dept->printer_port ?: 9100),
    ];
}

$cashierIp = trim((string) App\Models\Setting::get('cashier_printer_ip', ''));
if ($cashierIp !== '') {
    $targets[] = [
        'label' => 'Cashier',
        'ip' => $cashierIp,
        'port' => (int) (App\Models\Setting::get('cashier_printer_port') ?: 9100),
    ];
}

if ($targets === []) {
    echo "  (No printer IPs configured in Kitchen Agents)\n";

    exit(0);
}

foreach ($targets as $t) {
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($t['ip'], $t['port'], $errno, $errstr, 3);
    if (is_resource($fp)) {
        fclose($fp);
        echo "  OK   {$t['label']} -> {$t['ip']}:{$t['port']}\n";
    } else {
        echo "  FAIL {$t['label']} -> {$t['ip']}:{$t['port']} ($errstr)\n";
    }
}
