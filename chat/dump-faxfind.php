<?php
/**
 * ChatHelp — dump-faxfind — 'Faxnummer per KI finden' butonu + handler + isim-okuma
 *  kaynagini DOGRULA (OKUR). Bug: isim varken 'önce isim girin' diyor.
 * KULLANIM: pull2.php?key=...&files=dump-faxfind.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-faxfind (".strlen($src)." B) ==\n\n";
function ctx($src,$needle,$b,$l,$max=4){
  $off=0;$n=0;
  while(($p=stripos($src,$needle,$off))!==false && $n<$max){
    echo "  [$n] @$p ...".mask(substr($src,max(0,$p-$b),$l))."...\n\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  '$needle' yok\n";
}

echo "=== [1] 'KI finden' / 'Faxnummer per KI' buton + handler ===\n";
ctx($src,'per KI finden',180,220,3);
ctx($src,'KI finden',80,120,3);

echo "=== [2] 'Empfänger (Name) eingeben' / 'zuerst den Empfänger' guard mesaji ===\n";
ctx($src,'zuerst den Empf',260,360,3);
ctx($src,'Name) eingeben',260,300,2);
foreach(['Bitte zuerst','Empfänger (Name)','önce isim','isim girin','recipient name'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}

echo "\n=== [3] fax-find fonksiyonu (findFax / faxFind / ki_fax) ===\n";
foreach(['findFax','faxFind','ki_fax','faxKI','findFaxNr','faxSuche','sucheFax','faxLookup'] as $kw){
  $c=substr_count($src,$kw); if($c>0){ echo "  '$kw': $c\n"; ctx($src,$kw,60,300,1); }
}

echo "\n=== [4] isim okunan alan id'leri (ai-rec / ai-faxnr / recipient) civari ===\n";
foreach(['ai-faxnr','ai-rec','fax-rec','faxrecipient','getElementById(\'ai-rec'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
echo "\n== BITTI ==\n";
