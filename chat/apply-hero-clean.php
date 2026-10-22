<?php
/**
 * ChatHelp — apply-hero-clean (CH_HERO_CLEAN) — ana sayfa büyük foto bölümü kaldir
 *  + lazer basliga.
 *   (1) .wlc-scan (buyuk foto/belge-tarama CTA karti) tamamen gizlenir — foto okuma
 *       gorevi artik chat'te (📎) var.
 *   (2) Soldan-saga giden lazer sweep'i "Ihr Recht. Ihr Schutz." basligina (.wlc-h1)
 *       tasinir.
 *  Saf EKLEMELI CSS (</body> oncesi), JS yok. Reduced-motion destegi.
 * KULLANIM: pull2.php?key=...&files=apply-hero-clean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hero-clean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_HERO_CLEAN')!==false) exit("Zaten ekli (CH_HERO_CLEAN).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-hero-clean-css">
/* CH_HERO_CLEAN — buyuk foto/scan CTA kaldir + lazer -> Ihr Recht basligi */
.wlc-scan{display:none !important}
.wlc-h1{position:relative;overflow:hidden}
.wlc-h1::after{content:"";position:absolute;top:0;left:-60%;width:45%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(212,168,74,.48),transparent);
  animation:chHeroLaser 3.6s ease-in-out infinite;pointer-events:none;z-index:3}
@keyframes chHeroLaser{0%{left:-60%}42%{left:135%}100%{left:135%}}
@media(prefers-reduced-motion:reduce){.wlc-h1::after{animation:none;display:none}}
</style>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'hc').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-heroclean-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_HERO_CLEAN')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_HERO_CLEAN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Buyuk foto bolumu (.wlc-scan) kaldirildi; lazer 'Ihr Recht. Ihr Schutz.' basligina (.wlc-h1) tasindi.\n";
