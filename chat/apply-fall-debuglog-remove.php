<?php
/** ChatHelp — apply-fall-debuglog-remove — gecici log noktalarini (CH_FALL_DBG)
 *  fall-api.php'den kaldirir ve log dosyasini siler.
 *  KULLANIM: pull-updates.php?files=apply-fall-debuglog-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fall-debuglog-remove BASLADI OK\n\n";
$file = __DIR__.'/fall-api.php';
$src = @file_get_contents($file);
if ($src===false) exit("fall-api.php okunamadi\n");
$before = substr_count($src,'CH_FALL_DBG');
$src = preg_replace('/^.*CH_FALL_DBG.*\n?/m', '', $src);
$after = substr_count($src,'CH_FALL_DBG');
echo "  log satirlari: $before -> $after\n";
$tmp=tempnam(sys_get_temp_dir(),'fdr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-falldbgrm-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
@unlink(__DIR__.'/data/fall-debug.log');
echo "\n✓ CH_FALL_DBG kaldirildi, log dosyasi silindi.\n";
