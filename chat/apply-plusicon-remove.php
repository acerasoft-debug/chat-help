<?php
/**
 * ChatHelp — apply-plusicon-remove (CH_PLUSICON_REMOVE)
 *  Ana chat composer'daki eski "+" (.cha2-plus) gereksiz: premium 📎 ataç
 *  (CH_COMPOSER_PREMIUM) artik ekleme isini yapiyor. Sadece composer icindeki
 *  "+" ve onun mini-cip seridi gizlenir. Fall/form yukleme alanlari (.cha2-doc)
 *  DOKUNULMAZ. EKLEMELI (style+kucuk script), byte-exact insert.
 * KULLANIM: pull2.php?key=...&files=apply-plusicon-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plusicon-remove BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PLUSICON_REMOVE')!==false) exit("Zaten ekli (CH_PLUSICON_REMOVE).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-plusicon-remove-css">
/* CH_PLUSICON_REMOVE — ana chat composer'daki eski "+" gereksiz (premium 📎 var) */
#ia .ia-btns .cha2-plus,
#ia .ia-inner .cha2-plus,
#ia .ia-inner .cha2-mini{display:none !important}
</style>
<script id="ch-plusicon-remove-js">
/* CH_PLUSICON_REMOVE — composer'daki eski "+" DOM'dan da kaldir (idempotent) */
try{(function(){
  function kill(){
    try{
      var ia=document.getElementById('ia'); if(!ia) return;
      ia.querySelectorAll('.cha2-plus,.ia-inner .cha2-mini').forEach(function(el){
        try{ el.remove(); }catch(e){ try{ el.style.display='none'; }catch(_){} }
      });
    }catch(e){}
  }
  var n=0; (function loop(){ kill(); if(n++<120) setTimeout(loop,600); })();
  try{ if(window.MutationObserver){ var _ia=document.getElementById('ia'); new MutationObserver(kill).observe(_ia||document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'pi').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-plusicon-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PLUSICON_REMOVE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PLUSICON_REMOVE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Composer'daki eski '+' kaldirildi; premium 📎 ataç kaldi. Fall/form dokunulmadi.\n";
