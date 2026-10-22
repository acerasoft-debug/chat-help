<?php
/**
 * ChatHelp — dump-savefn2 — 'FK' localStorage anahtarinin TANIMI + Konto/Hesap
 *  sayfasindaki 'Persönliche Daten' DUZENLEME formunun (ayri, chat-onboarding
 *  formu DEGIL) kaydetme fonksiyonu + genDoc'un adres OKUMA yolu (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-savefn2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-savefn2 (".strlen($src)." B) ==\n\n";

echo "=== [1] 'FK' tanimi (const/let/var FK=...) ===\n";
$off=0;$n=0;
while(($p=preg_match('/\b(const|let|var)\s+FK\s*=/',$src,$m,PREG_OFFSET_CAPTURE,$off))&&$n<10){
  $pos=$m[0][1];
  echo "  [$n] @$pos ...".substr($src,max(0,$pos-60),260)."...\n\n";
  $off=$pos+5;$n++;
}
if($n===0) echo "  bulunamadi (regex)\n";
echo "  'FK=' düz metin sayisi: ".substr_count($src,'FK=')."\n\n";

echo "=== [2] 'Persönliche Daten' baslikli BOLUM (Konto/hesap duzenleme) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'Persönliche Daten',$off))!==false && $n<6){
  echo "  [$n] @$p ...".substr($src,max(0,$p-200),700)."...\n\n"; $off=$p+18;$n++;
}
if($n===0) echo "  bulunamadi\n";

echo "=== [3] genDoc / dilekce olusturma adres-OKUMA yolu (P.f3 / prof().f3 kullanan yerler) ===\n";
foreach(['P.f3','prof().f3','\.f3\b'] as $pat){
  $c=preg_match_all('/'.$pat.'/',$src);
  echo "  '$pat' sayisi: $c\n";
}
$off=0;$n=0;
while(($p=strpos($src,'function genDoc',$off))!==false && $n<3){
  echo "  [genDoc @$p] ...".substr($src,$p,900)."...\n\n"; $off=$p+10;$n++;
}
if($n===0) echo "  genDoc bulunamadi (baska isimde olabilir)\n";

echo "\n== BITTI ==\n";
