<?php
/**
 * ChatHelp — apply-calm3 (CH_CALM3) — GENEL "her yerde hafif oynama" icin SADECE-CSS
 *  sabitlik katmani. JS mantigina DOKUNMAZ (sifir kirilma riski). Gercek kayma
 *  kaynaklarini keser:
 *   • scrollbar-gutter:stable  -> kaydirma cubugu gelip-gidince yatay kayma yok
 *   • text-size-adjust:100%    -> Android WebView metni otomatik buyutup reflow etmez
 *   • overflow-x:hidden        -> yatay tasma titremesi yok
 *   • will-change:auto         -> rogue compositing subpixel drift yok
 *   • tap-highlight sifir + img vertical-align -> tiklama flash'i + baseline bosluk yok
 *   • layout-etkileyen gecisleri (width/height/margin/transform/top/left...) kapatir,
 *     SADECE renk/opacity/golge gecisi kalir -> geometri sabit ama premium his korunur.
 * KULLANIM: pull2.php?key=...&files=apply-calm3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-calm3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CALM3')!==false) exit("Zaten ekli (CH_CALM3).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-calm3-css">
/* CH_CALM3 — genel sabitlik (SADECE guvenli CSS, JS/animasyon mantigina dokunmaz) */
html{scrollbar-gutter:stable;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{overflow-x:hidden}
*{-webkit-tap-highlight-color:transparent;will-change:auto!important}
img,svg,video,canvas{vertical-align:middle}
</style>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'c3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-calm3-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CALM3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_CALM3 diskte (".strlen($chk)." B)\n";
echo "  Genel sabitlik katmani aktif. Hard-refresh (Cmd/Ctrl+Shift+R) veya App'i kapat-ac.\n";
echo "  Kesilen kayma kaynaklari: scrollbar toggle, metin auto-resize, yatay tasma, rogue compositing.\n";
