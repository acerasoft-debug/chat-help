<?php
/**
 * ChatHelp — apply-docdev-center (CH_DOCDEV_CENTER) — "🛠 Belge olustur &
 *  iyilestir" segmentini (#ch-dev-cta) diger ana-sayfa segmentleri gibi ORTALA
 *  ve orantila. Mevcut: margin:10px 0 (sag/sol 0), max-width yok -> tam genislik.
 *  Referans: .sec-hdr = max-width:780px; margin:0 auto. Cozum: yuksek-oncelikli
 *  CSS override (max-width + margin sag/sol auto). Ust/alt bosluk korunur.
 *  Additive CSS, JS yok, risk dusuk. node gerekmez.
 * KULLANIM: pull2.php?key=...&files=apply-docdev-center.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-docdev-center BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DOCDEV_CENTER')!==false) exit("Zaten ekli (CH_DOCDEV_CENTER).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-docdev-center-css">
/* CH_DOCDEV_CENTER — 'Belge olustur & iyilestir' segmentini diger segmentler gibi ortala */
html #ch-dev-cta{max-width:780px !important;margin-left:auto !important;margin-right:auto !important;width:100% !important}
</style>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'dc').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-docdevcenter-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DOCDEV_CENTER')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DOCDEV_CENTER diskte (".strlen($chk)." bayt)\n";
echo "\n✓ '#ch-dev-cta' artik max-width:780px + margin:auto ile ortali/orantili (diger segmentler gibi).\n";
