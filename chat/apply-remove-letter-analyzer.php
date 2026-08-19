<?php
/**
 * ChatHelp — apply-remove-letter-analyzer (CH_RM_LETTER)
 *  "📨 Schreiben verstehen / Mektup Analizi" modulunu kaldirir
 *  (gereksiz — foto bolumu + normal chat zaten var).
 *  Sadece ch-letter-analyzer script/style bloklarini siler; baska
 *  hicbir seye dokunmaz (window.__aiChatTurn ifsasi zararsiz, kalir).
 * KULLANIM: pull-updates.php?files=apply-remove-letter-analyzer.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-remove-letter-analyzer BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
file_put_contents($file . '.bak-rmletter-' . date('Ymd-His'), $src);

$removed = 0;
foreach ([['<style id="ch-letter-analyzer-css">','</style>'],
          ['<script id="ch-letter-analyzer">','</script>']] as $pair) {
    $start = strpos($src, $pair[0]);
    if ($start === false) { echo "  – blok zaten yok: {$pair[0]}\n"; continue; }
    $end = strpos($src, $pair[1], $start);
    if ($end === false) { echo "  ! kapanis bulunamadi: {$pair[0]}\n"; continue; }
    $src = substr($src, 0, $start) . substr($src, $end + strlen($pair[1]));
    $removed++;
    echo "  ✓ silindi: {$pair[0]}\n";
}

if (!$removed) exit("\nKaldirilacak blok kalmamis.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_RM_LETTER: Mektup Analizi modulu kaldirildi ($removed blok). Sol menu/ana sayfadan '📨' kayboldu.\n";
