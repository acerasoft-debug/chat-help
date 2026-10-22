<?php
/**
 * ChatHelp — dump-letterpdf — postversand.php ch_letter_pdf (CH_LXV2) TAMAMI (OKUR):
 *  font (Turkce?), pencere-alici konumu, imza gorseli, logo var mi — fax/brief premium+
 *  unicode+nologo icin gereken yapiyi anlamak.
 * KULLANIM: pull2.php?key=...&files=dump-letterpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/postversand.php');
if($src===false){ echo "postversand.php YOK\n"; exit; }
echo "== dump-letterpdf ==\n\n";
$p=strpos($src,'function ch_letter_pdf');
if($p===false){ echo "ch_letter_pdf yok\n"; exit; }
/* fonksiyon sonunu bul: bir sonraki '\nfunction ' */
$end=strpos($src,"\nfunction ",$p+20);
if($end===false) $end=$p+7000;
$len=$end-$p;
echo "ch_letter_pdf @$p .. $end ($len B)\n\n";
echo substr($src,$p,$len)."\n";
echo "\n=== ChatHelp/logo/marka gecisleri (ch_letter_pdf icinde) ===\n";
$body=substr($src,$p,$len);
foreach(['ChatHelp','chat-help','Erstellt','logo','Chat','Help'] as $w){
  $n=substr_count($body,$w); if($n) echo "  '$w': $n\n";
}
echo "\n== BITTI ==\n";
