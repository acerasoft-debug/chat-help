<?php
/** ChatHelp — apply-chat-debuglog-remove — CH_CHAT_DBG log satirlarini api.php'den
 *  kaldirir ve log dosyasini siler.
 *  KULLANIM: pull-updates.php?files=apply-chat-debuglog-remove.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chat-debuglog-remove BASLADI OK\n\n";
$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
$before = substr_count($src,'CH_CHAT_DBG');
$src = preg_replace('/^.*CH_CHAT_DBG.*\n?/m', '', $src);
echo "  log satirlari: $before -> ".substr_count($src,'CH_CHAT_DBG')."\n";
$tmp=tempnam(sys_get_temp_dir(),'cdr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-chatdbgrm-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
@unlink(__DIR__.'/data/chat-debug.log');
echo "\n✓ CH_CHAT_DBG kaldirildi, log dosyasi silindi.\n";
