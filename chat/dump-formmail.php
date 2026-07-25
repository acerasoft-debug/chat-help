<?php
/**
 * ChatHelp - dump-formmail (READ-ONLY) - 3 konu: secenek aciklamalari + e-posta(PDF) + PDF butonu.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-formmail.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-formmail (".number_format(strlen($src))." B) ==\n\n";

function ctx($src,$pat,$before,$len,$max){
  $off=0;$n=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $n<$max){
    echo "  [".$pat." @".$q."]: ...".str_replace(array("\r","\n"),array('',' '),substr($src,($q-$before>0?$q-$before:0),$len))."...\n";
    $off=$q+strlen($pat);$n++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "=== [1] RECHTSGRUNDLAGE secenekleri (VwVfG/SGB/AO) ===\n";
ctx($src,'VwVfG',120,260,3);
ctx($src,'SGB X',120,220,3);
ctx($src,'147 AO',120,220,2);
ctx($src,'Rechtsgrundlage',80,200,3);
ctx($src,'rechtsgrundlage',80,160,2);

echo "\n=== [2] E-posta gonderimi (metin + PDF eki?) ===\n";
foreach(array('E-Mail senden','per E-Mail','send_mail','sendMail','action=send_email','emailDoc','mailDoc','action=email','send-doc','send_doc') as $pat){
  ctx($src,$pat,90,220,2);
}
echo "  -- attachment/pdf_base64/dosya eki ipuclari --\n";
foreach(array('attachment','pdf_base64','pdfBase64','attach','anhang','Anhang') as $pat){
  echo "  ".$pat.": ".substr_count($src,$pat)." kez\n";
}

echo "\n=== [3] PDF uretimi/butonu ===\n";
foreach(array('makePDF','genPdf','generatePDF','jsPDF','downloadPDF','printDoc','PDF herunterladen','id=\'pdf','pdfBtn') as $pat){
  ctx($src,$pat,70,200,2);
}

echo "\n=== [4] gonderim/format secim modali (showResult sonrasi butonlar) ===\n";
foreach(array('Als PDF','Per E-Mail','E-Mail','.pdf','buildPdf','sendMailWith') as $pat){
  echo "  ".$pat.": ".substr_count($src,$pat)." kez\n";
}
echo "\n== BITTI ==\n";
