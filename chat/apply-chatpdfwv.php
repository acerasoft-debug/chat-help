<?php
/**
 * ChatHelp — apply-chatpdfwv (CH_CHATPDF_WVSKIP) — chat [[DOC]] kartinin PDF butonu
 *  App'te calismiyordu: printDoc() ONCE makePDF() (jsPDF) deniyor; WebView'de jsPDF
 *  sessizce basarisiz olup return ediyor -> alttaki dl.php (sunucu PDF) yoluna hic
 *  ulasilamiyor. DUZELTME: App/WebView'de makePDF ATLANIR -> dogrudan chPrintDoc
 *  (dl.php form POST) -> gercek PDF iner. Masaustu/tarayicida makePDF aynen kalir.
 * KULLANIM: pull2.php?key=...&files=apply-chatpdfwv.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatpdfwv BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CHATPDF_WVSKIP')!==false) exit("Zaten ekli (CH_CHATPDF_WVSKIP).\n");

$o='if(typeof makePDF==="function"){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; }';
$n='if(typeof makePDF==="function" && !(function(){try{return (typeof isWV==="function")&&isWV();}catch(_w){return false;}})()){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; }/*CH_CHATPDF_WVSKIP*/';

$c=substr_count($src,$o);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($o,$n,$src);
echo "  ✓ App/WebView'de makePDF atlanir -> dl.php gercek PDF yolu\n";

$tmp=tempnam(sys_get_temp_dir(),'cpw').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-chatpdfwv-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CHATPDF_WVSKIP')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_CHATPDF_WVSKIP diskte (".strlen($chk)." B)\n";
echo "  Test: App'te chat'ten belge uret -> 'Als PDF speichern' -> PDF gercekten iner.\n";
echo "  (Tarayici/masaustunde onceki gibi makePDF ile calisir.)\n";
