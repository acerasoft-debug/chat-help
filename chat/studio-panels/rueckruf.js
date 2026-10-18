/* ChatHelp Studio — Panel „Rückrufe & Lead-Sofortrückruf“: Einstellungen (KI-Rückruf, anrufender Agent, Anrufzeiten, Versuche), Einbinden
   (Formular-Link, HTML-Formular, JSON für Portale/Zapier), Rückrufliste mit Offen/Erledigt/Alle, Aktionen und Gesprächsprotokollen.
   Nutzt die Studio-Globals api(), msg(), esc(), de(), PLANS, active(). Aktualisiert die Liste alle 15 s, solange das Panel sichtbar ist. */
(function(){
  'use strict';
  var PANEL='panel-rueckruf', SCOPE='open', timer=null, loading=false, CFG=null, WIN={}, WAS_AI=false, OPEN_CALLS={};
  var ORDER=[1,2,3,4,5,6,0], SHORT=[0,1,2,3,4,5,6].map(function(i){ return I18N.lang==='de' ? ['So','Mo','Di','Mi','Do','Fr','Sa'][i] : I18N.wd(i,'short'); });
  var STAG={open:'gold',scheduled:'teal',calling:'indigo',done:'ok',failed:'rose',cancelled:''};
  function el(id){ return document.getElementById(id); }
  function h(s){ return esc(s==null ? '' : String(s)); }
  function visible(){ var p=el(PANEL); return !!p && !p.closest('.hidden'); }
  function errText(e){ var d=(e && e.data) || {}; return I18N.lang==='de' ? (d.message || d.hint || (e && e.message) || 'Fehler') : _te(e); }
  function lb(k, fb){ return I18N.has(k) ? _t(k) : _tx(fb); }
  function hm(v){ var m=/^(\d{1,2})[:.](\d{2})$/.exec(String(v || '').trim()); if(!m) return null; var H=+m[1], M=+m[2]; if(M>59 || H>24 || (H===24 && M>0)) return null; return (H<10 ? '0' : '')+H+':'+m[2]; }
  function hmShort(x){ var p=String(x).split(':'); return (+p[0])+(p[1]!=='00' ? ':'+p[1] : ''); }
  /** Meldung über der Liste: Erfolg verschwindet nach 8 s von selbst, Fehler bleiben bis zum nächsten erfolgreichen Laden. */
  var noteTimer=null;
  function note(html, kind){ var m=el('cbListMsg'); clearTimeout(noteTimer); msg(m,html,kind); m.setAttribute('data-kind',kind || ''); if(kind==='ok') noteTimer=setTimeout(function(){ msg(m,'',''); m.removeAttribute('data-kind'); },8000); }
  function htmlEsc(s){ return String(s==null ? '' : s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  /* ---------- Anrufzeiten ---------- */
  function winText(){
    var groups=[];
    ORDER.forEach(function(w){
      var r=(WIN[w] || []).filter(function(x){ var a=hm(x[0]), b=hm(x[1]); return a && b && b>a; });
      var t=r.length ? _t('od.at',{t:r.map(function(x){ return hmShort(hm(x[0]))+'–'+hmShort(hm(x[1])); }).join(', ')}) : _t('rrj.none');
      var g=groups[groups.length-1]; if(g && g.t===t) g.to=w; else groups.push({from:w,to:w,t:t});
    });
    return groups.map(function(g){ return SHORT[g.from]+(g.to!==g.from ? '–'+SHORT[g.to] : '')+' '+g.t; }).join(', ');
  }
  function winRender(){
    el('cbWin').innerHTML=ORDER.map(function(w){
      var r=WIN[w] || [];
      return '<div class="row" data-w="'+w+'" style="gap:8px;padding:7px 0;border-bottom:1px solid var(--line);align-items:center"><b style="width:30px">'+SHORT[w]+'</b><div class="row" style="gap:6px;flex:1">'
        +(r.length ? r.map(function(x,i){ return '<span class="row" style="gap:4px"><input type="text" class="cbA" data-i="'+i+'" value="'+h(x[0])+'" maxlength="5" aria-label="'+h(_t('onbj.from',{d:SHORT[w]}))+'" style="width:72px;padding:7px 9px"> – <input type="text" class="cbB" data-i="'+i+'" value="'+h(x[1])+'" maxlength="5" aria-label="'+h(_t('onbj.to',{d:SHORT[w]}))+'" style="width:72px;padding:7px 9px"><button class="btn s ghost" type="button" data-cbwdel="'+i+'" title="'+h(_t('hr.del'))+'">✕</button></span>'; }).join('')
          : '<span class="mute small">'+_t('rrj.none')+'</span>')
        +'</div>'+(r.length<3 ? '<button class="btn s ghost" type="button" data-cbwadd="1">'+_t('hr.add')+'</button>' : '')+'</div>';
    }).join('');
    el('cbWinText').textContent=winText();
  }
  /** Eingaben → WIN; gibt eine Fehlermeldung zurück ('' = alles gültig). */
  function winRead(){
    var err='';
    [].forEach.call(document.querySelectorAll('#cbWin [data-w]'),function(row){
      var w=+row.getAttribute('data-w'), list=[];
      [].forEach.call(row.querySelectorAll('.cbA'),function(a){
        var b=row.querySelector('.cbB[data-i="'+a.getAttribute('data-i')+'"]'), A=hm(a.value), B=b ? hm(b.value) : null, ok=!!(A && B && B>A);
        a.style.borderColor=A ? '' : '#e58aa6'; if(b) b.style.borderColor=ok ? '' : '#e58aa6';
        if(!ok && !err) err=_t('rrj.badWin',{d:WDN[w],a:a.value,b:b ? b.value : ''});
        list.push([A || a.value.trim(),B || (b ? b.value.trim() : '')]);
      });
      WIN[w]=list;
    });
    el('cbWinText').textContent=winText();
    return err;
  }
  function winClick(ev){
    var t=ev.target.closest('button'); if(!t) return; var row=t.closest('[data-w]'); if(!row) return; var w=+row.getAttribute('data-w');
    winRead();
    if(t.hasAttribute('data-cbwadd')){
      var r=WIN[w] || [], last=r[r.length-1], end=last ? hm(last[1]) : null;
      var fmt=function(m){ var H=Math.floor(m/60), M=m%60; return (H<10 ? '0' : '')+H+':'+(M<10 ? '0' : '')+M; };
      if(!last || !end) r.push(['09:00','18:00']);
      else { var e=parseInt(end,10)*60+parseInt(end.slice(3),10), a=Math.min(22*60,e+60); r.push([fmt(a),fmt(Math.min(24*60,a+240))]); }   // z. B. nach der Mittagspause
      WIN[w]=r;
    }
    else if(t.hasAttribute('data-cbwdel')){ (WIN[w] || []).splice(+t.getAttribute('data-cbwdel'),1); }
    winRender(); greetPreview();
  }

  /* ---------- Einstellungen ---------- */
  function planLabel(a){ return a.name+' · '+(a.phone_on ? (a.has_phone_number ? _t('rrj.phOn') : _t('rrj.phNoNum')) : _t('rrj.phOff'))+(a.active ? '' : ' · '+_t('rrj.notBooked')); }
  function fill(j){
    CFG=j; var s=j.settings || {}; WAS_AI=!!s.ai_callback;
    el('cbOn').checked=!!s.cb_on; el('cbAi').checked=!!s.ai_callback; el('cbInstant').checked=!!s.lead_instant;
    var ag=j.agents || [], sel=el('cbPlan');
    sel.innerHTML='<option value="">'+_t('rrj.autoAgent')+'</option>'+ag.map(function(a){ return '<option value="'+h(a.plan)+'">'+h(planLabel(a))+'</option>'; }).join('');
    sel.value=s.plan || ''; if(sel.value!==(s.plan || '')) sel.value='';
    el('cbFrom').value=s.from_number_display || s.from_number || '';
    el('cbMax').value=String(s.max_attempts || 3); if(!el('cbMax').value) el('cbMax').value='3';
    var rs=el('cbRetry'); if(![].slice.call(rs.options).some(function(o){ return o.value===String(s.retry_min); })){ var o=document.createElement('option'); o.value=String(s.retry_min); o.textContent=_t('rrj.minutes',{n:s.retry_min}); rs.appendChild(o); } rs.value=String(s.retry_min || 30);
    el('cbIntro').value=s.intro || ''; el('cbNotify').value=s.notify_email || '';
    var tz=el('cbTz'); if(s.tz && ![].slice.call(tz.options).some(function(o){ return o.value===s.tz; })){ var t=document.createElement('option'); t.textContent=s.tz; tz.appendChild(t); } tz.value=s.tz || 'Europe/Berlin';
    WIN={}; ORDER.forEach(function(w){ WIN[w]=((s.call_window || {})[String(w)] || []).map(function(x){ return [x[0],x[1]]; }); });
    winRender(); readiness(j.readiness || {}); embeds(j); stats(j.stats || {}); aiUI(); greetPreview();
    el('cbNBy').querySelector('option[value="assistant"]').disabled=!s.ai_callback;
    if(!s.ai_callback) el('cbNBy').value='team';
  }
  function aiUI(){
    var on=el('cbAi').checked; el('cbAiBox').style.opacity=on ? '1' : '.55';
    el('cbPlanInfo').textContent=on ? _t('rrj.planOn') : _t('rrj.planOff');
  }
  function readiness(r){
    var box=el('cbReady');
    if(r.ready){ box.innerHTML='<span class="tag ok">'+_t('rrj.ready')+'</span> <span class="mute small">'+h(_t('rrj.readyTxt',{n:r.caller_id_display,a:r.agent_name}))+' · '+(r.twilio_own ? _t('rrj.ownTw') : _t('rrj.chTw'))+'</span>'+((r.issues || []).length ? '<div class="mute small" style="margin-top:6px">'+r.issues.map(function(x){ return h(_tx(x)); }).join('<br>')+'</div>' : ''); return; }
    var on=el('cbAi').checked;
    box.innerHTML='<div class="msg '+(on ? 'err' : 'info')+'" style="margin-top:0"><b>'+(on ? _t('rrj.notReady') : _t('rrj.missing'))+'</b><br>'+(r.issues || []).map(function(x){ return '• '+h(_tx(x)); }).join('<br>')+'</div>';
  }
  function greetPreview(){
    var company=(CFG && CFG.company) || _t('rrj.yourCo'), intro=el('cbIntro').value.trim(), first;
    if(intro){ first=intro.replace(/\{firma\}/gi,company).replace(/\{name\}/gi,'Max Mustermann'); if(!/[.!?]$/.test(first)) first+='.'; }
    else { var H=new Date().getHours(); first=(H<11 ? _t('rrj.gm',{c:company}) : (H<18 ? _t('rrj.gd',{c:company}) : _t('rrj.ge',{c:company}))); }
    el('cbGreetPrev').textContent='🔊 „'+first+' '+_t('rrj.greetRest')+'“';
  }
  function collect(){
    var cw={}; ORDER.forEach(function(w){ cw[String(w)]=(WIN[w] || []).map(function(x){ return [hm(x[0]),hm(x[1])]; }); });
    return {cb_on:el('cbOn').checked ? 1 : 0,ai_callback:el('cbAi').checked ? 1 : 0,plan:el('cbPlan').value,from_number:el('cbFrom').value.trim(),lead_instant:el('cbInstant').checked ? 1 : 0,
      max_attempts:+el('cbMax').value || 3,retry_min:+el('cbRetry').value || 30,intro:el('cbIntro').value.trim(),call_window:cw,tz:el('cbTz').value,notify_email:el('cbNotify').value.trim()};
  }
  function save(){
    var m=el('cbMsg'), err=winRead(); if(err){ msg(m,h(err),'err'); return; }
    var d=collect();
    if(d.notify_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.notify_email)){ msg(m,_t('rrj.badMail'),'err'); return; }
    if(d.ai_callback && !WAS_AI && !confirm(_t('rrj.aiQ',{n:d.max_attempts}))) return;
    el('cbSave').disabled=true; msg(m,_t('c.saving'),'info');
    api('/callbacks/settings',{method:'POST',body:JSON.stringify(d)}).then(function(j){
      fill(j); var s=j.settings || {}, r=j.readiness || {};
      msg(m,_t('c.saved')+' '+(!s.cb_on ? _t('rrj.offS') : (s.ai_callback ? (r.ready ? h(_t('rrj.aiS',{w:I18N.lang==='de' ? s.window_text : winText()})) : _t('rrj.aiWait')) : _t('rrj.teamS'))),'ok');
      load();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); }).then(function(){ el('cbSave').disabled=false; });
  }

  /* ---------- Einbinden ---------- */
  function snippet(apiUrl, consent){
    var st='padding:11px 12px;border:1px solid #cfd3de;border-radius:10px;font:inherit';
    var A=function(s){ return htmlEsc(s); };
    return '<!-- ChatHelp '+A(_t('rrj.f.title'))+' -->\n'
      +'<form id="ch-rueckruf" action="'+apiUrl+'" method="post" style="max-width:440px;display:grid;gap:8px;font:15px system-ui,sans-serif">\n'
      +'  <input name="name" placeholder="'+A(_t('rrj.f.name'))+'" required autocomplete="name" style="'+st+'">\n'
      +'  <input name="phone" type="tel" placeholder="'+A(_t('rrj.f.phone'))+'" required autocomplete="tel" style="'+st+'">\n'
      +'  <input name="email" type="email" placeholder="'+A(_t('rrj.f.email'))+'" autocomplete="email" style="'+st+'">\n'
      +'  <textarea name="message" rows="3" placeholder="'+A(_t('rrj.f.msg'))+'" style="'+st+'"></textarea>\n'
      +'  <select name="preferred_time" style="'+st+'"><option>'+A(_t('rrj.f.asap'))+'</option><option>'+A(_t('rrj.f.pm'))+'</option><option>'+A(_t('rrj.f.tam'))+'</option><option>'+A(_t('rrj.f.tpm'))+'</option></select>\n'
      +'  <label style="font-size:13px;line-height:1.45"><input type="checkbox" name="consent" value="1" required> <span data-consent>'+htmlEsc(consent)+'</span></label>\n'
      +'  <input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">\n'
      +'  <button type="submit" style="padding:12px;border:0;border-radius:10px;background:#0f1222;color:#fff;font-weight:600;cursor:pointer">'+A(_t('rrj.f.submit'))+'</button>\n'
      +'  <p data-out style="margin:0;font-size:14px"></p>\n'
      +'</form>\n'
      +'<script>\n'
      +'document.getElementById("ch-rueckruf").addEventListener("submit",function(e){e.preventDefault();var f=e.target,d={},o=f.querySelector("[data-out]");\n'
      +'new FormData(f).forEach(function(v,k){d[k]=v;});d.consent_text=f.querySelector("[data-consent]").textContent;d.via="website";o.textContent='+JSON.stringify(_t('rrj.f.sending'))+';\n'
      +'fetch(f.action,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();})\n'
      +'.then(function(j){o.textContent=j.message||'+JSON.stringify(_t('rrj.f.thanks'))+';if(j.ok)f.reset();}).catch(function(){f.submit();});});\n'
      +'<\/script>';
  }
  function jsonExample(apiUrl, consent){
    var body={name:'Max Mustermann',phone:'+49 170 1234567',email:'max@example.com',message:'Ist die Wohnung LQ-2041 noch frei? Ich würde sie gern besichtigen.',consent:true,consent_text:consent,source:'ImmoScout24',objekt:'LQ-2041',budget:'1.300 € warm',einzug:'ab Januar'};
    return 'curl -X POST "'+apiUrl+'" \\\n  -H "Content-Type: application/json" \\\n  -d \''+JSON.stringify(body,null,2).replace(/'/g,'’')+'\'';
  }
  function embeds(j){
    var l=j.links || {};
    if(!l.form){ ['cbFormLink','cbSnippet','cbJson'].forEach(function(id){ el(id).textContent=_t('rrj.saveAgent'); }); el('cbFormOpen').removeAttribute('href'); el('cbFormState').textContent=''; return; }
    el('cbFormLink').textContent=l.form; el('cbFormOpen').href=l.form;
    el('cbSnippet').textContent=snippet(l.api,j.consent_text || ''); el('cbJson').textContent=jsonExample(l.api,j.consent_text || '');
    el('cbFormState').innerHTML=j.form_active ? '<span class="tag ok">'+_t('rrj.formOn')+'</span> <span class="mute small">'+(CFG && CFG.settings && CFG.settings.ai_callback && CFG.settings.lead_instant ? _t('rrj.formAi') : _t('rrj.formList'))+'</span>'
      : '<span class="tag rose">'+_t('rrj.notPublic')+'</span> <span class="mute small">'+_t('rrj.needActive')+'</span>';
  }
  function copyText(id, btn){
    var t=el(id).textContent, done=function(){ var o=btn.textContent; btn.textContent=_t('c.copied'); setTimeout(function(){ btn.textContent=o; },1600); };
    if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(t).then(done,function(){ fallbackCopy(t); done(); }); } else { fallbackCopy(t); done(); }
  }
  function fallbackCopy(t){ var a=document.createElement('textarea'); a.value=t; a.setAttribute('readonly',''); a.style.position='fixed'; a.style.left='-9999px'; document.body.appendChild(a); a.select(); try{ document.execCommand('copy'); }catch(e){} a.remove(); }

  /* ---------- Kennzahlen & Liste ---------- */
  function stats(st){
    if(!st || st.open==null) return;
    el('cbKOpen').textContent=de(st.open); el('cbKDue').textContent=_t('rrj.due',{n:de(st.due)}); el('cbKToday').textContent=de(st.today);
    el('cbKDone').textContent=de(st.done_7d); el('cbKFail').textContent=_t('rrj.failed',{n:de(st.failed)}); el('cbKAi').textContent=de(st.ai_talks_7d); el('cbKAiTry').textContent=_t('rrj.tries',{n:de(st.ai_attempts_7d)});
    el('cbCntOpen').textContent=st.open ? '('+st.open+')' : '';
  }
  function canCall(c){ return c.consent && ['open','scheduled','failed'].indexOf(c.status)>=0 && c.attempts<c.max_attempts; }
  function item(c){
    var det=c.details || {}, keys=Object.keys(det), open=['open','scheduled','calling','failed'].indexOf(c.status)>=0, acts='';
    if(open){
      if(c.status!=='calling') acts+='<button class="btn s p" type="button" data-cbact="done" data-id="'+c.id+'">'+_t('htj.doneBtn')+'</button>';
      if(canCall(c)) acts+='<button class="btn s" type="button" data-cbact="call" data-id="'+c.id+'" title="'+h(_t('rrj.callT'))+'">'+_t('rrj.callNow')+'</button>';
      else if(!c.consent && c.status!=='calling') acts+='<button class="btn s" type="button" disabled title="'+h(_t('rrj.noConsT'))+'">'+_t('rrj.noCons')+'</button>';
    } else acts+='<button class="btn s" type="button" data-cbact="reopen" data-id="'+c.id+'">'+_t('htj.reopen')+'</button>';
    acts+='<button class="btn s ghost" type="button" data-cbact="note" data-id="'+c.id+'">'+_t('rrj.note')+'</button>';
    if(c.has_calls) acts+='<button class="btn s ghost" type="button" data-cbact="calls" data-id="'+c.id+'">'+_t('rrj.calls')+'</button>';
    if(open && c.status!=='calling') acts+='<button class="btn s ghost" type="button" data-cbact="cancel" data-id="'+c.id+'">'+_t('rrj.cancel')+'</button>';
    var meta=['⏰ '+h(_tx(c.preferred)),h(_t('rrj.recv',{t:I18N.fx(c.created)})),h(_t('rrj.att',{a:c.attempts,m:c.max_attempts}))];
    if(c.next_call) meta.push('<b>'+h(_t('rrj.next',{t:I18N.fx(c.next_call)}))+'</b>');
    if(c.last_attempt) meta.push(h(_t('rrj.last',{t:I18N.fx(c.last_attempt)})));
    return '<div class="call" data-cbitem="'+c.id+'"'+(c.urgency==='dringend' && open ? ' style="border-color:#f7c6d5"' : '')+'><div class="row" style="justify-content:space-between;align-items:flex-start;gap:10px">'
      +'<div style="flex:1;min-width:210px"><div class="row" style="gap:6px"><b style="font-size:15.5px">'+h(c.name)+'</b>'+(c.urgency==='dringend' ? '<span class="tag rose">'+_t('rrj.urgent')+'</span>' : '')
      +'<span class="tag'+(c.mode==='ai' ? ' indigo' : '')+'">'+(c.mode==='ai' ? _t('rrj.ai') : _t('rrj.team'))+'</span><span class="tag '+(STAG[c.status] || '')+'">'+h(lb('rrj.st.'+c.status,c.status_label))+'</span></div>'
      +'<div class="small" style="margin-top:6px"><a href="tel:'+h(c.phone)+'"><b>📞 '+h(c.phone_display)+'</b></a>'+(c.email ? ' · <a href="mailto:'+h(c.email)+'">'+h(c.email)+'</a>' : '')+' <span class="mute">· '+h(_tx(c.source_label))+' · '+h(c.code)+'</span></div>'
      +'<div style="margin-top:6px">'+h(c.topic)+'</div>'
      +(keys.length ? '<div class="row" style="gap:6px;margin-top:6px">'+keys.map(function(k){ return '<span class="tag" style="text-transform:none;letter-spacing:0;font-weight:500">'+h(k)+': '+h(det[k])+'</span>'; }).join('')+'</div>' : '')
      +'<div class="mute small" style="margin-top:6px">'+meta.join(' · ')+'</div>'
      +(c.result ? '<div class="small" style="margin-top:8px;padding:8px 10px;border-radius:10px;background:var(--sur2)">📝 '+h(c.result)+'</div>' : '')
      +'</div><div class="row" style="gap:6px;justify-content:flex-end">'+acts+'</div></div>'
      +'<div class="cbNoteBox hidden" style="margin-top:10px"></div><div class="cbCallsBox hidden" style="margin-top:10px"></div></div>';
  }
  function busyEditing(){ return !!el('cbList').querySelector('.cbNoteIn'); }
  function render(j){
    var list=j.callbacks || []; LAST=list;
    if(el('cbListMsg').getAttribute('data-kind')==='err') note('','');
    if(!list.length){ el('cbList').innerHTML='<div class="mute" style="padding:18px 4px">'+(SCOPE==='open' ? _t('rrj.emptyOpen') : (SCOPE==='done' ? _t('rrj.emptyDone') : _t('rrj.emptyAll')))+'</div>'; return; }
    el('cbList').innerHTML=list.map(item).join('');
    Object.keys(OPEN_CALLS).forEach(function(id){ var it=el('cbList').querySelector('[data-cbitem="'+id+'"]'); if(it) showCalls(it,id,true); else delete OPEN_CALLS[id]; });
  }
  function load(){
    if(loading) return; loading=true;
    api('/callbacks?scope='+encodeURIComponent(SCOPE)).then(function(j){
      stats(j.stats || {}); if(!busyEditing()) render(j);
      el('cbLive').textContent=_t('rrj.live',{t:I18N.time(new Date())});
    }).catch(function(e){ note(h(errText(e)),'err'); }).then(function(){ loading=false; });
  }
  function update(id, body, btn){
    if(btn) btn.disabled=true; body.id=+id;
    return api('/callbacks/update',{method:'POST',body:JSON.stringify(body)}).then(function(){ load(); }).catch(function(e){ note(h(errText(e)),'err'); if(btn) btn.disabled=false; });
  }
  function callNow(id, btn, force){
    var c=findItem(id);
    if(!force){
      var txt=c && c.mode!=='ai' ? _t('rrj.teamQ',{n:c.name}) : _t('rrj.callQ',{n:c ? c.name : _t('rrj.person')});
      if(!confirm(txt)) return;
    }
    btn.disabled=true; btn.textContent=_t('rrj.calling');
    api('/callbacks/call',{method:'POST',body:JSON.stringify({id:+id,force:force ? 1 : 0})}).then(function(j){ note(h(I18N.lang==='de' ? (j.message || 'Anruf wird aufgebaut.') : _t('rrj.dialing')),'ok'); load(); })
      .catch(function(e){
        var d=(e && e.data) || {};
        if(d.error==='outside_window' && !force && confirm(((I18N.lang==='de' && d.hint) || _t('rrj.outside'))+'\n\n'+_t('rrj.anyway'))){ btn.disabled=false; callNow(id,btn,true); return; }
        note(h(errText(e)),'err'); btn.disabled=false; btn.textContent=_t('rrj.callNow');
      });
  }
  var LAST=[];   // zuletzt gezeigte Liste (für Bestätigungstexte und Notiz-Vorbelegung)
  function findItem(id){ for(var i=0;i<LAST.length;i++) if(String(LAST[i].id)===String(id)) return LAST[i]; return null; }
  function noteEdit(it, id){
    var box=it.querySelector('.cbNoteBox'), c=findItem(id);
    if(!box.classList.contains('hidden')){ box.classList.add('hidden'); box.innerHTML=''; return; }
    box.classList.remove('hidden');
    box.innerHTML='<div class="row" style="gap:6px"><input type="text" class="cbNoteIn" maxlength="500" placeholder="'+h(_t('rrj.notePh'))+'" style="flex:1;min-width:200px"><button class="btn s p" type="button" data-cbact="notesave" data-id="'+id+'">'+_t('c.save')+'</button><button class="btn s ghost" type="button" data-cbact="notecancel" data-id="'+id+'">'+_t('c.cancel')+'</button></div>';
    var inp=box.querySelector('.cbNoteIn'); inp.value=c ? c.result : ''; inp.focus();
    inp.addEventListener('keydown',function(ev){ if(ev.key==='Enter'){ ev.preventDefault(); box.querySelector('[data-cbact="notesave"]').click(); } if(ev.key==='Escape'){ box.querySelector('[data-cbact="notecancel"]').click(); } });
  }
  function showCalls(it, id, silent){
    var box=it.querySelector('.cbCallsBox');
    if(!silent && !box.classList.contains('hidden')){ box.classList.add('hidden'); delete OPEN_CALLS[id]; return; }
    OPEN_CALLS[id]=1; box.classList.remove('hidden'); if(!silent) box.innerHTML='<div class="mute small">'+_t('rrj.loadCalls')+'</div>';
    api('/callbacks/calls?id='+encodeURIComponent(id)).then(function(j){
      var a=j.attempts || [];
      box.innerHTML=a.length ? a.map(function(x){
        var tr=(x.call && x.call.transcript) || [], when=x.at ? I18N.dt(x.at,{weekday:'short',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '';
        return '<div class="call" style="margin-top:6px;background:var(--sur2)"><div class="small"><b>'+h(when)+'</b> · '+h(_tx(x.note || x.status))+(x.answered_by ? ' · '+h(x.answered_by) : '')+'</div>'
          +(tr.length ? '<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">'+tr.map(function(m){ return '<div class="small" style="padding:7px 10px;border-radius:10px;max-width:92%;'+(m.role==='user' ? 'align-self:flex-end;background:var(--ink);color:#fff' : 'align-self:flex-start;background:#fff;border:1px solid var(--line)')+'">'+h(m.content)+'</div>'; }).join('')+'</div>' : '')+'</div>';
      }).join('') : '<div class="mute small">'+_t('rrj.noTry')+'</div>';
    }).catch(function(e){ box.innerHTML='<div class="msg err">'+h(errText(e))+'</div>'; });
  }
  function listClick(ev){
    var b=ev.target.closest('[data-cbact]'); if(!b) return; var id=b.getAttribute('data-id'), act=b.getAttribute('data-cbact'), it=b.closest('[data-cbitem]');
    if(act==='done') update(id,{status:'done'},b);
    else if(act==='reopen') update(id,{status:'open'},b);
    else if(act==='cancel'){ if(confirm(_t('rrj.cancelQ'))) update(id,{status:'cancelled'},b); }
    else if(act==='call') callNow(id,b,false);
    else if(act==='note') noteEdit(it,id);
    else if(act==='notesave'){ var v=it.querySelector('.cbNoteIn').value; it.querySelector('.cbNoteBox').innerHTML=''; it.querySelector('.cbNoteBox').classList.add('hidden'); update(id,{result:v}); }
    else if(act==='notecancel'){ it.querySelector('.cbNoteBox').innerHTML=''; it.querySelector('.cbNoteBox').classList.add('hidden'); }
    else if(act==='calls') showCalls(it,id,false);
  }
  function setScope(sc){ SCOPE=sc; [].forEach.call(document.querySelectorAll('#'+PANEL+' [data-cbscope]'),function(x){ x.classList.toggle('p',x.getAttribute('data-cbscope')===sc); }); OPEN_CALLS={}; load(); }

  /* ---------- Manuell eintragen ---------- */
  function newToggle(show){ var n=el('cbNew'); var on=show==null ? n.classList.contains('hidden') : show; n.classList.toggle('hidden',!on); el('cbNMsg').innerHTML=''; if(on) el('cbNName').focus(); }
  function newSave(){
    var m=el('cbNMsg'), d={name:el('cbNName').value.trim(),phone:el('cbNPhone').value.trim(),topic:el('cbNTopic').value.trim(),preferred_time:el('cbNWhen').value.trim(),email:el('cbNEmail').value.trim(),
      callback_by:el('cbNBy').value,urgency:el('cbNUrgent').checked ? 'dringend' : 'normal',consent:el('cbNConsent').checked ? 1 : 0};
    if(d.name.length<2){ msg(m,_t('rrj.needName'),'err'); return; }
    if(d.phone.replace(/\D/g,'').length<6){ msg(m,_t('rrj.needPhone'),'err'); return; }
    if(d.callback_by==='assistant' && !d.consent){ msg(m,_t('rrj.needCons'),'err'); return; }
    el('cbNSave').disabled=true;
    api('/callbacks/new',{method:'POST',body:JSON.stringify(d)}).then(function(j){
      ['cbNName','cbNPhone','cbNTopic','cbNWhen','cbNEmail'].forEach(function(id){ el(id).value=''; }); el('cbNUrgent').checked=false; el('cbNConsent').checked=false;
      newToggle(false); note(_t('rrj.added')+' '+h(I18N.lang==='de' ? (j.message || '') : '')+(j.hint ? ' <span class="mute">'+h(_tx(j.hint))+'</span>' : ''),'ok'); if(SCOPE==='done') setScope('open'); else load();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); }).then(function(){ el('cbNSave').disabled=false; });
  }

  function settingsLoad(){ return api('/callbacks/settings').then(fill).catch(function(e){ msg(el('cbMsg'),h(errText(e)),'err'); }); }
  function tick(){ if(visible() && !document.hidden) load(); }
  function rentButton(){
    var b=el('cbRent'); if(!b) return;
    var p=(typeof PLANS!=='undefined' && PLANS ? PLANS : []).filter(function(x){ return x.id==='immo-agent'; })[0];
    if(typeof active==='function' && active('immo-agent')){ b.classList.add('hidden'); return; }
    if(p) b.textContent=_t('rrj.rent',{n:p.name.replace(/\s*\(.*\)$/,''),p:I18N.eur(p.eur_month,p.eur_month%1?2:0)});
  }
  

  window.STUDIO_PANELS=window.STUDIO_PANELS || {};
  STUDIO_PANELS['rueckruf']={
    init:function(){
      rentButton();
      el('cbSave').addEventListener('click',save);
      el('cbAi').addEventListener('change',function(){ aiUI(); if(CFG) readiness(CFG.readiness || {}); });
      el('cbIntro').addEventListener('input',greetPreview);
      el('cbWin').addEventListener('click',winClick);
      el('cbWin').addEventListener('input',function(){ winRead(); });
      el('cbWinCopy').addEventListener('click',function(){ winRead(); [2,3,4,5].forEach(function(w){ WIN[w]=(WIN[1] || []).map(function(x){ return [x[0],x[1]]; }); }); winRender(); });
      [].forEach.call(document.querySelectorAll('#'+PANEL+' [data-cbcopy]'),function(b){ b.addEventListener('click',function(){ copyText(b.getAttribute('data-cbcopy'),b); }); });
      [].forEach.call(document.querySelectorAll('#'+PANEL+' [data-cbscope]'),function(b){ b.addEventListener('click',function(){ setScope(b.getAttribute('data-cbscope')); }); });
      el('cbList').addEventListener('click',listClick);
      el('cbNewToggle').addEventListener('click',function(){ newToggle(); });
      el('cbNCancel').addEventListener('click',function(){ newToggle(false); });
      el('cbNSave').addEventListener('click',newSave);
      el('cbNBy').addEventListener('change',function(){ if(el('cbNBy').value==='assistant') el('cbNConsent').checked=true; });
      settingsLoad(); load();
      if(!timer) timer=setInterval(tick,15000);
    },
    show:function(){ load(); if(CFG) api('/callbacks/settings').then(function(j){ CFG.readiness=j.readiness; CFG.stats=j.stats; readiness(j.readiness || {}); stats(j.stats || {}); }).catch(function(){}); }
  };
})();
