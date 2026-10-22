<?php
/* apply-gendbg-off — CH_GENDBG teshis blogunu kaldirir.
   KULLANIM: pull2.php?key=...&files=apply-gendbg-off.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("okunamadi\n");
$new=preg_replace('#<script id="ch-gendbg">.*?</script>\s*#s','',$src,1);
if($new===$src){ echo "CH_GENDBG bulunamadi (zaten yok).\n"; exit; }
$tmp=tempnam(sys_get_temp_dir(),'go').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI, degistirilmedi\n"; exit; }
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
echo "✓ CH_GENDBG kaldirildi (".strlen($new)." B)\n";
