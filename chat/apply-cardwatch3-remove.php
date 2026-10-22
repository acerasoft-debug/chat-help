<?php
/** ChatHelp — apply-cardwatch3-remove — CH_CARDWATCH3 mor olcum kutusunu kaldirir.
 * KULLANIM: pull2.php?key=...&files=apply-cardwatch3-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch3-remove BASLADI OK\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$before=strlen($src);
$src=preg_replace('#<style id="ch-cardwatch3-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-cardwatch3-js">.*?</script>\s*#s','',$src,1);
$tmp=tempnam(sys_get_temp_dir(),'cw3r').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch3-rm-'.date('Ymd-His'),@file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ mor olcum kutusu kaldirildi (".($before-strlen($src))." bayt cikti)\n";
echo "\n✓ Sayfada artik hicbir teshis araci yok — yalniz kalici duzeltmeler.\n";
