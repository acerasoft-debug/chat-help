/* ChatHelp Studio — Panel „Kunden“ (contacts-lib): Kundenliste über alle Kanäle, Verlauf je Kunde (Anrufe mit Transkript, WhatsApp,
   Bestellungen, Termine, Fahrten, Hotel, Rückrufe, Benachrichtigungen), Antwort per WhatsApp (24-h-Fenster) oder Rückruf planen, Notizen.
   Nutzt die Studio-Globals api, esc, msg, go, loadPanel. Abfragen laufen nur, solange das Panel sichtbar ist. */
(function(){
  'use strict';
  var PANEL='panel-kunden';
  var g=function(id){return document.getElementById(id);};
  var h=function(v){return esc(v==null?'':String(v));};
  var X=function(v){return h(v==null?'':I18N.fx(_tx(String(v))));};
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
  var CHL={phone:_t('kuj.ch.phone'),whatsapp:'WhatsApp',web:'Website',studio:'Studio',sms:'SMS',email:_t('kuj.ch.email')};
  var KIND_ICON={call:'phone',whatsapp:'whatsapp',order:'order',appt:'appt',ride:'ride',hotel:'hotel',hotel_request:'hotel',callback:'callback',notify:null,reply:'whatsapp',note:'note'};
  var PANEL_LBL={bestellungen:_t('kuj.p.orders'),termine:_t('kuj.p.appt'),taxi:_t('kuj.p.taxi'),hotel:_t('kuj.p.hotel'),rueckruf:_t('kuj.p.cb'),phone:_t('kuj.ch.phone')};
  function chIcon(c){var k=ICON[c]?c:'studio';return '<span class="ku-ch '+h(k)+'" title="'+h(CHL[c]||c||'')+'">'+ic(k,13)+'<span class="ku-sr">'+h(CHL[c]||c||'')+'</span></span>';}
  function initials(n,p){var s=String(n||'').trim();if(!s)return String(p||'').slice(-2);var w=s.split(/\s+/);return ((w[0]||'')[0]||'').toUpperCase()+((w.length>1?w[w.length-1][0]:'')||'').toUpperCase();}
  function rel(iso){if(!iso)return '';var d=new Date(iso),n=new Date(),t=I18N.time(d);
    var day=function(x){return new Date(x.getFullYear(),x.getMonth(),x.getDate()).getTime();};var diff=Math.round((day(n)-day(d))/86400000);
    if(diff===0){var m=Math.round((n-d)/60000);return m<1?_t('kuj.now'):(m<60?_t('kuj.ago',{n:m}):_t('kuj.today',{t:t}));} if(diff===1)return _t('kuj.yest',{t:t});
    if(diff>1&&diff<7)return I18N.date(d,{weekday:'long'})+' '+t;
    return I18N.date(d,{day:'2-digit',month:'2-digit',year:d.getFullYear()===n.getFullYear()?undefined:'numeric'})+' '+t;}
  function full(iso){return iso?I18N.dt(iso,{weekday:'short',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
  function chip(label,tone){return label?'<span class="ku-chip '+h(tone||'')+'">'+X(label)+'</span>':'';}
  function live(t){var l=g('kuLive');if(l){l.textContent='';setTimeout(function(){l.textContent=t;},30);}}

  /* ---------- Liste ---------- */
  function loadList(quiet){
    var q=(g('kuSearch')||{}).value||''; var my=++seq;
    return api('/contacts/list?limit=200'+(q?'&q='+encodeURIComponent(q):'')).then(function(j){
      if(my!==seq)return; LIST=j.contacts||[]; var st=j.stats||{};
      g('kuKTotal').textContent=LIST.length>=200?'200+':String(LIST.length); g('kuKWin').textContent=String(st.window_open||0); g('kuKOpen').textContent=String(st.open_items||0);
      msg(g('kuListMsg'),''); renderList();
    }).catch(function(e){ if(!quiet) msg(g('kuListMsg'),h(e.message||_t('kuj.listErr'))+' '+_t('kuj.retry'),'err'); });
  }
  function renderList(){
    var el=g('kuList'); if(!el)return;
    var rows=LIST.filter(function(c){return FILTER==='open'?c.open_count>0:(FILTER==='wa'?c.window_open:true);});
    if(!rows.length){var q=(g('kuSearch')||{}).value||'';
      el.innerHTML='<li class="mute small" style="padding:18px 6px;text-align:center">'+(q?h(_t('kuj.noMatch',{q:q})):(FILTER!=='all'?_t('kuj.noFilter'):_t('kuj.none')))+'</li>';return;}
    el.innerHTML=rows.map(function(c){
      var nm=c.name||c.phone_masked; var cur=CUR===c.phone;
      return '<li><button type="button" class="ku-item" data-phone="'+h(c.phone)+'" aria-current="'+(cur?'true':'false')+'" aria-label="'+h((c.name?c.name+', ':'')+c.phone_masked+(c.last_seen?', '+_t('kuj.lastC',{t:rel(c.last_seen)}):'')+(c.open_count?', '+_t('htj.nOpen',{n:c.open_count}):''))+'">'+
        '<span class="ku-av" aria-hidden="true">'+h(initials(c.name,c.phone))+'</span>'+
        '<span class="ku-meta"><b>'+h(nm)+'</b><span class="ku-l2">'+(c.last_channel?chIcon(c.last_channel):'')+'<span>'+(c.name?h(c.phone_masked):X(c.last_channel_label||''))+'</span></span></span>'+
        '<span class="ku-side"><span>'+h(rel(c.last_seen))+'</span><span>'+(c.window_open?'<span class="ku-badge wa" title="'+h(_t('kuj.waOpen'))+'">WA</span> ':'')+(c.open_count?'<span class="ku-badge" title="'+h(_t('kuj.openItems'))+'">'+h(_t('htj.nOpen',{n:c.open_count}))+'</span>':'')+'</span></span></button></li>';
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
      .catch(function(e){ if(quiet||phone!==CUR)return; g('kuDetail').innerHTML='<div class="ku-body"><button type="button" class="btn s ku-back" data-ku="back">'+_t('kuj.all')+'</button><div class="msg err">'+h(e.status===404?_t('kuj.noHist'):(e.message||_t('kuj.histErr')))+'</div></div>'; });
  }
  function renderDetail(){
    var c=D.contact, det=g('kuDetail');
    det.innerHTML='<div class="ku-dh"><button type="button" class="btn s ku-back" data-ku="back" aria-label="'+h(_t('kuj.backL'))+'">'+_t('kuj.all')+'</button>'+
      '<span class="ku-av lg" aria-hidden="true" id="kuDAv"></span><div class="ku-who"><h3 id="kuDName"></h3><div class="ku-num" id="kuDNum"></div></div></div>'+
      '<div class="ku-body">'+
        '<div class="ku-sec ku-reply"><h4>'+_t('kuj.reply')+'</h4><div id="kuWin"></div>'+
          '<label class="ku-sr" for="kuText">'+_t('kuj.msgTo')+'</label><textarea id="kuText" maxlength="1500" placeholder="'+h(_t('kuj.msgPh'))+'"></textarea>'+
          '<div class="ku-actions"><button type="button" class="btn p" data-ku="send" id="kuSend">'+ic('whatsapp')+' '+_t('kuj.sendWa')+'</button><button type="button" class="btn" data-ku="cbtoggle" id="kuCbBtn" aria-expanded="false" aria-controls="kuCbf">'+ic('callback')+' '+_t('kuj.planCb')+'</button><span class="ku-count" id="kuCount">0/1500</span></div>'+
          '<div class="ku-cbf hidden" id="kuCbf"><div class="grid g2"><div><label for="kuCbTopic">'+_t('kuj.topic')+'</label><input type="text" id="kuCbTopic" maxlength="300" placeholder="'+h(_t('kuj.topicPh'))+'"></div>'+
            '<div><label for="kuCbTime">'+_t('kuj.when')+'</label><input type="text" id="kuCbTime" maxlength="60" placeholder="'+h(_t('kuj.whenPh'))+'"></div></div>'+
            '<label class="ku-chk"><input type="checkbox" id="kuCbNotify" checked> '+_t('kuj.inform')+'</label>'+
            '<div class="ku-actions"><button type="button" class="btn gold" data-ku="cb">'+_t('kuj.cbAdd')+'</button><button type="button" class="btn ghost" data-ku="cbtoggle">'+_t('c.cancel')+'</button></div></div>'+
          '<div id="kuMsg" aria-live="polite"></div></div>'+
        '<div class="ku-sec" id="kuOpenSec"><h4>'+_t('kuj.openItems')+'</h4><div class="ku-open" id="kuOpen"></div></div>'+
        '<div class="ku-sec ku-notes"><h4><label for="kuNotes" style="margin:0;font:inherit;color:inherit">'+_t('kuj.notes')+'</label></h4><textarea id="kuNotes" maxlength="4000" placeholder="'+h(_t('kuj.notesPh'))+'"></textarea>'+
          '<div class="ku-actions"><label class="ku-sr" for="kuName">'+_t('c.name')+'</label><input type="text" id="kuName" maxlength="120" placeholder="'+h(_t('kuj.namePh'))+'" style="flex:1;min-width:160px;min-height:44px;padding:10px 12px;border-radius:12px;border:1px solid var(--line2)"><button type="button" class="btn" data-ku="note">'+_t('c.save')+'</button></div><div id="kuNoteMsg" aria-live="polite"></div></div>'+
        '<div class="ku-sec"><h4>'+_t('kuj.hist')+'</h4><ol class="ku-tl" id="kuTl" aria-label="'+h(_t('kuj.histL'))+'"></ol><div id="kuMoreWrap"></div></div>'+
      '</div>';
    g('kuNotes').value=c.notes||''; g('kuName').value=c.name||'';
    var t=g('kuText'); t.addEventListener('input',function(){g('kuCount').textContent=t.value.length+'/1500';});
    t.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();send();}});
    lastSig=''; refreshDetail();
  }
  function refreshDetail(){
    if(!D||!g('kuTl'))return; var c=D.contact;
    g('kuDAv').textContent=initials(c.name,c.phone); g('kuDName').textContent=c.name||c.phone_display;
    g('kuDNum').innerHTML='<a href="tel:'+h(c.phone)+'" aria-label="'+h(_t('kuj.callL',{n:c.phone_display}))+'">'+h(c.phone_display)+'</a>'+(c.channels||[]).map(function(x){return chIcon(x);}).join('')+
      (c.last_seen?'<span class="ku-m">'+h(_t('kuj.lastSeen',{t:rel(c.last_seen)}))+'</span>':'')+((c.emails||[])[0]?'<span class="ku-m">'+h(c.emails[0])+'</span>':'');
    var wa=D.whatsapp||{}, w=g('kuWin'), s=g('kuSend');
    if(!wa.ready){ w.innerHTML='<div class="ku-win off">'+ic('whatsapp',18)+'<div>'+_t('kuj.waOff')+'</div></div>'; s.disabled=true; }
    else if(wa.window_open){ w.innerHTML='<div class="ku-win open">'+ic('whatsapp',18)+'<div>'+_t('kuj.waWin',{t:h(I18N.dt(wa.expires_at,{weekday:'short',hour:'2-digit',minute:'2-digit'}))})+'</div></div>'; s.disabled=false; }
    else { w.innerHTML='<div class="ku-win closed">'+ic('whatsapp',18)+'<div>'+(D.sms_ready?_t('kuj.waClosedSms'):_t('kuj.waClosed'))+'</div></div>'; s.disabled=true; }
    s.setAttribute('aria-disabled',s.disabled?'true':'false');
    var cbb=g('kuCbBtn'); if(cbb){ cbb.disabled=!D.callbacks_on; cbb.title=D.callbacks_on?'':_t('kuj.cbOff'); }
    /* offene Vorgänge */
    var op=D.open||[]; g('kuOpenSec').classList.toggle('hidden',!op.length);
    g('kuOpen').innerHTML=op.map(function(i){return '<div class="ku-oc"><div class="ku-oct"><span class="ku-ch '+h(i.channel)+'">'+ic(KIND_ICON[i.kind]||'studio',13)+'</span>'+chip(i.status_label,i.tone)+'</div><b>'+X(i.title)+'</b><span class="mute small">'+X(i.sub)+'</span>'+
      (i.panel&&PANEL_LBL[i.panel]?'<button type="button" class="btn s" data-ku="go" data-panel="'+h(i.panel)+'" data-code="'+h(i.code||'')+'">'+h(_t('kuj.openIn',{p:PANEL_LBL[i.panel]}))+'</button>':'')+'</div>';}).join('');
    /* Verlauf */
    var items=D.items||[], lim=showAll?items.length:30, sig=JSON.stringify([items,lim]); if(sig===lastSig)return; lastSig=sig;
    var keep={}; g('kuTl').querySelectorAll('details[open]').forEach(function(x){keep[x.getAttribute('data-k')]=1;});
    g('kuTl').innerHTML=items.length?items.slice(0,lim).map(evHtml).join(''):'<li class="mute small">'+_t('kuj.noEntries')+'</li>';
    g('kuTl').querySelectorAll('details').forEach(function(x){if(keep[x.getAttribute('data-k')])x.open=true;});
    g('kuMoreWrap').innerHTML=items.length>lim?'<button type="button" class="btn ku-more" data-ku="more">'+h(_t('kuj.older',{n:items.length-lim}))+'</button>':'';
  }
  function evHtml(i){
    var dotK=i.kind==='notify'?(i.channel==='whatsapp_ohne_fenster'?'whatsapp':i.channel):(KIND_ICON[i.kind]||'studio'), dotC=['notify','reply','note'].indexOf(i.kind)>=0?(i.channel||'studio'):i.channel;
    var body='';
    if(i.kind==='call'&&(i.transcript||[]).length) body+='<details data-k="c'+i.id+'"><summary>'+h(_t('kuj.trans',{n:i.transcript.length}))+'</summary><div class="ku-bub">'+i.transcript.map(function(m){return '<div class="'+(m.role==='user'?'u':'a')+'"><span class="lb">'+(m.role==='user'?(i.direction==='out'?_t('kuj.callee'):_t('kuj.caller')):_t('kuj.agent'))+'</span>'+h(m.content)+'</div>';}).join('')+'</div></details>';
    if(i.kind==='whatsapp'&&(i.messages||[]).length) body+='<details data-k="w'+i.id+'"><summary>'+h(_t('kuj.msgs',{n:i.messages.length}))+'</summary><div class="ku-bub">'+i.messages.map(function(m){return '<div class="'+(m.role==='user'?'u':'a')+'"><span class="lb">'+(m.role==='user'?_t('kuj.cust'):_t('kuj.agent'))+'</span>'+h(m.content)+'</div>';}).join('')+'</div></details>';
    if(i.body) body+='<div class="ku-evx">'+h(i.body)+'</div>';
    if(i.kind==='notify'&&i.status==='failed') body+='<div class="ku-warn">'+h(i.tried&&i.tried.length?_t('kuj.notDelT',{l:i.tried.map(function(x){return CHL[x]||(x==='whatsapp_ohne_fenster'?_t('kuj.waOut'):x);}).join(', ')}):_t('kuj.notDel'))+'</div>';
    if(i.verified===false&&['order','appt','ride','hotel','hotel_request','callback'].indexOf(i.kind)>=0) body+='<div class="mute small" style="margin-top:4px">'+_t('kuj.unverified')+'</div>';
    var act=i.panel&&PANEL_LBL[i.panel]&&i.kind!=='call'?'<div style="margin-top:6px"><button type="button" class="btn s ghost" style="padding-left:0" data-ku="go" data-panel="'+h(i.panel)+'" data-code="'+h(i.code||'')+'">'+h(_t('kuj.openIn',{p:PANEL_LBL[i.panel]}))+'</button></div>':'';
    return '<li class="ku-ev"><span class="ku-dot '+h(dotC||'')+'" aria-hidden="true">'+ic(dotK||'studio',16)+'</span><div class="ku-evb"><div class="ku-evh"><b>'+X(i.title)+'</b>'+chip(i.status_label,i.tone)+
      '<time datetime="'+h(i.at||'')+'" title="'+h(full(i.at))+'">'+h(rel(i.at))+'</time></div>'+(i.sub?'<div class="ku-evs">'+X(i.sub)+'</div>':'')+body+act+'</div></li>';
  }

  /* ---------- Aktionen ---------- */
  function setBusy(b,on,label){ if(!b)return; if(on){b.dataset.l=b.innerHTML;b.disabled=true;b.textContent=label||_t('c.wait');} else {b.disabled=false;if(b.dataset.l)b.innerHTML=b.dataset.l;} }
  function send(){
    var t=g('kuText'), b=g('kuSend'), m=g('kuMsg'); if(!t||busy)return; var text=t.value.trim();
    if(!text){msg(m,_t('kuj.writeFirst'),'err');t.focus();return;}
    if(b.disabled){msg(m,_t('kuj.cantSend'),'info');return;}
    busy=true; setBusy(b,true,_t('rrj.f.sending'));
    api('/contacts/reply',{method:'POST',body:JSON.stringify({phone:CUR,text:text})}).then(function(j){ t.value=''; g('kuCount').textContent='0/1500'; msg(m,h((I18N.lang==='de'&&j.message)||_t('onbj.sent')),'ok'); live(_t('kuj.sentLive')); return loadDetail(true); })
      .catch(function(e){ var d=e.data||{}; msg(m,h(e.message||_t('kuj.sendFail'))+(d.callback_possible?' <button type="button" class="btn s" data-ku="cbtoggle" style="margin-left:6px">'+_t('kuj.planCb')+'</button>':''),'err'); if(e.status===409)loadDetail(true); })
      .finally(function(){ busy=false; setBusy(b,false); refreshDetail(); });
  }
  function toggleCb(force){ var f=g('kuCbf'), b=g('kuCbBtn'); if(!f)return; var show=force!=null?force:f.classList.contains('hidden'); f.classList.toggle('hidden',!show); if(b)b.setAttribute('aria-expanded',show?'true':'false'); if(show){var tp=g('kuCbTopic');if(tp)tp.focus();} }
  function planCb(btn){
    var m=g('kuMsg'); if(busy)return; busy=true; setBusy(btn,true,_t('kuj.adding'));
    var body={phone:CUR,action:'callback',topic:(g('kuCbTopic').value||'').trim(),preferred_time:(g('kuCbTime').value||'').trim(),notify:!!g('kuCbNotify').checked};
    api('/contacts/reply',{method:'POST',body:JSON.stringify(body)}).then(function(j){
      var nt={whatsapp:' '+_t('kuj.nWa'),sms:' '+_t('kuj.nSms'),email:' '+_t('kuj.nMail')}[j.notified]||(body.notify?' '+_t('kuj.nFail'):'');
      msg(m,h(((I18N.lang==='de'&&j.message)||_t('kuj.cbDone'))+nt)+(j.hint?'<br><span class="small">'+h(_tx(j.hint))+'</span>':''),'ok'); live(_t('kuj.cbDone')); g('kuCbTopic').value=''; g('kuCbTime').value=''; toggleCb(false); return loadDetail(true); })
      .catch(function(e){ msg(m,h((I18N.lang==='de'&&e.data&&(e.data.message||e.data.hint))||e.message||_t('kuj.cbFail')),'err'); })
      .finally(function(){ busy=false; setBusy(btn,false); });
  }
  function saveNote(btn){
    var m=g('kuNoteMsg'); setBusy(btn,true,_t('kuj.savingN'));
    api('/contacts/note',{method:'POST',body:JSON.stringify({phone:CUR,notes:g('kuNotes').value,name:g('kuName').value})}).then(function(j){ msg(m,_t('c.savedDot'),'ok'); if(D){D.contact.notes=j.notes;D.contact.name=j.name;} refreshDetail(); loadList(true); setTimeout(function(){msg(m,'');},2500); })
      .catch(function(e){ msg(m,h(e.message||_t('c.saveFail')),'err'); }).finally(function(){ setBusy(btn,false); });
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
