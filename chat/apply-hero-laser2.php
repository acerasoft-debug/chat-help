<?php
/**
 * ChatHelp — apply-hero-laser2 (CH_HERO_LASER2) — "Ihr Recht" basligindaki lazeri
 *  yumusat: cok ince, cok hafif, ~5.5 sn'de bir kisa/nazik gecis ("yok gibi").
 *  CH_HERO_CLEAN CSS'ini !important ile ezer. Saf CSS.
 * KULLANIM: pull2.php?key=...&files=apply-hero-laser2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hero-laser2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_HERO_LASER2')!==false) exit("Zaten ekli (CH_HERO_LASER2).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-hero-laser2-css">
/* CH_HERO_LASER2 — lazer ince/hafif, ~5.5s'de bir nazik gecis */
.wlc-h1::after{
  width:16% !important;
  background:linear-gradient(90deg,transparent,rgba(212,168,74,.14),transparent) !important;
  animation:chHeroLaser2 5.5s ease-in-out infinite !important;
  filter:blur(.5px);
}
@keyframes chHeroLaser2{0%{left:-20%}13%{left:120%}100%{left:120%}}
@media(prefers-reduced-motion:reduce){.wlc-h1::after{animation:none !important;display:none !important}}
</style>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'l2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-laser2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_HERO_LASER2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_HERO_LASER2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Lazer inceltildi/hafifletildi, ~5.5s'de bir nazik gecis.\n";
