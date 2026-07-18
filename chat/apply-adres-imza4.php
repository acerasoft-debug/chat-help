<?php
/**
 * ChatHelp — apply-adres-imza4 (CH_ADRES_IMZA4) — 2 eksik: alici belgeye
 *  yazilsin + Themen'e ICERIK kaydedilsin. IMZA3 modalini kapsamli surumle
 *  degistirir (IMZA3 pass-through):
 *   [A] Alici (Empfänger) belgeye islenir: modaldeki alici adi/firmasi
 *       belgede yoksa "An: ..." blogu olarak eklenir (tum yollar: ucretsiz/
 *       gonder/evime). Taninmis firma/Behörde ise AI adresi tamamlar +
 *       "(bitte prüfen)" uyarisi; PLZ yoksa uyari satiri.
 *   [B] Themen ICERIGI: her sonlandirmada dilekce METNI ch_topics'e (doc)
 *       kaydedilir (dedup) -> Meine Themen'de tiklayinca icerik gorunur,
 *       PDF/duzenle/gonder yapilabilir.
 *  Kademeli fiyat (paketsiz 2,99 / Basic 1,99 / Pro 1,50 / Elite 1,50).
 *  node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-adres-imza4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-adres-imza4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ADRES_IMZA4')!==false) exit("Zaten ekli (CH_ADRES_IMZA4).\n");
if (strpos($src,'CH_ADRES_IMZA3')===false) exit("HATA: once CH_ADRES_IMZA3 gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-adres-imza4-js">
/* CH_ADRES_IMZA4 — alici belgeye + Themen icerik kaydi (IMZA3 kapsamli surum) */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function CCX(){ try{ var m=localStorage.getItem('ch_cc_manual'); var c=localStorage.getItem('ch_cc'); var w=(typeof window.CC!=='undefined'&&window.CC)?window.CC:''; return String((m&&m.length===2?m:(c||w||'DE'))).toUpperCase(); }catch(e){ return 'DE'; } }
  function API(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; }
  function PVURL(){ var a=API(); var i=a.lastIndexOf('/'); return (i>=0?a.slice(0,i+1):'')+'postversand.php'; }
  function prof(){ try{ var P=window.P; if(P&&(P.f1||P.f7)) return P; }catch(e){} try{ return JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; }catch(e){ return {}; } }
  function needsWet(html){ var h=(html||'').toLowerCase(); return /k(ü|ue)ndigung[^<]{0,60}(arbeit|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue)rgschaft|testament|erbausschlagung|befristeter mietvertrag|mietvertrag[^<]{0,40}befristet|darlehensvertrag|verbraucherdarlehen/.test(h); }
  function planNorm(p){ p=(''+(p||'')).toLowerCase().trim(); if(!p) return ''; if(/elite|unbegrenzt|unlimited|premium\+|max/.test(p)) return 'elite'; if(/\bpro\b|pro[_-]|professional/.test(p)) return 'pro'; if(/basic|basis|starter/.test(p)) return 'basic'; if(/free|gratis|kostenlos/.test(p)) return 'free'; return ''; }
  /* Elite/Pro/Basic: TUM kaynaklardan en yuksek kademe (tek kaynak bayat olsa bile paket taninir) */
  function plan(){ var cand=[]; try{ cand.push(localStorage.getItem('ch_plan')); }catch(e){} try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); if(u&&u.plan) cand.push(u.plan); }catch(e){} try{ if(window.P&&window.P.plan) cand.push(window.P.plan); }catch(e){} try{ if(typeof window.chPlan==='function') cand.push(window.chPlan()); }catch(e){} var rank={free:0,basic:1,pro:2,elite:3}, best='free', bestR=0; for(var i=0;i<cand.length;i++){ var n=planNorm(cand[i]); if(!n) continue; if(rank[n]>bestR){ bestR=rank[n]; best=n; } } return best; }
  function priceStr(){ var p=plan(); if(/elite/.test(p)) return '1,50 €'; if(/pro/.test(p)) return '1,50 €'; if(/basic/.test(p)) return '1,99 €'; return '2,99 €'; }
  function isLetter(html){ if(CCX()!=='DE') return false; if(/vfA4/.test(html)) return false; var P=prof(); return /Mit freundlichen Grüßen|Hochachtungsvoll|Mit freundlichem Gruß|freundlichen Grüßen/i.test(html)||(P.f3&&html.indexOf(P.f3)!==-1); }
  function T(k){
    var L={
      ttl:{de:'Empfänger, Adresse & Versand',tr:'Alıcı, Adres & Gönderim',en:'Recipient, address & sending'},
      sub:{de:'Prüfen Sie die Angaben. Der Empfänger erscheint im Dokument.',tr:'Bilgileri kontrol edin. Alıcı belgede görünecek.',en:'Check details. The recipient appears in the document.'},
      name:{de:'Ihr Name',tr:'Adınız Soyadınız',en:'Your name'},adr:{de:'Ihre Adresse',tr:'Adresiniz',en:'Your address'},plz:{de:'PLZ / Ort',tr:'Posta kodu / Şehir',en:'Postal / City'},
      rec:{de:'Empfänger (Person/Firma/Behörde)',tr:'Alıcı (kişi/firma/kurum)',en:'Recipient'},
      recph:{de:'Wird erkannt… oder Namen eintragen',tr:'Algılanıyor… ya da adı yazın',en:'Detecting… or type a name'},
      recwarn:{de:'⚠️ Adresse evtl. unvollständig — bitte prüfen/ergänzen.',tr:'⚠️ Adres eksik olabilir — kontrol edin/tamamlayın.',en:'⚠️ Address may be incomplete — please verify.'},
      sigsec:{de:'✍️ Unterschrift',tr:'✍️ İmza',en:'✍️ Signature'},
      sigok:{de:'Unterschreiben (für Versand erforderlich):',tr:'İmzalayın (gönderim için gerekli):',en:'Sign (required for sending):'},
      sigwet:{de:'⚠️ ORIGINAL-Unterschrift nötig (BGB §126). Ausdrucken & handschriftlich unterschreiben. Direktversand nicht möglich.',tr:'⚠️ ISLAK imza gerekli (BGB §126). Çıktı alıp elle imzalayın. Doğrudan gönderim mümkün değil.',en:'⚠️ Original signature required (BGB §126).'},
      clr:{de:'Löschen',tr:'Temizle',en:'Clear'},
      free:{de:'📄 Kostenlos selbst ausdrucken',tr:'📄 Ücretsiz kendim yazdırırım',en:'📄 Print myself (free)'},
      send:{de:'✍️ Unterschreiben & an Empfänger senden',tr:'✍️ İmzala & alıcıya gönder',en:'✍️ Sign & send to recipient'},
      home:{de:'✉️ Original nach Hause',tr:'✉️ Orijinali evime postala',en:'✉️ Original to my home'},
      cancel:{de:'Abbrechen',tr:'Vazgeç',en:'Cancel'},
      needsig:{de:'Bitte zuerst unterschreiben.',tr:'Lütfen önce imzalayın.',en:'Please sign first.'},
      needrec:{de:'Bitte Empfänger angeben.',tr:'Lütfen alıcıyı girin.',en:'Enter recipient.'},
      needbody:{de:'Der Dokumenttext ist zu kurz.',tr:'Belge metni çok kısa.',en:'Document text too short.'},
      editlbl:{de:'📝 Dokumenttext (bei Bedarf korrigieren)',tr:'📝 Belge metni (gerekirse düzeltin)',en:'📝 Document text (edit if needed)'},
      edithint:{de:'Diese Fassung wird an den Empfänger gesendet.',tr:'Bu metin alıcıya gönderilecek.',en:'This version is sent to the recipient.'},
      confirmq:{de:'⚠️ Das Dokument wird jetzt verbindlich an „{r}" versendet. Fortfahren?',tr:'⚠️ Belge şimdi „{r}" adresine gönderilecek. Devam edilsin mi?',en:'⚠️ The document will be sent to „{r}". Continue?'},
      confirmyes:{de:'✓ Bestätigen & senden',tr:'✓ Onayla & gönder',en:'✓ Confirm & send'},
      back:{de:'← Zurück',tr:'← Geri',en:'← Back'},
      reg:{de:'📬 Einwurf-Einschreiben (+3,50 €) — mit Zustellnachweis',tr:'📬 Taahhütlü gönderim (+3,50 €) — teslim kanıtlı',en:'📬 Registered mail (+3,50 €) — proof of delivery'},
      reghint:{de:'Fristkritisches Dokument — Einschreiben empfohlen.',tr:'Süreye bağlı belge — taahhütlü önerilir.',en:'Deadline-critical — registered recommended.'},
      sending:{de:'⏳ Wird gesendet…',tr:'⏳ Gönderiliyor…',en:'⏳ Sending…'},
      sentok:{de:'✓ Auftrag angenommen (Testmodus).',tr:'✓ Talep alındı (test modu).',en:'✓ Accepted (test mode).'},
      senderr:{de:'Senden fehlgeschlagen: ',tr:'Gönderim başarısız: ',en:'Send failed: '},
      homeok:{de:'Bestellung gespeichert.',tr:'Sipariş kaydedildi.',en:'Order saved.'},
      saved:{de:'In „Meine Themen" gespeichert.',tr:'Meine Themen\'e kaydedildi.',en:'Saved to My Topics.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function htmlText(html){
    try{
      var m=html.match(/<div class="a4"[^>]*>([\s\S]*?)<\/div>/);
      var inner=m?m[1]:html;
      inner=inner.replace(/<br\s*\/?>/gi,'\n').replace(/<\/(p|div|h[1-6])>/gi,'\n').replace(/<[^>]+>/g,'');
      var ta=document.createElement('textarea'); ta.innerHTML=inner; var t=ta.value;
      return t.replace(/\n{3,}/g,'\n\n').trim();
    }catch(e){ return ''; }
  }
  /* ── [A] alici belgeye "An:" blogu olarak isle ── */
  function injectRecip(html,rec){
    try{
      rec=String(rec||'').trim(); if(!rec) return html;
      var first=rec.split('\n')[0].trim();
      if(first && html.indexOf(first)!==-1) return html; /* zaten var */
      var htmlBlock='<div class="ch-an" style="margin:0 0 18px 0;white-space:pre-line">'+esc(rec)+'</div>';
      var m=html.match(/(<div class="a4"[^>]*>)/);
      if(m){ return html.replace(m[1], m[1]+htmlBlock); }
      return html;
    }catch(e){ return html; }
  }
  /* ── [B] Themen'e icerik kaydet (dedup) ── */
  function saveTopic(html){ saveTopicTxt(htmlText(html)); }
  function saveTopicTxt(txt){
    try{
      txt=String(txt||'').trim(); if(txt.length<40) return;
      var title=''; var mb=txt.match(/Betreff:?\s*(.+)/i)||txt.match(/Betrifft:?\s*(.+)/i);
      if(mb) title=mb[1].trim().slice(0,70);
      if(!title){ var ls=txt.split('\n').filter(function(x){ x=x.trim(); return x.length>6 && !/^(An:|Sehr geehrte|Mit freundlichen)/i.test(x); }); title=(ls[0]||'Dokument').slice(0,70); }
      var tps=JSON.parse(localStorage.getItem('ch_topics')||'[]'); if(!Array.isArray(tps)) tps=[];
      var key=txt.slice(0,50);
      for(var i=0;i<tps.length;i++){ if(tps[i] && (tps[i].doc||tps[i].text||'').slice(0,50)===key){ tps[i].doc=txt; tps[i].ts=new Date().getTime(); localStorage.setItem('ch_topics',JSON.stringify(tps)); return; } }
      tps.push({id:'d'+new Date().getTime(),title:'📄 '+title,doc:txt,ts:new Date().getTime(),keep:1,src:'doc'});
      localStorage.setItem('ch_topics',JSON.stringify(tps.slice(-60)));
    }catch(e){}
  }

  var CTX=null; var SIG={drawing:false,has:false,ctx:null,canvas:null};
  function ovEl(){ var o=document.getElementById('ai-ov'); if(!o){ o=document.createElement('div'); o.id='ai-ov'; o.innerHTML='<div id="ai-box"></div>'; document.body.appendChild(o); o.addEventListener('click',function(e){ if(e.target===o) close(); }); } return o; }
  function close(){ var o=document.getElementById('ai-ov'); if(o) o.classList.remove('on'); }
  function setupSig(){
    var c=document.getElementById('ai-sigpad'); if(!c) return;
    try{ c.width=c.clientWidth*2; c.height=c.clientHeight*2; }catch(e){}
    var ctx=c.getContext('2d'); try{ ctx.scale(2,2); }catch(e){}
    try{ ctx.fillStyle='#fff'; ctx.fillRect(0,0,c.width,c.height); }catch(e){}
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

  function recVal(){ return String((document.getElementById('ai-rec')||{}).value||'').trim(); }
  var ORIG_BODY='';
  function bodyVal(){ var b=document.getElementById('ai-body'); return b?String(b.value||'').trim():(ORIG_BODY||htmlText(CTX.html)); }
  function bodyEdited(){ var b=document.getElementById('ai-body'); return !!(b && String(b.value||'').trim() && String(b.value||'').trim()!==ORIG_BODY.trim()); }
  /* duzenlenmis metni yazdirilacak HTML'e uygula (a4 govdesini degistir) */
  function applyEdited(html){
    try{
      if(!bodyEdited()) return html;
      var htmlBody=esc(bodyVal()).replace(/\n/g,'<br>');
      var m=html.match(/(<div class="a4"[^>]*>)([\s\S]*)(<\/div>)/);
      if(m){ return html.replace(m[0], m[1]+'<div style="white-space:normal">'+htmlBody+'</div>'+m[3]); }
    }catch(e){}
    return html;
  }
  function proceed(html){ window.__addr3OK=true; window.__addrOK=true; try{ CTX.orig.call(window,html); }catch(e){ try{ CTX.orig(html); }catch(e2){} } }
  function saveProfAddr(html){
    try{ var P=prof(), f3=(document.getElementById('ai-f3')||{}).value, f4=(document.getElementById('ai-f4')||{}).value;
      if(f3!==undefined && P.f3 && f3 && f3!==P.f3) html=html.split(P.f3).join(f3);
      if(f4!==undefined && P.f4 && f4 && f4!==P.f4) html=html.split(P.f4).join(f4);
      if(window.P){ if(f3!==undefined) window.P.f3=f3; if(f4!==undefined) window.P.f4=f4; }
      var pv=JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; if(f3!==undefined)pv.f3=f3; if(f4!==undefined)pv.f4=f4; localStorage.setItem('ch_prof_v3',JSON.stringify(pv));
    }catch(e){}
    return html;
  }
  function finalHtml(withSigPng){
    var html=saveProfAddr(CTX.html);
    html=applyEdited(html);
    html=injectRecip(html, recVal());
    if(withSigPng){ var sig=sigPng(); if(sig){ var img='<div style="margin-top:24px"><img src="'+sig+'" style="height:62px"></div>'; if(/<\/body>/i.test(html)) html=html.replace(/<\/body>/i,img+'</body>'); else html=html+img; } }
    return html;
  }
  function doFree(){ var html=finalHtml(true); saveTopicTxt(bodyVal()); close(); proceed(html); }
  function doHome(){
    var html=finalHtml(true);
    try{ var P=prof(); var a=JSON.parse(localStorage.getItem('ch_mailorig_req')||'[]'); if(!Array.isArray(a))a=[]; a.push({ts:new Date().getTime(),adr:(document.getElementById('ai-f3')||{}).value||P.f3||'',html:String(html).slice(0,20000),paid:0}); localStorage.setItem('ch_mailorig_req',JSON.stringify(a.slice(-50))); }catch(e){}
    saveTopicTxt(bodyVal()); close(); try{ alert(T('homeok')); }catch(e){}
  }
  /* [1] onceki onay: imza+alici+metin dogrula, [2] kesin onay bar'i goster */
  function doSend(btn){
    if(!SIG.has){ try{ alert(T('needsig')); }catch(e){} return; }
    var rec=recVal(); if(rec.length<8){ try{ alert(T('needrec')); }catch(e){} return; }
    if(bodyVal().length<20){ try{ alert(T('needbody')); }catch(e){} return; }
    var acts=document.querySelector('#ai-box .ai-acts'); if(!acts) return;
    if(document.getElementById('ai-confirmbar')) return;
    acts.style.display='none';
    var bar=document.createElement('div'); bar.className='ai-acts'; bar.id='ai-confirmbar';
    var frk=/widerruf|widerspruch|k(ü|ue)ndigung|einspruch|frist|mahnung/i.test(ORIG_BODY);
    bar.innerHTML='<div class="ai-warn" style="width:100%;margin-bottom:6px">'+T('confirmq').replace('{r}',esc(rec.split('\n')[0]))+'</div>'
      +'<label style="display:flex;align-items:center;gap:7px;width:100%;margin:2px 0 8px;font-size:11.5px;color:#cfe0ff;cursor:pointer"><input type="checkbox" id="ai-reg"'+(frk?' checked':'')+'> <span>'+T('reg')+(frk?'<br><span style="color:#ffcf7a">'+T('reghint')+'</span>':'')+'</span></label>'
      +'<button id="ai-do">'+T('confirmyes')+' — '+priceStr()+'</button><button id="ai-back">'+T('back')+'</button>';
    acts.parentNode.insertBefore(bar,acts.nextSibling);
    document.getElementById('ai-do').onclick=function(){ reallySend(this); };
    document.getElementById('ai-back').onclick=function(){ bar.remove(); acts.style.display=''; };
  }
  function reallySend(btn){
    var rec=recVal(); var txt=bodyVal(); saveTopicTxt(txt);
    var P=prof(); var sender=((P.f1||'')+' '+(P.f2||'')).trim()+' - '+(P.f3||'')+' - '+(P.f4||'');
    var body={text:txt,recipient:rec,sender:sender,sig_jpeg:sigJpeg(),registered:((document.getElementById('ai-reg')||{}).checked?1:0)};
    var old=btn.textContent; btn.textContent=T('sending'); btn.disabled=true;
    fetch(PVURL()+'?action=send_letter',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
     .then(function(r){ return r.json(); })
     .then(function(j){ btn.textContent=old; btn.disabled=false;
        if(j&&j.ok){ close(); try{ alert(T('sentok')); }catch(e){} } else { try{ alert(T('senderr')+((j&&(j.error||(j.result&&j.result.message)))||'?')); }catch(e){} } })
     .catch(function(){ btn.textContent=old; btn.disabled=false; try{ alert(T('senderr')+'network'); }catch(e){} });
  }
  function extractRecipient(){
    var el=document.getElementById('ai-rec'); if(!el) return;
    var txt=htmlText(CTX.html).slice(0,1600);
    var msg='Aus dem folgenden deutschen Brief die EMPFÄNGER-Anschrift extrahieren (Name/Firma/Behörde + Straße + PLZ Ort), je Zeile ein Element. Wenn es eine BEKANNTE Firma/Behörde ist (z.B. Vodafone, Telekom, Finanzamt) und die Adresse fehlt, ergänze die dir bekannte offizielle Postanschrift und markiere unsichere Angaben mit (bitte prüfen). Wenn kein Empfänger erkennbar: leere Antwort. NUR die Anschrift, keine Erklärung.\n\n'+txt;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg,history:[],provider:'deepseek',lang:'de',country:'DE'})})
     .then(function(r){ return r.json(); })
     .then(function(j){ try{ var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').trim():''; if(t&&t.length<240 && el && !el.value.trim()){ el.value=t; var wl=document.getElementById('ai-recwarn'); if(wl) wl.style.display=(/\b\d{5}\b/.test(t)?'none':''); } }catch(e){} })
     .catch(function(){});
  }
  function render(){
    var P=prof(), box=document.getElementById('ai-box'); if(!box) return;
    var wet=needsWet(CTX.html);
    ORIG_BODY=htmlText(CTX.html);
    var h='<h3>📮 '+T('ttl')+'</h3><div class="sub">'+T('sub')+'</div>';
    h+='<div class="ai-f"><label>'+T('rec')+'</label><textarea class="ai-in" id="ai-rec" rows="3" placeholder="'+esc(T('recph'))+'"></textarea><div class="aik-hint" id="ai-recwarn" style="display:none;color:#ffcf7a;font-size:11px;margin-top:3px">'+T('recwarn')+'</div></div>';
    h+='<div class="ai-f"><label>'+T('editlbl')+'</label><textarea class="ai-in" id="ai-body" rows="9" style="font-size:12px;line-height:1.5">'+esc(ORIG_BODY)+'</textarea><div class="aik-hint" style="color:#9fb4d8;font-size:10.5px;margin-top:3px">'+T('edithint')+'</div></div>';
    h+='<div class="ai-f"><label>'+T('name')+'</label><input class="ai-in" id="ai-f1" value="'+esc(((P.f1||'')+' '+(P.f2||'')).trim())+'"></div>';
    h+='<div class="ai-f"><label>'+T('adr')+'</label><input class="ai-in" id="ai-f3" value="'+esc(P.f3||'')+'"></div>';
    h+='<div class="ai-f"><label>'+T('plz')+'</label><input class="ai-in" id="ai-f4" value="'+esc(P.f4||'')+'"></div>';
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
  var g=0;(function wrap(){
    try{
      if(typeof window.chPrintDoc==='function' && !window.chPrintDoc.__addr4){
        var _cpd=window.chPrintDoc;
        var w=function(html){
          try{
            if(window.__addr3OK){ /* IMZA3/altini pass-through */ return _cpd.apply(this,arguments); }
            if(typeof html==='string' && isLetter(html)){ CTX={html:html,orig:_cpd}; ovEl().classList.add('on'); render(); return; }
          }catch(e){}
          return _cpd.apply(this,arguments);
        };
        w.__addr4=1; w.__addr3=1; w.__addr=1;
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
$tmp = tempnam(sys_get_temp_dir(),'a4').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-adresimza4-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ADRES_IMZA4')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ADRES_IMZA4 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [A] Alici belgeye 'An:' blogu olarak islenir (yoksa eklenir); taninmis\n";
echo "     firma/Behörde adresi AI ile tamamlanir + uyari.\n";
echo "✓ [B] Dilekce METNI Meine Themen'e (doc) kaydedilir -> tiklayinca icerik\n";
echo "     gorunur, PDF/duzenle/gonder yapilabilir.\n";
