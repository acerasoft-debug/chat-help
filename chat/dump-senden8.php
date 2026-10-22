<?php
/**
 * ChatHelp — dump-senden8 — sari Senden'i DOM'a TEKRAR ekleyen enjektoru bulur (OKUR):
 *  index.php'de ch-senden-btn/Gratis-Fax ureten kod + etrafindaki setInterval/observer.
 * KULLANIM: pull2.php?key=...&files=dump-senden8.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-senden8 (".strlen($src)." B) ==\n\n";

/* ana kalip taramasi */
foreach(['ch-senden-btn','Gratis-Fax','CH_SENDEN1','CH_SENDEN1_OFF','CH_SENDEN3','CH_SENDEN7','ch-send-row'] as $pat){
  $off=0;$n=0;
  echo "=== '$pat' ===\n";
  while(($p=strpos($src,$pat,$off))!==false && $n<8){
    echo "  @$p: ...".str_replace(["\n","\r"],[' ',''],substr($src,max(0,$p-100),260))."...\n\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  (yok)\n\n";
}

/* butonu ekleyen createElement/appendChild yakinlari */
echo "=== createElement('button') yakini 'Senden' gecenler ===\n";
$off=0;$n=0;
while(($p=strpos($src,"createElement('button')",$off))!==false && $n<20){
  $ctx=substr($src,max(0,$p-150),700);
  if(stripos($ctx,'senden')!==false){ echo "  @$p: ...".str_replace(["\n","\r"],[' ',''],$ctx)."...\n\n"; $n++; }
  $off=$p+10;
}
echo "\n=== setInterval sayisi ===\n";
echo "  setInterval: ".substr_count($src,'setInterval')." adet\n";
echo "\n== BITTI ==\n";
