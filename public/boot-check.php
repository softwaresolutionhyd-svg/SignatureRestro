<?php
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
$log = $base.'/storage/logs/laravel.log';

// Clear last request noise: show last error block only
if (is_file($log)) {
    $content = file_get_contents($log);
    $pos = strrpos($content, 'local.ERROR');
    if ($pos === false) {
        $pos = strrpos($content, '.ERROR:');
    }
    if ($pos !== false) {
        echo "---- LAST ERROR ----\n";
        echo substr($content, $pos, 2500)."\n";
    } else {
        echo "No ERROR in laravel.log\n";
        echo substr($content, -1500)."\n";
    }
} else {
    echo "No laravel.log\n";
}

echo "\n---- BOOT ----\n";
try {
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create('/login', 'GET');
    $response = $kernel->handle($request);
    echo "STATUS=".$response->getStatusCode()."\n";
    // Try to pull exception message from Ignition/HTML title
    $html = $response->getContent();
    if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
        echo "TITLE=".html_entity_decode(strip_tags($m[1]))."\n";
    }
    if (preg_match('/class="exception-message[^"]*"[^>]*>(.*?)<\/div>/si', $html, $m)) {
        echo "EXC=".trim(html_entity_decode(strip_tags($m[1])))."\n";
    }
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "EXCEPTION=".get_class($e)."\nMSG=".$e->getMessage()."\nFILE=".$e->getFile().':'.$e->getLine()."\n";
}
