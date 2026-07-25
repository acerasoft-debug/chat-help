<?php
/* ChatHelp - dump-pdftail (READ-ONLY) - makePDF print/popup tetikleyicisi + PDF buton akisi */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-pdftail (".number_format(strlen($src))." B) ==\n\n";

$q=strpos($src,'function makePDF');
if($q!==false){
  echo "=== makePDF kuyrugu (@".$q." +2500..+5200) ===\n";
  echo str_replace("\r",'',substr($src,$q+2500,2700))."\n\n";
} else echo "  (makePDF yok)\n";

echo "=== print/popup/iframe/App-PDF ipuclari ===\n";
foreach(array('window.open','w.print','.print()','iframe','CH_APPPDF','chAppPdf','AndroidPDF','Blob(','application/pdf','printWindow','popup') as $pat){
  $off=0;$n=0;$hit=false;
  while(($p=strpos($src,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$p."]: ...".str_replace(array("\r","\n"),array('',' '),substr($src,($p-60>0?$p-60:0),170))."...\n";
    $off=$p+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}
echo "\n== BITTI ==\n";
