<?php
/**
 * ChatHelp — dump-chatpdf2 — chat 'IHR DOKUMENT' kartindaki 'Als PDF speichern'
 *  butonunun GERCEK handler'ini bulur (OKUR): hangi fonksiyon, WebView'de hangi yol.
 * KULLANIM: pull2.php?key=...&files=dump-chatpdf2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-chatpdf2 (".strlen($src)." B) ==\n\n";

/* [1] IHR DOKUMENT karti + PDF butonu baglami */
foreach(['IHR DOKUMENT','Als PDF speichern'] as $pat){
  $off=0;$n=0;
  echo "=== '$pat' (ilk 6) ===\n";
  while(($p=strpos($src,$pat,$off))!==false && $n<6){
    echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-260),620))."...\n\n";
    $off=$p+strlen($pat); $n++;
  }
  if(!$n) echo "  (yok)\n\n";
}

/* [2] chPrintDoc tanimlari (2 kopya) — WebView dallanmasi */
echo "=== chPrintDoc tanimlari ===\n";
$off=0;$n=0;
while(($p=strpos($src,'window.chPrintDoc',$off))!==false && $n<4){
  echo "  @$p:\n  ".str_replace(["\r"],'',substr($src,$p,1500))."\n\n";
  $off=$p+10; $n++;
}

/* [3] dl.php kullanimi */
echo "=== 'dl.php' referanslari ===\n";
$off=0;$n=0;
while(($p=strpos($src,'dl.php',$off))!==false && $n<8){
  echo "  @$p: ...".str_replace(["\n","\r"],' ',substr($src,max(0,$p-160),340))."...\n\n";
  $off=$p+6; $n++;
}
if(!$n) echo "  (yok)\n";

/* [4] isWV tanimi */
echo "=== isWV / WebView tespiti ===\n";
$p=strpos($src,'function isWV');
if($p!==false) echo "  @$p: ".str_replace(["\r"],'',substr($src,$p,400))."\n";
else echo "  (function isWV yok — baska ad?)\n";

echo "\n== BITTI ==\n";
