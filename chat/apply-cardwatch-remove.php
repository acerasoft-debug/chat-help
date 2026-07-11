<?php
/** ChatHelp — apply-cardwatch-remove — CH_CARDWATCH izleyicisini kaldirir.
 * KULLANIM: pull2.php?key=...&files=apply-cardwatch-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch-remove BASLADI OK\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$before=strlen($src);
$src=preg_replace('#<style id="ch-cardwatch-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-cardwatch-js">.*?</script>\s*#s','',$src,1);
if(strpos($src,'CH_CARDWATCH')!==false){ echo "  ⚠ CH_CARDWATCH izi kaldi — elle kontrol.\n"; }
$tmp=tempnam(sys_get_temp_dir(),'cwr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch-rm-'.date('Ymd-His'),@file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ CH_CARDWATCH kaldirildi (".($before-strlen($src))." bayt cikti)\n";
