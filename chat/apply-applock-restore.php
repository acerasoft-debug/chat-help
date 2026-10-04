<?php
/**
 * ChatHelp — apply-applock-restore — kilitlenmis CALISAN hale GERI DONER.
 *  index.php'yi index.php.GOOD-appprint anlik goruntusunden geri yukler,
 *  pv.php / pvsave.php'yi .GOOD yedeklerinden tazeler.
 *  Geri yuklemeden once mevcut hali .bak-beforerestore-<ts> olarak saklar.
 *
 * DIKKAT: Bu, kilitten SONRA yapilan TUM index.php degisikliklerini geri alir.
 * Sadece App-yazdirma bozuldugunda ve baska kaybedecek yaman yokken kullan.
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-applock-restore.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-applock-restore BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$snap=$file.'.GOOD-appprint';
if(!is_file($snap)) exit("✗ Kilit anlik goruntusu YOK (".basename($snap).")\n  Once apply-applock.php calistirilmis olmali.\n");

$cur=(string)@file_get_contents($file);
$good=(string)@file_get_contents($snap);
if($good==='') exit("✗ Anlik goruntu bos/okunamadi.\n");

echo "su anki index.php : ".strlen($cur)." B\n";
echo "kilitli surum     : ".strlen($good)." B  (".date('Y-m-d H:i',filemtime($snap)).")\n";
if(sha1($cur)===sha1($good)) exit("\n· Zaten birebir ayni — GERI YUKLEME GEREKMIYOR.\n");

/* lint */
$tmp=tempnam(sys_get_temp_dir(),'rs').'.php'; file_put_contents($tmp,$good);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ Anlik goruntu LINT'ten gecmedi — geri yukleme IPTAL:\n  ".implode("\n  ",$lo)."\n");

$ts=date('Ymd-His');
@file_put_contents($file.'.bak-beforerestore-'.$ts,$cur);
$w=@file_put_contents($file,$good);
if($w===false||$w<strlen($good)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n✓ index.php geri yuklendi (onceki hal: index.php.bak-beforerestore-$ts)\n";

foreach(['pv.php','pvsave.php'] as $f){
  if(is_file($dir.'/'.$f.'.GOOD')){ @copy($dir.'/'.$f.'.GOOD',$dir.'/'.$f); echo "✓ tazelendi: $f\n"; }
}
if(!is_dir($dir.'/pv')) @mkdir($dir.'/pv',0755,true);

$chk=(string)@file_get_contents($file);
echo "\nDogrulama: CH_ISWV_GLOBAL=".substr_count($chk,'CH_ISWV_GLOBAL')
    ."  CH_APPVIEW3=".substr_count($chk,'CH_APPVIEW3')
    ."  CH_APPVIEW2C=".substr_count($chk,'CH_APPVIEW2C')."\n";
echo "\n✓ BITTI — App'i tam kapat-ac ve tekrar dene.\n";
