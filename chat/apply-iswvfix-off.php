<?php
/**
 * ChatHelp — apply-iswvfix-off — apply-iswvfix'i geri alir.
 *  window.isWV global tanimini + App dali beacon'ini siler.
 *  (Silinen teshis alert'leri geri getirilmez — zaten istenmiyordu.)
 * KULLANIM: /chat/pull2.php?key=...&files=apply-iswvfix-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-iswvfix-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$new=$src;

$new=preg_replace('/<script>\/\*CH_ISWV_GLOBAL\*\/.*?<\/script>/s','',$new);
$new=preg_replace('/\s*\/\*CH_BRANCHLOG\*\/try\{.*?\}catch\(_eb\)\{\}/s','',$new);
if($new===null) exit("preg hatasi\n");
if($new===$src) exit("Zaten yok — DEGISIKLIK YOK.\n");

$tmp=tempnam(sys_get_temp_dir(),'iwo').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-iswvfixoff-'.date('Ymd-His'),$src);
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "✓ geri alindi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  CH_ISWV_GLOBAL=".substr_count($chk,'CH_ISWV_GLOBAL')."  CH_BRANCHLOG=".substr_count($chk,'CH_BRANCHLOG')."\n";
