<?php
/**
 * ChatHelp — apply-mic-pro (CH_MICPRO) — premium sesli anlatim ekrani.
 *  Mikrofona tiklayinca tam ekran sik bir kayit alani acilir: buyuk nabizli mic,
 *  CANLI transkript (interim+final), Übernehmen/Abbrechen. Cok dilli (ch_uilang).
 *  window.startVoice override edilir. Saf EKLEMELI. Gomulu JS node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-pro.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-pro BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICPRO')!==false) exit("Zaten ekli (CH_MICPRO).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-micpro-css">
/* CH_MICPRO — premium sesli anlatim ekrani */
#ch-mic{position:fixed;inset:0;z-index:9600;background:rgba(6,6,16,.9);backdrop-filter:blur(9px);display:none;align-items:center;justify-content:center;padding:20px}
#ch-mic.on{display:flex}
.chm-card{position:relative;width:100%;max-width:460px;background:linear-gradient(180deg,#16162c,#0c0c1c);border:1px solid rgba(212,168,74,.22);border-radius:26px;padding:34px 24px 26px;text-align:center;box-shadow:0 30px 90px rgba(0,0,0,.65)}
.chm-x{position:absolute;top:16px;right:16px;width:38px;height:38px;border-radius:11px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#c8c8e0;font-size:18px;cursor:pointer}
.chm-x:hover{background:rgba(255,255,255,.1)}
.chm-mic{width:116px;height:116px;border-radius:50%;margin:4px auto 20px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;cursor:pointer;position:relative;box-shadow:0 14px 44px rgba(212,168,74,.38);transition:transform .15s}
.chm-mic:active{transform:scale(.96)}
.chm-mic svg{width:48px;height:48px}
.chm-mic.rec::before,.chm-mic.rec::after{content:"";position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(212,168,74,.55);animation:chmRing 1.9s ease-out infinite;pointer-events:none}
.chm-mic.rec::after{animation-delay:.95s}
@keyframes chmRing{0%{transform:scale(1);opacity:.75}100%{transform:scale(1.85);opacity:0}}
.chm-status{font-size:14px;color:var(--gold,#d4a84a);font-weight:700;margin-bottom:16px;letter-spacing:.3px;min-height:18px}
.chm-txt{min-height:118px;max-height:230px;overflow-y:auto;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px;font-size:16px;line-height:1.55;color:#f2f2fa;text-align:left;margin-bottom:18px;white-space:pre-wrap;word-break:break-word}
.chm-txt .im{color:#9a9ac0}
.chm-txt.empty::before{content:attr(data-ph);color:#66668a}
.chm-btns{display:flex;gap:10px}
.chm-btn{flex:1;padding:14px;border-radius:14px;border:none;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;transition:transform .12s,box-shadow .15s}
.chm-ok{background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00}
.chm-ok:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(212,168,74,.3)}
.chm-ok:disabled{opacity:.5;cursor:default;transform:none;box-shadow:none}
.chm-cancel{background:rgba(255,255,255,.06);color:#c8c8e0;border:1px solid rgba(255,255,255,.12)}
.chm-cancel:hover{background:rgba(255,255,255,.1)}
</style>
<script id="ch-micpro-js">
/* CH_MICPRO */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',ru:'ru-RU',ar:'ar-SA',nl:'nl-NL',pl:'pl-PL',pt:'pt-PT',sv:'sv-SE'};
  var T={
    de:{tap:'Tippen zum Sprechen',rec:'Ich höre zu…',ph:'Beschreiben Sie in Ruhe Ihr Anliegen…',ok:'Übernehmen',cancel:'Abbrechen',nos:'Spracheingabe wird in diesem Browser nicht unterstützt.'},
    tr:{tap:'Konuşmak için dokunun',rec:'Dinliyorum…',ph:'Durumunuzu rahatça anlatın…',ok:'Kullan',cancel:'İptal',nos:'Bu tarayıcı sesli girişi desteklemiyor.'},
    en:{tap:'Tap to speak',rec:'Listening…',ph:'Describe your matter calmly…',ok:'Use text',cancel:'Cancel',nos:'Voice input is not supported in this browser.'},
    fr:{tap:'Appuyez pour parler',rec:'J\'écoute…',ph:'Décrivez tranquillement votre demande…',ok:'Utiliser',cancel:'Annuler',nos:'La saisie vocale n\'est pas prise en charge.'},
    es:{tap:'Toque para hablar',rec:'Escuchando…',ph:'Describa con calma su asunto…',ok:'Usar',cancel:'Cancelar',nos:'La entrada de voz no es compatible.'},
    it:{tap:'Tocca per parlare',rec:'Sto ascoltando…',ph:'Descrivi con calma la tua richiesta…',ok:'Usa',cancel:'Annulla',nos:'Input vocale non supportato.'},
    ru:{tap:'Нажмите, чтобы говорить',rec:'Слушаю…',ph:'Спокойно опишите ваш вопрос…',ok:'Применить',cancel:'Отмена',nos:'Голосовой ввод не поддерживается.'},
    ar:{tap:'اضغط للتحدث',rec:'أستمع…',ph:'صف طلبك بهدوء…',ok:'استخدام',cancel:'إلغاء',nos:'الإدخال الصوتي غير مدعوم.'}
  };
  function L(){ return T[lang()]||T.de; }
  var rec=null, listening=false, finalT='';

  function ensureUI(){
    var host=document.getElementById('ch-mic');
    if(host) return host;
    host=document.createElement('div'); host.id='ch-mic';
    host.innerHTML=
      '<div class="chm-card">'
      +'<button class="chm-x" data-a="cancel">✕</button>'
      +'<div class="chm-mic" data-a="toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></div>'
      +'<div class="chm-status"></div>'
      +'<div class="chm-txt empty"></div>'
      +'<div class="chm-btns"><button class="chm-btn chm-cancel" data-a="cancel"></button><button class="chm-btn chm-ok" data-a="ok" disabled></button></div>'
      +'</div>';
    document.body.appendChild(host);
    host.addEventListener('click',function(ev){
      var a=(ev.target.closest?ev.target.closest('[data-a]'):null);
      if(!a){ if(ev.target===host){ closeMic(); } return; }
      var act=a.getAttribute('data-a');
      if(act==='cancel') closeMic();
      else if(act==='ok') acceptMic();
      else if(act==='toggle') toggleRec();
    });
    return host;
  }
  function els(){ var h=document.getElementById('ch-mic'); return { host:h, mic:h.querySelector('.chm-mic'), st:h.querySelector('.chm-status'), tx:h.querySelector('.chm-txt'), ok:h.querySelector('.chm-ok'), cancel:h.querySelector('.chm-cancel') }; }
  function render(interim){
    var e=els(), l=L();
    e.st.textContent = listening ? l.rec : l.tap;
    e.mic.classList.toggle('rec', listening);
    var body=(finalT+(interim||'')).trim();
    if(body){ e.tx.classList.remove('empty'); e.tx.innerHTML=escapeHtml(finalT)+(interim?'<span class="im">'+escapeHtml(interim)+'</span>':''); e.tx.scrollTop=e.tx.scrollHeight; }
    else { e.tx.classList.add('empty'); e.tx.setAttribute('data-ph',l.ph); e.tx.innerHTML=''; }
    e.ok.disabled = !finalT.trim();
    e.ok.textContent=l.ok; e.cancel.textContent=l.cancel;
  }
  function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

  function startRec(){
    try{
      var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
      if(!SR){ var e=els(); e.st.textContent=L().nos; return; }
      rec=new SR();
      rec.lang=LOC[lang()]||'de-DE';
      rec.continuous=true; rec.interimResults=true; rec.maxAlternatives=1;
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
      listening=true; rec.start(); render('');
    }catch(e){ listening=false; render(''); }
  }
  function stopRec(){ listening=false; try{ if(rec){ rec.onend=null; rec.stop(); } }catch(e){} rec=null; render(''); }
  function toggleRec(){ if(listening) stopRec(); else startRec(); }

  function openMic(){
    ensureUI(); finalT='';
    var h=document.getElementById('ch-mic'); h.classList.add('on');
    render(''); startRec();
  }
  function closeMic(){ stopRec(); var h=document.getElementById('ch-mic'); if(h) h.classList.remove('on'); }
  function acceptMic(){
    var txt=finalT.trim(); stopRec();
    var h=document.getElementById('ch-mic'); if(h) h.classList.remove('on');
    if(!txt) return;
    try{
      var inp=document.getElementById('inp');
      if(inp){
        inp.value = (inp.value && inp.value.trim()) ? (inp.value.trim()+' '+txt) : txt;
        inp.focus();
        try{ if(typeof onInp==='function') onInp(inp); }catch(e){}
        try{ inp.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){}
      }
    }catch(e){}
  }

  /* startVoice'u override et — mic butonu (#pb onclick) bunu cagirir */
  window.startVoice=function(){ try{ openMic(); }catch(e){} };
  window.chOpenMic=openMic;
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'mp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micpro-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICPRO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICPRO diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Premium sesli anlatim ekrani: buyuk nabizli mic + canli transkript + Übernehmen.\n";
