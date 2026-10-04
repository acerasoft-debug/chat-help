<?php
/**
 * ChatHelp — dump-sr — showResult() + gold PDF butonu html kaynagini DOGRULA (OKUR).
 *  Amac: geriye-donuste-PDF neden bos? PDF butonu her belgenin kendi html'ini mi
 *  yoksa tek global closure/ch_lastdoc'u mu kullaniyor?
 * KULLANIM: pull2.php?key=...&files=dump-sr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***MASKED***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-sr (".strlen($src)." B) ==\n\n";

echo "=== showResult tanimi (ilk 2400 char) ===\n";
$p=strpos($src,'function showResult');
if($p===false) $p=strpos($src,'showResult=function');
if($p===false) $p=strpos($src,'showResult =');
if($p!==false){ echo mask(substr($src,$p,2400))."\n\n"; } else echo "  showResult bulunamadi\n\n";

echo "=== 'act gold' / PDF buton olusturma (racts icinde) civari ===\n";
$off=0;$n=0;
while(($p=strpos($src,'act gold',$off))!==false && $n<4){
  echo "  [$n] @$p ...".mask(substr($src,max(0,$p-260),520))."...\n\n";
  $off=$p+8;$n++;
}
if($n===0){
  /* alternatif: 'gold' butonu + onclick */
  $off=0;
  while(($p=strpos($src,'chPrintDoc',$off))!==false && $n<6){
    $seg=substr($src,max(0,$p-400),480);
    if(stripos($seg,'racts')!==false||stripos($seg,'act')!==false){ echo "  [alt$n] @$p ...".mask($seg)."...\n\n"; $n++; }
    $off=$p+10;
  }
}

echo "=== 'Metni Kopyala' / Kopieren buton bar civari (racts template) ===\n";
foreach(['Metni Kopyala','Kopieren','Kopyala'] as $kw){
  $p=strpos($src,$kw);
  if($p!==false){ echo "  --[$kw @$p]--\n  ".mask(substr($src,max(0,$p-520),760))."\n\n"; break; }
}
echo "== BITTI ==\n";
