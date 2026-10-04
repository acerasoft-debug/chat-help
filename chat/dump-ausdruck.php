<?php
/**
 * ChatHelp — dump-ausdruck — 'kostenlos ausdrucken' butonunun TAM handler'ini DOGRULA (OKUR).
 *  App'te tiklaninca disari atiyor — hangi fonksiyonu cagiriyor, print()/window.open() var mi?
 * KULLANIM: pull2.php?key=...&files=dump-ausdruck.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-ausdruck (".strlen($src)." B) ==\n\n";

echo "=== [1] 'kostenlos ausdrucken' / 'ausdrucken' TUM gecisler ===\n";
$off=0;$n=0;
while(($p=stripos($src,'ausdrucken',$off))!==false && $n<12){
  echo "  [$n] @$p ...".substr($src,max(0,$p-160),320)."...\n\n"; $off=$p+10;$n++;
}
if($n===0) echo "  'ausdrucken' bulunamadi\n";

echo "=== [2] 'selbst ausdrucken' civari genis baglam (buton + onclick) ===\n";
$p=stripos($src,'selbst ausdrucken');
if($p!==false) echo substr($src,max(0,$p-500),1400)."\n";

echo "\n== BITTI ==\n";
