<?php
/**
 * ChatHelp — apply-chlog2 — chlog.php CDN/edge tarafinda 404 olarak
 *  onbellege alinmis olabilir (dosya yokken yapilan ilk okumalar). Cozum:
 *  hic istenmemis YENI dosya adiyla (chlog9k1.php) ayni logger'i yazar ve
 *  index.php'deki CH_APPLOG beacon'inin hedefini bu yeni dosyaya cevirir.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-chlog2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-chlog2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* 1) yeni-isim logger'i yaz */
$logger = <<<'PHP'
<?php
/* ChatHelp gecici teshis logger v2 (CH_APPLOG). apply-chlog-off ile silinir. */
error_reporting(0);
$K='chl_2607';
if((isset($_GET['k'])?$_GET['k']:'')!==$K){ http_response_code(403); exit; }
$f=__DIR__.'/chlog9k1.txt';
if(isset($_GET['read'])){ header('Content-Type: text/plain; charset=UTF-8'); echo @file_get_contents($f); exit; }
$d=isset($_GET['d'])?(string)$_GET['d']:'';
if($d!==''){ if(@filesize($f)>60000) @unlink($f); @file_put_contents($f, date('H:i:s').'  '.substr($d,0,700)."\n", FILE_APPEND|LOCK_EX); }
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
PHP;
$w=@file_put_contents($dir.'/chlog9k1.php',$logger);
echo $w!==false ? "  ✓ chlog9k1.php yazildi ($w bayt)\n" : "  ✗ chlog9k1.php yazilamadi\n";

/* 2) index.php'deki beacon hedefini chlog.php -> chlog9k1.php yap */
if(strpos($src,'CH_APPLOG')===false){ echo "  ✗ index'te CH_APPLOG yok — once apply-chlog.php calistirilmali.\n"; exit; }
if(strpos($src,'chlog9k1.php')!==false){ echo "  (zaten chlog9k1.php'ye yonleniyor — degisiklik yok)\n"; exit; }

$new=str_replace('chlog.php?k=chl_2607','chlog9k1.php?k=chl_2607',$src,$cnt);
if($cnt<1){ echo "  ✗ beacon URL'i bulunamadi (index degismedi)\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'cl2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI, index degismedi:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-chlog2-'.date('Ymd-His'),$src);
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ index.php beacon hedefi chlog9k1.php'ye cevrildi ($cnt yer)\n";

echo "\nADIMLAR:\n";
echo " 1) Android App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas.\n";
echo " 2) Sonra Mac'te:\n";
echo "    curl -s \"https://chat-help.com/chat/chlog9k1.php?k=chl_2607&read=1\"\n";
