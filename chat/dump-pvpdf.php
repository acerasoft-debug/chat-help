<?php
/**
 * ChatHelp — dump-pvpdf — postversand.php icindeki PDF uretici (ch_text_pdf) tanimi
 *  + cagri yerleri (fax + brief). Premium'a cevirmek icin exact anchorlar (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-pvpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/postversand.php');
if($src===false){ echo "postversand.php YOK\n"; exit; }
echo "== dump-pvpdf (".strlen($src)." B) ==\n\n";

echo "=== ch_text_pdf TANIMI ===\n";
$p=strpos($src,'function ch_text_pdf');
if($p!==false){
  $end=strpos($src,"\nfunction ",$p+10); if($end===false)$end=$p+2600;
  echo substr($src,$p,min($end-$p,2600))."\n\n";
} else echo "  (ch_text_pdf tanimi yok)\n\n";

echo "=== ch_text_pdf / ch_premium_pdf / ch_letter_pdf CAGRI yerleri ===\n";
foreach(['ch_text_pdf(','ch_premium_pdf(','ch_letter_pdf('] as $pat){
  $off=0;$n=0;
  echo "--- '$pat' ---\n";
  while(($q=strpos($src,$pat,$off))!==false && $n<6){
    echo "  @$q: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$q-90),200))."...\n";
    $off=$q+strlen($pat); $n++;
  }
  if(!$n) echo "  (yok)\n";
  echo "\n";
}

echo "=== ch_letter_pdf var mi (LetterXpress DIN) ===\n";
$p=strpos($src,'function ch_letter_pdf');
echo $p!==false? "  ✓ ch_letter_pdf tanimli @$p\n" : "  ✗ yok\n";

echo "\n== BITTI ==\n";
