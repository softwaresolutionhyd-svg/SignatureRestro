<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$nos = ['EMP-00015', 'EMP-00021', 'EMP-00007', 'EMP-00046'];

foreach ($nos as $no) {
    $e = App\Models\Employee::withoutGlobalScopes()
        ->where('employee_no', $no)
        ->first(['id', 'company_id', 'employee_no', 'name', 'designation_id', 'department_id']);

    if (! $e) {
        echo "{$no}: NOT FOUND\n";
        continue;
    }

    $desig = App\Models\EmployeeDesignation::withoutGlobalScopes()
        ->find($e->designation_id, ['id', 'company_id', 'name']);

    echo "{$no} | {$e->name} | company={$e->company_id} | designation_id={$e->designation_id} | desig="
        .($desig ? "{$desig->name} (company={$desig->company_id})" : 'NULL')."\n";
}

echo "\nDesignations without company_id:\n";
App\Models\EmployeeDesignation::withoutGlobalScopes()
    ->whereNull('company_id')
    ->orWhere('company_id', 0)
    ->limit(10)
    ->get(['id', 'name', 'company_id'])
    ->each(fn ($d) => print("  {$d->id} | {$d->name} | company={$d->company_id}\n"));

echo "\nEmployees with null designation_id:\n";
echo App\Models\Employee::withoutGlobalScopes()->whereNull('designation_id')->count()."\n";
