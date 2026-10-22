<?php
/**
 * ChatHelp — apply-mic-app-timeout (CH_MIC_APPTO) — ACIL: mikrofon App'te
 *  "hicbir sey olmuyor". Kok neden: beginListen()'daki getUserMedia({audio:true})
 *  App WebView'inde native izin callback'i (WebChromeClient.onPermissionRequest)
 *  uygulanmamissa SESSIZCE SONSUZA KADAR ASILI KALIYOR (ne then ne catch calisir).
 *  Mikrofon ekrani "Mikrofon wird aktiviert…" durumunda donuk kalir -> kullanici
 *  hicbir sey olmadigini dusunur. Bu App tarafinda native kod degisikligi
 *  gerektirir (sunucudan tam cozulemez — daha once acikland i), AMA guvenli bir
 *  OTOMATIK KURTARMA eklenebilir: chOpenMic() sarmalanir, ~2.2sn icinde ses
 *  ALINAMAZSA (rec class hic eklenmediyse) ekran KAPANIR ve zaten var olan
 *  guvenilir klavye-dikte yontemine (window.chKbFocus) OTOMATIK gecilir.
 *  Boylece App'te "hicbir sey olmuyor" yerine ~2sn sonra HER ZAMAN klavye acilir.
 *  Sadece EKLEMELI (wrap), mevcut kod DEGISMEZ. node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-app-timeout.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-app-timeout BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_APPTO')!==false) exit("Zaten ekli (CH_MIC_APPTO).\n");
if (strpos($src,'window.chOpenMic')===false) exit("HATA: chOpenMic yok — once mikrofon modulleri gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-appto-js">
/* CH_MIC_APPTO — App WebView'de getUserMedia asilirsa OTOMATIK klavye-dikteye gec */
try{(function(){
  function fallback(){
    try{
      var h=document.getElementById('ch-mic2');
      if(h) h.classList.remove('on');
      try{ if(typeof window.chKbFocus==='function') window.chKbFocus();
           else { var i=document.getElementById('inp'); if(i){ i.focus(); i.click(); } } }catch(e){}
    }catch(e){}
  }
  function wrap(){
    try{
      if(typeof window.chOpenMic!=='function' || window.chOpenMic.__appto) return false;
      var _o=window.chOpenMic;
      var w=function(){
        var r; try{ r=_o.apply(this,arguments); }catch(e){}
        try{
          setTimeout(function(){
            try{
              var got=document.querySelector('#ch-mic2 .cm2-mic.rec');
              var open=document.querySelector('#ch-mic2.on');
              if(open && !got){ fallback(); }   /* 2.2sn'de hala ses alinamadiysa -> App'te asili kalmis demektir */
            }catch(e){}
          }, 2200);
        }catch(e){}
        return r;
      };
      w.__appto=1; window.chOpenMic=w;
      /* startVoice zaten chOpenMic'i cagiriyorsa otomatik kapsanir; ayrica dogrudan da sarmalayalim */
      if(typeof window.startVoice==='function' && !window.startVoice.__appto){
        var _sv=window.startVoice;
        var wsv=function(){ return _sv.apply(this,arguments); };
        wsv.__appto=1; window.startVoice=wsv;
      }
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ if(!wrap() && n++<200) setTimeout(loop,300); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'mt').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micappto-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_APPTO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_APPTO diskte (".strlen($chk)." bayt)\n";
echo "\n✓ App'te mikrofon asilirsa artik ~2.2sn sonra OTOMATIK klavye-dikte moduna gecer.\n";
echo "   Not: Bu bir GECICI ONLEM — App'te GERCEK web-mikrofon icin native kod\n";
echo "   (WebChromeClient.onPermissionRequest + RECORD_AUDIO izni) gerekir; bu sunucudan yapilamaz.\n";
