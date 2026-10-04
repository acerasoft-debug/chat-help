<?php
/* ChatHelp — apply-sw-v8 (CH_SW_V8) — SW'yi v8'e bump + gorunur build-etiketini v7->v8
   yapar. Amac: son gunlerdeki TUM guncellemelerin (Senden yesil buton, direkt-PDF,
   Fall premium, mikrofon-off, IBAN, gonderim PDF duzeni...) cihaza KESIN inmesi.
   sw.js yeni icerik-hash'i ile SW guncellemesini tetikler: tum cache silinir +
   acik pencereler otomatik taze yeniden yuklenir.
   KULLANIM: pull2.php?key=...&files=apply-sw-v8.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-sw-v8 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

/* [1] sw.js v8 */
$dest="$D/sw.js";
$L=array();
$L[]="/* ChatHelp service worker v8 (CH_SW_V8) — full reset + auto fresh reload */";
$L[]="var CH_SW='v8-".date('Ymd-His')."';";
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
if($w===false || strpos($chk,'CH_SW_V8')===false || strpos($chk,'.respondWith')!==false){ echo "✗ sw.js DOGRULAMA BASARISIZ\n"; exit; }
echo "  ✓ [1] sw.js v8 yazildi (".strlen($chk)." B)\n";

/* [2] gorunur build-etiketi v7 -> v8 */
$file="$D/index.php";
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$c=substr_count($src,"t.textContent='v7';");
if($c===1){
  $src=str_replace("t.textContent='v7';","t.textContent='v8';",$src);
  $tmp=tempnam(sys_get_temp_dir(),'bt8').'.php'; file_put_contents($tmp,$src);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0){ echo "  ✗ [2] LINT HATASI — etiket degistirilmedi\n"; }
  else{
    @file_put_contents($file.'.bak-swv8-'.date('Ymd-His'),(string)@file_get_contents($file));
    $w2=@file_put_contents($file,$src);
    echo $w2!==false ? "  ✓ [2] build-etiketi v7 -> v8\n" : "  ✗ [2] yazma hatasi\n";
  }
} else {
  echo "  = [2] 'v7' etiketi bulunamadi ($c) — atlandi (sw.js yine de v8)\n";
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n>>> SIMDI: App > AYARLAR > Uygulamalar > ChatHelp > ZORLA DURDUR (Force Stop), sonra ac.\n";
echo "    Menu basliginda kucuk gri 'v8' gorursen -> en son kod calisiyor.\n";
echo "    Sonra: belge uret -> yesil BUYUK 'Senden' butonu -> gonderim modali acilmali.\n";
