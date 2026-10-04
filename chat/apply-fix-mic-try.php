<?php
/**
 * ChatHelp — apply-fix-mic-try (CH_MIC_TRY) — App mikrofonu: "panel cikiyor
 *  ama ben dogrudan konusmak istiyorum". Onceki CH_MIC_TRACE App WebView'de
 *  ses tanimayi HIC DENEMEDEN dogrudan sesli-yazim paneline dusuyordu.
 *  Oysa App'in RECORD_AUDIO izni varsa webkitSpeechRecognition DOGRUDAN
 *  calisabilir (getUserMedia'dan farkli — Android konusma servisini kullanir).
 *  YENI (sonuc-bazli, watchdog'lu): HER yerde (App dahil) ONCE SpeechRecognition
 *  denenir; onstart/onresult gelirse -> dogrudan yaziya doker (sitedeki gibi).
 *  Yalniz (a) SR yoksa, (b) onerror (izin/servis yok), (c) 2.5sn icinde HIC
 *  olay gelmezse (donma) -> OTOMATIK sesli-yazim paneline dusulur. Boylece
 *  App'te mikrofon calisabiliyorsa panel HIC gorunmez; calismiyorsa panel
 *  (klavye mikrofonu) yedek kalir. En son katman: startVoice/chOpenMic buna
 *  baglanir, guard bayraklari (__final+__appkb+__try) tasir. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fix-mic-try.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-mic-try BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_TRY')!==false) exit("Zaten ekli (CH_MIC_TRY).\n");
if (strpos($src,'CH_MIC_TRACE')===false) exit("HATA: once CH_MIC_TRACE gerekli (apply-fix-mic-final.php).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-try-js">
/* CH_MIC_TRY — HER yerde once SpeechRecognition dene (watchdog'lu), olmazsa panele dus */
try{(function(){
  function DBG(){ try{ return (location.search.indexOf('micdebug=1')!==-1)||localStorage.getItem('ch_micdebug')==='1'; }catch(e){ return false; } }
  function trace(m){ if(!DBG()) return; try{ var b=document.getElementById('mtr-log'); if(!b){ b=document.createElement('div'); b.id='mtr-log'; b.className='on'; document.body.appendChild(b); } b.textContent=(b.textContent+'\n'+m).split('\n').slice(-8).join('\n'); }catch(e){} }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pt:'pt-PT',sv:'sv-SE',pl:'pl-PL',ru:'ru-RU',ar:'ar-SA'};
  function inp(){ return document.getElementById('inp'); }
  function glow(on){ try{ var b=document.getElementById('pb'); if(b){ b.style.color=on?'#e05050':''; b.style.animation=on?'pulse 1s infinite':''; } }catch(e){} }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:99999;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3500); }catch(e){} }
  /* mevcut CH_MIC_TRACE panelini yeniden kullan (varsa) */
  function openPanel(reason){
    trace('panel('+reason+')');
    var ov=document.getElementById('mkbx-ov');
    if(ov){ ov.classList.add('on'); try{ var ta=document.getElementById('mkbx-ta'); if(ta) ta.focus(); }catch(e){} return; }
    /* CH_MIC_TRACE yuklenmediyse: input'a odaklan (klavye) */
    try{ var i=inp(); if(i){ i.focus(); i.click(); } toast('🎤 '+({de:'Nutzen Sie das Mikrofon Ihrer Tastatur.',tr:'Klavyenizdeki mikrofon tuşunu kullanın.',en:'Use the microphone on your keyboard.'}[lang()]||'Use your keyboard mic.')); }catch(e){}
  }
  var R=null;
  function stopSR(){ try{ if(R){ R.onend=null;R.onresult=null;R.onerror=null;R.onstart=null; R.stop(); } }catch(e){} R=null; glow(false); }
  function tryVoice(){
    if(R){ stopSR(); return; }  /* dinlerken tekrar bas -> durdur */
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){ openPanel('no-SR'); return; }
    var settled=false, wd=null;
    function toPanel(reason){ if(settled) return; settled=true; try{ clearTimeout(wd); }catch(e){} try{ stopSR(); }catch(e){} openPanel(reason); }
    try{
      var i=inp(); var base=(i&&i.value)?i.value.replace(/\s+$/,''):''; var fin='';
      var r=new SR(); try{ r.lang=LOC[lang()]||'de-DE'; }catch(e){}
      r.interimResults=true; r.continuous=false; r.maxAlternatives=1;
      r.onstart=function(){ settled=true; try{ clearTimeout(wd); }catch(e){} trace('SR onstart (dogrudan)'); glow(true); };
      r.onresult=function(ev){ settled=true; try{ clearTimeout(wd); }catch(e){} try{ var it=''; for(var k=ev.resultIndex;k<ev.results.length;k++){ var rr=ev.results[k]; if(rr.isFinal) fin+=(fin&&!/\s$/.test(fin)?' ':'')+rr[0].transcript.trim(); else it+=rr[0].transcript; } var i2=inp(); if(i2){ i2.value=((base?base+' ':'')+fin+(it?' '+it:'')).replace(/\s+/g,' ').trim(); try{ if(typeof onInp==='function') onInp(i2); }catch(e){} } }catch(e){} };
      r.onerror=function(ev){ var er=(ev&&ev.error)||''; trace('SR onerror: '+er);
        if(er==='no-speech'){ settled=true; try{ clearTimeout(wd); }catch(e){} stopSR(); toast('🎤 '+({de:'Keine Sprache erkannt.',tr:'Ses algılanamadı.',en:'No speech detected.'}[lang()]||'No speech.')); return; }
        toPanel('sr-'+er);
      };
      r.onend=function(){ trace('SR onend'); if(!settled){ toPanel('end-no-start'); return; } stopSR(); };
      R=r; r.start(); trace('SR.start()');
      wd=setTimeout(function(){ trace('SR watchdog 2.5s -> panel'); toPanel('watchdog'); }, 2500);
    }catch(e){ trace('SR exception'); toPanel('exception'); }
  }
  tryVoice.__final=1; tryVoice.__appkb=1; tryVoice.__try=1;
  function install(){ try{ window.startVoice=tryVoice; window.chOpenMic=tryVoice; }catch(e){} }
  install();
  (function bindBtn(){ var n=0;(function loop(){ try{ var b=document.getElementById('pb'); if(b && !b.__mtry){ b.__mtry=1; b.addEventListener('click',function(ev){ try{ ev.stopImmediatePropagation(); }catch(e){} trace('#pb click -> tryVoice'); tryVoice(); },true); } }catch(e){} if(n++<300) setTimeout(loop,400); })(); })();
  var g=0;(function guard(){ try{ if(!(window.startVoice&&window.startVoice.__try)) install(); }catch(e){} if(g++<200) setTimeout(guard,500); })();
  trace('CH_MIC_TRY kuruldu');
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'my').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-mictry-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_TRY')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_TRY diskte (".strlen($chk)." bayt)\n";
echo "\n✓ App'te mikrofon artik ONCE dogrudan ses tanimayi dener:\n";
echo "   • Calisirsa (App'in mikrofon izni varsa) -> sitedeki gibi dogrudan\n";
echo "     konusma yaziya doker, PANEL HIC GORUNMEZ.\n";
echo "   • Izin yok / servis yok / 2.5sn donma -> otomatik sesli-yazim paneli\n";
echo "     (klavye mikrofonu) yedek olarak acilir.\n";
echo "   Test: App'te ?micdebug=1 ile butona bas -> hangi yol calisti gorunur.\n";
