<?php
/**
 * ChatHelp — apply-swkill (CH_SWKILL) — KESIN KOK NEDEN COZUMU:
 *  Cloudflare sw.js'ye 31-gun browser-cache (max-age=2678400) koyuyor -> cihazlar
 *  eski Service Worker'i guncelleyemiyor -> eski SW dondurulmus eski HTML'i sonsuza
 *  kadar servis ediyor -> yapilan HICBIR degisiklik cihazda gorunmuyor.
 *  Service Worker bize hicbir fayda saglamiyor (bos pass-through). COZUM: sayfa
 *  her acilista TUM service worker'lari KALDIRIR + CacheStorage'i siler ve BIR DAHA
 *  ASLA SW kaydetmez. Bir kez taze sayfa yuklendiginde (App verisi temizligi veya
 *  fresh.php) SW kalici olarak olur; bundan sonra her degisiklik ANINDA gorunur.
 *  Ayrica sw.js icerigi de kendini-kaldiran surume cevrilir (guncelleyen cihazlar
 *  icin ek guvence).
 * KULLANIM: pull2.php?key=...&files=apply-swkill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-swkill BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$ok=0;

/* ── [1] index.php: her acilista SW-kaldirici ── */
$file=$D.'/index.php';
$src=@file_get_contents($file);
if($src===false){ echo "  ✗ index.php okunamadi\n"; }
elseif(strpos($src,'CH_SWKILL')!==false){ echo "  = [1] zaten ekli\n"; $ok++; }
else{
  $block = <<<'HTMLBLOCK'
<script id="ch-swkill-js">
/* CH_SWKILL — Service Worker'i kalici kaldir (Cloudflare 31-gun sw.js cache'i etkisiz) */
try{(function(){
  if('serviceWorker' in navigator){
    try{
      navigator.serviceWorker.getRegistrations().then(function(rs){
        rs.forEach(function(r){ try{ r.unregister(); }catch(e){} });
      }).catch(function(){});
    }catch(e){}
    /* register'i etkisizlestir: sayfa/eski kod bir daha SW kaydedemesin */
    try{ navigator.serviceWorker.register = function(){ return Promise.reject(new Error('sw disabled')); }; }catch(e){}
  }
  try{ if(window.caches && caches.keys){ caches.keys().then(function(ks){ ks.forEach(function(k){ try{ caches.delete(k); }catch(e){} }); }).catch(function(){}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
  /* mumkun oldugunca ERKEN calissin: <head> sonrasi ilk <script>'ten once ideal,
     ama guvenli/basit: </head> hemen oncesi; yoksa </body> oncesi */
  $pos=stripos($src,'</head>');
  if($pos!==false){ $src=substr($src,0,$pos).$block."\n".substr($src,$pos); }
  else { $pos=strripos($src,'</body>'); if($pos===false){ echo "  ✗ [1] </head>/</body> yok\n"; goto sw2; } $src=substr($src,0,$pos).$block."\n".substr($src,$pos); }
  $tmp=tempnam(sys_get_temp_dir(),'sk').'.php'; file_put_contents($tmp,$src);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0){ echo "  ✗ [1] LINT: ".implode(' ',$lo)."\n"; }
  else{
    @file_put_contents($file.'.bak-swkill-'.date('Ymd-His'),(string)@file_get_contents($file));
    $w=@file_put_contents($file,$src);
    if($w!==false){ echo "  ✓ [1] index.php: SW-kaldirici eklendi (".strlen($src)." B)\n"; $ok++; }
    else echo "  ✗ [1] yazma hatasi\n";
  }
}
sw2:

/* ── [2] sw.js: kendini-kaldiran surum ── */
$sw = "/* ChatHelp SW v9 (CH_SWKILL) — kendini kaldirir, hicbir sey onbelleklemez */\n"
    . "self.addEventListener('install', function(e){ self.skipWaiting(); });\n"
    . "self.addEventListener('activate', function(e){ e.waitUntil((async function(){\n"
    . "  try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}\n"
    . "  try{ await self.registration.unregister(); }catch(err){}\n"
    . "  try{ var cs=await self.clients.matchAll({type:'window'}); cs.forEach(function(c){ try{ c.navigate(c.url); }catch(e){} }); }catch(err){}\n"
    . "})()); });\n"
    . "self.addEventListener('fetch', function(e){});\n";
$dest=$D.'/sw.js';
if(is_file($dest)) @copy($dest,$dest.'.bak-swkill-'.date('Ymd-His'));
$w=@file_put_contents($dest,$sw);
if($w!==false){ echo "  ✓ [2] sw.js: kendini-kaldiran v9 yazildi (".strlen($sw)." B)\n"; $ok++; }
else echo "  ✗ [2] sw.js yazilamadi\n";

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/3\n";
echo "  =========================================================\n";
echo "  BUNDAN SONRA (kok neden kalici cozuldu):\n";
echo "  Cihazda BIR KEZ taze sayfa yuklenince eski Service Worker SONSUZA KADAR olur.\n";
echo "  App icin: Ayarlar > Uygulamalar > ChatHelp > Depolama > VERILERI TEMIZLE, ac.\n";
echo "  Tarayici icin: chat-help.com/chat/fresh.php ac.\n";
echo "  O tek seferden sonra her degisiklik ANINDA gorunur — SW bir daha takilmaz.\n";
