<?php
/**
 * ChatHelp — dump-savefn4 — supheli 'F.forEach(k=>P[k]=(d&&d[k])||"")' ezme
 *  fonksiyonunun TAM baglami (fonksiyon adi + NEREDEN cagriliyor + 'd' neyi
 *  iceriyor) — adres neden siliniyor teshisi (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-savefn4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-savefn4 (".strlen($src)." B) ==\n\n";

echo "=== [1] ezme fonksiyonu GENIS baglam (1200 once, 900 sonra) ===\n";
$anchor='F.forEach(function(k){ P[k]=(d&&d[k])||""; });';
$p=strpos($src,$anchor);
if($p!==false){ echo substr($src,max(0,$p-1200),2200)."\n"; }
else echo "  bulunamadi (metin degisti mi?)\n  ara: ".substr_count($src,'P[k]=(d&&d[k])')."\n";

echo "\n=== [2] bu fonksiyonu CAGIRAN yerler (isim tahmini icin genis arama) ===\n";
foreach(['function loadUserData','function syncProfile','function applyUserData','function mergeUser','function setUser','function onLogin'] as $pat){
  $p=strpos($src,$pat);
  if($p!==false) echo "  BULUNDU: $pat @$p\n";
}

echo "\n=== [3] 'PDF erstellen' butonu/metni TUM kullanim yerleri (yeni istek: direkt dilekce cikarsin) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'PDF erstellen',$off))!==false && $n<8){
  echo "  [$n] @$p ...".substr($src,max(0,$p-250),450)."...\n\n"; $off=$p+13;$n++;
}
if($n===0) echo "  bulunamadi\n";

echo "\n== BITTI ==\n";
