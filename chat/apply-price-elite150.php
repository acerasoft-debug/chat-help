<?php
/**
 * ChatHelp — apply-price-elite150 (CH_PRICE_E150) — Elite fiyatini Pro ile
 *  esitler: alici gonderim ucreti Elite icin 1,20 € -> 1,50 €. Boylece
 *  Pro ve Elite ayni: 1,50 €. Basic 1,99, paketsiz 2,99 aynen kalir.
 *  Canli index.php'de (IMZA3 ve/veya IMZA4 priceStr) elite dalini gunceller.
 *  Count-guard'li str_replace; '1,20 €' kalmadiysa zaten guncel der.
 * KULLANIM: pull2.php?key=...&files=apply-price-elite150.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-price-elite150 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");

$old = "if(/elite/.test(p)) return '1,20 €';";
$new = "if(/elite/.test(p)) return '1,50 €';";
$c = substr_count($src,$old);
if ($c===0){
  if (strpos($src,"if(/elite/.test(p)) return '1,50 €';")!==false) exit("Zaten guncel (Elite = 1,50 €).\n");
  exit("HATA: Elite fiyat dali bulunamadi (IMZA3/IMZA4 yuklu mu?). index DEGISTIRILMEDI.\n");
}
$src = str_replace($old,$new,$src);
echo "  ✓ Elite fiyat dali guncellendi ($c yer): 1,20 € -> 1,50 €\n";

$tmp = tempnam(sys_get_temp_dir(),'pe').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pricee150-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (substr_count($chk,$old)!==0) { echo "\n✗ DOGRULAMA BASARISIZ (eski fiyat hala var).\n"; exit; }
echo "\n  ✓ DOGRULAMA: '1,20 €' kalmadi (".strlen($chk)." bayt)\n";
echo "\n✓ Alici gonderim ucreti: Pro ve Elite = 1,50 € (esit).\n";
echo "   Basic 1,99 € / paketsiz 2,99 € degismedi.\n";
