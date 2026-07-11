<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$without = App\Models\Employee::query()
    ->whereNull('designation_id')
    ->get(['employee_no', 'name']);

echo 'Without designation: '.$without->count().PHP_EOL;
$without->each(fn ($e) => print($e->employee_no.' | '.$e->name.PHP_EOL));

echo PHP_EOL.'Sample with designation:'.PHP_EOL;
App\Models\Employee::query()
    ->with('designation:id,name')
    ->whereNotNull('designation_id')
    ->orderBy('employee_no')
    ->limit(10)
    ->get(['employee_no', 'name', 'designation_id'])
    ->each(fn ($e) => print($e->employee_no.' | '.$e->name.' | '.($e->designation?->name ?? '-').PHP_EOL));

echo PHP_EOL.'All designations:'.PHP_EOL;
App\Models\EmployeeDesignation::query()->orderBy('name')->pluck('name')->each(fn ($n) => print(' - '.$n.PHP_EOL));
