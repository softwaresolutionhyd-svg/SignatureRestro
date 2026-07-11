<?php

require __DIR__.'/../vendor/autoload.php';

function read_xlsx_all_sheets(string $path): array
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

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    $relMap = [];
    foreach ($rels->Relationship as $rel) {
        $relMap[(string) $rel['Id']] = (string) $rel['Target'];
    }

    $sheets = [];
    foreach ($workbook->sheets->sheet as $sheet) {
        $name = (string) $sheet['name'];
        $rid = (string) $sheet->attributes('r', true)['id'];
        $target = 'xl/'.str_replace('xl/', '', $relMap[$rid] ?? '');
        $sheetXml = $zip->getFromName($target);
        if ($sheetXml === false) {
            continue;
        }
        $sheetDoc = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheetDoc->sheetData->row as $row) {
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
        $sheets[$name] = $rows;
    }

    $zip->close();

    return ['shared' => $shared, 'sheets' => $sheets];
}

$path = $argv[1] ?? 'C:/Users/Usman Computers/Desktop/STOCK DETAIL.xlsx';
$data = read_xlsx_all_sheets($path);

echo 'Shared strings ('.count($data['shared']).'):'.PHP_EOL;
foreach (array_slice($data['shared'], 0, 50) as $i => $s) {
    echo "  [$i] $s".PHP_EOL;
}
if (count($data['shared']) > 50) {
    echo '  ... and '.(count($data['shared']) - 50).' more'.PHP_EOL;
}

foreach ($data['sheets'] as $name => $rows) {
    echo PHP_EOL."=== Sheet: {$name} (".count($rows)." rows) ===".PHP_EOL;
    foreach (array_slice($rows, 0, 15) as $i => $row) {
        echo $i.': '.json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    echo '... non-empty text rows:'.PHP_EOL;
    $count = 0;
    foreach ($rows as $i => $row) {
        $texts = array_filter(array_map('trim', $row), fn ($v) => $v !== '' && ! is_numeric($v));
        if (count($texts) >= 2) {
            echo $i.': '.json_encode(array_values($texts), JSON_UNESCAPED_UNICODE).PHP_EOL;
            if (++$count >= 30) {
                break;
            }
        }
    }
}
