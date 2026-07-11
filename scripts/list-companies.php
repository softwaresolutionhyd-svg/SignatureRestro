<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::connection('mysql')->table('companies')->get(['id','name'])->each(function ($c) {
    echo $c->id.' | '.$c->name.PHP_EOL;
});
