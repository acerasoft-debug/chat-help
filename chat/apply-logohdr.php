<?php
/**
 * ChatHelp — apply-logohdr (CH_LOGO_HDR) — header/menu markasini CHelp wordmark logosuyla
 *  degistirir. .sb-logo (menu head + ust marka) icerigi -> ch-word.png (temiz wordmark).
 *  JS-tabanli (HTML byte riski yok), idempotent, .sb-logo onclick=NC() korunur.
 * KULLANIM: pull2.php?key=...&files=apply-logohdr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-logohdr BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_LOGO_HDR')!==false) exit("Zaten ekli (CH_LOGO_HDR).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-logohdr-css">
.sb-logo img.ch-wordmark{height:24px!important;width:auto!important;border-radius:0!important;display:block}
.sb-logo{gap:8px!important}
</style>
<script id="ch-logohdr-js">
/* CH_LOGO_HDR — header/menu markasi -> CHelp wordmark logosu */
try{(function(){
  function swap(){
    try{
      var els=document.querySelectorAll('.sb-logo');
      for(var i=0;i<els.length;i++){
        var el=els[i]; if(el.__chw) continue; el.__chw=1;
        el.innerHTML='<img class="ch-wordmark" src="/chat/ch-word.png?a7" alt="CHelp">';
      }
    }catch(e){}
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',swap); else swap();
  try{ setInterval(swap,1500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'lh').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-logohdr-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_LOGO_HDR')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_LOGO_HDR diskte (".strlen($chk)." B)\n";
echo "  Header/menu markasi artik CHelp wordmark logosu. App'i tam kapat-ac (SW cache).\n";
echo "  Ust topbar'da ayri bir 'SohbetYardim' yazisi kalirsa soyle — onun selektorunu de eklerim.\n";
