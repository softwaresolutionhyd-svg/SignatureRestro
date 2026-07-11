<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/inspect-xlsx-full.php';

$path = $argv[1] ?? 'C:/Users/Usman Computers/Desktop/STAFF DETAILS.xlsx';
$data = read_xlsx_all_sheets($path);
$rows = $data['sheets']['Sheet1'] ?? reset($data['sheets']);

$designations = [];
$employees = [];
$section = '';

foreach ($rows as $i => $row) {
    $c0 = trim((string) ($row[0] ?? ''));
    $c1 = trim((string) ($row[1] ?? ''));
    $c2 = trim((string) ($row[2] ?? ''));
    $c3 = trim((string) ($row[3] ?? ''));
    $c4 = trim((string) ($row[4] ?? ''));

    if ($c0 !== '' && $c1 === '' && $c2 === '' && stripos($c0, 'STAFF') === false && stripos($c0, 'SIGNATURE') === false) {
        $section = $c0;
        echo "SECTION: {$section}".PHP_EOL;
        continue;
    }

    if (stripos($c0, 'S . NO') !== false || stripos($c2, 'NAME') !== false) {
        continue;
    }

    $name = $c2 !== '' ? $c2 : ($c1 !== '' && ! is_numeric($c1) ? $c1 : '');
    $salary = is_numeric($c1) ? (float) $c1 : (is_numeric($c0) ? null : null);
    if ($name === '' && is_numeric($c0) && $c2 !== '') {
        $salary = is_numeric($c1) ? (float) $c1 : 0;
        $name = $c2;
        $desig = $c3;
        $join = $c4;
    } elseif ($name !== '' && $c3 !== '') {
        $salary = is_numeric($c1) ? (float) $c1 : 0;
        $desig = $c3;
        $join = $c4;
    } else {
        continue;
    }

    $name = trim(preg_replace('/\s+/', ' ', $name));
    $desig = trim(preg_replace('/\s+/', ' ', $desig));
    if ($name === '' || $desig === '') {
        continue;
    }

    $designations[$desig] = true;
    $employees[] = compact('i', 'section', 'name', 'desig', 'salary', 'join');
    echo sprintf('%3d | %-30s | %-20s | %s | %s'.PHP_EOL, count($employees), $name, $desig, $salary, $join);
}

echo PHP_EOL.'Total employees: '.count($employees).PHP_EOL;
echo 'Designations ('.count($designations).'): '.implode(', ', array_keys($designations)).PHP_EOL;
