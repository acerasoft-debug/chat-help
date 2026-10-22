<?php
/**
 * ChatHelp — apply-cfkill (CH_CFKILL) — SUCLU KESIN: Cloudflare 'Cache Everything'
 *  kurali HTML'i onbellekliyor (cf-cache-status: HIT) ve normal Cache-Control'u eziyor.
 *  CDN-Cache-Control / Cloudflare-CDN-Cache-Control basliklari CF'de sayfa
 *  kurallarindan YUKSEK onceliklidir -> bunlari gonderince CF sayfayi cachelemez.
 *  [1] index.php basina iki CF-ozel baslik eklenir
 *  [2] .htaccess'e ayni basliklar (sw.js dahil) eklenir
 * KULLANIM: pull2.php?key=...&files=apply-cfkill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cfkill BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$ok=0;

/* [1] index.php */
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CFKILL')!==false){ echo "  = [1] zaten ekli\n"; $ok++; }
else{
  $o1="header('Expires: 0'); header('Vary: Cookie');";
  $n1="header('Expires: 0'); header('CDN-Cache-Control: no-store'); header('Cloudflare-CDN-Cache-Control: no-store'); /*CH_CFKILL*/ header('Vary: Cookie');";
  $c=substr_count($src,$o1);
  if($c===1){
    $src=str_replace($o1,$n1,$src);
    $tmp=tempnam(sys_get_temp_dir(),'cfk').'.php'; file_put_contents($tmp,$src);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ [1] LINT — DEGISTIRILMEDI\n"; }
    else{
      @file_put_contents($file.'.bak-cfkill-'.date('Ymd-His'),(string)@file_get_contents($file));
      @file_put_contents($file,$src);
      echo "  ✓ [1] index.php: CDN-Cache-Control no-store basliklari eklendi\n"; $ok++;
    }
  } else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
}

/* [2] .htaccess */
$ht=__DIR__.'/.htaccess';
$cur=@file_get_contents($ht); if($cur===false)$cur='';
if(strpos($cur,'CH_CFKILL')!==false){ echo "  = [2] zaten ekli\n"; $ok++; }
else{
  $add="\n# CH_CFKILL — Cloudflare sayfa-kurali ezici basliklar\n"
      ."<IfModule mod_headers.c>\n"
      ."<FilesMatch \"^(index\\.php|sw\\.js|fresh\\.php)$\">\n"
      ."Header always set CDN-Cache-Control \"no-store\"\n"
      ."Header always set Cloudflare-CDN-Cache-Control \"no-store\"\n"
      ."</FilesMatch>\n"
      ."</IfModule>\n";
  @file_put_contents($ht.'.bak-cfkill-'.date('Ymd-His'),$cur);
  $w=@file_put_contents($ht,$cur.$add);
  if($w!==false){ echo "  ✓ [2] .htaccess: CF-ozel basliklar eklendi\n"; $ok++; }
  else echo "  ✗ [2] .htaccess yazilamadi\n";
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/2\n";
echo "  SIMDI CLOUDFLARE PANELI (kesin adim):\n";
echo "  1) dash.cloudflare.com -> chat-help.com\n";
echo "  2) Caching -> Configuration -> PURGE EVERYTHING\n";
echo "  3) Rules -> Page Rules / Cache Rules -> 'Cache Everything' kuralini bul ->\n";
echo "     ya KAPAT ya da '/chat/*' icin BYPASS CACHE kurali ekle\n";
echo "  4) Test: chat-help.com/chat -> menude v8 + YESIL buyuk Senden\n";
