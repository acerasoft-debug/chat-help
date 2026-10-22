<?php
/**
 * ChatHelp — dump-printdoc2 — aktif window.chPrintDoc (CH_PRINTDOC_WVFIRST) TAM govdesi
 *  + printDoc makePDF satiri: her platformu dl.php'ye yonlendirmek icin exact anchorlar.
 * KULLANIM: pull2.php?key=...&files=dump-printdoc2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-printdoc2 ==\n\n";

echo "=== aktif chPrintDoc (CH_PRINTDOC_WVFIRST civari) ===\n";
$p=strpos($src,'CH_PRINTDOC_WVFIRST');
if($p!==false){
  $st=strrpos(substr($src,0,$p),'window.chPrintDoc=function');
  if($st===false) $st=max(0,$p-200);
  echo substr($src,$st,2400)."\n\n";
} else echo "  CH_PRINTDOC_WVFIRST yok\n\n";

echo "=== printDoc makePDF satiri (CH_CHATPDF_WVSKIP) ===\n";
$q=strpos($src,'CH_CHATPDF_WVSKIP');
if($q!==false){ echo substr($src,max(0,$q-400),470)."\n\n"; } else echo "  yok\n\n";

echo "== BITTI ==\n";
