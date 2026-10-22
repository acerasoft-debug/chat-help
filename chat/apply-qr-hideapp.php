<?php
/**
 * ChatHelp — apply-qr-hideapp (CH_QR_HIDEAPP) — QR'i Android uygulamasi ICINDE gizle.
 *  Uygulama (TWA / WebView / standalone-Android) icindeyken kullanici zaten
 *  uygulamada; Google Play QR'i gereksiz. EKLEMELI script: uygulama icindeyse
 *  #ch-qr-wrap gizlenir. Normal mobil/masaustu tarayicida QR gorunur kalir.
 * KULLANIM: pull2.php?key=...&files=apply-qr-hideapp.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-qr-hideapp BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_QR_HIDEAPP')!==false) exit("Zaten ekli (CH_QR_HIDEAPP).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-qr-pos">/* CH_QR_HIDEAPP — QR pilini biraz asagi al */
#ch-qr-wrap{bottom:52px !important}
@media(max-width:520px){#ch-qr-wrap{bottom:60px !important}}
</style>
<script id="ch-qr-hideapp">
/* CH_QR_HIDEAPP — Google Play QR'i Android uygulamasi (TWA/WebView) icinde gizle */
try{(function(){
  function inApp(){
    try{
      var ua=navigator.userAgent||'', ref=document.referrer||'';
      if(ref.indexOf('android-app://')===0) return true;               /* Trusted Web Activity */
      if(/\bwv\b/.test(ua)) return true;                               /* Android WebView */
      if(/com\.acerasoft\.chathelp/i.test(ua)) return true;            /* ozel UA */
      var sa=(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches)||window.navigator.standalone;
      if(sa && /Android/i.test(ua)) return true;                       /* kurulu app-benzeri */
      return false;
    }catch(e){ return false; }
  }
  if(!inApp()) return;
  function hide(){ try{ var w=document.getElementById('ch-qr-wrap'); if(w) w.style.display='none'; }catch(e){} }
  var n=0; (function loop(){ hide(); if(n++<40) setTimeout(loop,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'qh').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-qrhide-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_QR_HIDEAPP')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_QR_HIDEAPP diskte (".strlen($chk)." bayt)\n";
echo "\n✓ QR artik Android uygulamasi icinde gizli; tarayicida gorunur.\n";
