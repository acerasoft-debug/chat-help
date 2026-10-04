<?php
/**
 * ChatHelp — apply-cleanup27 — sunucu temizligi (Task #27)
 *  GUVENLI kural:
 *   • Her canli dosyanin EN YENI 3 yedegi (.bak-*) KALIR — geri donus guvencesi.
 *     Daha eski yedekler silinir.
 *   • dump-*.php teshis dosyalari silinir (ciktilari zaten toplandi; hepsi
 *     git'te durur, gerekirse pull-updates ile geri indirilir).
 *   • Canli dosyalara (index/api/auth/fall-api/intake-api/config/pull-updates,
 *     data/ klasoru) ASLA dokunulmaz.
 *  Ne silindigi tek tek raporlanir + kazanilan alan gosterilir.
 * KULLANIM: pull-updates.php?files=apply-cleanup27.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cleanup27 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir = __DIR__;
$KEEP = 3;
$freed = 0; $nDel = 0;

/* ── 1) .bak-* yedekleri: canli dosya basina en yeni 3 kalsin ── */
$groups = [];
foreach (glob($dir.'/*.bak-*') as $f) {
    $b = basename($f);
    $prefix = substr($b, 0, strpos($b, '.bak-'));
    $groups[$prefix][] = $f;
}
echo "###### YEDEKLER (.bak-*) ######\n";
ksort($groups);
foreach ($groups as $prefix => $files) {
    /* en yeniden eskiye sirala (mtime) */
    usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
    $kept = array_slice($files, 0, $KEEP);
    $del  = array_slice($files, $KEEP);
    echo sprintf("  %-18s toplam %2d — kalan %d, silinen %d\n", $prefix, count($files), count($kept), count($del));
    foreach ($del as $f) {
        $sz = filesize($f);
        if (@unlink($f)) { $freed += $sz; $nDel++; }
    }
}
if (!$groups) echo "  (yedek yok)\n";

/* ── 2) dump-*.php teshisleri ── */
echo "\n###### TESHISLER (dump-*.php) ######\n";
$dumps = glob($dir.'/dump-*.php');
/* eski numarali dumplar da (dump1.php..dump99.php) */
foreach (glob($dir.'/dump*.php') as $f) { if (!in_array($f,$dumps,true)) $dumps[] = $f; }
foreach ($dumps as $f) {
    $b = basename($f);
    $sz = filesize($f);
    if (@unlink($f)) { $freed += $sz; $nDel++; echo "  🗑 $b\n"; }
}
if (!$dumps) echo "  (dump yok)\n";

/* ── 3) ozet ── */
echo "\n###### OZET ######\n";
echo "  Silinen dosya : $nDel\n";
echo "  Kazanilan alan: ".round($freed/1048576,1)." MB\n";
$left = count(glob($dir.'/*.bak-*'));
echo "  Kalan yedek   : $left (canli dosya basina en yeni $KEEP)\n";
if (function_exists('disk_free_space')) {
    $df = @disk_free_space($dir);
    if ($df) echo "  Disk bos alan : ".round($df/1073741824,2)." GB\n";
}
echo "\n✓ Temizlik bitti. Canli dosyalara dokunulmadi; son $KEEP yedek geri donus icin duruyor.\n";
