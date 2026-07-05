<?php
/**
 * ChatHelp — apply-debug-remove (CH_DEBUG_REMOVE)
 *  GECICI teshis panellerini kaldirir:
 *   • CH_NAVDEBUG (sol alttaki yesil kutu)
 *   • CH_NAVORDER_DEBUG (sag ustteki camgobegi kutu)
 *  Sadece bu iki script blogunu siler, baska hicbir seye dokunmaz.
 * KULLANIM: pull-updates.php?files=apply-debug-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-debug-remove BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
file_put_contents($file . '.bak-dbgremove-' . date('Ymd-His'), $src);

$removed = 0;
foreach (['ch-navdebug', 'ch-navorder-debug'] as $id) {
    $start = strpos($src, '<script id="' . $id . '">');
    if ($start === false) { echo "  – $id zaten yok\n"; continue; }
    $end = strpos($src, '</script>', $start);
    if ($end === false) { echo "  ! $id kapanisi bulunamadi, atlandi\n"; continue; }
    $src = substr($src, 0, $start) . substr($src, $end + strlen('</script>'));
    $removed++;
    echo "  ✓ $id kaldirildi\n";
}

if (!$removed) exit("\nKaldirilacak teshis blogu kalmamis.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_DEBUG_REMOVE tamam ($removed blok silindi). Yesil/camgobegi kutular artik gorunmeyecek.\n";
