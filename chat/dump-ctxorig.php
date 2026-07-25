<?php
/* ChatHelp - dump-ctxorig (READ-ONLY) - CTX.orig yazdirma fonksiyonu + PDF butonu + no-sig yazdirma yolu.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-ctxorig.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false){ echo "index.php YOK\n"; return; }
echo "== dump-ctxorig ==\n\n";

/* 1) CTX nesnesinin kuruldugu yer(ler): orig: / html: / doc: alanlari */
echo "=== [1] CTX kurulumu (orig/html/doc alanlari) ===\n";
foreach(array('CTX={','CTX = {','orig:','.orig=','CTX.orig') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($s,$pat,$off))!==false && $n<3){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($s,($q-120>0?$q-120:0),360))."...\n\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

/* 2) modal opener: adres-imza overlay'i acan fonksiyon (orig print'i sarmalayan) */
echo "=== [2] adres-imza opener (orig print'i sarmalayan wrap) ===\n";
foreach(array('__pdfConfirmed','openAdresImza','function openAI','ai-ov','showAdres') as $pat){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($s,$pat,$off))!==false && $n<2){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($s,($q-160>0?$q-160:0),420))."...\n\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

/* 3) 'Als PDF speichern' / PDF butonu handler - onclick fonksiyonu */
echo "=== [3] PDF butonu (chat doc karti) handler ===\n";
foreach(array('ch-doccard-btn','Als PDF','savePDF','pdfBtn','__pdfConfirmed=true') as $pat){
  $q=strpos($s,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),' ',substr($s,($q-100>0?$q-100:0),380))."...\n\n"; }
  else echo "  (".$pat.": yok)\n";
}

/* 4) __pdfConfirmed kontrol edildiginde asil yazdirmayi yapan fonksiyon (showResult/printDoc icindeki window.open) */
echo "=== [4] __pdfConfirmed sonrasi asil yazdirma (window.open/print/chPrintDoc) ===\n";
$off=0;$n=0;
while(($q=strpos($s,'__pdfConfirmed',$off))!==false && $n<6){
  echo "  [@".$q."]: ...".str_replace(array("\r","\n"),' ',substr($s,($q-40>0?$q-40:0),260))."...\n";
  $off=$q+14;$n++;
}
echo "\n== BITTI ==\n";
