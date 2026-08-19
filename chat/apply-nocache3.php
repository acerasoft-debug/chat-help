<?php
/**
 * ChatHelp — apply-nocache3 (CH_NOCACHE3) — GoDaddy Managed-WordPress / Varnish tarzi
 *  sunucu cache'leri, yanitinda Set-Cookie olan sayfalari ONBELLEGE ALMAZ.
 *  index.php artik hafif bir cerez ('chnc') gonderir -> host cache /chat'i atlar.
 *  Panelden yapilacak TEK SEFERLIK 'Flush Cache' sonrasi sayfa bir daha bayatlamaz.
 * KULLANIM: pull2.php?key=...&files=apply-nocache3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nocache3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_NOCACHE3')!==false) exit("Zaten ekli (CH_NOCACHE3).\n");

$old="/*CH_NOCACHE2_HDR*/ if(!headers_sent()){ header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); }";
$new="/*CH_NOCACHE2_HDR*/ if(!headers_sent()){ header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Cookie'); /*CH_NOCACHE3*/ if(empty(\$_COOKIE['chnc'])){ @setcookie('chnc','1',time()+3600,'/'); } }";
$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ Set-Cookie tabanli cache-bypass eklendi (host cache /chat'i atlar)\n";

$tmp=tempnam(sys_get_temp_dir(),'nc3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nocache3-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOCACHE3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOCACHE3 diskte (".strlen($chk)." B)\n";
echo "  SIRALAMA ONEMLI:\n";
echo "  1) Bu script calisti (tamam)\n";
echo "  2) WP-Admin ust cubugu -> GoDaddy -> FLUSH CACHE (veya GoDaddy panel -> Managed\n";
echo "     WordPress -> Overview -> Flush Cache)\n";
echo "  3) chat-help.com/chat ac -> menude 'v8' + YESIL buyuk Senden gorunmeli\n";
echo "  Bundan sonra /chat bir daha edge cache'e takilmaz (cerez sayesinde).\n";
