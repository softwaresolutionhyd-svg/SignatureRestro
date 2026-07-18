<?php
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
try {
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create('/login', 'GET');
    $response = $kernel->handle($request);
    echo "STATUS=".$response->getStatusCode()."\n";
    echo substr(strip_tags($response->getContent()), 0, 800)."\n";
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "EXCEPTION=".get_class($e)."\n";
    echo "MSG=".$e->getMessage()."\n";
    echo "FILE=".$e->getFile().':'.$e->getLine()."\n";
    echo $e->getTraceAsString();
}

$log = $base.'/storage/logs/laravel.log';
if (is_file($log)) {
    echo "\n---- LOG TAIL ----\n";
    $lines = @file($log);
    if (is_array($lines)) {
        echo implode('', array_slice($lines, -40));
    }
}
