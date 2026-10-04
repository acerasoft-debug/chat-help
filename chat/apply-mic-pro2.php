<?php
/**
 * ChatHelp — apply-mic-pro2 (CH_MICPRO2) — sesli anlatim: canli dalga gorseli +
 *  acik mikrofon izni.
 *  Sorun: ses alinamiyordu (mik izni istenmemis/SpeechRecognition sessiz basarisiz)
 *  ve sesi aldigini gosteren gorunum yoktu.
 *  Cozum: getUserMedia ile izni ACIKCA iste + AudioContext/Analyser ile GERCEK ses
 *  seviyesinden canli altin barlar (sesle oynar). SpeechRecognition transkript.
 *  window.startVoice/chOpenMic override. Gomulu JS node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-pro2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-pro2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICPRO2')!==false) exit("Zaten ekli (CH_MICPRO2).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-micpro2-css">
/* CH_MICPRO2 — canli dalga gorselli sesli anlatim */
#ch-mic2{position:fixed;inset:0;z-index:9700;background:rgba(6,6,16,.92);backdrop-filter:blur(10px);display:none;align-items:center;justify-content:center;padding:20px}
#ch-mic2.on{display:flex}
.cm2-card{position:relative;width:100%;max-width:470px;background:linear-gradient(180deg,#16162c,#0b0b1a);border:1px solid rgba(212,168,74,.24);border-radius:28px;padding:34px 24px 26px;text-align:center;box-shadow:0 34px 100px rgba(0,0,0,.7)}
.cm2-x{position:absolute;top:16px;right:16px;width:38px;height:38px;border-radius:11px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#c8c8e0;font-size:18px;cursor:pointer}
.cm2-x:hover{background:rgba(255,255,255,.1)}
.cm2-mic{width:104px;height:104px;border-radius:50%;margin:2px auto 18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;cursor:pointer;position:relative;box-shadow:0 14px 44px rgba(212,168,74,.4);transition:transform .12s}
.cm2-mic:active{transform:scale(.95)}
.cm2-mic svg{width:44px;height:44px}
.cm2-mic .cm2-glow{position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 0 rgba(212,168,74,.5);transition:box-shadow .08s ease-out}
/* canli dalga barlari */
.cm2-wave{display:flex;align-items:center;justify-content:center;gap:3px;height:56px;margin:2px 0 14px}
.cm2-wave i{width:5px;height:8px;border-radius:3px;background:linear-gradient(180deg,#e8b840,#c49038);transition:height .06s linear;opacity:.9}
.cm2-status{font-size:13.5px;color:var(--gold,#d4a84a);font-weight:700;margin-bottom:14px;letter-spacing:.3px;min-height:17px}
.cm2-txt{min-height:110px;max-height:220px;overflow-y:auto;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px;font-size:16px;line-height:1.55;color:#f2f2fa;text-align:left;margin-bottom:16px;white-space:pre-wrap;word-break:break-word}
.cm2-txt .im{color:#9a9ac0}
.cm2-txt.empty::before{content:attr(data-ph);color:#66668a}
.cm2-btns{display:flex;gap:10px}
.cm2-btn{flex:1;padding:14px;border-radius:14px;border:none;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;transition:transform .12s,box-shadow .15s}
.cm2-ok{background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00}
.cm2-ok:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(212,168,74,.3)}
.cm2-ok:disabled{opacity:.45;cursor:default;transform:none;box-shadow:none}
.cm2-cancel{background:rgba(255,255,255,.06);color:#c8c8e0;border:1px solid rgba(255,255,255,.12)}
.cm2-cancel:hover{background:rgba(255,255,255,.1)}
</style>
<script id="ch-micpro2-js">
/* CH_MICPRO2 */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',ru:'ru-RU',ar:'ar-SA',nl:'nl-NL',pl:'pl-PL',pt:'pt-PT',sv:'sv-SE'};
  var T={
    de:{tap:'Sprechen Sie jetzt',wait:'Mikrofon wird aktiviert…',rec:'Ich höre zu…',paused:'Pausiert – tippen zum Fortsetzen',ph:'Beschreiben Sie in Ruhe Ihr Anliegen…',ok:'Übernehmen',cancel:'Abbrechen',deny:'Mikrofon-Zugriff verweigert. Bitte in den Einstellungen erlauben.',noeng:'Diktat wird hier nicht unterstützt – Ton wird erkannt, aber bitte tippen.'},
    tr:{tap:'Şimdi konuşun',wait:'Mikrofon açılıyor…',rec:'Dinliyorum…',paused:'Duraklatıldı – devam için dokunun',ph:'Durumunuzu rahatça anlatın…',ok:'Kullan',cancel:'İptal',deny:'Mikrofon izni reddedildi. Lütfen ayarlardan izin verin.',noeng:'Bu ortamda dikte desteklenmiyor – ses algılanıyor ama lütfen yazın.'},
    en:{tap:'Speak now',wait:'Activating microphone…',rec:'Listening…',paused:'Paused – tap to resume',ph:'Describe your matter calmly…',ok:'Use text',cancel:'Cancel',deny:'Microphone access denied. Please allow it in settings.',noeng:'Dictation not supported here – audio detected, please type.'},
    fr:{tap:'Parlez maintenant',wait:'Activation du micro…',rec:'J\'écoute…',paused:'En pause – touchez pour reprendre',ph:'Décrivez tranquillement votre demande…',ok:'Utiliser',cancel:'Annuler',deny:'Accès micro refusé. Autorisez-le dans les réglages.',noeng:'Dictée non prise en charge – audio détecté, veuillez taper.'},
    es:{tap:'Hable ahora',wait:'Activando micrófono…',rec:'Escuchando…',paused:'En pausa – toque para continuar',ph:'Describa con calma su asunto…',ok:'Usar',cancel:'Cancelar',deny:'Acceso al micrófono denegado. Actívelo en ajustes.',noeng:'Dictado no compatible – audio detectado, escriba por favor.'},
    it:{tap:'Parla ora',wait:'Attivazione microfono…',rec:'Sto ascoltando…',paused:'In pausa – tocca per riprendere',ph:'Descrivi con calma la tua richiesta…',ok:'Usa',cancel:'Annulla',deny:'Accesso al microfono negato. Consentilo nelle impostazioni.',noeng:'Dettatura non supportata – audio rilevato, digita.'}
  };
  function L(){ return T[lang()]||T.de; }

  var rec=null, listening=false, finalT='', stream=null, actx=null, analyser=null, raf=0, NB=28;

  function ui(){
    var h=document.getElementById('ch-mic2'); if(h) return h;
    h=document.createElement('div'); h.id='ch-mic2';
    var bars=''; for(var i=0;i<NB;i++) bars+='<i></i>';
    h.innerHTML='<div class="cm2-card">'
      +'<button class="cm2-x" data-a="cancel">✕</button>'
      +'<div class="cm2-mic" data-a="toggle"><span class="cm2-glow"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></div>'
      +'<div class="cm2-wave">'+bars+'</div>'
      +'<div class="cm2-status"></div>'
      +'<div class="cm2-txt empty"></div>'
      +'<div class="cm2-btns"><button class="cm2-btn cm2-cancel" data-a="cancel"></button><button class="cm2-btn cm2-ok" data-a="ok" disabled></button></div>'
      +'</div>';
    document.body.appendChild(h);
    h.addEventListener('click',function(ev){
      var a=(ev.target.closest?ev.target.closest('[data-a]'):null);
      if(!a){ if(ev.target===h) closeMic(); return; }
      var act=a.getAttribute('data-a');
      if(act==='cancel') closeMic();
      else if(act==='ok') acceptMic();
      else if(act==='toggle'){ if(listening) pauseRec(); else resumeRec(); }
    });
    return h;
  }
  function E(){ var h=document.getElementById('ch-mic2'); return {h:h,mic:h.querySelector('.cm2-mic'),glow:h.querySelector('.cm2-glow'),st:h.querySelector('.cm2-status'),tx:h.querySelector('.cm2-txt'),ok:h.querySelector('.cm2-ok'),cancel:h.querySelector('.cm2-cancel'),bars:h.querySelectorAll('.cm2-wave i')}; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
  function setStatus(s){ try{ E().st.textContent=s; }catch(e){} }
  function render(interim){
    try{
      var e=E(), l=L();
      var body=(finalT+(interim||'')).trim();
      if(body){ e.tx.classList.remove('empty'); e.tx.innerHTML=esc(finalT)+(interim?'<span class="im">'+esc(interim)+'</span>':''); e.tx.scrollTop=e.tx.scrollHeight; }
      else { e.tx.classList.add('empty'); e.tx.setAttribute('data-ph',l.ph); e.tx.innerHTML=''; }
      e.ok.disabled=!finalT.trim(); e.ok.textContent=l.ok; e.cancel.textContent=l.cancel;
    }catch(e){}
  }

  /* ── canli ses gorselleştirici ── */
  function startViz(s){
    try{
      var AC=window.AudioContext||window.webkitAudioContext; if(!AC) return;
      actx=new AC(); try{ actx.resume(); }catch(e){}
      var src=actx.createMediaStreamSource(s);
      analyser=actx.createAnalyser(); analyser.fftSize=64; analyser.smoothingTimeConstant=.75;
      src.connect(analyser);
      var data=new Uint8Array(analyser.frequencyBinCount);
      var bars=E().bars, glow=E().glow;
      (function draw(){
        try{
          if(!analyser){ return; }
          analyser.getByteFrequencyData(data);
          var sum=0;
          for(var i=0;i<bars.length;i++){
            var v=(data[i+2]||0)/255; sum+=v;
            bars[i].style.height=(6+v*46)+'px';
            bars[i].style.opacity=(.45+v*.55);
          }
          var avg=sum/bars.length;
          if(glow) glow.style.boxShadow='0 0 0 '+(avg*22)+'px rgba(212,168,74,'+(0.06+avg*0.22)+')';
          raf=requestAnimationFrame(draw);
        }catch(e){}
      })();
    }catch(e){}
  }
  function stopViz(){
    try{ if(raf) cancelAnimationFrame(raf); }catch(e){} raf=0;
    try{ if(analyser) analyser.disconnect(); }catch(e){} analyser=null;
    try{ if(actx) actx.close(); }catch(e){} actx=null;
    try{ if(stream){ stream.getTracks().forEach(function(t){ t.stop(); }); } }catch(e){} stream=null;
    try{ E().bars.forEach(function(b){ b.style.height='8px'; }); }catch(e){}
    try{ if(E().glow) E().glow.style.boxShadow='none'; }catch(e){}
  }

  /* ── konusma tanima ── */
  function startRec(){
    try{
      var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
      if(!SR){ setStatus(L().noeng); return; }
      rec=new SR(); rec.lang=LOC[lang()]||'de-DE'; rec.continuous=true; rec.interimResults=true; rec.maxAlternatives=1;
      rec.onresult=function(ev){
        var interim='';
        for(var i=ev.resultIndex;i<ev.results.length;i++){
          var r=ev.results[i];
          if(r.isFinal){ finalT+=(finalT&&!/\s$/.test(finalT)?' ':'')+r[0].transcript.trim(); }
          else interim+=r[0].transcript;
        }
        render(interim);
      };
      rec.onerror=function(){};
      rec.onend=function(){ if(listening){ try{ rec.start(); }catch(e){} } };
      rec.start();
    }catch(e){}
  }
  function stopRecEngine(){ try{ if(rec){ rec.onend=null; rec.stop(); } }catch(e){} rec=null; }

  function beginListen(){
    listening=true; setStatus(L().wait);
    var md=(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia)?navigator.mediaDevices:null;
    if(md){
      md.getUserMedia({audio:true}).then(function(s){
        stream=s; startViz(s); startRec(); setStatus(L().rec);
        try{ E().mic.classList.add('rec'); }catch(e){}
      }).catch(function(err){
        listening=false;
        setStatus(L().deny);
      });
    } else {
      startRec(); setStatus(L().rec);
    }
  }
  function pauseRec(){ listening=false; stopRecEngine(); stopViz(); setStatus(L().paused); }
  function resumeRec(){ if(listening) return; beginListen(); }

  function openMic(){ ui(); finalT=''; var h=document.getElementById('ch-mic2'); h.classList.add('on'); render(''); beginListen(); }
  function closeMic(){ listening=false; stopRecEngine(); stopViz(); var h=document.getElementById('ch-mic2'); if(h) h.classList.remove('on'); }
  function acceptMic(){
    var txt=finalT.trim(); closeMic(); if(!txt) return;
    try{
      var inp=document.getElementById('inp');
      if(inp){
        inp.value=(inp.value&&inp.value.trim())?(inp.value.trim()+' '+txt):txt;
        inp.focus();
        try{ if(typeof onInp==='function') onInp(inp); }catch(e){}
        try{ inp.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){}
      }
    }catch(e){}
  }

  window.startVoice=function(){ try{ openMic(); }catch(e){} };
  window.chOpenMic=openMic;
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'m2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micpro2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICPRO2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICPRO2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Canli ses dalga gorseli + acik mik izni + transkript. Ses algilaniyor gorunur.\n";
