<?php
/**
 * ChatHelp — apply-micoff-app (CH_MICOFF_APP) — mikrofon App'te (Capacitor WebView)
 *  guvenilir calismiyor (Web Speech API WebView'de yok) — KARAR: App'te mikrofon
 *  TAMAMEN KALDIRILIR. Normal tarayici/sitede mikrofon AYNEN calismaya devam eder.
 *  Additive </body> blogu: WebView tespitinde <html>'e sinif eklenir; tum ch-mic*
 *  buton/ikonlari CSS ile gizlenir + JS supurgesi gec-enjekte edilenleri de gizler.
 * KULLANIM: pull2.php?key=...&files=apply-micoff-app.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-micoff-app BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_MICOFF_APP')!==false) exit("Zaten ekli (CH_MICOFF_APP).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-micoff-css">
/* CH_MICOFF_APP — App'te mikrofon tamamen gizli (site etkilenmez) */
html.ch-noapp-mic button[id^="ch-mic"],html.ch-noapp-mic div[id^="ch-mic"]:not([id$="-css"]):not([id$="-js"]),html.ch-noapp-mic span[id^="ch-mic"],html.ch-noapp-mic [data-chmic],html.ch-noapp-mic .ch-micbtn{display:none!important;visibility:hidden!important;width:0!important;min-width:0!important;padding:0!important;margin:0!important;border:0!important;overflow:hidden!important}
</style>
<script id="ch-micoff-js">
/* CH_MICOFF_APP — WebView tespiti + mikrofon supurgesi */
try{(function(){
  function wv(){
    try{ if(typeof isWV==='function') return !!isWV(); }catch(e){}
    try{ if(window.chIsApp) return true; }catch(e){}
    try{ var u=navigator.userAgent||''; return /\bwv\b|Median|Capacitor|; ?Android.*Version\/[\d.]+ Chrome\/[\d.]+ Mobile/.test(u) && !window.chrome_pwa; }catch(e){ return false; }
  }
  if(!wv()) return;
  try{ document.documentElement.classList.add('ch-noapp-mic'); }catch(e){}
  function sweep(){
    try{
      var els=document.querySelectorAll('button[id^="ch-mic"],span[id^="ch-mic"],.ch-micbtn,[data-chmic]');
      for(var i=0;i<els.length;i++){ try{ els[i].style.setProperty('display','none','important'); }catch(e){} }
      var dv=document.querySelectorAll('div[id^="ch-mic"]');
      for(var j=0;j<dv.length;j++){ var id=dv[j].id||''; if(/-css$|-js$/.test(id)) continue; try{ dv[j].style.setProperty('display','none','important'); }catch(e){} }
    }catch(e){}
  }
  sweep();
  try{ setInterval(sweep,900); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'mo').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micoff-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_MICOFF_APP')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_MICOFF_APP diskte (".strlen($chk)." B)\n";
echo "  ETKI: App'te mikrofon butonu HIC GORUNMEZ (klavyedeki dikte her zaman kullanilabilir).\n";
echo "  Sitede/tarayicida mikrofon AYNEN calisir.\n";
echo "  Test: App ac -> chat giris alani -> mikrofon ikonu YOK; siteyi tarayicida ac -> VAR.\n";
