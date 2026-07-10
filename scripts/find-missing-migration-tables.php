<?php

$tablesOutput = shell_exec('C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe -uroot signature_local -N -e "SHOW TABLES"');
$tables = array_flip(array_filter(array_map('trim', explode("\n", (string) $tablesOutput))));

$dir = __DIR__ . '/../database/migrations';
$missing = [];

foreach (glob($dir . '/*.php') as $file) {
    $content = file_get_contents($file);
    if (preg_match_all("/Schema::create\(['\"]([^'\"]+)['\"]/", $content, $matches)) {
        foreach ($matches[1] as $table) {
            if (! isset($tables[$table])) {
                $missing[basename($file)][] = $table;
            }
        }
    }
}

foreach ($missing as $file => $tabs) {
    echo $file . ' => ' . implode(', ', $tabs) . PHP_EOL;
}
