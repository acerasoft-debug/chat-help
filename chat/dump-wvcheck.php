<?php
/**
 * ChatHelp — dump-wvcheck — PDF halen App'te disari atiyor. 2 hipotez (OKUR):
 *  (A) isWV() bu App'in User-Agent'ini TANIMIYOR -> jsPDF dali hic calismiyor.
 *  (B) jsPDF harici CDN'den (cdnjs) yuklenemiyor (App network/CSP kisitlamasi).
 * KULLANIM: pull2.php?key=...&files=dump-wvcheck.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-wvcheck (".strlen($src)." B) ==\n\n";

echo "=== [1] isWV() TAM govde (kesilmemis) ===\n";
$p=strpos($src,'function isWV');
if($p!==false) echo substr($src,$p,700)."\n\n"; else echo "  yok\n";

echo "=== [2] chIsApp / custom App-marker (varsa daha guvenilir tespit) ===\n";
foreach(['chIsApp','chIsAndroidApp','CH_APP_MARKER','Capacitor','x-ch-app','ChatHelpApp'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [3] CSP / Content-Security-Policy meta etiketi ===\n";
$p=stripos($src,'Content-Security-Policy');
if($p!==false) echo "  ...".substr($src,max(0,$p-40),400)."...\n"; else echo "  CSP meta yok\n";

echo "\n=== [4] jspdf script tag TAM satir ===\n";
$p=stripos($src,'jspdf.umd');
if($p!==false) echo "  ...".substr($src,max(0,$p-160),260)."...\n";

echo "\n=== [5] mobile/ klasorunde Capacitor allowNavigation / server config var mi? ===\n";
$cfg=@file_get_contents(__DIR__.'/../mobile/capacitor.config.json');
if($cfg!==false) echo $cfg."\n"; else echo "  mobile/capacitor.config.json bu dizinden erisilemedi (normal, ayri repo yolu olabilir)\n";

echo "\n== BITTI ==\n";
