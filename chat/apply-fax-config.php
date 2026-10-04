<?php
/**
 * ChatHelp — apply-fax-config — ClickSend fax kimliklerini config.php'ye ekler
 *  (FAX_PROVIDER, CLICKSEND_USER, CLICKSEND_KEY). Sadece EKLEMELI (var olan
 *  ayarlara dokunmaz). Calistiktan sonra bu installer repodan SILINIR.
 *  NOT: kimlik bu betikte gomulu — kullanildiktan sonra kaldirilir.
 * KULLANIM: pull2.php?key=...&files=apply-fax-config.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-config BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$provider = 'clicksend';
$user     = 'acerasoft@gmail.com';
$key      = '596B1BE3-1FCE-B13C-13F7-3E301F6F2FAA';

$file = __DIR__.'/config.php';
$src = @file_get_contents($file);
if ($src===false) exit("config.php okunamadi\n");

if (strpos($src,'CLICKSEND_KEY')!==false) exit("Zaten ekli (CLICKSEND_KEY var). Degistirmek istersen once elle temizle.\n");

$ins = "\n/* ChatHelp fax (ClickSend) — apply-fax-config */\n"
     . "if(!defined('FAX_PROVIDER'))   define('FAX_PROVIDER','".$provider."');\n"
     . "if(!defined('CLICKSEND_USER')) define('CLICKSEND_USER','".$user."');\n"
     . "if(!defined('CLICKSEND_KEY'))  define('CLICKSEND_KEY','".$key."');\n";

/* kapanis ?> varsa ondan ONCE ekle; yoksa sona ekle (PHP baglami surer) */
$pos = strrpos($src, '?>');
if ($pos!==false) {
  $new = substr($src,0,$pos).$ins."\n".substr($src,$pos);
} else {
  $new = rtrim($src)."\n".$ins;
}

/* lint */
$tmp = tempnam(sys_get_temp_dir(),'cfg').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "LINT HATASI — config.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@copy($file, $file.'.bak-faxcfg-'.date('Ymd-His'));
$w=@file_put_contents($file,$new);
if ($w===false) { echo "✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CLICKSEND_KEY')===false) { echo "✗ DOGRULAMA BASARISIZ.\n"; exit; }

if (function_exists('opcache_reset')) @opcache_reset();

echo "  ✓ config.php guncellendi (".strlen($chk)." B): FAX_PROVIDER=clicksend, CLICKSEND_USER, CLICKSEND_KEY eklendi\n";
echo "  ✓ opcache reset\n\n";
echo "SONRAKI: pull2.php?key=...&files=dump-fax-probe.php  (status: fax_configured=EVET beklenir)\n";
echo "GUVENLIK: bu installer repodan kaldirilacak (kimlik icermesin).\n";
