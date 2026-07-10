<?php

/**
 * Minimal XLSX reader (first sheet) without PhpSpreadsheet.
 */
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
        $colIndex = 0;
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
            $colIndex = max($colIndex, $idx);
        }
        $rows[] = $line;
    }

    return $rows;
}

$path = 'C:/Users/Usman Computers/Downloads/Export (2026-07-09_to_2026-07-10).xlsx';
$rows = read_xlsx_rows($path);
echo 'Rows: '.count($rows).PHP_EOL;
foreach (array_slice($rows, 0, 12) as $i => $row) {
    echo $i.': '.implode(' | ', $row).PHP_EOL;
}

$units = [];
foreach (array_slice($rows, 1) as $row) {
    $u = strtoupper(trim((string) ($row[1] ?? '')));
    if ($u !== '') {
        $units[$u] = ($units[$u] ?? 0) + 1;
    }
}
arsort($units);
echo PHP_EOL.'Unique units:'.PHP_EOL;
foreach ($units as $u => $c) {
    echo $u.': '.$c.PHP_EOL;
}
