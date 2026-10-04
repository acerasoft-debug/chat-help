<?php
/**
 * ChatHelp — apply-sw-reset3 (CH_SW_V4) — "guncellemeler aktif degil" = eski SW
 *  hala eski index'i sunuyor. sw.js YENI surum (v4): install skipWaiting; activate
 *  TUM cache'leri siler + kontrol alir + acik pencereleri TAZE yukler; fetch bos
 *  (pass-through -> HTML hep taze). Yeni surum stringi -> tarayici SW'yi gunceller.
 *  Ayrica index.php'de SON marker'larin varligini RAPOR eder (sunucu tazeligi).
 * KULLANIM: pull2.php?key=...&files=apply-sw-reset3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sw-reset3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$D=__DIR__;
/* ── sunucu tazeligi: son marker'lar index'te mi? ── */
$idx=(string)@file_get_contents("$D/index.php");
echo "index.php: ".strlen($idx)." B\n";
$marks=['CH_APPSTORE_IOS','CH_VERLAUF_RECENT2','CH_KONTO_REALUSER','CH_HIDE_BILLING','CH_KUEND_FIX','CH_IOS_PWA','CH_CALM_PREM','CH_MENUICON','CH_THEME_UNIFY','CH_APPICON'];
echo "SON GUNCELLEMELER (sunucuda):\n";
foreach($marks as $m){ echo "  ".(strpos($idx,$m)!==false?"✓":"✗")." ".$m."\n"; }

/* ── sw.js v4 yaz ── */
$dest="$D/sw.js";
$old=@file_get_contents($dest);
echo "\neski sw.js: ".($old!==false?strlen($old)." B":"(yok)")."\n";

$L=array();
$L[]="/* ChatHelp service worker v4 (CH_SW_V4) — cache reset + otomatik taze yukleme */";
$L[]="var CH_SW='v4-".date('Ymd-His')."';";
$L[]="self.addEventListener('install', function(e){ self.skipWaiting(); });";
$L[]="self.addEventListener('activate', function(e){";
$L[]="  e.waitUntil((async function(){";
$L[]="    try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}";
$L[]="    try{ await self.clients.claim(); }catch(err){}";
$L[]="    try{ var cs=await self.clients.matchAll({type:'window'}); cs.forEach(function(c){ try{ c.navigate(c.url); }catch(e){} }); }catch(err){}";
$L[]="  })());";
$L[]="});";
$L[]="/* pass-through: fetch dinleyicisi bos -> HTML hep taze */";
$L[]="self.addEventListener('fetch', function(e){});";
$L[]="self.addEventListener('message', function(e){ if(e.data==='skipWaiting') self.skipWaiting(); });";
$sw=implode("\n",$L)."\n";

if(is_file($dest)) @copy($dest,$dest.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($dest,$sw);
$chk=(string)@file_get_contents($dest);
if($w===false || strpos($chk,'CH_SW_V4')===false || strpos($chk,'.respondWith')!==false){
  echo "\n✗ sw.js DOGRULAMA BASARISIZ\n"; exit;
}
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n✓ sw.js v4 yazildi (".strlen($chk)." B): cache reset + otomatik taze yukleme\n";
echo "\n>>> SIMDI: App'i TAM KAPAT (arka plandan kaldir) ve 2 kez ac. Yeni SW kurulur,\n";
echo "    tum cache'ler silinir, sayfa taze index.php ile yenilenir.\n";
echo "    Tarayicida: sekmeyi kapat/ac veya 1 kez sil-yenile (Ctrl+Shift+R).\n";
