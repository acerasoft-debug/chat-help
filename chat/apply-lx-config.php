<?php
/**
 * ChatHelp — apply-lx-config — LetterXpress API bilgilerini config.php'ye ekler.
 * KULLANIM (parametreli):
 *  pull2.php?key=...&files=apply-lx-config.php&lxuser=KULLANICI&lxkey=APIANAHTAR&lxmode=test
 *  lxuser  = LetterXpress kullanici adi (giris e-postasi/kullanici)
 *  lxkey   = LetterXpress API anahtari (panel > Einstellungen > API)
 *  lxmode  = 'test' (deneme) veya 'live' (gercek gonderim)
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lx-config BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$u=trim((string)($_GET['lxuser']??'')); $k=trim((string)($_GET['lxkey']??'')); $m=trim((string)($_GET['lxmode']??'test'));
if($u===''||$k===''){
  echo "KULLANIM: URL'ye ekleyin:\n  ...&files=apply-lx-config.php&lxuser=KULLANICI&lxkey=APIANAHTAR&lxmode=test\n";
  echo "  lxuser -> LetterXpress kullanici adi\n  lxkey  -> API anahtari (panel > API)\n  lxmode -> test | live\n"; exit;
}
if($m!=='live') $m='test';
$f=__DIR__.'/config.php';
$s=(string)@file_get_contents($f);
if($s===''){ exit("HATA: config.php okunamadi\n"); }
if(strpos($s,'<?php')!==0 && strpos($s,'<?php')===false){ exit("HATA: config.php beklenen formatta degil\n"); }
/* mevcut tanimlari sok, yeniden ekle */
$s=preg_replace("/\n?define\('LETTERXPRESS_(USER|APIKEY|MODE)'.*?;\s*/","",$s);
$ins="\ndefine('LETTERXPRESS_USER', ".var_export($u,true).");\n"
    ."define('LETTERXPRESS_APIKEY', ".var_export($k,true).");\n"
    ."define('LETTERXPRESS_MODE', ".var_export($m,true).");\n";
$pos=strpos($s,'<?php'); $pos=($pos===false)?0:$pos+5;
$new=substr($s,0,$pos).$ins.substr($s,$pos);
$tmp=tempnam(sys_get_temp_dir(),'lx').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — config DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($f.'.bak-lx-'.date('Ymd-His'),$s);
@file_put_contents($f,$new);
$chk=(string)@file_get_contents($f);
echo (strpos($chk,'LETTERXPRESS_APIKEY')!==false)
  ? "  ✓ LetterXpress bilgileri config.php'ye eklendi (mode=$m)\n\n✓ Kontrol: https://chat-help.com/chat/postversand.php?action=status\n  'configured':true gormeli.\n"
  : "  ✗ eklenemedi\n";
