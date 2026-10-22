<?php
/**
 * ChatHelp — apply-freitext-cclang (CH_FREITEXT_CC)
 *  KANIT (kullanici ekran goruntusu): NL sablonunda tum alanlar Felemenkce
 *  ama serbest-metin alani "Weitere Angaben in eigenen Worten" ALMANCA.
 *  SEBEP: decorateFreitext() dili getLang()'den (ARAYUZ dili) aliyor; arayuz
 *  dili de/desteklenmeyen ise Almanca'ya dusuyor. Oysa alan, SABLONUN
 *  ULKESININ dilinde olmali (NL sablonu -> Felemenkce).
 *  COZUM: dil secimi once CC'den (hukuk ulkesi -> dil haritasi), bulunamazsa
 *  getLang()'e duser. _FL haritasinda 12 dil zaten mevcut.
 * KULLANIM: pull-updates.php?files=apply-freitext-cclang.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-freitext-cclang BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FREITEXT_CC')!==false) exit("Zaten ekli (CH_FREITEXT_CC).\n");

$o = "    var last=fis[fis.length-1]; if(!last||!last.parentNode) return;\n"
   . "    var lang=getLang();";
$n = "    var last=fis[fis.length-1]; if(!last||!last.parentNode) return;\n"
   . "    var _CCL={DE:'de',AT:'de',CH:'de',TR:'tr',FR:'fr',BE:'fr',ES:'es',IT:'it',NL:'nl',PL:'pl',SE:'sv',PT:'pt',GB:'en',US:'en'}; /*CH_FREITEXT_CC*/\n"
   . "    var lang=null; try{ var _cc=(typeof CC!=='undefined'&&CC)?CC:null; if(_cc&&_CCL[_cc]) lang=_CCL[_cc]; }catch(e){}\n"
   . "    if(!lang) lang=getLang();";
$c=0; $src=str_replace($o,$n,$src,$c);
if ($c!==1) { echo "HATA: decorateFreitext anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ serbest-metin alani artik SABLONUN ULKE DILINDE (NL->Felemenkce, ES->Ispanyolca...)\n";

$tmp = tempnam(sys_get_temp_dir(),'fc').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-freitextcc-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FREITEXT_CC uygulandi.\n";
