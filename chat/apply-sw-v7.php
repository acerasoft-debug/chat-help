<?php
/* ChatHelp — apply-sw-v7 (CH_SW_V7) — SW'yi v7'ye bump + gorunur surum-isareti ekler.
   Amac: 'Ungultige Webadresse' hatasinin ESKI KOD calistigi icin mi yoksa YENI KODDA
   GERCEKTEN bir sorun mu oldugunu KESINLESTIRMEK. Menu basligina kucuk bir build-etiketi
   (ornek: v7) eklenir — App'te bu etiket goruluyorsa yeni kod calisiyor demektir.
   KULLANIM: pull2.php?key=...&files=apply-sw-v7.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-sw-v7 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

/* [1] sw.js v7 */
$dest="$D/sw.js";
$L=array();
$L[]="/* ChatHelp service worker v7 (CH_SW_V7) — full reset + auto fresh reload */";
$L[]="var CH_SW='v7-".date('Ymd-His')."';";
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
if($w===false || strpos($chk,'CH_SW_V7')===false || strpos($chk,'.respondWith')!==false){ echo "✗ sw.js DOGRULAMA BASARISIZ\n"; exit; }
echo "  ✓ [1] sw.js v7 yazildi (".strlen($chk)." B)\n";

/* [2] gorunur build-etiketi (.sb-logo yaninda kucuk 'v7') */
$file="$D/index.php";
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_BUILDTAG')!==false){ echo "  = [2] build-etiketi zaten var\n"; }
else{
  $block = <<<'HTMLBLOCK'
<script id="ch-buildtag-js">
/* CH_BUILDTAG — App'te gercek yeni kod calisiyor mu gorsel dogrulama */
try{(function(){
  function add(){
    try{
      var els=document.querySelectorAll('.sb-logo');
      for(var i=0;i<els.length;i++){
        var el=els[i]; if(el.querySelector('.ch-btag')) continue;
        var t=document.createElement('span'); t.className='ch-btag';
        t.style.cssText='margin-left:4px;font-size:9px;font-weight:800;color:#6b6474;opacity:.6;align-self:flex-end';
        t.textContent='v7';
        el.appendChild(t);
      }
    }catch(e){}
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',add); else add();
  try{ setInterval(add,1500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos===false) exit("</body> yok\n");
  $src=substr($src,0,$pos).$block."\n".substr($src,$pos);
  $tmp=tempnam(sys_get_temp_dir(),'bt').'.php'; file_put_contents($tmp,$src);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0){ echo "  ✗ [2] LINT HATASI — build-etiketi eklenmedi:\n  ".implode("\n  ",$lo)."\n"; }
  else{
    @file_put_contents($file.'.bak-buildtag-'.date('Ymd-His'),(string)@file_get_contents($file));
    $w2=@file_put_contents($file,$src);
    if($w2!==false){ echo "  ✓ [2] gorunur 'v7' etiketi menu basligina eklendi\n"; }
  }
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n>>> SIMDI: App'i AYARLAR > Uygulamalar > ChatHelp > ZORLA DURDUR (Force Stop) yap,\n";
echo "    SONRA ac. (Sadece recents'ten kaydirmak yetmeyebilir — Force Stop KESIN cozer.)\n";
echo "    Menu basliginin yaninda kucuk gri 'v7' yazisini GOR -> yeni kod calisiyor demektir.\n";
echo "    'v7' YOKSA hala eski kod calisiyor -> Force Stop adimini tekrar dene.\n";
echo "    'v7' VARSA ve PDF hala 'Ungultige Webadresse' veriyorsa, sorun gercekten kodda —\n";
echo "    o zaman farkli bir yaklasim gerekir, hemen soyle.\n";
