<?php
/**
 * ChatHelp - dump-pdfcap (READ-ONLY, sir maskeli) - sunucu PDF yetenegi + send-doc gonderim + makePDF print.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-pdfcap.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){
  $s=preg_replace('/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]+/','$1_$2_***',$s);
  $s=preg_replace('/\bwhsec_[A-Za-z0-9]+/','whsec_***',$s);
  $s=preg_replace('/([\'"]?(?:pass|password|secret|api_?key|smtp_pass|token)[\'"]?\s*(?:=>|=|:)\s*)([\'"]).*?\2/i','$1$2***$2',$s);
  return $s;
}
echo "== dump-pdfcap ==\n\n";

echo "=== [1] send-doc.php TAM (nasil gonderiyor: mail()/PHPMailer?) ===\n";
$s=(string)@file_get_contents(__DIR__.'/send-doc.php');
echo "  (".strlen($s)." B)\n  ".mask(str_replace("\n","\n  ",$s))."\n\n";

echo "=== [2] sunucuda PDF kutuphanesi / uc var mi ===\n";
$cands=array('fpdf.php','FPDF.php','tfpdf.php','tcpdf.php','dompdf','mpdf','vendor/autoload.php',
  'letterpdf.php','pdf.php','makepdf.php','gen-pdf.php','fn_letter_uni.php','lib/fpdf.php','fonts/DejaVuSans.ttf');
foreach($cands as $c){
  $p=__DIR__.'/'.$c;
  echo "  ".$c.": ".(file_exists($p)?("VAR (".(is_dir($p)?'dir':filesize($p).'B').")"):"yok")."\n";
}
echo "  -- chat/ altinda pdf/letter iceren php dosyalari --\n";
foreach(glob(__DIR__.'/*.php') as $f){
  $bn=basename($f);
  if(preg_match('/pdf|letter|mail/i',$bn)) echo "    ".$bn." (".filesize($f)."B)\n";
}
echo "  -- PHP eklentileri --\n";
echo "    gd: ".(extension_loaded('gd')?'VAR':'yok')." · mbstring: ".(extension_loaded('mbstring')?'VAR':'yok')." · imagick: ".(extension_loaded('imagick')?'VAR':'yok')."\n";
echo "    class FPDF: ".(class_exists('FPDF')?'VAR':'yok')." · class Dompdf\\Dompdf: ".(class_exists('Dompdf\\Dompdf')?'VAR':'yok')." · class PHPMailer: ".(class_exists('PHPMailer\\PHPMailer\\PHPMailer')||class_exists('PHPMailer')?'VAR':'yok')."\n";
echo "    wkhtmltopdf (exec): ".(trim((string)@shell_exec('command -v wkhtmltopdf 2>/dev/null'))?:'yok')."\n";

echo "\n=== [3] makePDF print-tetikleyici (index.php 999200 +1400) ===\n";
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$q=strpos($idx,'function makePDF');
if($q!==false){ echo "  makePDF @".$q." — 1200..2600 arasi:\n  ".str_replace("\n","\n  ",substr($idx,$q+1200,1500))."\n"; }
echo "\n== BITTI ==\n";
