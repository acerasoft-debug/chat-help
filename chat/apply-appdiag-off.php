<?php
/**
 * ChatHelp — apply-appdiag-off — teshis popup'larini KALDIRIR (CH_APPDIAG + CH_APPDIAG2).
 *  chPrintDoc normal haline doner. Teshis bitince cagir.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appdiag-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appdiag-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPDIAG')===false) exit("Zaten yok (CH_APPDIAG bulunamadi) — DEGISIKLIK YOK.\n");

/* Her iki payload da ` /*CH_APPDIAG(2)*/ try{...}catch(_e2){}}` ile biter. Non-greedy sil. */
$new=preg_replace('/\s*\/\*CH_APPDIAG2?\*\/ try\{.*?\}catch\(_e2\)\{\}\}/s','',$src);
if($new===null) exit("preg hatasi.\n");

$removed=substr_count($src,'CH_APPDIAG')-substr_count($new,'CH_APPDIAG');

$tmp=tempnam(sys_get_temp_dir(),'ado').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appdiagoff-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ teshis kaldirildi ($removed marker) (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kalan CH_APPDIAG referansi: ".substr_count($chk,'CH_APPDIAG')."\n";
echo "\n✓ chPrintDoc normal haline dondu.\n";
