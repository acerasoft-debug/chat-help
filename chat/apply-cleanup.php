<?php
/**
 * ChatHelp — apply-cleanup — teshis dosyasi temizligi (GUVENLI)
 *  • Sunucudaki tum dump-*.php teshis betiklerini siler (hepsi git'te durur;
 *    gerekirse pull-updates ile tek tek geri indirilebilir).
 *  • index.php / api.php / fall-api.php ve TUM .bak-* yedeklerine DOKUNMAZ.
 *  • Son durumu raporlar: canli dosyalarin lint'i, stabilite isaretleri,
 *    yedek sayisi/boyutu.
 * KULLANIM: pull-updates.php?files=apply-cleanup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D = __DIR__;
echo "apply-cleanup BASLADI OK\n\n";

/* [1] dump-* teshis betikleri */
$dumps = glob($D.'/dump-*.php') ?: [];
echo "──── [1] Teshis betikleri ────\n";
$n=0; $b=0;
foreach ($dumps as $f) {
    $s=filesize($f);
    if (@unlink($f)) { echo "  🗑 ".basename($f)." (".number_format($s)." B)\n"; $n++; $b+=$s; }
    else echo "  ✗ silinemedi: ".basename($f)."\n";
}
echo $n ? "  ✓ $n teshis dosyasi silindi (".number_format($b)." B)\n" : "  (silinecek dump yok)\n";

/* [2] artakalan apply-* var mi? (pull-updates normalde siler) */
$applies = array_filter(glob($D.'/apply-*.php') ?: [], fn($f)=>basename($f)!=='apply-cleanup.php');
echo "\n──── [2] Artakalan apply-* ────\n";
if ($applies) foreach ($applies as $f) echo "  ⚠ duruyor: ".basename($f)." (pull-updates silmemis — elle kontrol)\n";
else echo "  ✓ yok (temiz)\n";

/* [3] canli dosyalar saglik kontrolu */
echo "\n──── [3] Canli dosyalar ────\n";
foreach (['index.php','api.php','fall-api.php'] as $f) {
    $p="$D/$f";
    if (!file_exists($p)) { echo "  ✗ $f YOK!\n"; continue; }
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($p).' 2>&1',$lo,$rc);
    echo "  ".($rc===0?"✓":"✗")." $f  ".number_format(filesize($p))." B  lint:".($rc===0?"TEMIZ":implode(' ',$lo))."\n";
}
$idx=(string)@file_get_contents("$D/index.php");
echo "\n──── [4] Stabilite isaretleri ────\n";
foreach (['CH_UI_STABILITY','CH_STAB_FINAL','CH_STAB_FINAL2','CH_STAB_FINAL3','CH_CATGRID_CSSORDER','CH_SBPROBE3','CH_CLICKFIX'] as $m)
    echo "  ".str_pad($m,22).": ".(strpos($idx,$m)!==false?"VAR":"yok")."\n";
echo "  (beklenen: ilk 5 VAR, son 2 yok)\n";

/* [5] yedek envanteri (bilgi — dokunulmadi) */
$baks = glob($D.'/index.php.bak-*') ?: [];
$tot=0; foreach($baks as $f) $tot+=filesize($f);
echo "\n──── [5] Yedekler (DOKUNULMADI) ────\n";
echo "  index.php yedekleri: ".count($baks)." adet, toplam ".number_format($tot)." B\n";
echo "  En yenisi: ".(count($baks)?basename(end($baks)):"-")."\n";

echo "\n✓ Temizlik bitti. Site dosyalarina dokunulmadi.\n";
