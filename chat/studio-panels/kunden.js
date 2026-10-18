/* ChatHelp Studio — Panel „Kunden“ (contacts-lib): Kundenliste über alle Kanäle, Verlauf je Kunde (Anrufe mit Transkript, WhatsApp,
   Bestellungen, Termine, Fahrten, Hotel, Rückrufe, Benachrichtigungen), Antwort per WhatsApp (24-h-Fenster) oder Rückruf planen, Notizen.
   Nutzt die Studio-Globals api, esc, msg, go, loadPanel. Abfragen laufen nur, solange das Panel sichtbar ist. */
(function(){
  'use strict';
  var PANEL='panel-kunden';
  var g=function(id){return document.getElementById(id);};
  var h=function(v){return esc(v==null?'':String(v));};
  var visible=function(){var el=g(PANEL);return !!el&&!el.closest('.hidden')&&!document.hidden;};
  var LIST=[], FILTER='all', CUR=null, D=null, listTimer=null, detTimer=null, qTimer=null, busy=false, showAll=false, seq=0, lastSig='';
  var ICON={
    phone:'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    whatsapp:'<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5.1A8.5 8.5 0 1 1 21 11.5Z"/><path d="M9 9.5c.3 2 2 4 4.5 4.8"/>',
    web:'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    studio:'<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8Z"/>',
    sms:'<rect x="3" y="5" width="18" height="13" rx="3"/><path d="M8 11h.01M12 11h.01M16 11h.01"/>',
    email:'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
    order:'<path d="M6 8h12l-1 12H7Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
    appt:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    ride:'<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/><circle cx="7.5" cy="16" r="1.5"/><circle cx="16.5" cy="16" r="1.5"/>',
    hotel:'<path d="M3 18V8M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/>',
    callback:'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/><path d="M15 3v6h6"/>',
    note:'<path d="M4 20h4L19 9l-4-4L4 16Z"/>'
  };
  function ic(k,s){return '<svg width="'+(s||16)+'" height="'+(s||16)+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(ICON[k]||ICON.studio)+'</svg>';}
  var CHL={phone:'Telefon',whatsapp:'WhatsApp',web:'Website',studio:'Studio',sms:'SMS',email:'E-Mail'};
  var KIND_ICON={call:'phone',whatsapp:'whatsapp',order:'order',appt:'appt',ride:'ride',hotel:'hotel',hotel_request:'hotel',callback:'callback',notify:null,reply:'whatsapp',note:'note'};
  var PANEL_LBL={bestellungen:'Bestellungen',termine:'Termine',taxi:'Taxi',hotel:'Hotel',rueckruf:'Rückrufe',phone:'Telefon'};
  function chIcon(c){var k=ICON[c]?c:'studio';return '<span class="ku-ch '+h(k)+'" title="'+h(CHL[c]||c||'')+'">'+ic(k,13)+'<span class="ku-sr">'+h(CHL[c]||c||'')+'</span></span>';}
  function initials(n,p){var s=String(n||'').trim();if(!s)return String(p||'').slice(-2);var w=s.split(/\s+/);return ((w[0]||'')[0]||'').toUpperCase()+((w.length>1?w[w.length-1][0]:'')||'').toUpperCase();}
  function rel(iso){if(!iso)return '';var d=new Date(iso),n=new Date(),t=d.toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});
    var day=function(x){return new Date(x.getFullYear(),x.getMonth(),x.getDate()).getTime();};var diff=Math.round((day(n)-day(d))/86400000);
    if(diff===0){var m=Math.round((n-d)/60000);return m<1?'gerade eben':(m<60?'vor '+m+' Min.':'heute '+t);} if(diff===1)return 'gestern '+t;
    if(diff>1&&diff<7)return d.toLocaleDateString('de-DE',{weekday:'long'})+' '+t;
    return d.toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit',year:d.getFullYear()===n.getFullYear()?undefined:'numeric'})+' '+t;}
  function full(iso){return iso?new Date(iso).toLocaleString('de-DE',{weekday:'short',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
  function chip(label,tone){return label?'<span class="ku-chip '+h(tone||'')+'">'+h(label)+'</span>':'';}
  function live(t){var l=g('kuLive');if(l){l.textContent='';setTimeout(function(){l.textContent=t;},30);}}

  /* ---------- Liste ---------- */
  function loadList(quiet){
    var q=(g('kuSearch')||{}).value||''; var my=++seq;
    return api('/contacts/list?limit=200'+(q?'&q='+encodeURIComponent(q):'')).then(function(j){
      if(my!==seq)return; LIST=j.contacts||[]; var st=j.stats||{};
      g('kuKTotal').textContent=LIST.length>=200?'200+':String(LIST.length); g('kuKWin').textContent=String(st.window_open||0); g('kuKOpen').textContent=String(st.open_items||0);
      msg(g('kuListMsg'),''); renderList();
    }).catch(function(e){ if(!quiet) msg(g('kuListMsg'),h(e.message||'Liste konnte nicht geladen werden.')+' Bitte gleich noch einmal versuchen.','err'); });
  }
  function renderList(){
    var el=g('kuList'); if(!el)return;
    var rows=LIST.filter(function(c){return FILTER==='open'?c.open_count>0:(FILTER==='wa'?c.window_open:true);});
    if(!rows.length){var q=(g('kuSearch')||{}).value||'';
      el.innerHTML='<li class="mute small" style="padding:18px 6px;text-align:center">'+(q?'Kein Kunde passt zu „'+h(q)+'“.':(FILTER!=='all'?'Niemand in diesem Filter.':'Noch keine Kunden. Sobald jemand anruft, per WhatsApp schreibt oder über die Website bestellt bzw. bucht, erscheint er hier.'))+'</li>';return;}
    el.innerHTML=rows.map(function(c){
      var nm=c.name||c.phone_masked; var cur=CUR===c.phone;
      return '<li><button type="button" class="ku-item" data-phone="'+h(c.phone)+'" aria-current="'+(cur?'true':'false')+'" aria-label="'+h((c.name?c.name+', ':'')+c.phone_masked+(c.last_seen?', letzter Kontakt '+rel(c.last_seen):'')+(c.open_count?', '+c.open_count+' offen':''))+'">'+
        '<span class="ku-av" aria-hidden="true">'+h(initials(c.name,c.phone))+'</span>'+
        '<span class="ku-meta"><b>'+h(nm)+'</b><span class="ku-l2">'+(c.last_channel?chIcon(c.last_channel):'')+'<span>'+h(c.name?c.phone_masked:(c.last_channel_label||''))+'</span></span></span>'+
        '<span class="ku-side"><span>'+h(rel(c.last_seen))+'</span><span>'+(c.window_open?'<span class="ku-badge wa" title="WhatsApp-Fenster offen">WA</span> ':'')+(c.open_count?'<span class="ku-badge" title="Offene Vorgänge">'+c.open_count+' offen</span>':'')+'</span></span></button></li>';
    }).join('');
  }

  /* ---------- Detail ---------- */
  function open(phone,focus){
    if(!phone)return; if(CUR!==phone){CUR=phone;D=null;showAll=false;lastSig='';}
    g(PANEL).querySelector('#kuRoot').classList.add('ku-show'); renderList();
    var det=g('kuDetail'); if(!D) det.innerHTML='<div class="ku-body"><div class="ku-skel"></div><div class="ku-skel"></div><div class="ku-skel"></div></div>';
    return loadDetail(false).then(function(){ if(focus){var hd=det.querySelector('h3');if(hd){hd.setAttribute('tabindex','-1');hd.focus({preventScroll:false});}} if(window.innerWidth<=900)window.scrollTo({top:0}); });
  }
  function back(){ var r=g(PANEL).querySelector('#kuRoot'); r.classList.remove('ku-show'); var b=g(PANEL).querySelector('.ku-item[data-phone="'+(CUR||'').replace(/"/g,'')+'"]'); if(b)b.focus(); }
  function loadDetail(quiet){
    var phone=CUR; if(!phone)return Promise.resolve();
    return api('/contacts/get?phone='+encodeURIComponent(phone)).then(function(j){ if(phone!==CUR)return; var first=!D; D=j; if(first) renderDetail(); else refreshDetail(); })
      .catch(function(e){ if(quiet||phone!==CUR)return; g('kuDetail').innerHTML='<div class="ku-body"><button type="button" class="btn s ku-back" data-ku="back">← Alle Kunden</button><div class="msg err">'+h(e.status===404?'Zu dieser Nummer gibt es keinen Verlauf.':(e.message||'Verlauf konnte nicht geladen werden.'))+'</div></div>'; });
  }
  function renderDetail(){
    var c=D.contact, det=g('kuDetail');
    det.innerHTML='<div class="ku-dh"><button type="button" class="btn s ku-back" data-ku="back" aria-label="Zurück zur Kundenliste">← Alle Kunden</button>'+
      '<span class="ku-av lg" aria-hidden="true" id="kuDAv"></span><div class="ku-who"><h3 id="kuDName"></h3><div class="ku-num" id="kuDNum"></div></div></div>'+
      '<div class="ku-body">'+
        '<div class="ku-sec ku-reply"><h4>Antworten</h4><div id="kuWin"></div>'+
          '<label class="ku-sr" for="kuText">Nachricht an den Kunden</label><textarea id="kuText" maxlength="1500" placeholder="Nachricht per WhatsApp …"></textarea>'+
          '<div class="ku-actions"><button type="button" class="btn p" data-ku="send" id="kuSend">'+ic('whatsapp')+' Per WhatsApp senden</button><button type="button" class="btn" data-ku="cbtoggle" id="kuCbBtn" aria-expanded="false" aria-controls="kuCbf">'+ic('callback')+' Rückruf planen</button><span class="ku-count" id="kuCount">0/1500</span></div>'+
          '<div class="ku-cbf hidden" id="kuCbf"><div class="grid g2"><div><label for="kuCbTopic">Anliegen</label><input type="text" id="kuCbTopic" maxlength="300" placeholder="z. B. Rückfrage zur Bestellung"></div>'+
            '<div><label for="kuCbTime">Wann?</label><input type="text" id="kuCbTime" maxlength="60" placeholder="so schnell wie möglich · heute 16 Uhr · morgen vormittag"></div></div>'+
            '<label class="ku-chk"><input type="checkbox" id="kuCbNotify" checked> Kunden über den Rückruf informieren (WhatsApp/SMS/E-Mail)</label>'+
            '<div class="ku-actions"><button type="button" class="btn gold" data-ku="cb">Rückruf eintragen</button><button type="button" class="btn ghost" data-ku="cbtoggle">Abbrechen</button></div></div>'+
          '<div id="kuMsg" aria-live="polite"></div></div>'+
        '<div class="ku-sec" id="kuOpenSec"><h4>Offene Vorgänge</h4><div class="ku-open" id="kuOpen"></div></div>'+
        '<div class="ku-sec ku-notes"><h4><label for="kuNotes" style="margin:0;font:inherit;color:inherit">Notizen (nur intern)</label></h4><textarea id="kuNotes" maxlength="4000" placeholder="z. B. bevorzugt Abholung, allergisch gegen Nüsse, Stammkunde …"></textarea>'+
          '<div class="ku-actions"><label class="ku-sr" for="kuName">Name</label><input type="text" id="kuName" maxlength="120" placeholder="Name des Kunden" style="flex:1;min-width:160px;min-height:44px;padding:10px 12px;border-radius:12px;border:1px solid var(--line2)"><button type="button" class="btn" data-ku="note">Speichern</button></div><div id="kuNoteMsg" aria-live="polite"></div></div>'+
        '<div class="ku-sec"><h4>Verlauf</h4><ol class="ku-tl" id="kuTl" aria-label="Verlauf, neueste zuerst"></ol><div id="kuMoreWrap"></div></div>'+
      '</div>';
    g('kuNotes').value=c.notes||''; g('kuName').value=c.name||'';
    var t=g('kuText'); t.addEventListener('input',function(){g('kuCount').textContent=t.value.length+'/1500';});
    t.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();send();}});
    lastSig=''; refreshDetail();
  }
  function refreshDetail(){
    if(!D||!g('kuTl'))return; var c=D.contact;
    g('kuDAv').textContent=initials(c.name,c.phone); g('kuDName').textContent=c.name||c.phone_display;
    g('kuDNum').innerHTML='<a href="tel:'+h(c.phone)+'" aria-label="Anrufen: '+h(c.phone_display)+'">'+h(c.phone_display)+'</a>'+(c.channels||[]).map(function(x){return chIcon(x);}).join('')+
      (c.last_seen?'<span class="ku-m">zuletzt '+h(rel(c.last_seen))+'</span>':'')+((c.emails||[])[0]?'<span class="ku-m">'+h(c.emails[0])+'</span>':'');
    var wa=D.whatsapp||{}, w=g('kuWin'), s=g('kuSend');
    if(!wa.ready){ w.innerHTML='<div class="ku-win off">'+ic('whatsapp',18)+'<div><b>WhatsApp ist nicht verbunden.</b> Verbinde WhatsApp unter <a href="#bestellungen" data-ku="go" data-panel="bestellungen">Bestellungen → WhatsApp</a>. Bis dahin kannst du einen Rückruf planen.</div></div>'; s.disabled=true; }
    else if(wa.window_open){ w.innerHTML='<div class="ku-win open">'+ic('whatsapp',18)+'<div><b>WhatsApp-Fenster offen</b> – freie Nachrichten sind bis '+h(new Date(wa.expires_at).toLocaleString('de-DE',{weekday:'short',hour:'2-digit',minute:'2-digit'}))+' Uhr zustellbar. Dein Text landet auch im Gesprächsverlauf des Agenten.</div></div>'; s.disabled=false; }
    else { w.innerHTML='<div class="ku-win closed">'+ic('whatsapp',18)+'<div><b>WhatsApp-Fenster geschlossen.</b> Der Kunde hat in den letzten 24 Stunden nicht per WhatsApp geschrieben – freie Nachrichten werden dann nicht zugestellt. Plane einen Rückruf'+(D.sms_ready?' (der Kunde wird per SMS informiert)':'')+' oder warte, bis er wieder schreibt.</div></div>'; s.disabled=true; }
    s.setAttribute('aria-disabled',s.disabled?'true':'false');
    var cbb=g('kuCbBtn'); if(cbb){ cbb.disabled=!D.callbacks_on; cbb.title=D.callbacks_on?'':'Rückrufe sind ausgeschaltet (Studio → Rückrufe).'; }
    /* offene Vorgänge */
    var op=D.open||[]; g('kuOpenSec').classList.toggle('hidden',!op.length);
    g('kuOpen').innerHTML=op.map(function(i){return '<div class="ku-oc"><div class="ku-oct"><span class="ku-ch '+h(i.channel)+'">'+ic(KIND_ICON[i.kind]||'studio',13)+'</span>'+chip(i.status_label,i.tone)+'</div><b>'+h(i.title)+'</b><span class="mute small">'+h(i.sub)+'</span>'+
      (i.panel&&PANEL_LBL[i.panel]?'<button type="button" class="btn s" data-ku="go" data-panel="'+h(i.panel)+'" data-code="'+h(i.code||'')+'">In „'+h(PANEL_LBL[i.panel])+'“ öffnen →</button>':'')+'</div>';}).join('');
    /* Verlauf */
    var items=D.items||[], lim=showAll?items.length:30, sig=JSON.stringify([items,lim]); if(sig===lastSig)return; lastSig=sig;
    var keep={}; g('kuTl').querySelectorAll('details[open]').forEach(function(x){keep[x.getAttribute('data-k')]=1;});
    g('kuTl').innerHTML=items.length?items.slice(0,lim).map(evHtml).join(''):'<li class="mute small">Noch keine Einträge.</li>';
    g('kuTl').querySelectorAll('details').forEach(function(x){if(keep[x.getAttribute('data-k')])x.open=true;});
    g('kuMoreWrap').innerHTML=items.length>lim?'<button type="button" class="btn ku-more" data-ku="more">Ältere Einträge anzeigen ('+(items.length-lim)+')</button>':'';
  }
  function evHtml(i){
    var dotK=i.kind==='notify'?(i.channel==='whatsapp_ohne_fenster'?'whatsapp':i.channel):(KIND_ICON[i.kind]||'studio'), dotC=['notify','reply','note'].indexOf(i.kind)>=0?(i.channel||'studio'):i.channel;
    var body='';
    if(i.kind==='call'&&(i.transcript||[]).length) body+='<details data-k="c'+i.id+'"><summary>Transkript anzeigen ('+i.transcript.length+')</summary><div class="ku-bub">'+i.transcript.map(function(m){return '<div class="'+(m.role==='user'?'u':'a')+'"><span class="lb">'+(m.role==='user'?(i.direction==='out'?'Angerufener':'Anrufer'):'Agent')+'</span>'+h(m.content)+'</div>';}).join('')+'</div></details>';
    if(i.kind==='whatsapp'&&(i.messages||[]).length) body+='<details data-k="w'+i.id+'"><summary>Nachrichten anzeigen ('+i.messages.length+')</summary><div class="ku-bub">'+i.messages.map(function(m){return '<div class="'+(m.role==='user'?'u':'a')+'"><span class="lb">'+(m.role==='user'?'Kunde':'Agent')+'</span>'+h(m.content)+'</div>';}).join('')+'</div></details>';
    if(i.body) body+='<div class="ku-evx">'+h(i.body)+'</div>';
    if(i.kind==='notify'&&i.status==='failed') body+='<div class="ku-warn">Nicht zugestellt'+(i.tried&&i.tried.length?' (versucht: '+h(i.tried.map(function(x){return CHL[x]||x.replace('whatsapp_ohne_fenster','WhatsApp außerhalb des Fensters');}).join(', '))+')':'')+'.</div>';
    if(i.verified===false&&['order','appt','ride','hotel','hotel_request','callback'].indexOf(i.kind)>=0) body+='<div class="mute small" style="margin-top:4px">Nummer auf der Website eingetippt – nicht vom Kanal bestätigt.</div>';
    var act=i.panel&&PANEL_LBL[i.panel]&&i.kind!=='call'?'<div style="margin-top:6px"><button type="button" class="btn s ghost" style="padding-left:0" data-ku="go" data-panel="'+h(i.panel)+'" data-code="'+h(i.code||'')+'">In „'+h(PANEL_LBL[i.panel])+'“ öffnen →</button></div>':'';
    return '<li class="ku-ev"><span class="ku-dot '+h(dotC||'')+'" aria-hidden="true">'+ic(dotK||'studio',16)+'</span><div class="ku-evb"><div class="ku-evh"><b>'+h(i.title)+'</b>'+chip(i.status_label,i.tone)+
      '<time datetime="'+h(i.at||'')+'" title="'+h(full(i.at))+'">'+h(rel(i.at))+'</time></div>'+(i.sub?'<div class="ku-evs">'+h(i.sub)+'</div>':'')+body+act+'</div></li>';
  }

  /* ---------- Aktionen ---------- */
  function setBusy(b,on,label){ if(!b)return; if(on){b.dataset.l=b.innerHTML;b.disabled=true;b.textContent=label||'Bitte warten …';} else {b.disabled=false;if(b.dataset.l)b.innerHTML=b.dataset.l;} }
  function send(){
    var t=g('kuText'), b=g('kuSend'), m=g('kuMsg'); if(!t||busy)return; var text=t.value.trim();
    if(!text){msg(m,'Bitte zuerst eine Nachricht schreiben.','err');t.focus();return;}
    if(b.disabled){msg(m,'Senden ist gerade nicht möglich – siehe Hinweis oben. Du kannst einen Rückruf planen.','info');return;}
    busy=true; setBusy(b,true,'Wird gesendet …');
    api('/contacts/reply',{method:'POST',body:JSON.stringify({phone:CUR,text:text})}).then(function(j){ t.value=''; g('kuCount').textContent='0/1500'; msg(m,h(j.message||'Gesendet.'),'ok'); live('Nachricht gesendet.'); return loadDetail(true); })
      .catch(function(e){ var d=e.data||{}; msg(m,h(d.message||e.message||'Senden fehlgeschlagen.')+(d.callback_possible?' <button type="button" class="btn s" data-ku="cbtoggle" style="margin-left:6px">Rückruf planen</button>':''),'err'); if(e.status===409)loadDetail(true); })
      .finally(function(){ busy=false; setBusy(b,false); refreshDetail(); });
  }
  function toggleCb(force){ var f=g('kuCbf'), b=g('kuCbBtn'); if(!f)return; var show=force!=null?force:f.classList.contains('hidden'); f.classList.toggle('hidden',!show); if(b)b.setAttribute('aria-expanded',show?'true':'false'); if(show){var tp=g('kuCbTopic');if(tp)tp.focus();} }
  function planCb(btn){
    var m=g('kuMsg'); if(busy)return; busy=true; setBusy(btn,true,'Wird eingetragen …');
    var body={phone:CUR,action:'callback',topic:(g('kuCbTopic').value||'').trim(),preferred_time:(g('kuCbTime').value||'').trim(),notify:!!g('kuCbNotify').checked};
    api('/contacts/reply',{method:'POST',body:JSON.stringify(body)}).then(function(j){
      var nt={whatsapp:' Der Kunde wurde per WhatsApp informiert.',sms:' Der Kunde wurde per SMS informiert.',email:' Der Kunde wurde per E-Mail informiert.'}[j.notified]||(body.notify?' Der Kunde konnte nicht automatisch informiert werden.':'');
      msg(m,h((j.message||'Rückruf eingetragen.')+nt)+(j.hint?'<br><span class="small">'+h(j.hint)+'</span>':''),'ok'); live('Rückruf eingetragen.'); g('kuCbTopic').value=''; g('kuCbTime').value=''; toggleCb(false); return loadDetail(true); })
      .catch(function(e){ msg(m,h((e.data&&(e.data.message||e.data.hint))||e.message||'Rückruf konnte nicht eingetragen werden.'),'err'); })
      .finally(function(){ busy=false; setBusy(btn,false); });
  }
  function saveNote(btn){
    var m=g('kuNoteMsg'); setBusy(btn,true,'Speichert …');
    api('/contacts/note',{method:'POST',body:JSON.stringify({phone:CUR,notes:g('kuNotes').value,name:g('kuName').value})}).then(function(j){ msg(m,'Gespeichert.','ok'); if(D){D.contact.notes=j.notes;D.contact.name=j.name;} refreshDetail(); loadList(true); setTimeout(function(){msg(m,'');},2500); })
      .catch(function(e){ msg(m,h(e.message||'Speichern fehlgeschlagen.'),'err'); }).finally(function(){ setBusy(btn,false); });
  }
  function goPanel(p,code){
    if(typeof go!=='function')return; go(p);
    if(!code)return; var tries=0;   /* Vorgang im Ziel-Panel hervorheben, sobald er geladen ist */
    var t=setInterval(function(){ tries++; var root=p==='bestellungen'?g('pBestellungen'):(p==='termine'?g('pTermine'):g('panel-'+p)); if(root){ var w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT,null), n;
      while((n=w.nextNode())){ if(n.nodeValue&&n.nodeValue.indexOf(code)>=0){ var el=n.parentElement.closest('.card,.call,.row,tr,li,div')||n.parentElement; el.scrollIntoView({block:'center',behavior:'smooth'}); el.style.transition='box-shadow .3s'; el.style.boxShadow='0 0 0 3px rgba(184,134,43,.55)'; setTimeout(function(){el.style.boxShadow='';},2600); clearInterval(t); return; } } }
      if(tries>20) clearInterval(t); },250);
  }
  function bind(){
    var root=g(PANEL);
    root.addEventListener('click',function(e){
      var it=e.target.closest('.ku-item'); if(it){ open(it.getAttribute('data-phone'),true); return; }
      var a=e.target.closest('[data-ku]'); if(!a)return; var k=a.getAttribute('data-ku');
      if(k==='back'){back();} else if(k==='send'){send();} else if(k==='cbtoggle'){toggleCb();} else if(k==='cb'){planCb(a);} else if(k==='note'){saveNote(a);}
      else if(k==='more'){showAll=true;refreshDetail();var tl=g('kuTl');if(tl&&tl.children[30]){var f=tl.children[30].querySelector('b');if(f){f.setAttribute('tabindex','-1');f.focus();}}} else if(k==='go'){e.preventDefault();goPanel(a.getAttribute('data-panel'),a.getAttribute('data-code'));}
    });
    root.querySelectorAll('[data-kuf]').forEach(function(b){ b.addEventListener('click',function(){ FILTER=b.getAttribute('data-kuf'); root.querySelectorAll('[data-kuf]').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');}); renderList(); }); });
    var s=g('kuSearch'); s.addEventListener('input',function(){ clearTimeout(qTimer); qTimer=setTimeout(function(){loadList(false);},280); });
    s.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); var f=root.querySelector('.ku-item'); if(f)f.focus(); } });
    document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&visible()&&root.querySelector('#kuRoot.ku-show')&&window.innerWidth<=900){ var ae=document.activeElement; if(!ae||!/TEXTAREA|INPUT/.test(ae.tagName))back(); } });
    document.addEventListener('visibilitychange',function(){ if(visible()){ loadList(true); if(CUR)loadDetail(true); } });
  }
  function timers(){
    if(!listTimer) listTimer=setInterval(function(){ if(visible()) loadList(true); },30000);
    if(!detTimer) detTimer=setInterval(function(){ if(!visible()||!CUR||busy)return; var ae=document.activeElement; if(ae&&ae.closest&&ae.closest('#kuDetail details'))return; loadDetail(true); },15000);
  }
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS.kunden={
    init:function(){ bind(); loadList(false).then(function(){ var m=/[?&]kunde=([^&#]+)/.exec(location.search); if(m) open(decodeURIComponent(m[1]),false); }); timers(); },
    show:function(){ loadList(true); if(CUR) loadDetail(true); timers(); },
    open:function(phone){ return open(phone,true); }
  };
})();
