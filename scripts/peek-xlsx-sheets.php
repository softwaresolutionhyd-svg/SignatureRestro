<?php

require __DIR__.'/../vendor/autoload.php';

function read_xlsx_sheet(string $path, string $sheetFile): array
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

    $sheetXml = $zip->getFromName($sheetFile);
    if ($sheetXml === false) {
        $zip->close();
        return [];
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
$zip = new ZipArchive();
$zip->open($path);

echo "Workbook: {$path}\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i)."\n";
}
$zip->close();

$workbook = simplexml_load_string((new ZipArchive())->open($path) ? (function () use ($path) {
    $z = new ZipArchive();
    $z->open($path);
    $xml = $z->getFromName('xl/workbook.xml');
    $z->close();
    return $xml;
})() : '');

$zip = new ZipArchive();
$zip->open($path);
$wb = $zip->getFromName('xl/workbook.xml');
$rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
$zip->close();

$wbXml = simplexml_load_string($wb);
$relsXml = simplexml_load_string($rels);
$relMap = [];
foreach ($relsXml->Relationship as $rel) {
    $relMap[(string) $rel['Id']] = (string) $rel['Target'];
}

$ns = $wbXml->getNamespaces(true);
$wbXml->registerXPathNamespace('m', $ns['']);
$sheets = $wbXml->xpath('//m:sheets/m:sheet');

echo "\nSheets:\n";
foreach ($sheets as $sheet) {
    $name = (string) $sheet['name'];
    $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
    $target = $relMap[$rid] ?? '';
    $file = 'xl/'.ltrim(str_replace('\\', '/', $target), '/');
    echo "- {$name} => {$file}\n";
    $rows = read_xlsx_sheet($path, $file);
    echo "  rows: ".count($rows)."\n";
    foreach (array_slice($rows, 0, 8) as $ri => $row) {
        $text = trim(implode(' | ', array_filter(array_map('trim', $row))));
        if ($text !== '') {
            echo "  {$ri}: {$text}\n";
        }
    }
    echo "\n";
}
