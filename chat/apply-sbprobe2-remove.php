<?php
/** ChatHelp — apply-sbprobe2-remove — surekli izleme panelini (CH_SBPROBE2) kaldirir.
 *  KULLANIM: pull-updates.php?files=apply-sbprobe2-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbprobe2-remove BASLADI OK\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$before=strlen($src);
$src=preg_replace('#<style id="ch-sbprobe2-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-sbprobe2-js">.*?</script>\s*#s','',$src,1);
if(strpos($src,'CH_SBPROBE2')!==false){ echo "  ⚠ CH_SBPROBE2 izleri kaldi — elle kontrol gerekebilir.\n"; }
$tmp=tempnam(sys_get_temp_dir(),'sr2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-sbprobe2-rm-'.date('Ymd-His'),@file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ CH_SBPROBE2 kaldirildi (".($before-strlen($src))." bayt cikti)\n\n✓ Izleme paneli temizlendi.\n";
