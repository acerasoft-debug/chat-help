<?php
/**
 * ChatHelp — apply-fallprof4 (CH_FALLPROF3) — KESIN kok neden bulundu: canli
 *  fall-api.php telefon satirina ONCE f5'i (DOGUM TARIHI!) yaziyordu:
 *    . "Telefon: " . ($prof['f5'] ?? ($prof['f6'] ?? '')) . "\n"
 *  Simdi dogrulamali $chTel kullanilir (telefon-görünumlu deger yoksa satir YOK).
 * KULLANIM: pull2.php?key=...&files=apply-fallprof4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fallprof4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$fa=__DIR__.'/fall-api.php';
$src=@file_get_contents($fa);
if($src===false) exit("fall-api.php okunamadi\n");
if(strpos($src,'CH_FALLPROF3')!==false) exit("Zaten ekli (CH_FALLPROF3).\n");
if(strpos($src,'$chTel')===false) exit("On kosul eksik: once apply-fallprof2 calismali.\n");

$old='. "Telefon: " . ($prof[\'f5\'] ?? ($prof[\'f6\'] ?? \'\')) . "\n"';
$new='. (($chTel !== \'\') ? ("Telefon: " . $chTel . "\n") : "")/*CH_FALLPROF3*/';
$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ Telefon satiri: f5 (dogum tarihi) onceligi KALDIRILDI, dogrulamali telefon\n";

$tmp=tempnam(sys_get_temp_dir(),'fp4').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@copy($fa,$fa.'.bak-fallprof4-'.date('Ymd-His'));
$w=@file_put_contents($fa,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($fa);
if(strpos($chk,'CH_FALLPROF3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_FALLPROF3 diskte\n";
echo "  Test: Fall lösen -> 'Telefon:' satirinda tarih ASLA cikmaz; gercek telefon\n";
echo "  varsa o yazilir, yoksa satir tamamen atlanir. Isim de tam cikar (profil onarimi).\n";
