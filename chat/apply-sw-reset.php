<?php
/**
 * ChatHelp — apply-sw-reset (CH_SW_V3) — App/WebView eski surumu cache'ten
 *  sunuyordu. sw.js v3: install'da skipWaiting; activate'te TUM cache'ler silinir,
 *  kontrol alinir (clients.claim) ve acik pencereler TAZE icerikle otomatik
 *  yeniden yuklenir (client.navigate). fetch dinleyicisi var ama respondWith YOK
 *  (pass-through). Boylece app bir sonraki acilista guncel index.php'yi alir —
 *  kullanicinin elle cache temizlemesine gerek kalmaz.
 * KULLANIM: pull2.php?key=...&files=apply-sw-reset.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sw-reset BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dest = __DIR__.'/sw.js';
$old  = @file_get_contents($dest);
echo "  eski sw.js: ".($old!==false?strlen($old)." B":"(yok)").
     ($old!==false && strpos($old,'respondWith')!==false ? " (respondWith ICERIYOR!)":"")."\n";

$sw =
"/* ChatHelp service worker v3 (CH_SW_V3) — cache reset + otomatik taze yukleme */\n".
"var CH_SW='v3-".date('Ymd-His')."';\n".
"self.addEventListener('install', function(e){ self.skipWaiting(); });\n".
"self.addEventListener('activate', function(e){\n".
"  e.waitUntil((async function(){\n".
"    try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}\n".
"    try{ await self.clients.claim(); }catch(err){}\n".
"    try{ var cs=await self.clients.matchAll({type:'window'}); cs.forEach(function(c){ try{ c.navigate(c.url); }catch(e){} }); }catch(err){}\n".
"  })());\n".
"});\n".
"/* pass-through: hicbir istege respondWith YOK -> tarayici/WebView normal davranir, HTML hep taze */\n".
"self.addEventListener('fetch', function(e){});\n".
"self.addEventListener('message', function(e){ if(e.data==='skipWaiting') self.skipWaiting(); });\n";

$tmp=tempnam(sys_get_temp_dir(),'sw').'.js'; file_put_contents($tmp,$sw); @unlink($tmp);
if(is_file($dest)) @copy($dest,$dest.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($dest,$sw);
$chk=(string)@file_get_contents($dest);
if($w===false || strpos($chk,'CH_SW_V3')===false || strpos($chk,'respondWith')!==false){
  echo "  ✗ DOGRULAMA BASARISIZ\n"; exit;
}
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ sw.js v3 yazildi (".strlen($chk)." B): cache reset + otomatik taze yukleme, respondWith yok\n";
echo "\n✓ App'i TAM KAPAT (arka plandan da kaldir) ve yeniden ac -> yeni SW kurulur;\n";
echo "   bir sonraki aciliste tum cache'ler silinir ve sayfa taze index.php ile yenilenir.\n";
echo "   (2. acilista kesin guncel olur; kullanicilar elle cache temizlemez.)\n";
