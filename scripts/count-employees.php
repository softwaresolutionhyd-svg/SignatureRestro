<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'employees: '.App\Models\Employee::count().PHP_EOL;
echo 'designations: '.App\Models\EmployeeDesignation::count().PHP_EOL;
App\Models\Employee::query()->orderBy('id')->limit(10)->get(['employee_no', 'name'])->each(function ($e) {
    echo $e->employee_no.' | '.$e->name.PHP_EOL;
});
