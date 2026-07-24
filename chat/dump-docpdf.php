<?php
/**
 * ChatHelp — dump-docpdf — CH_AICHAT_DOCPDF blogunun TAMAMI (chat [[DOC]] karti):
 *  PDF butonu onclick -> hangi fonksiyon? WebView'de dl.php'ye gidiyor mu? (OKUR)
 * KULLANIM: pull2.php?key=...&files=dump-docpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-docpdf ==\n\n";
$p=strpos($src,'CH_AICHAT_DOCPDF');
if($p===false){ echo "CH_AICHAT_DOCPDF yok\n"; exit; }
$start=strrpos(substr($src,0,$p),'<script');
$end=strpos($src,'</script>',$p); if($end!==false) $end+=9;
echo "blok @$start .. $end (".($end-$start)." B)\n\n";
echo substr($src,$start,($end-$start))."\n";
echo "\n== BITTI ==\n";
