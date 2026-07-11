<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$e = App\Models\Employee::withoutGlobalScopes()->where('employee_no', 'EMP-00015')->first();
$e->load(['department', 'designation']);
echo 'department attr: '.json_encode($e->department).PHP_EOL;
echo 'department relation: '.($e->getRelation('designation')?->name ?? 'null').PHP_EOL;
