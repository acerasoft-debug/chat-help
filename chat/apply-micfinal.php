<?php
/**
 * ChatHelp — apply-micfinal (CH_MIC_FINAL) — mikrofonu hem web'de hem App'te GERCEKTEN calistirir.
 *   Sorun: startVoice 6 katmanda (orijinal, CH_MIC_FIX, CH_MICPRO, CH_MICPRO2, CH_MIC_APPTO,
 *   CH_MIC_WVDIRECT) ust uste override edilmis -> ongorulemez. Cozum: hepsini gecersiz kilan
 *   TEK yetkili startVoice'u </body>'den hemen once (en son) enjekte etmek.
 *
 *   Web  : canli interim STT, arayuz diline gore lang, metni #inp'e yazar.
 *   App/WV: STT denenmez -> klavye diktesine yonlendirir (+ kisa ipucu). Her zaman calisir.
 *
 *   Additive + fail-open: enjekte script hata verirse mikrofon eski haliyle kalir, site calisir.
 *   Idempotent + php -l lint + .bak + opcache + dogrulama.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-micfinal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-micfinal BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_MIC_FINAL')!==false) exit("Zaten ekli (CH_MIC_FINAL).\n");

$block=<<<'BLOCK'
<script>/*CH_MIC_FINAL — tek yetkili sesli giris; tum onceki mic katmanlarini override eder (fail-open)*/(function(){try{
  function uil(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2).toLowerCase(); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pl:'pl-PL',pt:'pt-PT',sv:'sv-SE',ar:'ar-SA',ru:'ru-RU'};
  function vlang(){ return LOC[uil()]||'de-DE'; }
  var MSG={
    denied:{de:'Mikrofon-Zugriff verweigert. Bitte in den Browser-Einstellungen erlauben.',tr:'Mikrofon izni reddedildi. Lutfen tarayici ayarlarindan izin verin.',en:'Microphone access denied. Please allow it in your browser settings.',fr:'Acces micro refuse. Autorisez-le dans les reglages du navigateur.',es:'Acceso al microfono denegado. Activelo en los ajustes del navegador.',it:'Accesso al microfono negato. Consentilo nelle impostazioni.',ar:'تم رفض الوصول إلى الميكروفون. يرجى السماح به من إعدادات المتصفح.',ru:'Доступ к микрофону запрещён. Разрешите его в настройках браузера.'},
    kbhint:{de:'🎤 Mikrofon der Tastatur benutzen',tr:'🎤 Klavyedeki mikrofon tusuna basin',en:'🎤 Use your keyboard microphone',fr:'🎤 Utilisez le micro du clavier',es:'🎤 Use el microfono del teclado',it:'🎤 Usa il microfono della tastiera',ar:'🎤 استخدم ميكروفون لوحة المفاتيح',ru:'🎤 Используйте микрофон клавиатуры'}
  };
  function M(k){ var l=uil(); return (MSG[k]&&(MSG[k][l]||MSG[k].de))||''; }
  function isWV(){
    try{ var ua=navigator.userAgent||'';
      if(ua.indexOf('; wv)')!==-1) return true;                 /* standart Android WebView */
      if(/com\.acerasoft\.chathelp/i.test(ua)) return true;     /* app ozel UA */
      if(window.chIsApp===true) return true;                    /* app kendini tanitirsa */
      return false;
    }catch(e){ return false; }
  }
  function toast(txt){
    try{ if(!txt) return; var t=document.createElement('div');
      t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483000;'
        +'background:rgba(15,22,52,.96);color:#f0e6c8;border:1px solid rgba(212,168,74,.45);border-radius:12px;'
        +'padding:10px 16px;font-size:13px;font-weight:600;max-width:86vw;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.45)');
      t.textContent=txt; document.body.appendChild(t);
      setTimeout(function(){ try{ t.remove(); }catch(e){} },4200);
    }catch(e){}
  }
  function toKeyboard(){
    try{ if(typeof window.chKbFocus==='function'){ window.chKbFocus(); }
      else { var i=document.getElementById('inp'); if(i){ i.focus(); try{ i.click(); }catch(_){} } } }catch(e){}
    toast(M('kbhint'));
  }
  var busy=false, rec=null;
  function pulse(on){ try{ var pb=document.getElementById('pb'); if(pb){ pb.style.color=on?'var(--rd,#e05050)':''; pb.style.animation=on?'pulse 1s infinite':''; } }catch(e){} }
  function reset(){ pulse(false); busy=false; rec=null; }
  function start(){
    if(busy){ try{ if(rec) rec.stop(); }catch(e){} return; }
    var inp=document.getElementById('inp');
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(isWV() || !SR){ toKeyboard(); return; }               /* App/WebView veya destek yok -> klavye */
    var base=(inp&&inp.value)?inp.value.replace(/\s+$/,''):'';
    var got='';
    try{
      rec=new SR();
      try{ rec.lang=vlang(); }catch(e){}
      rec.interimResults=true; rec.continuous=false; rec.maxAlternatives=1;
      rec.onresult=function(ev){
        var interim='';
        for(var i=ev.resultIndex;i<ev.results.length;i++){
          var tr=ev.results[i][0].transcript;
          if(ev.results[i].isFinal) got+=tr; else interim+=tr;
        }
        if(inp){
          inp.value=(base?base+' ':'')+(got+interim).replace(/^\s+/,'');
          try{ inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,160)+'px'; }catch(_){}
          try{ inp.dispatchEvent(new Event('input',{bubbles:true})); }catch(_){}
        }
      };
      rec.onerror=function(ev){
        var er=(ev&&ev.error)||''; reset();
        if(er==='not-allowed'||er==='service-not-allowed'){ toast(M('denied')); }
        else if(er==='no-speech'||er==='aborted'){ /* sessiz */ }
        else { /* App'te getUserMedia asili kalirsa */ if(isWV()) toKeyboard(); }
      };
      rec.onend=function(){ reset(); if(inp){ try{ inp.focus(); }catch(_){} } };
      busy=true; pulse(true); rec.start();
    }catch(e){ reset(); toKeyboard(); }
  }
  /* EN SON yetkili baglama — onceki tum override'lari ezer */
  window.startVoice=function(){ try{ start(); }catch(e){ try{ toKeyboard(); }catch(_){} } };
  window.chOpenMic=window.startVoice;
  try{ window.chOpenMic.__appto=1; }catch(e){}   /* CH_MIC_APPTO'nun tekrar sarmalamasini engelle */
  /* gec baglanan katmanlara karsi kisa bir kez daha sabitle (App'te klavye yolu zaten dogru) */
  setTimeout(function(){ try{ window.startVoice=window.startVoice; if(window.chOpenMic!==window.startVoice){ window.chOpenMic=window.startVoice; try{window.chOpenMic.__appto=1;}catch(_){} } }catch(e){} },1800);
}catch(e){}})();</script>
BLOCK;

$anchor='</body>';
$pos=strrpos($src,$anchor);
if($pos===false){ echo "  ✗ </body> capasi bulunamadi — DEGISTIRILMEDI.\n"; exit; }
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'mf').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-micfinal-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_MIC_FINAL')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_MIC_FINAL enjekte edildi (".strlen($chk)." B)\n";
echo "  · Web: canli STT, arayuz diline gore lang, metni #inp'e yazar.\n";
echo "  · App/WebView: dogrudan klavye diktesi + ipucu (her zaman calisir).\n";
echo "  · #pb butonu (onclick=startVoice) artik bu yetkili surumu cagirir.\n";
