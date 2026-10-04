<?php
/**
 * ChatHelp — apply-sw-reset2 (CH_SW_V3) — canli sw.js hala eski cache'leyen
 *  surumdu (app eski icerigi sunuyordu). sw.js v3: install skipWaiting; activate
 *  TUM cache'leri siler + kontrol alir + acik pencereleri taze yukler; fetch
 *  dinleyicisi bos (istekleri ele almaz -> HTML hep taze). Dogrulama duzeltildi
 *  (yorumda anahtar kelime yok, gercek cagri aranir).
 * KULLANIM: pull2.php?key=...&files=apply-sw-reset2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sw-reset2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dest = __DIR__.'/sw.js';
$old  = @file_get_contents($dest);
$hadRW = ($old!==false && strpos($old,'.respondWith')!==false);
echo "  eski sw.js: ".($old!==false?strlen($old)." B":"(yok)").($hadRW?" (cache'liyor — .respondWith VAR)":"")."\n";

$L=array();
$L[]="/* ChatHelp service worker v3 (CH_SW_V3) — cache reset + otomatik taze yukleme */";
$L[]="var CH_SW='v3-".date('Ymd-His')."';";
$L[]="self.addEventListener('install', function(e){ self.skipWaiting(); });";
$L[]="self.addEventListener('activate', function(e){";
$L[]="  e.waitUntil((async function(){";
$L[]="    try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}";
$L[]="    try{ await self.clients.claim(); }catch(err){}";
$L[]="    try{ var cs=await self.clients.matchAll({type:'window'}); cs.forEach(function(c){ try{ c.navigate(c.url); }catch(e){} }); }catch(err){}";
$L[]="  })());";
$L[]="});";
$L[]="/* pass-through: fetch dinleyicisi bos -> istekler tarayici/WebView tarafindan normal yapilir, HTML taze */";
$L[]="self.addEventListener('fetch', function(e){});";
$L[]="self.addEventListener('message', function(e){ if(e.data==='skipWaiting') self.skipWaiting(); });";
$sw=implode("\n",$L)."\n";

if(is_file($dest)) @copy($dest,$dest.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($dest,$sw);
$chk=(string)@file_get_contents($dest);
/* DOGRU dogrulama: gercek .respondWith CAGRISI olmamali, CH_SW_V3 olmali */
if($w===false || strpos($chk,'CH_SW_V3')===false || strpos($chk,'.respondWith')!==false){
  echo "  ✗ DOGRULAMA BASARISIZ (w=".var_export($w,true).")\n"; exit;
}
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ sw.js v3 yazildi (".strlen($chk)." B): cache reset + otomatik taze yukleme, .respondWith YOK\n";
echo "\n✓ App'i TAM KAPAT (arka plandan da kaldir) ve 2 kez ac -> yeni SW kurulur,\n";
echo "   cache'ler silinir, sayfa taze index.php ile yenilenir. Elle temizlik gerekmez.\n";
