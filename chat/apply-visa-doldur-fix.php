<?php
/**
 * ChatHelp — apply-visa-doldur-fix (CH_VISA_DOLDUR_FIX)
 *  BUG: Vize Dosyasi'nda "Doldur" tiklaninca form acilmiyor gibi gorunuyor.
 *  SEBEP: form paneli #pnl-vsf z-index:540; Dosya paneli #pnl-vdos z-index:545
 *  (DAHA YUKSEK). "Doldur" -> openForm form panelini .on yapiyor ama dosya
 *  panelinin ARKASINDA aciliyor -> kullanici hicbir sey gormuyor.
 *  COZUM: #pnl-vsf z-index'ini dosya panelinin uzerine cikar (560).
 *  Kapatinca (vsf-back) form kapanir, altta dosya paneli ayni ulkede kalir.
 * KULLANIM: pull-updates.php?files=apply-visa-doldur-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-visa-doldur-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VISA_DOLDUR_FIX')!==false) exit("Zaten ekli (CH_VISA_DOLDUR_FIX).\n");

$bundle = <<<'HTML'
<style id="ch-visa-doldur-css">
/* CH_VISA_DOLDUR_FIX — form paneli, Vize Dosyasi panelinin (545) UZERINDE acilsin.
   Boylece "Doldur"/"Düzenle" tiklaninca form gerçekten görünür. */
#pnl-vsf{z-index:560 !important}
</style>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index.php degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ #pnl-vsf z-index 560 yapildi (dosya panelinin uzeri)\n";

$tmp = tempnam(sys_get_temp_dir(),'vd').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-visadoldur-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VISA_DOLDUR_FIX uygulandi. Artik 'Doldur' formu görünür şekilde açar.\n";
