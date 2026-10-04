<?php
/**
 * ChatHelp — dump-dlpdf — dl.php'nin TAMAMI (asil PDF/HTML uretici) + hangi motor
 *  (dompdf/mpdf/wkhtmltopdf/tcpdf/salt-HTML) kullaniliyor (OKUR).
 *  Ayrica index.php'deki makePDF tanimi (varsa) kisa baglam.
 * KULLANIM: pull2.php?key=...&files=dump-dlpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$dl=@file_get_contents("$D/dl.php");
echo "== dump-dlpdf ==\n\n";
if($dl===false){ echo "dl.php YOK\n"; }
else{
  echo "=== dl.php (".strlen($dl)." B) TAMAMI ===\n";
  echo $dl."\n\n";
}

/* motor ipuclari */
echo "=== PDF motor ipuclari ===\n";
foreach(['dompdf','Dompdf','mpdf','Mpdf','wkhtmltopdf','tcpdf','TCPDF','FPDF','Content-Type: application/pdf','vendor/autoload','Prince'] as $m){
  $inDl=($dl!==false && stripos($dl,$m)!==false);
  echo "  ".($inDl?'✓':'·')." $m".($inDl?' (dl.php)':'')."\n";
}

/* index.php makePDF tanimi */
$src=@file_get_contents("$D/index.php");
if($src!==false){
  echo "\n=== index.php: makePDF tanimi ===\n";
  $p=strpos($src,'function makePDF');
  if($p===false){ $p=strpos($src,'makePDF='); }
  if($p===false){ $p=strpos($src,'window.makePDF'); }
  if($p!==false) echo "  @$p: ".str_replace(["\r"],'',substr($src,$p,900))."\n";
  else echo "  (makePDF tanimi bulunamadi — belki jsPDF harici)\n";

  echo "\n=== 'jspdf' / 'jsPDF' referanslari ===\n";
  foreach(['jspdf','jsPDF','html2canvas','html2pdf'] as $j){
    $c=substr_count($src,$j); echo "  ".str_pad($j,12).": $c\n";
  }
}
echo "\n== BITTI ==\n";
