<?php
/** ChatHelp — dump-verify2: EKSIK gorunen iki marker'in GERCEKTEN
 *  icerik olarak orada olup olmadigini dogrudan kontrol eder. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__ . '/index.php');
if ($idx === false) exit("index.php okunamadi\n");

echo "###### CH_VST_EXPAND2 gercek icerik kontrolu (16 sozlesme) ######\n";
foreach (['kaufvertrag:','aufhebung:','schenkung:','werkvertrag:','dienstvertrag:','buergschaft:','leihvertrag:','ratenzahlung:','garage:'] as $k) {
    echo "  " . (strpos($idx, $k) !== false ? '✓ VAR' : '✗ YOK') . "  $k\n";
}

echo "\n###### CH_CV_PHOTO_MANAGE gercek icerik kontrolu ######\n";
foreach (['cvs-photo-del','cvs-photo-manage-css'] as $k) {
    echo "  " . (strpos($idx, $k) !== false ? '✓ VAR' : '✗ YOK') . "  $k\n";
}

echo "\n=== BITTI ===\n";
