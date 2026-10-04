<?php
/**
 * ChatHelp — apply-appstore-ios (CH_APPSTORE_IOS) — "premium menu"deki Google
 *  Play/Store butonunun TAM ALTINA, AYNI premium stille iPhone (Safari) butonu.
 *  Yontem: sayfadaki her Google Play linkini (play.google.com/store/apps) bulur,
 *  onu cloneNode ile klonlar (birebir ayni gorunum), icerigini Apple+iPhone yapar,
 *  hemen altina ekler; tik -> 'Ana Ekrana Ekle' adimlari. QR kutusu (#ch-qr-btn)
 *  atlanir (orada zaten var). Tamamen DOM tabanli (kirilgan anchor yok).
 * KULLANIM: pull2.php?key=...&files=apply-appstore-ios.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appstore-ios BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPSTORE_IOS')!==false) exit("Zaten ekli (CH_APPSTORE_IOS).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-appstore-ios-js">
/* CH_APPSTORE_IOS — Google Play butonunun altina ayni stille iPhone butonu */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var APPLE='<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" style="vertical-align:-2px;margin-right:5px"><path d="M17.05 12.04c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.09-2.02-3.76-2.05-1.6-.16-3.12.94-3.93.94-.81 0-2.06-.92-3.39-.9-1.74.03-3.35 1.01-4.25 2.57-1.81 3.15-.46 7.81 1.3 10.37.86 1.25 1.89 2.66 3.24 2.61 1.3-.05 1.79-.84 3.36-.84 1.57 0 2.01.84 3.39.81 1.4-.03 2.28-1.28 3.14-2.54.99-1.46 1.4-2.87 1.42-2.94-.03-.01-2.72-1.05-2.75-4.16zM14.6 4.6c.71-.87 1.19-2.07 1.06-3.27-1.02.04-2.26.68-3 1.54-.66.76-1.24 1.98-1.08 3.15 1.14.09 2.31-.58 3.02-1.42z"/></svg>';
  var L={
    de:{b:"iPhone (Safari)", s:"Safari öffnen → <b>Teilen</b> ⬆️ → <b>„Zum Home‑Bildschirm"</b>"},
    tr:{b:"iPhone (Safari)", s:"Safari'yi aç → <b>Paylaş</b> ⬆️ → <b>„Ana Ekrana Ekle"</b>"},
    en:{b:"iPhone (Safari)", s:"Open Safari → <b>Share</b> ⬆️ → <b>“Add to Home Screen”</b>"},
    fr:{b:"iPhone (Safari)", s:"Safari → <b>Partager</b> ⬆️ → <b>« Sur l'écran d'accueil »</b>"},
    es:{b:"iPhone (Safari)", s:"Safari → <b>Compartir</b> ⬆️ → <b>«Añadir a inicio»</b>"},
    it:{b:"iPhone (Safari)", s:"Safari → <b>Condividi</b> ⬆️ → <b>«Aggiungi a Home»</b>"}
  };
  function run(){
    try{
      var links=document.querySelectorAll('a[href*="play.google.com/store/apps"]');
      Array.prototype.forEach.call(links,function(a){
        if(!a.parentNode) return;
        if(a.id==='ch-qr-btn') return; /* QR kutusunda zaten var */
        if(a.getAttribute('data-ch-ios')) return;
        a.setAttribute('data-ch-ios','1');
        var l=L[lang()]||L.de;
        var ios=a.cloneNode(false);
        ios.className=(a.className||'')+' ch-ios-appbtn';
        ios.removeAttribute('id'); ios.removeAttribute('target'); ios.removeAttribute('data-ch-ios');
        ios.setAttribute('href','javascript:void(0)');
        try{ ios.style.marginTop='8px'; }catch(e){}
        ios.innerHTML=APPLE+l.b;
        var steps=document.createElement('div');
        steps.style.cssText='display:none;font-size:11.5px;color:#c8c8e0;line-height:1.5;margin-top:6px;text-align:center';
        steps.innerHTML=l.s;
        ios.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); steps.style.display=(steps.style.display==='none'?'block':'none'); });
        a.parentNode.insertBefore(ios, a.nextSibling);
        a.parentNode.insertBefore(steps, ios.nextSibling);
      });
    }catch(e){}
  }
  run(); try{ setInterval(run,1500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'ai').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-appstoreios-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_APPSTORE_IOS')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_APPSTORE_IOS eklendi (".strlen($chk)." B)\n";
echo "✓ Her Google Play butonunun altina (QR haric) ayni premium stille iPhone butonu.\n";
