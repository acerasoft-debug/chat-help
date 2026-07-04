<?php
/** ChatHelp — dump-bakcheck: chat/ icindeki index.php.bak-* dosyalarini
 *  mtime sirasina gore listeler, her biri icin <script>/</script> dengesini
 *  kontrol eder (esit ise TEMIZ, degilse BOZUK/kirilma noktasi bu dosyada
 *  veya oncesinde degil, TAM BU ADIMDA olustu demektir).
 *  SADECE OKUR — hicbir sey degistirmez. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);

$files = glob(__DIR__ . '/index.php.bak-*');
if (!$files) exit("hic .bak dosyasi yok\n");

usort($files, function($a, $b) { return filemtime($a) <=> filemtime($b); });

echo "###### index.php.bak-* dosyalari (mtime sirali, eskiden yeniye) ######\n\n";
foreach ($files as $f) {
    $c = @file_get_contents($f);
    if ($c === false) { echo basename($f) . "  OKUNAMADI\n"; continue; }
    $opens = substr_count($c, '<script');
    $closes = substr_count($c, '</script>');
    $len = strlen($c);
    $balance = ($opens === $closes) ? 'TEMIZ' : 'BOZUK (fark=' . ($opens - $closes) . ')';
    echo basename($f) . "\n";
    echo "  mtime: " . date('Y-m-d H:i:s', filemtime($f)) . "\n";
    echo "  boyut: $len bayt\n";
    echo "  <script acilis=$opens  </script> kapanis=$closes  => $balance\n\n";
}

$cur = @file_get_contents(__DIR__ . '/index.php');
if ($cur !== false) {
    $opens = substr_count($cur, '<script');
    $closes = substr_count($cur, '</script>');
    echo "###### GUNCEL index.php ######\n";
    echo "  boyut: " . strlen($cur) . " bayt\n";
    echo "  <script acilis=$opens  </script> kapanis=$closes  => " . (($opens === $closes) ? 'TEMIZ' : 'BOZUK (fark=' . ($opens - $closes) . ')') . "\n";
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-bakcheck.php ===\n";
