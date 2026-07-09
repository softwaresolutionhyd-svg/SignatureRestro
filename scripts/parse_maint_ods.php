<?php

$path = $argv[1] ?? '';
if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "Usage: php parse_maint_ods.php <file.ods>\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Cannot open ODS\n");
    exit(1);
}
$xml = $zip->getFromName('content.xml');
$zip->close();

$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
$xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
$xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');

$rows = $xpath->query('//table:table-row');
$out = [];
$isHeader = true;

foreach ($rows as $row) {
    $cells = $xpath->query('table:table-cell', $row);
    $values = [];
    foreach ($cells as $cell) {
        $repeated = (int) $cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'number-columns-repeated');
        if ($repeated > 1) {
            break;
        }
        $value = $cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0', 'value');
        if ($value !== '') {
            $values[] = $value;
        } else {
            $p = $xpath->query('text:p', $cell)->item(0);
            $values[] = trim($p ? $p->textContent : '');
        }
    }
    if (count($values) < 2) {
        continue;
    }
    if ($isHeader) {
        $isHeader = false;
        continue;
    }
    $ser = $values[0] ?? '';
    $name = trim($values[1] ?? '');
    if ($name === '') {
        continue;
    }
    $rate = (float) ($values[2] ?? 0);
    $qty = (float) ($values[3] ?? 0);
    $out[] = compact('ser', 'name', 'rate', 'qty');
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
