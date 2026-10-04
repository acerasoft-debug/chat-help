<?php
/* dump-docgen2 — 'Dokument generieren' butonu tam onclick handler'i (salt-okunur).
   KULLANIM: pull2.php?key=...&files=dump-docgen2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("okunamadi\n");
$p=strpos($src,"'\\u{1F4C4} Dokument generieren");
if($p===false) $p=strpos($src,"Dokument generieren \\u2192");
if($p===false){ $p=strpos($src,"btn.className='btn-g'"); }
if($p===false) exit("buton bulunamadi\n");
echo "buton @".$p."\n\n== TAM HANDLER (2800 char) ==\n";
echo substr($src,max(0,$p-40),2800)."\n";
echo "\n\n== genDoc / makePDF / chPrintDoc cagrisi bu blokta? ==\n";
$blk=substr($src,$p,2800);
foreach(['genDoc','makePDF','chPrintDoc','fetch(','api.php','fall-api','intake-api','required','box.querySelector','preview','innerHTML'] as $t){
  echo "  ".str_pad($t,20)." : ".substr_count($blk,$t)."\n";
}
