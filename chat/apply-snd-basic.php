<?php
/**
 * ChatHelp — apply-snd-basic — Meine Sendungen erisimini Basic'e de acar.
 *  Canli index.php'de CH_SENDUNGEN isPrem() -> /pro|elite/ yerine /basic|pro|elite/.
 *  Count-guard'li str_replace. opcache reset.
 * KULLANIM: pull2.php?key=...&files=apply-snd-basic.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-snd-basic BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SENDUNGEN')===false) exit("HATA: once CH_SENDUNGEN gerekli (apply-sendungen.php).\n");

$old = "function isPrem(){ return /pro|elite/.test(plan()); }";
$new = "function isPrem(){ return /basic|pro|elite/.test(plan()); }";
$c = substr_count($src,$old);
if ($c===0){
  if (strpos($src,$new)!==false) exit("Zaten guncel (Basic dahil).\n");
  exit("HATA: isPrem() satiri bulunamadi — index DEGISTIRILMEDI.\n");
}
$src = str_replace($old,$new,$src);
echo "  ✓ isPrem() Basic'i de kapsayacak sekilde guncellendi ($c yer)\n";

$tmp = tempnam(sys_get_temp_dir(),'sb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sndbasic-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false) { echo "\n✗ YAZMA HATASI.\n"; exit; }
if (function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if (strpos($chk,$new)===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: Meine Sendungen artik Basic + Pro + Elite'e acik (free upsell gorur).\n";
