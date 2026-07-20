<?php
/**
 * ChatHelp — apply-dominspect-off — 🔍 DOM teshis dugmesini kaldirir (CH_DOMINSPECT).
 * KULLANIM: pull2.php?key=...&files=apply-dominspect-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$n=0;
$src=preg_replace('#<script id="ch-dominspect-js">.*?</script>#s','',$src,-1,$n);
if($n===0){ echo "= ch-dominspect-js bulunamadi (belki zaten kaldirilmis)\n"; }
/* kalmis dugmeyi runtime'da da sil */
if(strpos($src,'ch-dominspect-off-js')===false){
  $clean='<script id="ch-dominspect-off-js">try{setInterval(function(){var b=document.getElementById("ch-dominspect-b");if(b)b.remove();},600);}catch(e){}</script>';
  $pos=strripos($src,'</body>'); if($pos!==false) $src=substr($src,0,$pos).$clean."\n".substr($src,$pos);
}
$tmp=tempnam(sys_get_temp_dir(),'do').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI\n"; exit; }
@file_put_contents($file,$src);
if(function_exists('opcache_reset')) @opcache_reset();
echo "✓ 🔍 DOM teshis dugmesi kaldirildi ($n script blogu). Hard-refresh.\n";
