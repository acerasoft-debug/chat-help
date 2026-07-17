<?php
/**
 * ChatHelp — apply-fix-sw (CH_SW_SAFE) — foto/kamera regresyonu ACIL duzeltme.
 *  KOK NEDEN (guclu suphe): az once kurulan PWA service worker'i (sw.js) TUM
 *  istekleri respondWith ile yakaliyordu; buyuk POST govdeli istekler
 *  (analyze_photo/aichat) SW uzerinden yeniden oynatilirken dusebilir ve ag
 *  hatasi durumunda caches.match POST'lar icin undefined dondurup istegi
 *  OLDURUR. Dun calisan foto akisi tam PWA kurulumundan sonra kirildi.
 *  DUZELTME: sw.js PASS-THROUGH surumle degistirilir — fetch dinleyicisi
 *  respondWith CAGIRMAZ (tarayici istekleri normal yoldan yapar, SW hicbir
 *  istege dokunmaz). PWA yuklenebilirligi icin SW varligi yeterlidir.
 *  Ayrica activate'te eski cache'ler temizlenir ve skipWaiting/claim ile
 *  acik sekmelerdeki ESKI SW aninda bu guvenli surumle degistirilir.
 * KULLANIM: pull2.php?key=...&files=apply-fix-sw.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-sw BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

$sw = "/* ChatHelp service worker v2 (CH_SW_SAFE) — PASS-THROUGH: hicbir istege dokunmaz */\n"
    . "self.addEventListener('install', function(e){ self.skipWaiting(); });\n"
    . "self.addEventListener('activate', function(e){\n"
    . "  e.waitUntil((async function(){\n"
    . "    try{ var ks=await caches.keys(); await Promise.all(ks.map(function(k){ return caches.delete(k); })); }catch(err){}\n"
    . "    try{ await self.clients.claim(); }catch(err){}\n"
    . "  })());\n"
    . "});\n"
    . "/* fetch dinleyicisi var (PWA yuklenebilirlik) ama respondWith YOK -> tarayici normal davranir */\n"
    . "self.addEventListener('fetch', function(e){});\n";

$old=(string)@file_get_contents("$D/sw.js");
echo "  eski sw.js: ".strlen($old)." B".(strpos($old,'respondWith')!==false?" (respondWith ICERIYOR — sorunlu surum)":"")."\n";
$w=@file_put_contents("$D/sw.js",$sw);
if($w===false) exit("  ✗ sw.js yazilamadi\n");
echo "  ✓ sw.js guvenli v2 ile degistirildi ($w B)\n";
$chk=(string)@file_get_contents("$D/sw.js");
if(strpos($chk,'CH_SW_SAFE')===false || strpos($chk,'respondWith')!==false) exit("  ✗ DOGRULAMA BASARISIZ\n");
echo "  ✓ DOGRULAMA: pass-through SW diskte, respondWith yok\n";
echo "\n✓ Service worker artik HICBIR istege dokunmuyor; foto/kamera/sohbet\n";
echo "   istekleri dogrudan tarayicidan gider. Acik sekmeler bir sonraki\n";
echo "   yenilemede otomatik bu surume gecer (skipWaiting+claim+cache temizligi).\n";
echo "   PWA yuklenebilirligi ve PWABuilder paketleme etkilenmez.\n";
