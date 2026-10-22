<?php
/** ChatHelp — apply-cardwatch2-remove — CH_CARDWATCH2 yesil izleyicisini kaldirir.
 * KULLANIM: pull2.php?key=...&files=apply-cardwatch2-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch2-remove BASLADI OK\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$before=strlen($src);
$src=preg_replace('#<style id="ch-cardwatch2-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-cardwatch2-js">.*?</script>\s*#s','',$src,1);
if(strpos($src,'CH_CARDWATCH2')!==false){ echo "  ⚠ CH_CARDWATCH2 izi kaldi — elle kontrol.\n"; }
$tmp=tempnam(sys_get_temp_dir(),'cw2r').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch2-rm-'.date('Ymd-His'),@file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ CH_CARDWATCH2 kaldirildi (".($before-strlen($src))." bayt cikti)\n";
