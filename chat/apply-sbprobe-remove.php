<?php
/** ChatHelp — apply-sbprobe-remove — gecici teshis panelini (CH_SBPROBE) kaldirir.
 *  KULLANIM: pull-updates.php?files=apply-sbprobe-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbprobe-remove BASLADI OK\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$before=strlen($src);
/* CH_SBPROBE style + script bloklarini sil */
$src=preg_replace('#<style id="ch-sbprobe-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-sbprobe-js">.*?</script>\s*#s','',$src,1);
if(strpos($src,'CH_SBPROBE')!==false){ echo "  ⚠ CH_SBPROBE izleri kaldi — elle kontrol gerekebilir.\n"; }
$tmp=tempnam(sys_get_temp_dir(),'sr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-sbprobe-rm-'.date('Ymd-His'),@file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ CH_SBPROBE kaldirildi (".($before-strlen($src))." bayt cikti)\n\n✓ Teshis paneli temizlendi.\n";
