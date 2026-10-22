<?php
/**
 * ChatHelp — dump-pdfhist — 4 konuyu tek seferde DOGRULA (OKUR):
 *  (a) chPrintDoc / PDF uretimi (geriye donuste PDF calismiyor)
 *  (b) Verlauf/gecmis geri-yukleme render'i (.racts / gold PDF butonu nasil basiliyor)
 *  (c) gonderim/PDF akisinda alert/hata mesajlari ("bazen hata veriyor")
 *  (d) 'Faxnummer per KI finden' handler (fax-bul)
 * KULLANIM: pull2.php?key=...&files=dump-pdfhist.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{8,}|[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-pdfhist index.php (".strlen($src)." B) ==\n\n";

function ctx($src,$needle,$before,$len,$max=3){
  $off=0;$n=0;
  while(($p=stripos($src,$needle,$off))!==false && $n<$max){
    echo "  [".$n."] @".$p." ...".mask(substr($src,max(0,$p-$before),$len))."...\n\n";
    $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  '$needle' bulunamadi\n";
}

echo "=== (a) chPrintDoc tanimi ===\n";
$p=strpos($src,'function chPrintDoc');
if($p===false) $p=strpos($src,'chPrintDoc=');
if($p!==false) echo mask(substr($src,$p,900))."\n\n"; else echo "  chPrintDoc bulunamadi\n\n";

echo "=== (b) Verlauf/gecmis geri-yukleme (restore/renderMsg/racts insert) ===\n";
foreach(['renderResult','racts','buildActs','function renderMsg','restoreChat','loadHistory','ch_history','openTopic','renderDoc'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- '.racts' olusturuldugu yer (±80) --\n";
ctx($src,"racts",80,320,3);

echo "=== (c) hata/alert mesajlari (Analyse/Fehler/fehlgeschlagen/hata/error toast) ===\n";
foreach(['fehlgeschlagen','Fehler','leere Antwort','sunucu bos','Analiz','alert(','showToast','toast(','catch('] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n  -- 'fehlgeschlagen'/'leere' baglami --\n";
ctx($src,"fehlgeschlagen",120,260,4);

echo "=== (d) Faxnummer per KI finden handler ===\n";
ctx($src,"KI finden",100,80,2);
foreach(['findFax','faxFind','fax_lookup','findNumber','ki_fax','faxKI'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n== BITTI ==\n";
