<?php
/** ChatHelp — dump-photoflow: canli index.php'de foto yukleme akisini gosterir.
 *  Amac: "foto yuklenince AI'ya gitmiyor" sorununu tam yerinden teshis etmek.
 *  SADECE OKUR. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__ . '/index.php');
if ($idx === false) exit("index.php okunamadi\n");

function win($s, $needle, $before, $len, $label) {
    echo "###### $label ######\n";
    $p = strpos($s, $needle);
    if ($p === false) { echo "  (bulunamadi: '$needle')\n\n"; return; }
    $start = max(0, $p - $before);
    echo substr($s, $start, $len) . "\n\n";
}

echo "index.php: " . strlen($idx) . " bayt\n\n";

/* 1) tum foto/dosya file input'lari */
echo "###### TUM <input type=\"file\" ... > (offset + 160 char) ######\n";
$pos = 0; $i = 0;
while (($p = strpos($idx, 'type="file"', $pos)) !== false) {
    $s2 = max(0, $p - 90);
    echo "  #".($i++)." @".$p.": ..." . str_replace("\n"," ", substr($idx, $s2, 260)) . "...\n";
    $pos = $p + 10;
    if ($i > 25) { echo "  (25+ kesildi)\n"; break; }
}
echo "\n";

/* 2) openFall / Fall intake overlay */
win($idx, 'function openFall', 0, 900, 'openFall() govdesi');
win($idx, 'ch-intake-ov', -40, 700, 'intake overlay (ch-intake-ov) civari');

/* 3) foto degiskeni _chPhotos nerede set/read ediliyor */
echo "###### _chPhotos GECEN TUM YERLER ######\n";
$pos=0;$i=0;
while (($p = strpos($idx, '_chPhotos', $pos)) !== false) {
    echo "  @".$p.": ..." . str_replace("\n"," ", substr($idx, max(0,$p-70), 200)) . "...\n";
    $pos=$p+5; if(++$i>20){echo "  (20+ kesildi)\n";break;}
}
echo "\n";

/* 4) intake solve() — photos gonderiyor mu? */
win($idx, "apiPost('intake_solve'", -30, 500, "solve() -> intake_solve cagrisi (photos VAR MI?)");

/* 5) "scan" / "Brief" / "Bescheid" / analiz butonlari */
echo "###### 'scannen'/'Bescheid'/'analy' gecen kisa kesitler ######\n";
foreach (['scannen','Bescheid scan','Foto','pf\''] as $needle) {
    $p = strpos($idx, $needle);
    echo "  '$needle': " . ($p===false ? 'yok' : ("@$p ..." . str_replace("\n"," ", substr($idx, max(0,$p-60), 160)) . "...")) . "\n";
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-photoflow.php ===\n";
