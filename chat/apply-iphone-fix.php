<?php
/**
 * ChatHelp — apply-iphone-fix (CH_APPSTORE_IOS2) — iPhone butonu GORUNMUYORDU:
 *  eski CH_APPSTORE_IOS, QR kutusundaki Google Play butonunu (#ch-qr-btn)
 *  atliyordu; CH_QR_IOS de panele ekleyemiyordu. Bu yama:
 *   [1] eski CH_QR_IOS scriptini devre disi birakir (cift ekleme yok).
 *   [2] CH_APPSTORE_IOS scriptini, HER Google Play linkinin (QR DAHIL) tam
 *       altina birebir ayni stille iPhone butonu koyan surumle degistirir.
 * KULLANIM: pull2.php?key=...&files=apply-iphone-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-iphone-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPSTORE_IOS2')!==false) exit("Zaten ekli (CH_APPSTORE_IOS2).\n");

/* [1] eski CH_QR_IOS devre disi */
$patQ='~<script id="ch-qr-ios-js">.*?</script>~s';
if(preg_match($patQ,$src)===1){ $src=preg_replace($patQ,'<script id="ch-qr-ios-js">/*CH_QR_IOS devre disi (CH_APPSTORE_IOS2)*/</script>',$src,1); echo "  ✓ [1] eski CH_QR_IOS devre disi\n"; }
else echo "  ! [1] ch-qr-ios-js bulunamadi (atlandi)\n";

/* [2] CH_APPSTORE_IOS -> IOS2 */
$new = <<<'JSBLOCK'
<script id="ch-appstore-ios-js">
/* CH_APPSTORE_IOS2 — iPhone butonu HER Google Play linkinin (QR dahil) altina */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var APPLE='<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" style="vertical-align:-2px;margin-right:5px"><path d="M17.05 12.04c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.09-2.02-3.76-2.05-1.6-.16-3.12.94-3.93.94-.81 0-2.06-.92-3.39-.9-1.74.03-3.35 1.01-4.25 2.57-1.81 3.15-.46 7.81 1.3 10.37.86 1.25 1.89 2.66 3.24 2.61 1.3-.05 1.79-.84 3.36-.84 1.57 0 2.01.84 3.39.81 1.4-.03 2.28-1.28 3.14-2.54.99-1.46 1.4-2.87 1.42-2.94-.03-.01-2.72-1.05-2.75-4.16zM14.6 4.6c.71-.87 1.19-2.07 1.06-3.27-1.02.04-2.26.68-3 1.54-.66.76-1.24 1.98-1.08 3.15 1.14.09 2.31-.58 3.02-1.42z"/></svg>';
  var L={
    de:{b:"iPhone (Safari)", s:"Safari öffnen → <b>Teilen</b> ⬆️ → <b>„Zum Home‑Bildschirm"</b>"},
    tr:{b:"iPhone (Safari)", s:"Safari'yi aç → <b>Paylaş</b> ⬆️ → <b>„Ana Ekrana Ekle"</b>"},
    en:{b:"iPhone (Safari)", s:"Open Safari → <b>Share</b> ⬆️ → <b>“Add to Home Screen”</b>"},
    fr:{b:"iPhone (Safari)", s:"Safari → <b>Partager</b> ⬆️ → <b>« Sur l'écran d'accueil »</b>"},
    es:{b:"iPhone (Safari)", s:"Safari → <b>Compartir</b> ⬆️ → <b>«Añadir a inicio»</b>"},
    it:{b:"iPhone (Safari)", s:"Safari → <b>Condividi</b> ⬆️ → <b>«Aggiungi a Home»</b>"}
  };
  function mkBtn(a){
    try{
      if(!a||!a.parentNode) return;
      if(a.getAttribute('data-ch-iph')) return;
      a.setAttribute('data-ch-iph','1');
      var l=L[lang()]||L.de;
      var ios=a.cloneNode(false);
      ios.className=(a.className||'')+' ch-iph-btn';
      ios.removeAttribute('id'); ios.removeAttribute('target');
      ios.setAttribute('href','javascript:void(0)');
      try{ ios.style.marginTop='8px'; ios.style.display='block'; }catch(e){}
      ios.innerHTML=APPLE+l.b;
      var steps=document.createElement('div');
      steps.style.cssText='display:none;font-size:11.5px;color:#c8c8e0;line-height:1.5;margin-top:6px;text-align:center';
      steps.innerHTML=l.s;
      ios.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); steps.style.display=(steps.style.display==='none'?'block':'none'); });
      a.parentNode.insertBefore(ios, a.nextSibling);
      a.parentNode.insertBefore(steps, ios.nextSibling);
    }catch(e){}
  }
  function run(){
    try{
      var oldw=document.getElementById('ch-qr-ios-wrap'); if(oldw) oldw.remove();
      var links=document.querySelectorAll('a[href*="play.google.com/store/apps"]');
      Array.prototype.forEach.call(links, mkBtn);
    }catch(e){}
  }
  run(); try{ setInterval(run,1200); }catch(e){}
})();}catch(e){}
</script>
JSBLOCK;
$patA='~<script id="ch-appstore-ios-js">.*?</script>~s';
if(preg_match($patA,$src)!==1){ echo "  ✗ [2] ch-appstore-ios-js bulunamadi — DEGISTIRILMEDI\n"; exit; }
$src=preg_replace($patA,$new,$src,1);
echo "  ✓ [2] CH_APPSTORE_IOS2: iPhone butonu Google Play (QR dahil) altina\n";

$tmp=tempnam(sys_get_temp_dir(),'if').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-iphonefix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_APPSTORE_IOS2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_APPSTORE_IOS2 diskte (".strlen($chk)." B)\n";
echo "✓ QR kutusunu ac -> 'Google Play →' butonunun TAM ALTINDA iPhone (Safari) butonu.\n";
