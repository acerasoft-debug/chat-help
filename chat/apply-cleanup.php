<?php
/**
 * ChatHelp — apply-cleanup (CH_CLEANUP) — sunucudaki kalintilari temizle.
 *  (a) kalinti dump-*.php ve apply-*.php betikleri (kimlik dogrulamasiz)
 *  (b) *.bak-* yedek dosyalari (.php degil -> duz metin servis edilebilir; sizinti riski)
 *  Canli dosyalara (index/api/auth/config/db/stripe/pull2/admin...) DOKUNMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-cleanup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cleanup BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$self=basename(__FILE__);
$del=0;

echo "-- kalinti betikler --\n";
foreach(array_merge(glob("$D/dump-*.php"), glob("$D/apply-*.php")) as $f){
  $b=basename($f);
  if($b===$self) continue;
  if(@unlink($f)){ echo "  silindi: $b\n"; $del++; }
  else echo "  silinemedi: $b\n";
}

echo "\n-- yedek (.bak) dosyalari --\n";
$baks=array_unique(array_merge(glob("$D/*.bak-*"), glob("$D/*.bak")));
$bdel=0;
foreach($baks as $f){ if(@unlink($f)) $bdel++; else echo "  silinemedi: ".basename($f)."\n"; }
echo "  $bdel yedek silindi\n";

echo "\n-- kalan canli .php dosyalari (DOKUNULMADI) --\n";
$live=[];
foreach(glob("$D/*.php") as $f){ $b=basename($f); if($b!==$self && strpos($b,'dump-')!==0 && strpos($b,'apply-')!==0) $live[]=$b; }
echo "  ".implode(', ', $live)."\n";

echo "\nOK: $del betik + $bdel yedek silindi. Canli dosyalar korundu.\n";
