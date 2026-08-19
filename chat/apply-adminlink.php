<?php
/**
 * ChatHelp — apply-adminlink (CH_ADMLINK) — admin.php'ye sabit "📊 Analyse" butonu ekler.
 *   analyse.php ile ayni ADMIN_PASS + oturum; tek girisle iki panel arasi gecis.
 *   Her <body...> sonrasina eklenir (giris + panel). Idempotent + php -l lint + .bak.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-adminlink.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-adminlink BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/admin.php';
$src=@file_get_contents($file);
if($src===false) exit("admin.php okunamadi\n");
if(strpos($src,'CH_ADMLINK')!==false) exit("Zaten ekli (CH_ADMLINK).\n");

$btn='<a href="analyse.php" style="position:fixed;top:14px;right:14px;z-index:99999;background:linear-gradient(180deg,#E4C688,#C6A15B);color:#0B1E38;font:700 13px system-ui,-apple-system,sans-serif;text-decoration:none;padding:9px 16px;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.4)">&#128202; Analyse</a><!--CH_ADMLINK-->';

$cnt=0;
$new=preg_replace('/(<body[^>]*>)/', '$1'.$btn, $src, -1, $cnt);
if($cnt<1){ echo "  ✗ <body> bulunamadi — DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ $cnt adet <body> sonrasina Analyse butonu eklendi\n";

$tmp=tempnam(sys_get_temp_dir(),'al').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-admlink-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_ADMLINK')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_ADMLINK eklendi (".strlen($chk)." B)\n";
echo "  · admin.php sag ustte '📊 Analyse' -> analyse.php (ayni sifre/oturum).\n";
