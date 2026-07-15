<?php
/**
 * ChatHelp — apply-mic-smart (CH_MICSMART) — mikrofon akilli yonlendirme.
 *  Web mikrofonu (getUserMedia) calisiyorsa -> premium dalga ekrani (chOpenMic).
 *  Reddedilirse (WebView/izin yok) -> OTOMATIK klavye diktesi (chKeyboardDictate:
 *  yazi alani + klavye mikrofonu). Boylece mic butonu her ortamda "calisir".
 *  Izin zaten verildiyse ikinci istek prompt gostermez (cift-prompt yok).
 *  window.startVoice override. MutationObserver YOK (freeze-safe). node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-smart.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-smart BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICSMART')!==false) exit("Zaten ekli (CH_MICSMART).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-smart">
/* CH_MICSMART — web mic varsa premium ekran, yoksa otomatik klavye diktesi */
try{(function(){
  function fallback(){ try{ if(typeof window.chKeyboardDictate==='function') window.chKeyboardDictate(); else { var i=document.getElementById('inp'); if(i){ i.focus(); i.click(); } } }catch(e){} }
  window.startVoice=function(){
    try{
      var md=(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia)?navigator.mediaDevices:null;
      if(!md){ fallback(); return; }
      md.getUserMedia({audio:true}).then(function(s){
        try{ s.getTracks().forEach(function(t){ t.stop(); }); }catch(e){}
        if(typeof window.chOpenMic==='function') window.chOpenMic(); else fallback();
      }).catch(function(){ fallback(); });
    }catch(e){ fallback(); }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ms').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micsmart-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICSMART')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICSMART diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mic butonu: web mic varsa premium ekran, yoksa otomatik klavye diktesi.\n";
