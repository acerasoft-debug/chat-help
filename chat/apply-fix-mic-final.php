<?php
/**
 * ChatHelp — apply-fix-mic-final (CH_MIC_TRACE) — mikrofon KESIN duzeltme +
 *  gorunur iz. ANALIZ (workflow) buldu: sonsuz yaris YOK (APPKB kazaniyor),
 *  asil sorunlar: (a) App WebView tespiti UA'ya bagli, chIsApp hic set edilmez
 *  -> App'te yanlis dallanma; (b) gercek mobil tarayicilar (UC/Miui, 'Version/4
 *  …Chrome') yanlis WebView sayilip Turkce panel aciliyor; (c) karar UA'dan
 *  veriliyor, SONUC'tan degil.
 *  COZUM (tum eski katmanlardan SONRA tek kanonik blok):
 *   [1] canonicalVoice(): __final=1 + __appkb=1 tasir -> UNI ve APPKB
 *       guard'larini SUSTURUR (geri sarma olmaz). startVoice/chOpenMic buna
 *       baglanir; #pb'ye capture-fazli click dinleyici (closure-baypas'a karsi).
 *   [2] SONUC-bazli karar: kesin App (';wv)' veya window.chIsApp) -> panel;
 *       degilse ONCE SpeechRecognition dene; onerror 'not-allowed'/'service-
 *       not-allowed'/SR-yok gelirse OTOMATIK panele dus. Gevsek UA kalibi
 *       yalniz gercek-tarayici token'lari (OPR|SamsungBrowser|UCBrowser|
 *       MiuiBrowser|EdgA|YaBrowser|Firefox) YOKSA App sayilir.
 *   [3] GORUNUR IZ: ?micdebug=1 veya localStorage.ch_micdebug='1' ile ekran
 *       altinda son 8 olay (yol secimi + SR onstart/onerror/onend) gosterilir
 *       -> kullanici tam olarak ne oldugunu bize soyleyebilir.
 *  Sitede SR calisirsa metin kutuya akar; App'te panel + klavye. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fix-mic-final.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-mic-final BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_TRACE')!==false) exit("Zaten ekli (CH_MIC_TRACE).\n");
if (strpos($src,'id="pb"')===false) exit("HATA: mikrofon butonu (#pb) yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-mic-trace-css">
#mtr-log{position:fixed;left:8px;right:8px;bottom:8px;z-index:2147483600;background:rgba(6,8,20,.95);color:#8fe;border:1px solid rgba(120,220,255,.4);border-radius:10px;padding:8px 10px;font:11px/1.45 monospace;max-height:34vh;overflow:auto;display:none;white-space:pre-wrap}
#mtr-log.on{display:block}
#mkbx-ov{position:fixed;inset:0;z-index:2147483205;background:rgba(4,4,12,.82);backdrop-filter:blur(5px);display:none;align-items:flex-end;justify-content:center;font-family:'Inter',system-ui,sans-serif}
#mkbx-ov.on{display:flex}
#mkbx-card{position:relative;width:100%;max-width:560px;background:linear-gradient(180deg,#14142e,#0b0b19);border:1px solid rgba(212,168,74,.45);border-radius:20px 20px 0 0;padding:18px 16px 16px;color:#f2f2fb}
#mkbx-card .t{font-size:15px;font-weight:800;margin-bottom:4px}
#mkbx-card .s{font-size:12px;color:#b9b9dc;line-height:1.5;margin-bottom:10px}
#mkbx-ta{width:100%;min-height:110px;box-sizing:border-box;background:#101024;border:2px solid rgba(212,168,74,.5);border-radius:13px;padding:12px;color:#fff;font-size:16px;outline:none;font-family:inherit}
#mkbx-nav{display:flex;gap:8px;margin-top:12px}
#mkbx-nav button{flex:1;padding:13px;border-radius:12px;border:none;cursor:pointer;font-family:inherit;font-size:14px;font-weight:800}
#mkbx-add{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#mkbx-close{background:transparent;border:1.5px solid rgba(255,255,255,.18)!important;color:#c8c8e8}
</style>
<script id="ch-mic-trace-js">
/* CH_MIC_TRACE — kanonik mikrofon + sonuc-bazli fallback + gorunur iz */
try{(function(){
  var DBG=false; try{ DBG=(location.search.indexOf('micdebug=1')!==-1)||localStorage.getItem('ch_micdebug')==='1'; }catch(e){}
  function trace(m){
    if(!DBG) return;
    try{
      var box=document.getElementById('mtr-log');
      if(!box){ box=document.createElement('div'); box.id='mtr-log'; box.className='on'; document.body.appendChild(box); }
      box.textContent=(box.textContent+'\n'+m).split('\n').slice(-8).join('\n');
    }catch(e){}
  }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pt:'pt-PT',sv:'sv-SE',pl:'pl-PL',ru:'ru-RU',ar:'ar-SA'};
  function inp(){ return document.getElementById('inp'); }
  function glow(on){ try{ var b=document.getElementById('pb'); if(b){ b.style.color=on?'#e05050':''; b.style.animation=on?'pulse 1s infinite':''; } }catch(e){} }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:99999;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3500); }catch(e){} }

  function surelyApp(){
    try{
      var ua=navigator.userAgent||'';
      if(ua.indexOf('; wv)')!==-1) return true;
      if(window.chIsApp===true) return true;
      return false;
    }catch(e){ return false; }
  }
  function maybeApp(){
    try{
      var ua=navigator.userAgent||'';
      if(/OPR\/|SamsungBrowser|UCBrowser|MiuiBrowser|EdgA\/|YaBrowser|Firefox|FxiOS|CriOS/.test(ua)) return false; /* gercek tarayici */
      return /Android/.test(ua)&&/Version\/\d/.test(ua)&&/Chrome\//.test(ua);
    }catch(e){ return false; }
  }

  /* App sesli yazim paneli (kullanici dokunusuyla klavye acilir) */
  function panel(reason){
    trace('panel('+reason+')');
    var ov=document.getElementById('mkbx-ov');
    if(!ov){
      ov=document.createElement('div'); ov.id='mkbx-ov';
      var L=lang();
      var TT={de:'🎤 Spracheingabe',tr:'🎤 Sesli Yazım',en:'🎤 Voice input'};
      var SS={de:'Tippen Sie ins Feld — die Tastatur öffnet sich. Nutzen Sie das 🎤 Ihrer Tastatur und sprechen Sie.',tr:'Alana dokunun — klavye açılır. Klavyenizdeki 🎤 tuşuyla konuşun.',en:'Tap the field — the keyboard opens. Use the 🎤 on your keyboard and speak.'};
      var AD={de:'✓ Übernehmen',tr:'✓ Metni Ekle',en:'✓ Insert'};
      var CL={de:'Schließen',tr:'Kapat',en:'Close'};
      ov.innerHTML='<div id="mkbx-card"><div class="t">'+(TT[L]||TT.en)+'</div><div class="s">'+(SS[L]||SS.en)+'</div>'
        +'<textarea id="mkbx-ta"></textarea>'
        +'<div id="mkbx-nav"><button id="mkbx-close">'+(CL[L]||CL.en)+'</button><button id="mkbx-add">'+(AD[L]||AD.en)+'</button></div></div>';
      document.body.appendChild(ov);
      document.getElementById('mkbx-close').onclick=function(){ ov.classList.remove('on'); };
      document.getElementById('mkbx-add').onclick=function(){
        try{ var ta=document.getElementById('mkbx-ta'); var v=(ta&&ta.value)?ta.value.trim():''; if(v){ var i=inp(); if(i){ var base=(i.value||'').replace(/\s+$/,''); i.value=(base?base+' ':'')+v; try{ if(typeof onInp==='function') onInp(i); }catch(e){} try{ i.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){} } if(ta) ta.value=''; } }catch(e){}
        ov.classList.remove('on');
      };
      ov.addEventListener('click',function(ev){ if(ev.target===ov) ov.classList.remove('on'); });
    }
    ov.classList.add('on');
    try{ var ta=document.getElementById('mkbx-ta'); if(ta){ ta.focus(); } }catch(e){}
  }

  var R=null;
  function stopSR(){ try{ if(R){ R.onend=null;R.onresult=null;R.onerror=null; R.stop(); } }catch(e){} R=null; glow(false); }
  function startSR(){
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){ trace('SR yok -> panel'); panel('no-SR'); return; }
    try{
      var i=inp(); var base=(i&&i.value)?i.value.replace(/\s+$/,''):''; var fin='';
      var r=new SR(); try{ r.lang=LOC[lang()]||'de-DE'; }catch(e){}
      r.interimResults=true; r.continuous=false; r.maxAlternatives=1;
      r.onstart=function(){ trace('SR onstart'); glow(true); };
      r.onresult=function(ev){ try{ var it=''; for(var k=ev.resultIndex;k<ev.results.length;k++){ var rr=ev.results[k]; if(rr.isFinal) fin+=(fin&&!/\s$/.test(fin)?' ':'')+rr[0].transcript.trim(); else it+=rr[0].transcript; } var i2=inp(); if(i2){ i2.value=((base?base+' ':'')+fin+(it?' '+it:'')).replace(/\s+/g,' ').trim(); try{ if(typeof onInp==='function') onInp(i2); }catch(e){} } }catch(e){} };
      r.onerror=function(ev){ var er=(ev&&ev.error)||''; trace('SR onerror: '+er); stopSR(); if(er==='not-allowed'||er==='service-not-allowed'){ panel('sr-denied'); } else if(er==='no-speech'){ toast('🎤 '+({de:'Keine Sprache erkannt.',tr:'Ses algılanamadı.',en:'No speech detected.'}[lang()]||'No speech.')); } else if(er&&er!=='aborted'){ panel('sr-err-'+er); } };
      r.onend=function(){ trace('SR onend'); stopSR(); };
      R=r; r.start(); trace('SR.start()');
    }catch(e){ trace('SR exception -> panel'); stopSR(); panel('sr-exception'); }
  }

  function canonicalVoice(){
    trace('voice() ua-app='+surelyApp()+' maybe='+maybeApp());
    if(R){ stopSR(); return; }
    if(surelyApp()){ panel('surely-app'); return; }
    if(maybeApp()){ panel('maybe-app'); return; }
    startSR();
  }
  canonicalVoice.__final=1; canonicalVoice.__appkb=1;

  function install(){ try{ window.startVoice=canonicalVoice; window.chOpenMic=canonicalVoice; window.chKbFocus=function(){ panel('kbfocus'); }; }catch(e){} }
  install();
  /* #pb'ye capture-click (closure-baypas'a karsi) */
  (function bindBtn(){
    var n=0;(function loop(){
      try{ var b=document.getElementById('pb'); if(b && !b.__mtr){ b.__mtr=1; b.addEventListener('click',function(ev){ try{ ev.stopImmediatePropagation(); }catch(e){} trace('#pb click(capture)'); canonicalVoice(); },true); trace('#pb capture baglandi'); } }catch(e){}
      if(n++<300) setTimeout(loop,400);
    })();
  })();
  /* sahiplik korumasi */
  var g=0;(function guard(){ try{ if(!(window.startVoice&&window.startVoice.__final&&window.startVoice.__appkb)) install(); }catch(e){} if(g++<200) setTimeout(guard,500); })();
  trace('CH_MIC_TRACE kuruldu (debug='+DBG+')');
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
@file_put_contents($file.'.bak-mictrace-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_TRACE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_TRACE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mikrofon kanonik yola baglandi (tum eski katmanlar susturuldu):\n";
echo "   • Karar SONUC-bazli: once SpeechRecognition denenir, izin reddi/SR-yok\n";
echo "     olursa OTOMATIK sesli-yazim paneline dusulur (UA yanlislarina bagimli degil)\n";
echo "   • Panel kullanici dilinde (de/tr/en), #pb'ye capture-click (closure-baypas'a karsi)\n";
echo "   • GORUNUR IZ: adres cubuguna ?micdebug=1 ekle -> ekran altinda ne oldugu\n";
echo "     satir satir gorunur (butona bas, ciktiyi bize gonder).\n";
