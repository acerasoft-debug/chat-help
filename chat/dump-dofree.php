<?php
/* ChatHelp - dump-dofree (READ-ONLY) - doFree + ondan onceki yazdirma-yardimcisi BIREBIR (str_replace icin).
 * KULLANIM: /chat/pull2.php?key=...&files=dump-dofree.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false){ echo "index.php YOK\n"; return; }
echo "== dump-dofree ==\n\n";

/* doFree fonksiyonunun basi */
$df=strpos($s,'function doFree()');
echo "=== [1] doFree() konum: @".($df!==false?$df:-1)." ===\n";
if($df!==false){
  /* doFree'den ONCE ~700 char (yardimci fonksiyon) + doFree govdesi ~1800 char */
  $start=$df-800>0?$df-800:0;
  echo "----- ONCEKI 800 char (yazdirma yardimcisi) + doFree govdesi -----\n";
  echo str_replace("\r",'',substr($s,$start,2800))."\n\n";
}

/* doMail / doHome / doSend baslari (ayni yardimciyi mi kullaniyorlar?) */
echo "=== [2] doMail/doHome/doSend baslari (ilk 400 char) ===\n";
foreach(array('function doMail','function doHome','function doSend') as $fn){
  $q=strpos($s,$fn);
  if($q!==false){ echo "--- $fn @".$q." ---\n".str_replace("\r",'',substr($s,$q,500))."\n\n"; }
  else echo "  ($fn: yok)\n";
}

/* yazdirma yardimcisinin tam adi: doFree'den once tanimlanan 'function XXX(' */
echo "=== [3] doFree oncesi son fonksiyon tanimi (yardimci adi) ===\n";
if($df!==false){
  $pre=substr($s,$df-1500>0?$df-1500:0,1500);
  if(preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/',$pre,$m,PREG_OFFSET_CAPTURE)){
    foreach($m[1] as $fm){ echo "  function ".$fm[0]."(\n"; }
  }
}

/* chPrintDoc App-dl.php bloklari sayimi (mevcut WebView destegi kanit) */
echo "\n=== [4] chPrintDoc WebView (dl.php) destek isaretleri ===\n";
foreach(array('CH_CPDWV','CH_PDFFORM','dl.php','isWV') as $pat){
  echo "  '".$pat."': ".substr_count($s,$pat)." kez\n";
}
/* dl.php sunucuda var mi? */
$dl=@file_get_contents(__DIR__.'/dl.php');
echo "  dl.php dosyasi: ".($dl===false?'YOK':strlen($dl).' B')."\n";
echo "\n== BITTI ==\n";
