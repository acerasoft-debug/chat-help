<?php
/**
 * ChatHelp — apply-fallprof3 — kalan tek nokta: fall-api'de "Telefon:" satirini
 *  dogrulamali $chTel ile degistir (regex, bosluk-toleransli). Eslesmezse
 *  '"Telefon' civarindaki canli metni basar.
 * KULLANIM: pull2.php?key=...&files=apply-fallprof3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fallprof3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$fa=__DIR__.'/fall-api.php';
$src=@file_get_contents($fa);
if($src===false) exit("fall-api.php okunamadi\n");
if(strpos($src,'CH_FALLPROF3')!==false) exit("Zaten ekli (CH_FALLPROF3).\n");
if(strpos($src,'$chTel')===false) exit("On kosul eksik: once apply-fallprof2 calismali.\n");

$pat='/\.\s*"Telefon:\s*"\s*\.\s*\(\s*\$prof\[\'f6\'\]\s*\?\?\s*\'\'\s*\)\s*\.\s*"\\\\n"/';
$rep='. (($chTel !== \'\') ? ("Telefon: " . $chTel . "\n") : "")/*CH_FALLPROF3*/';
$cnt=0;
$out=preg_replace($pat,$rep,$src,1,$cnt);
if($cnt!==1 || !is_string($out)){
  echo "  ✗ regex eslesmedi ($cnt). Canli '\"Telefon' civari:\n";
  $p=strpos($src,'"Telefon');
  if($p!==false){ echo "---8<---\n".substr($src,max(0,$p-160),420)."\n--->8---\n"; }
  else echo "  '\"Telefon' hic yok — belki zaten kosullu.\n";
  exit("\nHICBIR sey degistirilmedi.\n");
}
$src=$out;
echo "  ✓ Telefon satiri dogrulamali yapildi\n";

$tmp=tempnam(sys_get_temp_dir(),'fp3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@copy($fa,$fa.'.bak-fallprof3-'.date('Ymd-His'));
$w=@file_put_contents($fa,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($fa);
if(strpos($chk,'CH_FALLPROF3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_FALLPROF3 diskte\n";
echo "  Fall lösen -> telefon satirina tarih ASLA yazilmaz; telefon yoksa satir yok.\n";
