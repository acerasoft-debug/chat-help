<?php
/**
 * ChatHelp — dump-nocross — CH_NOCROSS periyodik adres-silme mantigi + curUid tespiti
 *  (OKUR): adres neden siliniyor/kayboluyor? uid kararsizsa adres wipe olur.
 * KULLANIM: pull2.php?key=...&files=dump-nocross.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-nocross (".number_format(strlen($src))." B) ==\n\n";

/* curUid / bound uid tanimi */
echo "=== [A] curUid / ch_bound_uid tanimi ===\n";
foreach(['function curUid','curUid=','ch_bound_uid'] as $pat){
  $off=0;$n=0;
  while(($p=strpos($src,$pat,$off))!==false && $n<4){
    echo "  [$pat @$p]: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-40),260))."...\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  [$pat]: yok\n";
}

/* CH_NOCROSS tick blogu (@1921133 civari) TAM */
echo "\n=== [B] CH_NOCROSS periyodik blok (2. kopya) TAM ===\n";
$p=strpos($src,'CH_NOCROSS',1900000);
if($p!==false){
  $st=strrpos(substr($src,0,$p),'<script');
  $en=strpos($src,'</script>',$p); if($en!==false)$en+=9;
  echo substr($src,$st,min($en-$st,2600))."\n";
} else echo "  bulunamadi\n";

/* ilk CH_NOCROSS (logout/kullanici degisimi) */
echo "\n=== [C] ilk CH_NOCROSS baglam (@11555 civari) ===\n";
$p=strpos($src,'CH_NOCROSS');
if($p!==false) echo substr($src,max(0,$p-500),700)."\n";

echo "\n== BITTI ==\n";
