<?php
/**
 * ChatHelp — apply-senden5 (CH_SENDEN5) — cift Senden: yesilin altindaki ESKI ALTIN
 *  Senden (enjekte edilen .ch-senden-btn) gizlenir. Yesil Senden (#ch-sd2 / CH_SENDEN3)
 *  kalir. CSS ile kesin gizleme — her onbellek durumunda calisir, yesili etkilemez.
 * KULLANIM: pull2.php?key=...&files=apply-senden5.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden5 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDEN5')!==false) exit("Zaten ekli (CH_SENDEN5).\n");

$css = <<<'HTMLBLOCK'
<style id="ch-senden5-css">
/* CH_SENDEN5 — eski altin Senden (.ch-senden-btn enjektoru) tamamen gizli; yesil #ch-sd2 kalir */
.ch-senden-btn{display:none!important}
.ch-send-row{display:contents!important}
</style>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$css."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'s5').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-senden5-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDEN5')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SENDEN5 diskte (".strlen($chk)." B)\n";
echo "  Sonuc: altin Senden gizli, TEK yesil Senden kalir.\n";
echo "  Test (app2.php): dilekce -> kartta yalniz Kopieren | E-Mail | YESIL Senden | PDF.\n";
