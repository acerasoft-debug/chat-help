<?php
/**
 * ChatHelp — apply-adres-imza3 (CH_ADRES_IMZA3) — basit dilekcede EKRAN IMZASI
 *  + DOGRUDAN ALICIYA gonderim (Aboalarm modeli). Onceki adres-onay modalini
 *  (chPrintDoc seviyesi) kapsamli surumle degistirir:
 *   • DE + Textform (islak imza gerektirmeyen) dilekce -> alici adresi AI ile
 *     cikarilir (duzenlenebilir) + ekran imzasi + 3 secenek:
 *        📄 Ucretsiz kendim yazdir
 *        ✍️ Imzala & aliciya gonder — 2,99 €   (postversand send_letter)
 *        ✉️ Orijinali evime postala — 2,99 €    (kaydeder)
 *   • Islak imza sart belgede: BGB §126 uyarisi + sadece evime/ucretsiz.
 *  Onceki CH_ADRES_IMZA2 modali pass-through yapilir (cift modal yok).
 *  Imza canvas beyaz zeminli -> JPEG'e temiz cevrilir. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-adres-imza3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-adres-imza3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ADRES_IMZA3')!==false) exit("Zaten ekli (CH_ADRES_IMZA3).\n");
if (strpos($src,'CH_ADRES_IMZA2')===false) exit("HATA: once CH_ADRES_IMZA2 gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-adres-imza3-js">
/* CH_ADRES_IMZA3 — ekran imzasi + dogrudan aliciya gonderim (postversand) */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function CCX(){ try{ var m=localStorage.getItem('ch_cc_manual'); var c=localStorage.getItem('ch_cc'); var w=(typeof window.CC!=='undefined'&&window.CC)?window.CC:''; return String((m&&m.length===2?m:(c||w||'DE'))).toUpperCase(); }catch(e){ return 'DE'; } }
  function API(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; }
  function PVURL(){ var a=API(); var i=a.lastIndexOf('/'); return (i>=0?a.slice(0,i+1):'')+'postversand.php'; }
  function prof(){ try{ var P=window.P; if(P&&(P.f1||P.f7)) return P; }catch(e){} try{ return JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; }catch(e){ return {}; } }
  function needsWet(html){ var h=(html||'').toLowerCase(); return /k(ü|ue)ndigung[^<]{0,60}(arbeit|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue)rgschaft|testament|erbausschlagung/.test(h); }
  function plan(){ try{ if(typeof window.chPlan==='function'){ var p=window.chPlan(); if(p) return String(p).toLowerCase(); } }catch(e){} try{ if(window.P&&window.P.plan) return String(window.P.plan).toLowerCase(); }catch(e){} try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); if(u.plan) return String(u.plan).toLowerCase(); }catch(e){} return 'free'; }
  function hasPack(){ var p=plan(); return /basic|pro|elite/.test(p); }
  function priceStr(){ var p=plan(); if(/elite/.test(p)) return '1,20 €'; if(/pro/.test(p)) return '1,50 €'; if(/basic/.test(p)) return '1,99 €'; return '2,99 €'; }
  function isLetter(html){ if(CCX()!=='DE') return false; if(/vfA4/.test(html)) return false; var P=prof(); return /Mit freundlichen Grüßen|Hochachtungsvoll|Mit freundlichem Gruß|freundlichen Grüßen/i.test(html)||(P.f3&&html.indexOf(P.f3)!==-1); }
  function T(k){
    var L={
      ttl:{de:'Adresse & Versand',tr:'Adres & Gönderim',en:'Address & sending'},
      sub:{de:'Prüfen Sie die Angaben. Bei Bedarf korrigieren.',tr:'Bilgileri kontrol edin, gerekirse düzeltin.',en:'Check the details.'},
      name:{de:'Ihr Name',tr:'Adınız Soyadınız',en:'Your name'},adr:{de:'Ihre Adresse',tr:'Adresiniz',en:'Your address'},plz:{de:'PLZ / Ort',tr:'Posta kodu / Şehir',en:'Postal / City'},
      rec:{de:'Empfänger (Behörde/Firma)',tr:'Alıcı (kurum/firma)',en:'Recipient'},
      recph:{de:'Wird automatisch erkannt…',tr:'Otomatik algılanıyor…',en:'Detecting…'},
      sigsec:{de:'✍️ Unterschrift',tr:'✍️ İmza',en:'✍️ Signature'},
      sigok:{de:'Unterschreiben Sie unten (für Versand erforderlich):',tr:'Aşağıya imzalayın (gönderim için gerekli):',en:'Sign below (required for sending):'},
      sigwet:{de:'⚠️ ORIGINAL-Unterschrift nötig (BGB §126). Bitte ausdrucken und handschriftlich unterschreiben. Versand an Empfänger hier nicht möglich.',tr:'⚠️ ISLAK imza gerekli (BGB §126). Çıktı alıp elle imzalayın. Alıcıya doğrudan gönderim burada mümkün değil.',en:'⚠️ Original signature required (BGB §126). Print & sign by hand.'},
      clr:{de:'Löschen',tr:'Temizle',en:'Clear'},
      free:{de:'📄 Kostenlos selbst ausdrucken',tr:'📄 Ücretsiz kendim yazdırırım',en:'📄 Print myself (free)'},
      send:{de:'✍️ Unterschreiben & an Empfänger senden',tr:'✍️ İmzala & alıcıya gönder',en:'✍️ Sign & send to recipient'},
      home:{de:'✉️ Original nach Hause',tr:'✉️ Orijinali evime postala',en:'✉️ Original to my home'},
      cancel:{de:'Abbrechen',tr:'Vazgeç',en:'Cancel'},
      needsig:{de:'Bitte zuerst unterschreiben.',tr:'Lütfen önce imzalayın.',en:'Please sign first.'},
      needrec:{de:'Bitte Empfängeradresse angeben.',tr:'Lütfen alıcı adresini girin.',en:'Enter recipient address.'},
      sending:{de:'⏳ Wird gesendet…',tr:'⏳ Gönderiliyor…',en:'⏳ Sending…'},
      sentok:{de:'✓ Auftrag angenommen. (Testmodus — im Livebetrieb wird nach Zahlung versendet.)',tr:'✓ Talep alındı. (Test modu — canlıda ödeme sonrası gönderilir.)',en:'✓ Accepted (test mode).'},
      senderr:{de:'Senden fehlgeschlagen: ',tr:'Gönderim başarısız: ',en:'Send failed: '},
      homeok:{de:'Bestellung gespeichert.',tr:'Sipariş kaydedildi.',en:'Order saved.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  /* HTML -> duz metin */
  function htmlText(html){
    try{
      var m=html.match(/<div class="a4"[^>]*>([\s\S]*?)<\/div>\s*(<div class="sig)/);
      var inner=m?m[1]:html;
      inner=inner.replace(/<br\s*\/?>/gi,'\n').replace(/<\/(p|div|h[1-6])>/gi,'\n').replace(/<[^>]+>/g,'');
      var ta=document.createElement('textarea'); ta.innerHTML=inner; var t=ta.value;
      return t.replace(/\n{3,}/g,'\n\n').trim();
    }catch(e){ return ''; }
  }

  var CTX=null; var SIG={drawing:false,has:false,ctx:null,canvas:null};
  function ovEl(){ var o=document.getElementById('ai-ov'); if(!o){ o=document.createElement('div'); o.id='ai-ov'; o.innerHTML='<div id="ai-box"></div>'; document.body.appendChild(o); o.addEventListener('click',function(e){ if(e.target===o) close(); }); } return o; }
  function close(){ var o=document.getElementById('ai-ov'); if(o) o.classList.remove('on'); }
  function setupSig(){
    var c=document.getElementById('ai-sigpad'); if(!c) return;
    try{ c.width=c.clientWidth*2; c.height=c.clientHeight*2; }catch(e){}
    var ctx=c.getContext('2d'); try{ ctx.scale(2,2); }catch(e){}
    try{ ctx.fillStyle='#fff'; ctx.fillRect(0,0,c.width,c.height); }catch(e){} /* JPEG icin beyaz zemin */
    ctx.lineWidth=2.2; ctx.lineCap='round'; ctx.strokeStyle='#0b1a3a';
    SIG.canvas=c; SIG.ctx=ctx; SIG.has=false;
    function pos(ev){ var r=c.getBoundingClientRect(); var t=(ev.touches&&ev.touches[0])||ev; return {x:t.clientX-r.left,y:t.clientY-r.top}; }
    function down(ev){ ev.preventDefault(); SIG.drawing=true; var p=pos(ev); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
    function move(ev){ if(!SIG.drawing) return; ev.preventDefault(); var p=pos(ev); ctx.lineTo(p.x,p.y); ctx.stroke(); SIG.has=true; }
    function up(){ SIG.drawing=false; }
    c.addEventListener('pointerdown',down); c.addEventListener('pointermove',move); window.addEventListener('pointerup',up);
    c.addEventListener('touchstart',down,{passive:false}); c.addEventListener('touchmove',move,{passive:false}); c.addEventListener('touchend',up);
  }
  function clearSig(){ try{ if(SIG.ctx&&SIG.canvas){ SIG.ctx.fillStyle='#fff'; SIG.ctx.fillRect(0,0,SIG.canvas.width,SIG.canvas.height); SIG.has=false; } }catch(e){} }
  function sigJpeg(){ try{ return SIG.has?SIG.canvas.toDataURL('image/jpeg',0.92):''; }catch(e){ return ''; } }
  function sigPng(){ try{ return SIG.has?SIG.canvas.toDataURL('image/png'):''; }catch(e){ return ''; } }

  function proceed(html){ window.__addr3OK=true; window.__addrOK=true; try{ CTX.orig.call(window,html); }catch(e){ try{ CTX.orig(html); }catch(e2){} } }
  function saveProfAddr(){
    try{ var P=prof(), f3=(document.getElementById('ai-f3')||{}).value, f4=(document.getElementById('ai-f4')||{}).value, html=CTX.html;
      if(f3!==undefined && P.f3 && f3 && f3!==P.f3) html=html.split(P.f3).join(f3);
      if(f4!==undefined && P.f4 && f4 && f4!==P.f4) html=html.split(P.f4).join(f4);
      if(window.P){ if(f3!==undefined) window.P.f3=f3; if(f4!==undefined) window.P.f4=f4; }
      var pv=JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; if(f3!==undefined)pv.f3=f3; if(f4!==undefined)pv.f4=f4; localStorage.setItem('ch_prof_v3',JSON.stringify(pv));
      return html;
    }catch(e){ return CTX.html; }
  }
  function doFree(){
    var html=saveProfAddr(); var sig=sigPng();
    if(sig){ var img='<div style="margin-top:24px"><img src="'+sig+'" style="height:62px"></div>'; if(/<\/body>/i.test(html)) html=html.replace(/<\/body>/i,img+'</body>'); else html=html+img; }
    close(); proceed(html);
  }
  function doHome(){
    try{ var P=prof(); var a=JSON.parse(localStorage.getItem('ch_mailorig_req')||'[]'); if(!Array.isArray(a))a=[]; a.push({ts:new Date().getTime(),adr:(document.getElementById('ai-f3')||{}).value||P.f3||'',plz:(document.getElementById('ai-f4')||{}).value||P.f4||'',html:String(CTX.html||'').slice(0,20000),paid:0}); localStorage.setItem('ch_mailorig_req',JSON.stringify(a.slice(-50))); }catch(e){}
    close(); try{ alert(T('homeok')); }catch(e){}
  }
  function doSend(btn){
    if(!SIG.has){ try{ alert(T('needsig')); }catch(e){} return; }
    var rec=String((document.getElementById('ai-rec')||{}).value||'').trim();
    if(rec.length<8){ try{ alert(T('needrec')); }catch(e){} return; }
    saveProfAddr();
    var P=prof();
    var sender=((P.f1||'')+' '+(P.f2||'')).trim()+' - '+(P.f3||'')+' - '+(P.f4||'');
    var body={text:htmlText(CTX.html),recipient:rec,sender:sender,sig_jpeg:sigJpeg()};
    var old=btn.textContent; btn.textContent=T('sending'); btn.disabled=true;
    fetch(PVURL()+'?action=send_letter',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
     .then(function(r){ return r.json(); })
     .then(function(j){
        btn.textContent=old; btn.disabled=false;
        if(j&&j.ok){ close(); try{ alert(T('sentok')); }catch(e){}
          try{ var a=JSON.parse(localStorage.getItem('ch_sent_letters')||'[]'); if(!Array.isArray(a))a=[]; a.push({ts:new Date().getTime(),recipient:rec,job:(j.result&&j.result.letter&&j.result.letter.job_id)||'',mode:j.mode}); localStorage.setItem('ch_sent_letters',JSON.stringify(a.slice(-50))); }catch(e){}
        } else { try{ alert(T('senderr')+((j&&(j.error||(j.result&&j.result.message)))||'?')); }catch(e){} }
     })
     .catch(function(){ btn.textContent=old; btn.disabled=false; try{ alert(T('senderr')+'network'); }catch(e){} });
  }

  function extractRecipient(){
    var el=document.getElementById('ai-rec'); if(!el) return;
    var txt=htmlText(CTX.html).slice(0,1500);
    var msg='Aus dem folgenden deutschen Brief NUR die Empfänger-Anschrift (Name/Firma + Straße + PLZ Ort) extrahieren, je Zeile ein Element, ohne Erklärung. Wenn kein Empfänger erkennbar ist, gib eine leere Antwort.\n\n'+txt;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg,history:[],provider:'deepseek',lang:'de',country:'DE'})})
     .then(function(r){ return r.json(); })
     .then(function(j){ try{ var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').trim():''; if(t&&t.length<200 && el && !el.value.trim()) el.value=t; }catch(e){} })
     .catch(function(){});
  }

  function render(){
    var P=prof(), box=document.getElementById('ai-box'); if(!box) return;
    var wet=needsWet(CTX.html);
    var h='<h3>📮 '+T('ttl')+'</h3><div class="sub">'+T('sub')+'</div>';
    h+='<div class="ai-f"><label>'+T('name')+'</label><input class="ai-in" id="ai-f1" value="'+esc(((P.f1||'')+' '+(P.f2||'')).trim())+'"></div>';
    h+='<div class="ai-f"><label>'+T('adr')+'</label><input class="ai-in" id="ai-f3" value="'+esc(P.f3||'')+'"></div>';
    h+='<div class="ai-f"><label>'+T('plz')+'</label><input class="ai-in" id="ai-f4" value="'+esc(P.f4||'')+'"></div>';
    if(!wet){
      h+='<div class="ai-f"><label>'+T('rec')+'</label><textarea class="ai-in" id="ai-rec" rows="3" placeholder="'+esc(T('recph'))+'"></textarea></div>';
    }
    h+='<div class="ai-sec">'+T('sigsec')+'</div>';
    if(wet){ h+='<div class="ai-warn">'+T('sigwet')+'</div>'; }
    else{ h+='<div class="sub" style="margin-bottom:6px">'+T('sigok')+'</div><canvas id="ai-sigpad"></canvas><div class="ai-sigrow"><button id="ai-sigclr">🗑 '+T('clr')+'</button></div>'; }
    h+='<div class="ai-acts">';
    if(!wet) h+='<button id="ai-send">'+T('send')+' — '+priceStr()+'</button>';
    h+='<button id="ai-free">'+T('free')+'</button><button id="ai-home">'+T('home')+' — '+priceStr()+'</button><button id="ai-cancel">'+T('cancel')+'</button></div>';
    box.innerHTML=h;
    if(!wet){ setupSig(); var cb=document.getElementById('ai-sigclr'); if(cb) cb.onclick=clearSig; extractRecipient(); var sb=document.getElementById('ai-send'); if(sb) sb.onclick=function(){ doSend(sb); }; }
    document.getElementById('ai-free').onclick=doFree;
    document.getElementById('ai-home').onclick=doHome;
    document.getElementById('ai-cancel').onclick=function(){ close(); proceed(CTX.html); };
  }

  /* CH_ADRES_IMZA2 chPrintDoc modalini pass-through yap + kendi modalimizi kur */
  var g=0;(function wrap(){
    try{
      if(typeof window.chPrintDoc==='function' && !window.chPrintDoc.__addr3){
        var _cpd=window.chPrintDoc; /* IMZA2 sarmasi */
        var w=function(html){
          try{
            if(window.__addr3OK){ window.__addr3OK=false; return _cpd.apply(this,arguments); }
            if(typeof html==='string' && isLetter(html)){
              CTX={html:html,orig:_cpd};
              ovEl().classList.add('on'); render();
              return;
            }
          }catch(e){}
          return _cpd.apply(this,arguments);
        };
        w.__addr3=1; w.__addr=1;
        try{ if(_cpd.__ai2)w.__ai2=1; if(_cpd.__fit)w.__fit=1; if(_cpd.__vsfprn)w.__vsfprn=1; }catch(e){}
        window.chPrintDoc=w;
      }
    }catch(e){}
    if(g++<500) setTimeout(wrap,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp = tempnam(sys_get_temp_dir(),'a3').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-adresimza3-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ADRES_IMZA3')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ADRES_IMZA3 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Basit dilekce: ekran imzasi + DOGRUDAN ALICIYA gonderim:\n";
echo "   • Alici adresi AI ile cikarilir (duzenlenebilir)\n";
echo "   • '✍️ Imzala & aliciya gonder — 2,99 €' -> postversand send_letter\n";
echo "   • Islak imza sart belgede bu secenek yok (BGB §126 uyarisi)\n";
echo "   Not: su an TEST modu -> gercek mektup gitmez. Canliya gecis: Stripe\n";
echo "   2,99 odeme kapisi + LetterXpress bakiye + mode=live.\n";
