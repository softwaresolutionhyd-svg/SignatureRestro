<?php

require __DIR__.'/inspect-xlsx-full.php';

$data = read_xlsx_all_sheets($argv[1] ?? 'C:/Users/Usman Computers/Desktop/STAFF DETAILS.xlsx');
$rows = $data['sheets']['Sheet1'] ?? reset($data['sheets']);

for ($i = 28; $i <= 38; $i++) {
    echo $i.': '.json_encode($rows[$i] ?? [], JSON_UNESCAPED_UNICODE).PHP_EOL;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cmd = new App\Console\Commands\ImportEmployeesFromXlsxCommand();
$ref = new ReflectionClass($cmd);
$parse = $ref->getMethod('parseStaffRows');
$parse->setAccessible(true);
$read = $ref->getMethod('readXlsxRows');
$read->setAccessible(true);
$parsed = $parse->invoke($cmd, $read->invoke($cmd, $argv[1] ?? 'C:/Users/Usman Computers/Desktop/STAFF DETAILS.xlsx'));
echo PHP_EOL.'Parsed count: '.count($parsed).PHP_EOL;
foreach (array_slice($parsed, 25, 10) as $p) {
    echo $p['name'].' | '.$p['designation'].PHP_EOL;
}
