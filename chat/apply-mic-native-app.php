<?php
/**
 * ChatHelp — apply-mic-native-app (CH_MIC_NATIVEAPP) — App'te GERÇEK mikrofon.
 *
 *  Sorun: WebView'de webkitSpeechRecognition çalışmaz (WebView'in konuşma servisi
 *  yoktur), bu yüzden App'te mikrofon şimdiye kadar klavye-diktesine düşüyordu
 *  (CH_MIC_WVDIRECT). Yeni App sürümü (v6+) native bir köprü sunar:
 *      window.ChelpNative.chStartVoice(langTag)  -> Android yerel Spracherkennung
 *      (RecognizerIntent, Google STT — klavye mikrofonuyla aynı motor, çok dilli)
 *  ve tanınan metni  window.chVoiceResult(text)  ile geri verir.
 *
 *  Bu script:
 *   1) window.chVoiceResult(text)  -> tanınan metni #inp yazı kutusuna koyar
 *      (input event tetikler: gönder butonu aktifleşir, kutu yeniden boyutlanır).
 *   2) window.__chNativeVoice()    -> mevcut arayüz diline göre native tanımayı başlatır.
 *   3) chOpenMic/startVoice'ı geç sarar: KÖPRÜ VARSA (yani yeni App'te) mikrofon
 *      her zaman native tanımayı açar; KÖPRÜ YOKSA (tarayıcı VEYA eski App) hiçbir
 *      şey değişmez — premium mic / klavye-dikte aynen çalışır. Sürüm-güvenli.
 *   4) (opsiyonel) CH_MIC_WVDIRECT kb() içine native-önce satırı ekler (çift emniyet).
 *
 *  Sadece EKLEMELI. Tarayıcıda (köprü yok) DAVRANIŞ AYNEN KORUNUR. php -l ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-native-app.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-native-app BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_NATIVEAPP')!==false) exit("Zaten ekli (CH_MIC_NATIVEAPP).\n");
if (strpos($src,'window.chOpenMic')===false) exit("HATA: chOpenMic yok — once mikrofon modulleri gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-nativeapp-js">
/* CH_MIC_NATIVEAPP — App WebView: gercek mikrofon = yerel Google Spracherkennung
   (native ChelpNative.chStartVoice). webkitSpeechRecognition WebView'de calismaz;
   bu kopru taninan metni window.chVoiceResult ile yazi kutusuna koyar.
   Tarayicida kopru olmadigi icin HICBIR SEY degismez (premium mic aynen). */
try{(function(){
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pl:'pl-PL',ru:'ru-RU',ar:'ar-SA',pt:'pt-PT',uk:'uk-UA',ro:'ro-RO',el:'el-GR'};
  function loc(){ var l='de'; try{ l=(localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){} return LOC[l]||'de-DE'; }
  function hasNative(){ try{ return !!(window.ChelpNative&&typeof window.ChelpNative.chStartVoice==='function'); }catch(e){ return false; } }
  /* native tanima sonucu -> yazi kutusu */
  window.chVoiceResult=function(text){
    try{
      text=(text==null?'':(''+text)).trim(); if(!text) return;
      var i=document.getElementById('inp'); if(!i) return;
      try{ i.readOnly=false; i.removeAttribute('readonly'); }catch(e){}
      var base=(i.value||'').replace(/\s+$/,'');
      i.value=(base?base+' ':'')+text;
      try{ i.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){}
      try{ i.focus(); var l2=i.value.length; i.setSelectionRange(l2,l2); }catch(e){}
      try{ i.style.height='auto'; i.style.height=Math.min(i.scrollHeight,160)+'px'; }catch(e){}
    }catch(e){}
  };
  /* mikrofon -> native tanima baslat (App'te). Kopru yoksa false doner. */
  window.__chNativeVoice=function(){
    try{ if(hasNative()){ window.ChelpNative.chStartVoice(loc()); return true; } }catch(e){}
    return false;
  };
  /* chOpenMic/startVoice'i gec sar: kopru VARSA her zaman native tanima;
     kopru YOKSA (tarayici/eski App) orijinal davranis aynen. Surum-guvenli. */
  function wrap(){
    try{
      if(typeof window.chOpenMic!=='function') return false;
      if(window.chOpenMic.__nativeapp) return true;
      var _p=window.chOpenMic;
      var w=function(){ if(hasNative()){ window.__chNativeVoice(); return; } return _p.apply(this,arguments); };
      w.__nativeapp=1; try{ if(_p.__final) w.__final=1; }catch(e){}
      window.chOpenMic=w; try{ window.startVoice=w; }catch(e){}
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ wrap(); if(n++<240) setTimeout(loop,300); })();
})();}catch(e){}
</script>
HTMLBLOCK;

// --- 1) Ana blogu </body> oncesine ekle ---
$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

// --- 2) (opsiyonel, cift emniyet) CH_MIC_WVDIRECT kb() -> native once ---
$kbAdded = false;
$kbPattern = '/(function kb\(\)\{)(\s*try\{ if\(typeof window\.chKbFocus)/';
if (preg_match($kbPattern, $new)) {
  $inject = '$1'
    . "\n    try{ if(window.ChelpNative&&typeof window.ChelpNative.chStartVoice==='function'){ if(window.__chNativeVoice){ window.__chNativeVoice(); return; } } }catch(e){} /* CH_MIC_NATIVEAPP */"
    . '$2';
  $new2 = preg_replace($kbPattern, $inject, $new, 1);
  if ($new2 && $new2 !== $new) { $new = $new2; $kbAdded = true; }
}

$tmp = tempnam(sys_get_temp_dir(),'na').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-nativeapp-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_NATIVEAPP')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }

echo "  ✓ DOGRULAMA: CH_MIC_NATIVEAPP diskte (".strlen($chk)." bayt)\n";
echo "  ✓ chVoiceResult + __chNativeVoice + chOpenMic gec-sarma eklendi\n";
echo "  ".($kbAdded ? "✓" : "•")." CH_MIC_WVDIRECT kb() native-once yamasi: ".($kbAdded?"eklendi":"anchor yok, atlandi (ana sarma yeterli)")."\n";
echo "\n✓ Yeni App'te (v6+) mikrofon artik GERCEK yerel Spracherkennung acar,\n";
echo "   taninan metin dogrudan yazi kutusuna gelir. Tarayicida premium mic aynen.\n";
echo "   NOT: Bu ancak native kopruyu iceren yeni AAB (versionCode 6) yuklendiginde devreye girer.\n";
