<?php

require __DIR__.'/../vendor/autoload.php';

function read_xlsx_rows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Cannot open xlsx: '.$path);
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = simplexml_load_string($sharedXml);
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $shared[] = (string) $si->t;
            } else {
                $parts = [];
                foreach ($si->r as $r) {
                    $parts[] = (string) $r->t;
                }
                $shared[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        throw new RuntimeException('sheet1.xml missing');
    }
    $zip->close();

    $sheet = simplexml_load_string($sheetXml);
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $letters = $m[1] ?? 'A';
            $idx = 0;
            foreach (str_split($letters) as $ch) {
                $idx = $idx * 26 + (ord($ch) - 64);
            }
            $idx--;

            while (count($line) < $idx) {
                $line[] = '';
            }

            $type = (string) ($c['t'] ?? '');
            $value = isset($c->v) ? (string) $c->v : '';
            if ($type === 's') {
                $value = $shared[(int) $value] ?? '';
            }
            $line[$idx] = $value;
        }
        $rows[] = $line;
    }

    return $rows;
}

$path = $argv[1] ?? 'C:/Users/Usman Computers/Desktop/STOCK DETAIL.xlsx';
if (! is_file($path)) {
    fwrite(STDERR, "File not found: {$path}\n");
    exit(1);
}

$rows = read_xlsx_rows($path);
echo 'Rows: '.count($rows).PHP_EOL;
foreach (array_slice($rows, 0, 25) as $i => $row) {
    echo $i.': '.json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

$designations = [];
foreach (array_slice($rows, 1) as $row) {
    $des = trim((string) ($row[1] ?? $row[2] ?? ''));
    if ($des !== '') {
        $designations[$des] = ($designations[$des] ?? 0) + 1;
    }
}
echo PHP_EOL.'Designations:'.PHP_EOL;
foreach ($designations as $d => $c) {
    echo "  {$d}: {$c}".PHP_EOL;
}
