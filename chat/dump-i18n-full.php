<?php
/** ChatHelp — dump-i18n-full (SADECE OKUR)
 *  ch-i18n-full script bloğunun TAMAMINI gosterir (deText, mm/ceviri-haritasi
 *  yukleme, span-ekleme mantigi) -> kesin duzeltme icin gerekli.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-i18n-full.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

$p = strpos($idx, '<script id="ch-i18n-full">');
if ($p===false) { echo "ch-i18n-full script bulunamadi\n"; exit; }
$end = strpos($idx, '</script>', $p);
if ($end===false) { echo "kapanis </script> bulunamadi\n"; exit; }
$blk = substr($idx, $p, $end-$p+9);
echo "════════ ch-i18n-full TAM BLOK (".strlen($blk)." bayt) ════════\n\n";
echo $blk."\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-i18n-full.php ════════\n";
