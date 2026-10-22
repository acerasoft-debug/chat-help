<?php
/* ChatHelp — apply-sw-reset5 (CH_SW_V6) — SW'yi yeni surume (v6) zorla: install
   skipWaiting; activate TUM cache'leri sil + kontrol al + acik pencereleri TAZE
   yukle (clients.navigate); fetch bos (pass-through). Byte-diff -> tarayici SW'yi
   gunceller -> eski HTML cache'i silinir -> yeni hero/H1 gorunur.
   KULLANIM: pull2.php?key=...&files=apply-sw-reset5.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-sw-reset5 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__; $dest="$D/sw.js";
$L=array();
$L[]="/* ChatHelp service worker v6 (CH_SW_V6) — full reset + auto fresh reload */";
$L[]="var CH_SW='v6-".date('Ymd-His')."';";
$L[]="self.addEventListener('install', function(e){ self.skipWaiting(); });";
$L[]="self.addEventListener('activate', function(e){";
$L[]="  e.waitUntil((async function(){";
$L[]="    try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}";
$L[]="    try{ await self.clients.claim(); }catch(err){}";
$L[]="    try{ var cs=await self.clients.matchAll({type:'window'}); cs.forEach(function(c){ try{ c.navigate(c.url); }catch(e){} }); }catch(err){}";
$L[]="  })());";
$L[]="});";
$L[]="self.addEventListener('fetch', function(e){});";
$L[]="self.addEventListener('message', function(e){ if(e.data==='skipWaiting') self.skipWaiting(); });";
$sw=implode("\n",$L)."\n";
if(is_file($dest)) @copy($dest,$dest.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($dest,$sw);
$chk=(string)@file_get_contents($dest);
if($w===false || strpos($chk,'CH_SW_V6')===false || strpos($chk,'.respondWith')!==false){ echo "✗ DOGRULAMA BASARISIZ\n"; exit; }
if(function_exists('opcache_reset')) @opcache_reset();
echo "✓ sw.js v6 yazildi (".strlen($chk)." B): tam reset + otomatik taze yukleme\n\n";
echo ">>> SIMDI: App'i ARKA PLANDAN TAMAMEN KALDIR (recents'ten kaydir-at) ve 2 kez ac.\n";
echo "    1. acilis: yeni SW kurulur + cache silinir. 2. acilis: taze index.php (yeni H1/hero) yuklenir.\n";
echo "    Tarayicida ise: sayfayi kapat, tekrar ac (veya Ctrl+Shift+R).\n";
echo "    (Veri silinmez — gecmis/profiller/login durur.)\n";
