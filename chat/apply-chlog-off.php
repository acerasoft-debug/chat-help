<?php
/**
 * ChatHelp — apply-chlog-off — teshis logger'i temizler.
 *  index.php'den CH_APPLOG beacon'i siler + chlog.php ve chlog.txt dosyalarini siler.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-chlog-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-chlog-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src!==false && strpos($src,'CH_APPLOG')!==false){
  $new=preg_replace('/\s*\/\*CH_APPLOG\*\/ try\{.*?\}catch\(_e9\)\{\}/s','',$src);
  if($new!==null && $new!==$src){
    $tmp=tempnam(sys_get_temp_dir(),'clo').'.php'; file_put_contents($tmp,$new);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc===0){ @file_put_contents($file.'.bak-chlogoff-'.date('Ymd-His'),$src); @file_put_contents($file,$new); if(function_exists('opcache_reset'))@opcache_reset(); echo "  ✓ index.php beacon silindi\n"; }
    else { echo "  ✗ LINT HATASI, index degismedi\n"; }
  }
} else { echo "  (index'te CH_APPLOG yok)\n"; }

foreach(['chlog.php','chlog.txt','chlog9k1.php','chlog9k1.txt'] as $x){ echo (@unlink($dir.'/'.$x) ? "  ✓ silindi: $x\n" : "  (yok: $x)\n"); }
echo "\n✓ Temizlik bitti.\n";
