/* ChatHelp Studio — Panel „Agent trainieren“: Website-/Dokument-Import mit Vorschau, FAQ (verbindliche Antworten), Korrekturen,
   Verhaltensregeln, Prüfung mit Testfragen, Lücken finden. Lange Arbeiten laufen als Auftrag; das Panel fragt den Stand ab.
   Nutzt die Studio-Globals api(), msg(), esc(), de(), go(), openAgent(), active(), refreshMe(), ME, PLANS, KEY. */
(function(){
  'use strict';
  var PANEL='panel-training', LS_PLAN='ch_train_plan', KB_LIMIT=60000, KB_PROMPT=40000;
  var PLAN='', OV=null, FAQ=[], TESTS=[], seq=0, inited=false, faqDirty=false, testDirty=false;
  var JOB={imp:null,tests:null}, POLL={imp:false,tests:false}, PREV=null, FILE=null, CORR=null, pendingCorr=null, tick=null, RULES_EX='';
  var POLL_MS=15000;   // Studio-Regel: Live-Daten höchstens alle 15 s abfragen
  var EXT={pdf:'PDF',docx:'Word',txt:'Text',md:'Markdown',markdown:'Markdown',csv:'CSV'};
  var CH={phone:_t('kuj.ch.phone'),whatsapp:'WhatsApp',reported:_t('trj.reported')};

  function el(id){ return document.getElementById(id); }
  function h(s){ return esc(s==null ? '' : s); }
  function n(x){ return de(x); }
  function visible(){ var p=el(PANEL); return !!p && !p.closest('.hidden'); }
  function errText(e){ var d=(e && e.data) || {}; return I18N.lang==='de' ? (d.message || d.hint || (e && e.message) || 'Unbekannter Fehler') : _te(e); }
  function T(s){ return h(_tx(s==null ? '' : String(s))); }
  function lsGet(){ try{ return localStorage.getItem(LS_PLAN) || ''; }catch(e){ return ''; } }
  function lsSet(v){ try{ localStorage.setItem(LS_PLAN,v); }catch(e){} }
  function when(iso){ if(!iso) return ''; var d=new Date(iso); return isNaN(d) ? '' : I18N.dt(d,{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
  function clip(s,max){ s=String(s==null ? '' : s); return s.length>max ? s.slice(0,max-1)+'…' : s; }
  function isAct(id){ if(typeof active!=='function') return false; try{ return active(id) || active('buy-'+String(id).replace(/^agent-/,'')); }catch(e){ return false; } }
  function planOfId(id){ return (PLANS || []).find(function(p){ return p.id===id; }) || null; }
  function planName(id){ var p=planOfId(id); return p ? p.name : id; }
  function cfgPlans(){ var m={}; ((ME && ME.configs) || []).forEach(function(c){ m[c.plan]=1; }); return m; }
  function planList(){
    var cfg=cfgPlans(), l=(PLANS || []).filter(function(p){ return p.kind==='agent'; });
    if(PLAN && !l.some(function(p){ return p.id===PLAN; }) && planOfId(PLAN) && planOfId(PLAN).kind!=='buy') l.push(planOfId(PLAN));
    return l.sort(function(a,b){ return (isAct(b.id)-isAct(a.id)) || ((cfg[b.id] ? 1 : 0)-(cfg[a.id] ? 1 : 0)) || String(a.name).localeCompare(String(b.name),I18N.tag); });
  }
  function qs(p){ return '?plan='+encodeURIComponent(PLAN)+(p || ''); }
  function post(path,o,plan){ o=o || {}; o.plan=plan || PLAN; return api(path,{method:'POST',body:JSON.stringify(o)}); }
  function balance(){ if(typeof refreshMe==='function'){ try{ refreshMe(); }catch(e){} } }
  function buyHtml(d){ d=d || {}; return h(d.need!=null ? _t('trj.noTkN',{n:n(d.need),b:n(d.balance || 0)}) : _t('trj.noTk'))+' <a href="/#preise" target="_blank" rel="noopener"><b>'+_t('st.tokens_kaufen')+'</b></a>'; }
  function showErr(box,e){ var d=(e && e.data) || {}; if((e && e.status===402) || d.error==='insufficient_tokens') msg(box,buyHtml(d),'err'); else msg(box,h(errText(e)),'err'); }
  function busy(btn,on,label){ if(!btn) return; if(on){ if(!btn.dataset.l) btn.dataset.l=btn.textContent; btn.disabled=true; if(label) btn.textContent=label; } else { btn.disabled=false; if(btn.dataset.l){ btn.textContent=btn.dataset.l; delete btn.dataset.l; } } }
  function confirmLeave(){ return !(faqDirty || testDirty) || confirm(_t('trj.leaveQ')); }

  /* ---------------------------------------------------------------- Agent-Auswahl & Laden */
  function renderPlans(){
    var sel=el('trPlan'), l=planList();
    if(!PLAN || !l.some(function(p){ return p.id===PLAN; })){
      var want=lsGet(), cfg=cfgPlans();
      if(!l.some(function(p){ return p.id===want; })){ var a=l.find(function(p){ return isAct(p.id); }), c=l.find(function(p){ return cfg[p.id]; }); want=a ? a.id : (c ? c.id : (l[0] ? l[0].id : '')); }
      PLAN=want;
    }
    sel.innerHTML=l.map(function(p){ return '<option value="'+h(p.id)+'">'+h(p.name)+(isAct(p.id) ? ' · '+_t('sj.active.l') : '')+'</option>'; }).join('') || '<option value="">'+_t('trj.noAgents')+'</option>';
    sel.value=PLAN; head();
  }
  function head(){
    var act=isAct(PLAN), p=planOfId(PLAN), r=el('trRent');
    el('trActive').classList.toggle('hidden',!act);
    r.classList.toggle('hidden',act || !p);
    if(p){ r.href='/chat/tokens-checkout.php?plan='+encodeURIComponent(PLAN)+'&_='+Date.now().toString(36); r.textContent=_t('trj.rent',{p:p.eur_month!=null ? I18N.eur(p.eur_month,p.eur_month%1?2:0) : '–'}); }
  }
  function load(){
    if(!PLAN){ msg(el('trTop'),_t('trj.noAgentsDot'),'err'); return Promise.resolve(); }
    var my=++seq; msg(el('trTop'),'');
    return Promise.all([api('/train/overview'+qs()),api('/train/faq'+qs()),api('/train/rules'+qs()),api('/train/tests'+qs())]).then(function(r){
      if(my!==seq) return;
      OV=r[0]; renderOverview();
      FAQ=r[1].items || []; renderFaq(r[1].prompt); faqDirty=false; dirtyUI();
      RULES_EX=(I18N.lang!=='de' && I18N.has('trj.rulesEx')) ? _t('trj.rulesEx') : (r[2].example || ''); el('trRules').value=r[2].rules || ''; el('trRules').placeholder=RULES_EX; rulesCount();
      TESTS=r[3].items || []; renderTests(r[3].summary); testDirty=false; dirtyUI();
      resumeJobs(); applyPendingCorr();
    }).catch(function(e){ if(my===seq) msg(el('trTop'),h(errText(e)),'err'); });
  }
  function refreshOverview(){ var my=seq; return api('/train/overview'+qs()).then(function(o){ if(my!==seq) return; OV=o; renderOverview(); }).catch(function(){}); }
  function switchPlan(id){
    if(id===PLAN) return;
    stopJobs(); PLAN=id; lsSet(id); head();
    el('trPrev').classList.add('hidden'); PREV=null; ['trUrlMsg','trFileMsg','trPrevMsg','trTestMsg','trGapMsg','trFaqMsg','trRulesMsg'].forEach(function(x){ msg(el(x),''); });
    el('trGapList').innerHTML=''; load();
  }

  /* ---------------------------------------------------------------- Kennzahlen */
  function renderOverview(){
    var o=OV || {}, kb=o.kb_chars || 0, pct=Math.min(100,kb*100/KB_LIMIT);
    el('trKbNum').textContent=n(kb);
    el('trKbSub').innerHTML=h(_t('trj.ofChars',{n:n(KB_LIMIT)}))+(kb>KB_PROMPT ? ' · <span style="color:var(--rose)">'+h(_t('trj.readsFirst',{n:n(KB_PROMPT)}))+'</span>' : '');
    var bar=el('trKbBar'); bar.style.width=(kb>0 ? Math.max(1.5,pct) : 0).toFixed(1)+'%'; bar.style.background=pct>=90 ? 'var(--rose)' : 'linear-gradient(90deg,#ecd08a,#c9972f)';
    el('trFaqNum').textContent=n(o.faq_count || 0);
    el('trFaqSub').textContent=_t('trj.binding')+(o.corrections ? ' · '+(o.corrections===1 ? _t('trj.corr1') : _t('trj.corrN',{n:o.corrections})) : '');
    var t=o.tests || {};
    el('trScoreNum').textContent=t.score!=null ? t.score+' %' : '–';
    el('trScoreNum').style.color=t.score==null ? '' : (t.score>=80 ? 'var(--ok)' : (t.score>=50 ? 'var(--gold)' : 'var(--rose)'));
    el('trScoreSub').textContent=t.ran ? _t('trj.passed',{p:t.passed,r:t.ran})+(t.ran<t.total ? ' · '+_t('htj.nOpen',{n:t.total-t.ran}) : '')+(t.last_run ? ' · '+when(t.last_run) : '') : (t.total ? (t.total===1 ? _t('trj.test1') : _t('trj.testN',{n:t.total})) : _t('trj.noTests'));
    var s=o.last_source;
    el('trSrcNum').textContent=s ? clip(s.title || (s.kind==='url' ? 'Website' : _t('trj.doc')),60) : '–';
    el('trSrcSub').textContent=s ? ({url:'Website',file:_t('trj.doc'),text:'Text'}[s.kind] || _t('trj.source'))+' · '+_t('trj.chars',{n:n(s.chars)})+' · '+(s.mode==='replace' ? _t('trj.replaced') : _t('trj.appended'))+' · '+when(s.created_at) : _t('trj.nothingImp');
    el('trKbTag').textContent=n(kb)+' / '+_t('trj.chars',{n:n(KB_LIMIT)});
    el('trKbWarn').innerHTML=kb>KB_PROMPT ? '<div class="msg err">'+h(_t('trj.tooLong',{n:n(KB_PROMPT)}))+'</div>'
      : (kb===0 ? '<div class="msg info">'+_t('trj.empty')+'</div>' : '');
    el('trGapTool').checked=!!(o.settings && +o.settings.gap_tool);
    if(o.prompt) faqInfo(o.prompt);
  }

  /* ---------------------------------------------------------------- Aufträge (Import, Testlauf) */
  function stopJobs(){ JOB.imp=null; JOB.tests=null; if(tick){ clearInterval(tick); tick=null; } setImportBusy(false); busy(el('trTestRun'),false); }
  function elapsed(j){ var t=j && j.created_at ? Math.max(0,Math.round((Date.now()-new Date(j.created_at).getTime())/1000)) : 0; return Math.floor(t/60)+':'+String(t%60).padStart(2,'0'); }
  function startTick(){ if(tick) return; tick=setInterval(function(){ var any=false; ['imp','tests'].forEach(function(k){ var j=JOB[k]; if(j && j.status==='running'){ any=true; var s=document.querySelector('[data-trel="'+k+'"]'); if(s) s.textContent=elapsed(j); } }); if(!any){ clearInterval(tick); tick=null; } },1000); }
  function impBox(j){ return el(j && j.kind==='url' ? 'trUrlMsg' : 'trFileMsg'); }
  function setImportBusy(on){ el('trUrlGo').disabled=on; el('trFileGo').disabled=on || !FILE; }
  function progressHtml(kind,j){ return '<span class="tag gold">'+_t('trj.running')+' · <span data-trel="'+kind+'">'+elapsed(j)+'</span></span> '+(j.progress ? T(j.progress) : _t('trj.working'))+' <button class="btn ghost s" type="button" data-trcancel="'+kind+'">'+_t('c.cancel')+'</button>'; }
  function onJob(kind,j){
    JOB[kind]=j;
    if(kind==='imp'){
      var box=impBox(j);
      if(j.status==='running'){ setImportBusy(true); msg(box,progressHtml(kind,j),'info'); startTick(); return; }
      setImportBusy(false);
      if(j.status==='done'){ msg(box,j.result && j.result.condensed ? _t('trj.doneC') : _t('trj.doneR'),'ok'); renderPreview(j); balance(); refreshOverview(); return; }
      if(j.status==='cancelled'){ msg(box,j.error==='superseded' ? '' : _t('trj.aborted'),'info'); return; }
      if(j.status==='closed'){ if(PREV && PREV.job===j.job) hidePreview(); msg(box,_t('trj.closed'),'info'); refreshOverview(); return; }   // z. B. in einem zweiten Studio-Tab
      if(j.error==='insufficient_tokens') msg(box,buyHtml(j),'err'); else msg(box,h((j.message && (I18N.lang==='de' ? j.message : (_tx(j.message)!==j.message ? _tx(j.message) : ''))) || _t('trj.impFail')),'err');
      return;
    }
    var tb=el('trTestMsg');
    if(j.status==='running'){ busy(el('trTestRun'),true,_t('trj.testRun')); msg(tb,progressHtml(kind,j)+(j.total_tests ? ' <span class="mute">'+h(_t('trj.progress',{d:j.done_tests,t:j.total_tests}))+'</span>' : ''),'info'); startTick(); return; }
    busy(el('trTestRun'),false);
    if(j.status==='done' || j.status==='closed'){   // 'closed' = fertig und danach geschlossen (z. B. „Abbrechen“ im letzten Moment) – Ergebnisse sind gespeichert
      var r=j.result || {};
      msg(tb,'<b>'+h(_t('trj.passed',{p:r.passed || 0,r:r.ran || 0}))+'</b>'+(r.ran ? ' ('+Math.round((r.passed || 0)*100/r.ran)+' %)' : '')+' · '+h(_t('trj.charged',{n:n(r.cost || 0)}))+(r.note ? '<br>'+T(r.note) : ''),r.ran && r.passed===r.ran ? 'ok' : 'info');
      reloadTests(); balance(); refreshOverview(); return;
    }
    if(j.status==='cancelled'){ msg(tb,_t('trj.testAbort'),'info'); reloadTests(); return; }
    if(j.error==='insufficient_tokens') msg(tb,buyHtml(j),'err'); else msg(tb,h((j.message && (I18N.lang==='de' ? j.message : (_tx(j.message)!==j.message ? _tx(j.message) : ''))) || _t('trj.testFail')),'err');
  }
  function poll(kind){
    var j=JOB[kind]; if(!j || j.status!=='running' || POLL[kind]) return;
    POLL[kind]=true; var plan=PLAN, id=j.job;
    (function step(){
      var cur=JOB[kind];
      if(plan!==PLAN || !cur || cur.job!==id || cur.status!=='running' || !visible()){ POLL[kind]=false; return; }   // unsichtbar → show() setzt fort
      var t0=Date.now();
      post('/train/job',{id:id},plan).then(function(r){
        if(plan!==PLAN || !JOB[kind] || JOB[kind].job!==id){ POLL[kind]=false; return; }
        onJob(kind,r);
        if(r.status==='running') setTimeout(step,Math.max(600,POLL_MS-(Date.now()-t0))); else POLL[kind]=false;   // höchstens alle 15 s; der Server hält jede Abfrage bis zu 12 s (Long-Poll) und antwortet sofort, wenn der Auftrag fertig ist
      }).catch(function(e){
        POLL[kind]=false;
        if(e && e.status===404){ JOB[kind]=null; if(kind==='imp') setImportBusy(false); else busy(el('trTestRun'),false); return; }
        setTimeout(function(){ if(JOB[kind] && JOB[kind].job===id) poll(kind); },5000);   // Netzfehler → später erneut
      });
    })();
  }
  function resumeJobs(){
    var jo=(OV && OV.jobs) || {};
    if(jo.import){ if(jo.import.status==='running'){ onJob('imp',jo.import); poll('imp'); } else if(jo.import.status==='done' && (!PREV || PREV.job!==jo.import.job)){ JOB.imp=jo.import; renderPreview(jo.import,true); } }
    if(jo.tests && jo.tests.status==='running'){ onJob('tests',jo.tests); poll('tests'); }
  }
  function cancelJob(kind){
    var j=JOB[kind]; if(!j) return;
    post('/train/job/cancel',{id:j.job}).then(function(r){
      /* Import war im selben Moment schon fertig und berechnet → Vorschau trotzdem zeigen statt sie zu verwerfen */
      if(kind==='imp' && r && r.status==='closed' && r.result && r.result.preview && j.status==='running') r=Object.assign({},r,{status:'done'});
      onJob(kind,r);
    }).catch(function(e){ showErr(kind==='imp' ? impBox(j) : el('trTestMsg'),e); });
  }

  /* ---------------------------------------------------------------- 1 · Website */
  function importUrl(){
    var u=el('trUrl').value.trim(), box=el('trUrlMsg');
    if(!u){ msg(box,_t('trj.urlNeed'),'err'); el('trUrl').focus(); return; }
    if(PREV && !confirm(_t('trj.prevQ'))) return;
    hidePreview(); busy(el('trUrlGo'),true,_t('trj.starting')); msg(box,_t('trj.conn'),'info');
    post('/train/import-url',{url:u}).then(function(j){ busy(el('trUrlGo'),false); onJob('imp',j); poll('imp'); })
      .catch(function(e){ busy(el('trUrlGo'),false); setImportBusy(false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 2 · Dokumente */
  function fileExt(name){ var m=/\.([a-z0-9]+)$/i.exec(name || ''); return m ? m[1].toLowerCase() : ''; }
  var RAW_MAX=120000;   // so viel Rohtext liest der Server höchstens → Import höchstens 80 Tokens (PDF 100)
  function fileCost(f){ var e=fileExt(f.name); if(e==='pdf') return _t('trj.costPdf'); if(e==='docx') return _t('tr.20_80_tokens'); var c=Math.min(80,20+Math.ceil(Math.min(f.size,RAW_MAX)/2000)); return _t('trj.costN',{n:c}); }
  function mb(x){ return I18N.num(x/1048576,{minimumFractionDigits:1,maximumFractionDigits:1}); }
  function pickFile(f){
    var box=el('trFileMsg'); msg(box,''); FILE=null; el('trFileGo').disabled=true; el('trFileGo').textContent=_t('trj.upload');
    if(!f){ el('trFileName').textContent=''; return; }
    var e=fileExt(f.name), lim=(OV && OV.upload_limit) || 10485760;
    if(!EXT[e]){ el('trFileName').textContent=''; msg(box,e==='doc' ? _t('trj.oldDoc') : (e==='xlsx' || e==='xls' ? _t('trj.xls') : _t('trj.allowed')),'err'); return; }
    if(f.size>lim){ el('trFileName').textContent=''; msg(box,h(_t('trj.tooBig',{s:mb(f.size),m:mb(lim)})),'err'); return; }
    if(e==='pdf' && OV && OV.pdf===false){ el('trFileName').textContent=''; msg(box,_t('trj.noPdf'),'err'); return; }
    FILE=f; el('trFileName').innerHTML='<span class="tag indigo">'+h(EXT[e])+'</span> <b>'+h(clip(f.name,70))+'</b> · '+(f.size<1048576 ? Math.max(1,Math.round(f.size/1024))+' KB' : mb(f.size)+' MB');
    el('trFileCost').textContent=e==='pdf' ? _t('trj.pdfCost') : _t('trj.costLater');
    el('trFileGo').textContent=_t('trj.upload')+' · '+fileCost(f); el('trFileGo').disabled=!!(JOB.imp && JOB.imp.status==='running');
  }
  function uploadFile(){
    if(!FILE) return; var box=el('trFileMsg'), btn=el('trFileGo');
    if(PREV && !confirm(_t('trj.prevQ'))) return;
    hidePreview(); busy(btn,true,_t('trj.uploading')); msg(box,_t('trj.fileUp'),'info');
    var fd=new FormData(); fd.append('plan',PLAN); fd.append('file',FILE,FILE.name);
    fetch('/chat/tokens-api.php/v1/train/import-file?_='+Date.now(),{method:'POST',cache:'no-store',headers:{'Authorization':'Bearer '+KEY},body:fd})
      .then(function(r){ return r.json().catch(function(){ return {}; }).then(function(j){ if(!r.ok){ var e=new Error(_te({data:j},j.error || ('HTTP '+r.status))); e.status=r.status; e.data=j; throw e; } return j; }); })
      .then(function(j){ busy(btn,false); onJob('imp',j); poll('imp'); })
      .catch(function(e){ busy(btn,false); setImportBusy(false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- Vorschau */
  function renderPreview(j,restored){
    var r=j.result || {}; PREV={job:j.job,kind:r.kind,title:r.title || ''};
    el('trPrev').classList.remove('hidden');
    var tag=el('trPrevTag'); tag.textContent=r.condensed ? _t('trj.aiPrep') : _t('trj.raw'); tag.className='tag '+(r.condensed ? 'ok' : 'rose');
    var src=r.kind==='url' ? 'Website <b>'+h(r.title)+'</b>' : _t('trj.doc')+' <b>'+h(clip(r.title,80))+'</b>'+(r.file_kind && EXT[r.file_kind] ? ' ('+EXT[r.file_kind]+')' : '');
    var parts=[src];
    if(r.pages && r.pages.length) parts.push(h(r.pages.length===1 ? _t('trj.page1') : _t('trj.pageN',{n:r.pages.length})));
    parts.push(h(_t('trj.rawChars',{n:n(r.raw_chars || 0)}))); parts.push(h(_t('trj.charged',{n:n(r.cost || 0)})));
    if(restored && j.updated_at) parts.push(h(_t('trj.from',{d:when(j.updated_at)})));
    el('trPrevMeta').innerHTML=parts.join(' · ')+(r.note ? '<div class="msg info">'+T(r.note)+'</div>' : '')+(r.truncated ? '<div class="msg info">'+_t('trj.trunc')+'</div>' : '');
    var pg=el('trPrevPages');
    if(r.pages && r.pages.length){
      pg.classList.remove('hidden'); el('trPrevPagesSum').textContent=_t('trj.pagesRead',{n:r.pages.length})+(r.errors && r.errors.length ? ' · '+_t('trj.skippedN',{n:r.errors.length}) : '');
      el('trPrevList').innerHTML=r.pages.map(function(p){ return '<li><b>'+h(clip(p.title || p.url,70))+'</b> <span class="mute">· '+h(_t('trj.chars',{n:n(p.chars)}))+'</span><br><span class="mute" style="word-break:break-all">'+h(p.url)+'</span></li>'; }).join('')
        +(r.errors || []).map(function(x){ return '<li class="mute">'+_t('trj.skipped')+' <span style="word-break:break-all">'+h(x.url)+'</span> – '+T(x.hint || x.error)+'</li>'; }).join('');
    } else pg.classList.add('hidden');
    el('trPrevText').value=r.preview || ''; prevCount(); msg(el('trPrevMsg'),'');
    if(!restored) setTimeout(function(){ el('trPrev').scrollIntoView({behavior:'smooth',block:'start'}); },80);
  }
  function hidePreview(){ el('trPrev').classList.add('hidden'); PREV=null; }
  function prevCount(){
    var t=el('trPrevText').value.trim().length, kb=(OV && OV.kb_chars) || 0, after=kb ? kb+2+t : t, over=after>KB_LIMIT;
    el('trPrevCount').innerHTML=h(_t('trj.chars',{n:n(t)}))+' · '+_t('trj.after')+' <b style="color:'+(over ? 'var(--rose)' : 'var(--ink)')+'">'+n(after)+'</b> / '+n(KB_LIMIT)+(over ? ' – '+_t('trj.shorten') : '')+(t>KB_LIMIT ? ' · '+_t('trj.tooLongR') : '');
  }
  function applyPreview(mode){
    var t=el('trPrevText').value, box=el('trPrevMsg');
    if(!t.trim()){ msg(box,_t('trj.prevEmpty'),'err'); return; }
    if(mode==='replace' && OV && OV.kb_chars>0 && !confirm(_t('trj.replQ',{n:n(OV.kb_chars)}))) return;
    var b1=el('trPrevAppend'), b2=el('trPrevReplace'); busy(b1,true); busy(b2,true);
    post('/train/apply',{text:t,mode:mode,job:PREV ? PREV.job : '',title:PREV ? PREV.title : ''}).then(function(r){
      busy(b1,false); busy(b2,false); var kind=PREV ? PREV.kind : 'url'; hidePreview(); JOB.imp=null;
      msg(el(kind==='url' ? 'trUrlMsg' : 'trFileMsg'),h(mode==='replace' ? _t('trj.replOk',{n:n(r.kb_chars)}) : _t('trj.appOk',{n:n(r.kb_chars)}))+' <a href="#" data-tropen="1">'+_t('trj.testNow')+'</a>','ok');
      if(kind==='file'){ FILE=null; el('trFile').value=''; el('trFileName').textContent=''; el('trFileGo').disabled=true; el('trFileGo').textContent=_t('trj.upload'); }
      refreshOverview();
    }).catch(function(e){ busy(b1,false); busy(b2,false); showErr(box,e); });
  }
  function dropPreview(){
    if(!confirm(_t('trj.dropQ'))) return;
    var j=PREV; hidePreview(); JOB.imp=null; if(j && j.job) post('/train/job/cancel',{id:j.job}).catch(function(){});
  }

  /* ---------------------------------------------------------------- 3 · FAQ */
  var SRC={correction:[_t('trj.s.corr'),'rose'],import:[_t('trj.s.imp'),'indigo'],gap:[_t('trj.s.gap'),'teal']};
  function faqRow(f,focus){
    var d=document.createElement('div'); d.className='call trFrow'; d.dataset.id=String(f.id || 0); d.dataset.src=f.source || 'manual';
    var t=SRC[f.source];
    d.innerHTML='<div class="row" style="gap:8px;align-items:flex-start"><input type="text" class="trFq" maxlength="500" placeholder="'+h(_t('trj.qPh'))+'" style="flex:1;min-width:160px" value="'+h(f.question)+'"><button class="btn s ghost trFdel" type="button" title="'+h(_t('trj.del'))+'" aria-label="'+h(_t('trj.del'))+'">✕</button></div>'
      +'<textarea class="trFa" maxlength="2000" placeholder="'+h(_t('trj.aPh'))+'" style="min-height:72px;margin-top:8px">'+h(f.answer)+'</textarea>'
      +'<div class="row" style="justify-content:space-between;margin-top:6px"><label class="small" style="margin:0;display:inline-flex;gap:6px;align-items:center;color:var(--ink2)"><input type="checkbox" class="trFon"'+(f.active===0 ? '' : ' checked')+'> '+_t('sj.active.l')+'</label>'+(t ? '<span class="tag '+t[1]+'">'+t[0]+'</span>' : '')+'</div>'
      +(f.wrong ? '<div class="mute small" style="margin-top:6px">'+h(_t('trj.instead',{t:clip(f.wrong,180)}))+'</div>' : '');
    d.addEventListener('input',function(){ d.style.borderColor=''; faqDirty=true; dirtyUI(); });
    d.querySelector('.trFdel').onclick=function(){ delFaq(d); };
    el('trFaqList').appendChild(d);
    if(focus){ setTimeout(function(){ d.scrollIntoView({behavior:'smooth',block:'center'}); var x=focus==='a' ? d.querySelector('.trFa') : d.querySelector('.trFq'); x.focus({preventScroll:true}); },60); }
    return d;
  }
  function renderFaq(prompt){
    var l=el('trFaqList'); l.innerHTML='';
    FAQ.forEach(function(f){ faqRow(f); });
    if(!FAQ.length) l.innerHTML='<div class="mute small trFempty" style="margin-top:10px">'+_t('trj.faqEmpty')+'</div>';
    if(prompt) faqInfo({faq_in_prompt:prompt.faq_in,faq_active:prompt.faq_total,chars:prompt.chars,limit:6000});
  }
  function faqInfo(p){
    var i=el('trFaqInfo'); if(!p || !p.faq_active){ i.innerHTML=''; return; }
    i.innerHTML=p.faq_in_prompt<p.faq_active ? _t('trj.faqFit',{a:p.faq_in_prompt,b:p.faq_active})
      : h(_t('trj.faqAll',{n:p.faq_active}));
  }
  function faqCollect(){ return [].slice.call(document.querySelectorAll('#trFaqList .trFrow')).map(function(r){ return {id:+r.dataset.id || 0,question:r.querySelector('.trFq').value,answer:r.querySelector('.trFa').value,active:r.querySelector('.trFon').checked ? 1 : 0,source:r.dataset.src}; }); }
  function addFaq(f,focus){ var e=document.querySelector('#trFaqList .trFempty'); if(e) e.remove(); faqDirty=true; dirtyUI(); return faqRow(f || {question:'',answer:'',source:'manual'},focus || 'q'); }
  function delFaq(row){
    var id=+row.dataset.id || 0;
    if(!id){ row.remove(); faqDirty=faqCollect().some(function(x){ return !x.id; }) || faqDirty; dirtyUI(); return; }
    if(!confirm(_t('trj.delQ'))) return;
    post('/train/faq/delete',{id:id}).then(function(){ row.remove(); FAQ=FAQ.filter(function(f){ return f.id!==id; }); msg(el('trFaqMsg'),_t('trj.deleted'),'ok'); refreshOverview(); })
      .catch(function(e){ showErr(el('trFaqMsg'),e); });
  }
  function saveFaq(){
    var btn=el('trFaqSave'), box=el('trFaqMsg'); busy(btn,true,_t('kuj.savingN'));
    return post('/train/faq',{items:faqCollect(),known:FAQ.map(function(f){ return f.id; })}).then(function(r){
      busy(btn,false); FAQ=r.items || []; renderFaq(); faqDirty=false; dirtyUI(); msg(box,_t('trj.faqSaved'),'ok'); refreshOverview(); return true;
    }).catch(function(e){
      busy(btn,false); showErr(box,e);
      var i=e && e.data ? e.data.index : null, rows=document.querySelectorAll('#trFaqList .trFrow');
      if(i!=null && rows[i]){ rows[i].style.borderColor='var(--rose)'; rows[i].scrollIntoView({behavior:'smooth',block:'center'}); }
      return false;
    });
  }
  function dirtyUI(){ el('trFaqDirty').textContent=faqDirty ? _t('trj.dirty') : ''; el('trTestDirty').textContent=testDirty ? _t('trj.dirty') : ''; }

  /* ---------------------------------------------------------------- Korrektur (auch von anderen Tabs: window.trainCorrect) */
  function openCorr(c){
    if(typeof go==='function' && !visible()) go('training');
    var target=c.plan && planOfId(c.plan) ? c.plan : PLAN;
    if(target!==PLAN && confirmLeave()){
      pendingCorr={plan:target,question:c.question,wrong:c.wrong}; faqDirty=false; testDirty=false;
      stopJobs(); hidePreview(); PLAN=target; lsSet(target); renderPlans(); load(); return;
    }
    fillCorr({plan:target,question:c.question,wrong:c.wrong});
  }
  function fillCorr(c){
    CORR={plan:c.plan || PLAN}; var box=el('trSecCorr'); box.classList.remove('hidden');
    el('trCorrPlan').textContent=planName(CORR.plan); el('trCorrQ').value=clip(c.question || '',500); el('trCorrWrong').value=clip(c.wrong || '',1000); el('trCorrRight').value=''; msg(el('trCorrMsg'),'');
    setTimeout(function(){ box.scrollIntoView({behavior:'smooth',block:'start'}); el(c.question ? 'trCorrRight' : 'trCorrQ').focus({preventScroll:true}); },80);
  }
  function applyPendingCorr(){ if(pendingCorr && (!pendingCorr.plan || pendingCorr.plan===PLAN)){ var c=pendingCorr; pendingCorr=null; fillCorr(c); } }
  function saveCorr(){
    var btn=el('trCorrSave'), box=el('trCorrMsg'), plan=(CORR && CORR.plan) || PLAN;
    var b={question:el('trCorrQ').value,wrong:el('trCorrWrong').value,correct:el('trCorrRight').value,as_test:el('trCorrTest').checked ? 1 : 0};
    if(!b.question.trim()){ msg(box,_t('trj.needQ'),'err'); el('trCorrQ').focus(); return; }
    if(!b.correct.trim()){ msg(box,_t('trj.needA'),'err'); el('trCorrRight').focus(); return; }
    busy(btn,true,_t('kuj.savingN'));
    post('/train/correct',b,plan).then(function(r){
      busy(btn,false); msg(box,(r.updated ? _t('trj.corrUpd') : _t('trj.corrSaved'))+(r.test_id ? ' '+_t('trj.asTest') : ''),'ok');
      if(plan===PLAN){ if(!faqDirty){ api('/train/faq'+qs()).then(function(x){ FAQ=x.items || []; renderFaq(x.prompt); }).catch(function(){}); } if(r.test_id && !testDirty) reloadTests(); refreshOverview(); }
      setTimeout(function(){ el('trSecCorr').classList.add('hidden'); },2600);
    }).catch(function(e){ busy(btn,false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 4 · Regeln */
  function rulesCount(){ var c=el('trRules').value.length; el('trRulesCount').innerHTML='<span style="color:'+(c>8000 ? 'var(--rose)' : 'inherit')+'">'+n(c)+' / '+h(_t('trj.chars',{n:n(8000)}))+'</span>'+(OV && OV.prompt && OV.prompt.rules_cut ? ' · <span style="color:var(--rose)">'+_t('trj.cut')+'</span>' : ''); }
  function saveRules(){
    var btn=el('trRulesSave'), box=el('trRulesMsg'); busy(btn,true,_t('kuj.savingN'));
    post('/train/rules',{rules:el('trRules').value}).then(function(r){ busy(btn,false); el('trRules').value=r.rules || ''; rulesCount(); msg(box,_t('trj.rulesSaved'),'ok'); refreshOverview(); })
      .catch(function(e){ busy(btn,false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 5 · Prüfung */
  function testRow(t,focus){
    var d=document.createElement('div'); d.className='call trTrow'; d.dataset.id=String(t.id || 0);
    var st=t.last_pass===1 ? '<span class="tag ok" title="'+h(_t('trj.t.pass'))+'">✓</span>' : (t.last_pass===0 ? '<span class="tag rose" title="'+h(_t('trj.t.fail'))+'">✗</span>' : '<span class="tag" title="'+h(_t('trj.t.none'))+'">–</span>');
    d.innerHTML='<div class="row" style="gap:8px;align-items:center">'+st+'<input type="text" class="trTq" maxlength="500" placeholder="'+h(_t('trj.tqPh'))+'" style="flex:1;min-width:160px" value="'+h(t.question)+'">'
      +(t.id ? '<button class="btn s trTone" type="button" title="'+h(_t('trj.oneT'))+'">'+_t('trj.check')+'</button>' : '')+'<button class="btn s ghost trTdel" type="button" title="'+h(_t('trj.tdel'))+'" aria-label="'+h(_t('trj.tdel'))+'">✕</button></div>'
      +'<input type="text" class="trTe" maxlength="2000" placeholder="'+h(_t('trj.tePh'))+'" style="margin-top:8px" value="'+h(t.expect)+'">'
      +(t.last_pass!=null || t.last_reason ? '<div class="small" style="margin-top:8px;color:'+(t.last_pass===1 ? 'var(--ok)' : (t.last_pass===0 ? 'var(--rose)' : 'var(--mute)'))+'"><b>'+(t.last_pass===1 ? _t('trj.T.pass') : (t.last_pass===0 ? _t('trj.T.fail') : _t('trj.T.none')))+'</b> · '+T(t.last_reason || '')+(t.last_run_at ? ' <span class="mute">· '+when(t.last_run_at)+'</span>' : '')+'</div>' : '')
      +(t.last_answer ? '<details style="margin-top:6px"><summary class="small" style="cursor:pointer;color:var(--gold)">'+_t('trj.agAns')+'</summary><div class="small" style="white-space:pre-wrap;margin-top:6px;padding:10px 12px;border-radius:12px;background:var(--sur2)">'+h(t.last_answer)+'</div>'
        +'<button class="btn ghost s trTfix" type="button" style="margin-top:6px">'+_t('trj.fix')+'</button></details>' : '');
    d.addEventListener('input',function(){ d.style.borderColor=''; testDirty=true; dirtyUI(); });
    d.querySelector('.trTdel').onclick=function(){ d.remove(); testDirty=true; dirtyUI(); if(!document.querySelector('#trTestList .trTrow')) emptyTests(); };
    var one=d.querySelector('.trTone'); if(one) one.onclick=function(){ runTests([+d.dataset.id]); };
    var fx=d.querySelector('.trTfix'); if(fx) fx.onclick=function(){ openCorr({plan:PLAN,question:t.question,wrong:t.last_answer || ''}); };
    el('trTestList').appendChild(d);
    if(focus) setTimeout(function(){ d.scrollIntoView({behavior:'smooth',block:'center'}); d.querySelector('.trTq').focus({preventScroll:true}); },60);
  }
  function emptyTests(){ el('trTestList').innerHTML='<div class="mute small trTempty" style="margin-top:10px">'+_t('trj.testsEmpty')+'</div>'; }
  function renderTests(sum){
    el('trTestList').innerHTML=''; TESTS.forEach(function(t){ testRow(t); }); if(!TESTS.length) emptyTests();
    var s=sum || {}, sc=el('trTestScore');
    sc.innerHTML=s.ran ? '<div class="row" style="gap:10px"><span class="num" style="font-size:28px;color:'+(s.score>=80 ? 'var(--ok)' : (s.score>=50 ? 'var(--gold)' : 'var(--rose)'))+'">'+s.score+' %</span><span class="mute small">'+h(_t('trj.passed',{p:s.passed,r:s.ran}))+(s.total>s.ran ? ' · '+h(_t('trj.notYet',{n:s.total-s.ran})) : '')+(s.last_run ? '<br>'+h(_t('kuj.lastSeen',{t:when(s.last_run)})) : '')+'</span></div>' : '';
    var nT=TESTS.length; el('trTestRun').textContent=nT ? _t('trj.startN',{n:n(nT*8)}) : _t('trj.start');
  }
  function testsCollect(){ return [].slice.call(document.querySelectorAll('#trTestList .trTrow')).map(function(r){ return {id:+r.dataset.id || 0,question:r.querySelector('.trTq').value,expect:r.querySelector('.trTe').value}; }); }
  function reloadTests(){ var my=seq; return api('/train/tests'+qs()).then(function(r){ if(my!==seq) return; TESTS=r.items || []; if(!testDirty) renderTests(r.summary); }).catch(function(){}); }
  function saveTests(){
    var btn=el('trTestSave'), box=el('trTestMsg'); busy(btn,true,_t('kuj.savingN'));
    return post('/train/tests',{items:testsCollect(),known:TESTS.map(function(t){ return t.id; })}).then(function(r){ busy(btn,false); TESTS=r.items || []; testDirty=false; dirtyUI(); renderTests(r.summary); msg(box,_t('trj.testsSaved'),'ok'); refreshOverview(); return true; })
      .catch(function(e){ busy(btn,false); showErr(box,e); var i=e && e.data ? e.data.index : null, rows=document.querySelectorAll('#trTestList .trTrow'); if(i!=null && rows[i]){ rows[i].style.borderColor='var(--rose)'; rows[i].scrollIntoView({behavior:'smooth',block:'center'}); } return false; });
  }
  function runTests(ids){
    var box=el('trTestMsg');
    (testDirty ? saveTests() : Promise.resolve(true)).then(function(ok){
      if(!ok) return;
      if(!TESTS.length){ msg(box,_t('trj.needTests'),'err'); return; }
      var cnt=ids ? ids.length : TESTS.length;
      busy(el('trTestRun'),true,_t('trj.starting')); msg(box,h(cnt===1 ? _t('trj.run1',{t:n(8)}) : _t('trj.runN',{n:cnt,t:n(cnt*8)})),'info');
      post('/train/run',ids ? {ids:ids} : {}).then(function(j){ onJob('tests',j); poll('tests'); })
        .catch(function(e){ busy(el('trTestRun'),false); showErr(box,e); });
    });
  }

  /* ---------------------------------------------------------------- 6 · Lücken */
  function findGaps(){
    var btn=el('trGapGo'), box=el('trGapMsg'), list=el('trGapList'); busy(btn,true,_t('trj.scanning')); msg(box,''); list.innerHTML='';
    api('/train/gaps'+qs()).then(function(r){
      busy(btn,false); var sc=r.scanned || {};
      var stats=h(_t('trj.scanned',{c:n(sc.calls || 0),w:n(sc.whatsapp || 0),r:n(sc.reported || 0)})+(r.covered ? ' · '+_t('trj.covered',{n:r.covered}) : '')+(r.cost ? ' · '+_t('trj.charged',{n:r.cost}) : ' · '+_t('trj.free')));
      if(!(r.suggestions || []).length){ msg(box,stats+'<br><b>'+_t('trj.noGaps')+'</b> '+(sc.conversations || sc.reported ? _t('trj.allGood') : _t('trj.noConv')),'ok'); return; }
      msg(box,stats+(r.ai_note ? '<br>'+T(r.ai_note) : ''),'info');
      var html='';
      if((r.clusters || []).length){
        html+='<div class="small mute" style="margin-top:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">'+_t('trj.aiSugg')+'</div>'+r.clusters.map(function(c,i){ return gapItem(c.question,c.count,null,c.examples,'','c'+i); }).join('');
        html+='<details class="guide"><summary>'+h(_t('trj.allFound',{n:r.suggestions.length}))+'</summary><div style="padding:0 12px 12px">'+r.suggestions.map(function(s,i){ return gapItem(s.question,s.count,s.channels,s.examples,s.answer_was,'s'+i,s.last_at); }).join('')+'</div></details>';
      } else html+=r.suggestions.map(function(s,i){ return gapItem(s.question,s.count,s.channels,s.examples,s.answer_was,'s'+i,s.last_at); }).join('');
      list.innerHTML=html;
      [].slice.call(list.querySelectorAll('[data-trfaq]')).forEach(function(b){ b.onclick=function(){ var q=b.getAttribute('data-q') || ''; addFaq({question:q,answer:'',source:'gap'},'a'); b.disabled=true; b.textContent=_t('trj.asFaq'); msg(el('trFaqMsg'),_t('trj.newFaq'),'info'); }; });
      balance();
    }).catch(function(e){ busy(btn,false); showErr(box,e); });
  }
  function gapItem(q,count,channels,examples,answer,key,last){
    var ch=(channels || []).map(function(c){ return '<span class="tag'+(c==='reported' ? ' teal' : '')+'">'+h(CH[c] || c)+'</span>'; }).join(' ');
    var ex=(examples || []).filter(function(x){ return x!==q; }).slice(0,3);
    return '<div class="call"><div class="row" style="justify-content:space-between;align-items:flex-start;gap:10px"><div style="flex:1;min-width:180px"><b>'+h(q)+'</b><div class="mute small" style="margin-top:4px">'+h(_t('trj.asked',{n:count}))+(last ? ' · '+h(_t('kuj.lastSeen',{t:when(last)})) : '')+'</div>'
      +(ex.length ? '<div class="mute small" style="margin-top:4px">'+_t('trj.also')+' '+ex.map(function(x){ return '„'+h(clip(x,90))+'“'; }).join(' · ')+'</div>' : '')
      +(answer ? '<div class="small" style="margin-top:6px;padding:8px 10px;border-radius:10px;background:var(--sur2);color:var(--ink2)">'+h(_t('trj.said',{a:answer}))+'</div>' : '')
      +'</div><div class="row" style="gap:6px">'+ch+'<button class="btn s" type="button" data-trfaq="'+h(key)+'" data-q="'+h(q)+'">'+_t('trj.toFaq')+'</button></div></div></div>';
  }
  function saveGapTool(){
    var cb=el('trGapTool'), on=cb.checked;
    post('/train/settings',{gap_tool:on ? 1 : 0}).then(function(r){ cb.checked=!!+r.settings.gap_tool; msg(el('trGapMsg'),on ? _t('trj.gapOn') : _t('trj.gapOff'),'ok'); })
      .catch(function(e){ cb.checked=!on; showErr(el('trGapMsg'),e); });
  }

  /* ---------------------------------------------------------------- Verdrahtung */
  function wire(){
    el('trPlan').onchange=function(){ var v=this.value; if(!confirmLeave()){ this.value=PLAN; return; } faqDirty=false; testDirty=false; switchPlan(v); };
    el('trReload').onclick=function(){ if(confirmLeave()){ stopJobs(); load(); } };
    var kb=function(){ if(typeof openAgent==='function' && PLAN) openAgent(PLAN); };
    el('trOpenKb').onclick=kb; el('trOpenKb2').onclick=kb;
    el('trUrlGo').onclick=importUrl; el('trUrl').addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); importUrl(); } });
    var drop=el('trDrop'), fi=el('trFile');
    drop.onclick=function(){ fi.click(); };
    drop.addEventListener('keydown',function(e){ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); fi.click(); } });
    fi.onchange=function(){ pickFile(fi.files && fi.files[0]); };
    ['dragenter','dragover'].forEach(function(t){ drop.addEventListener(t,function(e){ e.preventDefault(); drop.style.borderColor='var(--gold)'; drop.style.background='var(--goldl)'; }); });
    ['dragleave','drop'].forEach(function(t){ drop.addEventListener(t,function(e){ e.preventDefault(); drop.style.borderColor=''; drop.style.background=''; }); });
    drop.addEventListener('drop',function(e){ var f=e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if(f) pickFile(f); });
    el('trFileGo').onclick=uploadFile;
    el('trPrevText').addEventListener('input',prevCount);
    el('trPrevAppend').onclick=function(){ applyPreview('append'); }; el('trPrevReplace').onclick=function(){ applyPreview('replace'); }; el('trPrevDrop').onclick=dropPreview;
    el('trFaqAdd').onclick=function(){ addFaq(); }; el('trFaqSave').onclick=saveFaq;
    el('trRules').addEventListener('input',rulesCount); el('trRulesSave').onclick=saveRules;
    el('trRulesEx').onclick=function(){ var t=el('trRules'); if(t.value.trim() && !confirm(_t('trj.rulesQ'))) return; t.value=RULES_EX; rulesCount(); t.focus(); };
    el('trTestAdd').onclick=function(){ var e=document.querySelector('#trTestList .trTempty'); if(e) e.remove(); testRow({id:0,question:'',expect:'',last_pass:null},true); testDirty=true; dirtyUI(); };
    el('trTestSave').onclick=saveTests; el('trTestRun').onclick=function(){ runTests(null); };
    el('trGapGo').onclick=findGaps; el('trGapTool').onchange=saveGapTool;
    el('trCorrSave').onclick=saveCorr; el('trCorrClose').onclick=function(){ el('trSecCorr').classList.add('hidden'); CORR=null; };
    el(PANEL).addEventListener('click',function(e){
      var c=e.target.closest('[data-trcancel]'); if(c){ e.preventDefault(); if(confirm(_t('trj.cancelQ'))) cancelJob(c.getAttribute('data-trcancel')); return; }
      var o=e.target.closest('[data-tropen]'); if(o){ e.preventDefault(); kb(); }
    });
    window.addEventListener('beforeunload',function(e){ if((faqDirty || testDirty) && visible()){ e.preventDefault(); e.returnValue=''; } });
  }

  /** Von anderen Tabs („Antwort korrigieren“): öffnet das Korrektur-Formular vorbelegt. */
  window.trainCorrect=function(plan,question,wrongAnswer){
    var c={plan:String(plan || ''),question:String(question || ''),wrong:String(wrongAnswer || '')};
    if(!inited){ pendingCorr=c; if(typeof go==='function') go('training'); return; }
    openCorr(c);
  };

  window.STUDIO_PANELS=window.STUDIO_PANELS || {};
  STUDIO_PANELS['training']={
    init:function(){
      if(inited) return; inited=true; wire();
      var want=pendingCorr && pendingCorr.plan && planOfId(pendingCorr.plan) ? pendingCorr.plan : '';
      if(want){ PLAN=want; lsSet(want); }
      renderPlans(); load();
    },
    show:function(){
      if(!inited) return this.init();
      head(); refreshOverview().then(function(){ ['imp','tests'].forEach(function(k){ if(JOB[k] && JOB[k].status==='running') poll(k); }); });
    }
  };
})();
