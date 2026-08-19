<?php
/* ChatHelp — apply-sw-reset4 (CH_SW_V5) — SW'yi yeni surume (v5) zorla: install
   skipWaiting; activate TUM cache'leri sil + kontrol al + acik pencereleri TAZE
   yukle; fetch bos (pass-through). Yeni surum -> tarayici SW'yi gunceller.
   KULLANIM: pull2.php?key=...&files=apply-sw-reset4.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-sw-reset4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__; $dest="$D/sw.js";
$L=array();
$L[]="/* ChatHelp service worker v5 (CH_SW_V5) — full reset + auto fresh reload */";
$L[]="var CH_SW='v5-".date('Ymd-His')."';";
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
if($w===false || strpos($chk,'CH_SW_V5')===false || strpos($chk,'.respondWith')!==false){ echo "✗ DOGRULAMA BASARISIZ\n"; exit; }
if(function_exists('opcache_reset')) @opcache_reset();
echo "✓ sw.js v5 yazildi (".strlen($chk)." B): tam reset + otomatik taze yukleme\n\n";
echo ">>> SIMDI: App'i ARKA PLANDAN TAMAMEN KALDIR (recents'ten kaydir-at) ve 2 kez ac.\n";
echo "    Ilk acilis yeni SW'yi kurar, ikinci acilis cache silinmis taze sayfayi yukler.\n";
echo "    (Veri silinmez — gecmis/profiller durur.)\n";
