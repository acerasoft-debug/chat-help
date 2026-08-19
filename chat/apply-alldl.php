<?php
/**
 * ChatHelp — apply-alldl (CH_ALLDL_CHAT) — chat [[DOC]] karti 'Als PDF speichern'
 *  HER platformda (masaustu/mobil/App) dogrudan dl.php'ye POST eder -> premium sunucu
 *  PDF. Onceden masaustunde makePDF (jsPDF) kullaniliyordu ve DUZ gorunuyordu.
 *  Sadece chat belgesi (basit mektup) etkilenir; CV/Vertrag zengin sablonlari DOKUNULMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-alldl.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-alldl BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_ALLDL_CHAT')!==false) exit("Zaten ekli (CH_ALLDL_CHAT).\n");

$o='if(typeof makePDF==="function" && !(function(){try{return (typeof isWV==="function")&&isWV();}catch(_w){return false;}})()){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; }/*CH_CHATPDF_WVSKIP*/';
$n='if(true){ /*CH_ALLDL_CHAT — her platform dl.php premium*/ try{ var _dlf=document.createElement("form"); _dlf.method="POST"; _dlf.action="dl.php"; _dlf.target="_self"; _dlf.style.display="none"; var _di=document.createElement("input"); _di.name="html"; _di.value=\'<div class="a4">\'+esc(real)+\'</div>\'; _dlf.appendChild(_di); var _dn=document.createElement("input"); _dn.name="name"; _dn.value="ChatHelp-Dokument"; _dlf.appendChild(_dn); document.body.appendChild(_dlf); _dlf.submit(); setTimeout(function(){try{document.body.removeChild(_dlf);}catch(e){}},1500); return; }catch(_dle){} }/*CH_CHATPDF_WVSKIP*/';

$c=substr_count($src,$o);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($o,$n,$src);
echo "  ✓ chat PDF butonu -> her platformda dl.php premium\n";

$tmp=tempnam(sys_get_temp_dir(),'ad').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-alldl-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_ALLDL_CHAT')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_ALLDL_CHAT diskte (".strlen($chk)." B)\n";
echo "  Test: masaustu/mobil/App -> chat belge -> 'Als PDF speichern' -> premium PDF iner.\n";
