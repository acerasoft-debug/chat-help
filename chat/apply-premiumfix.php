<?php
/**
 * ChatHelp — apply-premiumfix — denetim (dump-audit) sonrasi 3'lu premium paket:
 *  [1] CH_TOAST_V1   — window.alert() -> zarif toast bildirim (105 alert tek noktadan
 *                      premium olur; bloklayici sistem kutusu tamamen gider).
 *  [2] CH_TIMER_TAME — sekme/App arka plandayken TUM setInterval callback'leri uyur
 *                      (116 interval pil/CPU yemez; one gelince kaldigi yerden devam).
 *  [3] CH_POLISH_V2  — genel cila: yumusak buton gecisleri, premium ince scrollbar,
 *                      secim rengi, focus halkasi, tap-highlight temizligi.
 *  Her bolum bagimsiz + marker korumali.
 * KULLANIM: pull2.php?key=...&files=apply-premiumfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-premiumfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$ok=0;

/* ── [1] alert -> premium toast ── */
if(strpos($src,'CH_TOAST_V1')===false){
  $b1 = <<<'HTMLBLOCK'
<style id="ch-toast-css">
/* CH_TOAST_V1 — premium toast bildirimleri */
#ch-toasts{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:2147483000;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none;width:min(92vw,480px)}
.ch-toast{pointer-events:auto;display:flex;gap:10px;align-items:flex-start;width:100%;box-sizing:border-box;background:rgba(18,20,30,.92);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:12px 16px;color:#eef1f8;font-size:13.5px;line-height:1.45;box-shadow:0 12px 40px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.03) inset;animation:chToastIn .28s cubic-bezier(.2,.9,.3,1.2);max-height:40vh;overflow-y:auto}
.ch-toast.out{animation:chToastOut .25s ease forwards}
.ch-toast .tico{flex:0 0 auto;font-size:16px;margin-top:1px}
.ch-toast .tmsg{flex:1 1 auto;white-space:pre-wrap;word-break:break-word}
.ch-toast .tx{flex:0 0 auto;background:none;border:0;color:rgba(255,255,255,.45);font-size:15px;cursor:pointer;padding:0 2px;line-height:1}
.ch-toast .tx:hover{color:#fff}
@keyframes chToastIn{from{opacity:0;transform:translateY(-14px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes chToastOut{to{opacity:0;transform:translateY(-10px) scale(.97)}}
</style>
<script id="ch-toast-js">
/* CH_TOAST_V1 — window.alert -> toast (bloklamaz, zarif) */
try{(function(){
  if(window.__chToastOn) return; window.__chToastOn=true;
  window.chNativeAlert=window.alert;
  function box(){ var c=document.getElementById('ch-toasts');
    if(!c){ c=document.createElement('div'); c.id='ch-toasts'; (document.body||document.documentElement).appendChild(c); } return c; }
  window.chToast=function(msg,ms){
    try{
      var c=box(); while(c.children.length>=3){ c.removeChild(c.firstChild); }
      var t=document.createElement('div'); t.className='ch-toast';
      var s=String(msg==null?'':msg);
      var icon='ℹ️'; if(/[✓✔]|erfolg|başarı|success|gesendet|gespeichert/i.test(s)) icon='✅';
      else if(/[✗✖⚠]|fehler|hata|error|ungültig|fehlgeschlagen/i.test(s)) icon='⚠️';
      t.innerHTML='<span class="tico"></span><span class="tmsg"></span><button class="tx" type="button">×</button>';
      t.firstChild.textContent=icon; t.children[1].textContent=s;
      t.lastChild.onclick=function(){ try{ t.classList.add('out'); setTimeout(function(){ t.remove(); },240);}catch(e){} };
      c.appendChild(t);
      var dur=ms|| (s.length>140? 8000 : 4500);
      setTimeout(function(){ try{ if(t.parentNode){ t.classList.add('out'); setTimeout(function(){ t.remove(); },240);} }catch(e){} },dur);
    }catch(e){ try{ window.chNativeAlert(msg); }catch(e2){} }
  };
  window.alert=function(m){ window.chToast(m); };
})();}catch(e){}
</script>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$b1."\n".substr($src,$pos); echo "  ✓ [1] alert() -> premium toast (105 alert tek noktadan)\n"; $ok++; }
  else echo "  ✗ [1] </body> yok\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] arka planda interval uyutma — <head> basina (tum kayitlari yakalasin) ── */
if(strpos($src,'CH_TIMER_TAME')===false){
  $b2="<script id=\"ch-timertame\">/*CH_TIMER_TAME — arka planda interval'ler uyur (pil/CPU)*/try{(function(){var _si=window.setInterval;window.setInterval=function(fn,ms){if(typeof fn!=='function')return _si.apply(window,arguments);var a=[].slice.call(arguments,2);return _si(function(){if(document.hidden)return;try{fn.apply(window,a);}catch(e){}},ms);};})();}catch(e){}</script>";
  $p=stripos($src,'<head');
  $gt=($p!==false)?strpos($src,'>',$p):false;
  if($gt!==false){ $src=substr($src,0,$gt+1)."\n".$b2.substr($src,$gt+1); echo "  ✓ [2] TIMER_TAME: arka planda 116 interval uyur\n"; $ok++; }
  else echo "  ✗ [2] <head> yok\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] genel premium cila CSS ── */
if(strpos($src,'CH_POLISH_V2')===false){
  $b3 = <<<'HTMLBLOCK'
<style id="ch-polish2-css">
/* CH_POLISH_V2 — genel premium cila */
*{-webkit-tap-highlight-color:transparent}
button,.act,a.act{transition:filter .18s ease,transform .12s ease,box-shadow .18s ease,background .2s ease}
button:not(:disabled):active{transform:scale(.975)}
::selection{background:rgba(63,224,138,.28);color:inherit}
:focus-visible{outline:2px solid rgba(110,160,255,.55);outline-offset:2px;border-radius:4px}
::-webkit-scrollbar{width:9px;height:9px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.13);border-radius:100px;border:2px solid transparent;background-clip:padding-box}
::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.22);border:2px solid transparent;background-clip:padding-box}
img{image-rendering:auto}
</style>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$b3."\n".substr($src,$pos); echo "  ✓ [3] premium cila CSS (gecisler, scrollbar, focus, selection)\n"; $ok++; }
  else echo "  ✗ [3] </body> yok\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-premiumfix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: 1) herhangi bir hata/basari mesaji artik ustte zarif toast olarak cikar,\n";
echo "        2) sekme arka plandayken CPU sakin, 3) butonlarda yumusak bas-cek hissi.\n";
