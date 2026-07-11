<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

App\Models\Employee::query()
    ->with('designation:id,name')
    ->orderBy('id')
    ->get(['id','employee_no','name','designation_id','salary'])
    ->each(function ($e) {
        echo sprintf("%s | %-30s | %s | %s\n",
            $e->employee_no,
            $e->name,
            $e->designation?->name ?? '(none)',
            $e->salary
        );
    });
