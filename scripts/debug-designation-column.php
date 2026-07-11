<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = Illuminate\Support\Facades\Schema::connection('tenant')->getColumnListing('employees');
echo 'employees columns: '.implode(', ', $cols).PHP_EOL;

$e = App\Models\Employee::withoutGlobalScopes()
    ->where('employee_no', 'EMP-00015')
    ->first();

echo 'designation_id: '.$e->designation_id.PHP_EOL;
echo 'getAttributes designation: '.json_encode($e->getAttributes()['designation'] ?? 'NO COLUMN').PHP_EOL;
echo 'relation loaded: '.($e->relationLoaded('designation') ? 'yes' : 'no').PHP_EOL;

$e->load('designation');
echo 'after load relation: '.($e->relationLoaded('designation') ? 'yes' : 'no').PHP_EOL;
echo '$e->designation type: '.gettype($e->designation).PHP_EOL;
echo '$e->designation value: '.json_encode($e->designation).PHP_EOL;
echo 'getRelation designation: '.($e->getRelation('designation')?->name ?? 'null').PHP_EOL;
