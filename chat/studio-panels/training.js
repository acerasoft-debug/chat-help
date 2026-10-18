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
  var CH={phone:'Telefon',whatsapp:'WhatsApp',reported:'gemeldet'};

  function el(id){ return document.getElementById(id); }
  function h(s){ return esc(s==null ? '' : s); }
  function n(x){ return de(x); }
  function visible(){ var p=el(PANEL); return !!p && !p.closest('.hidden'); }
  function errText(e){ var d=(e && e.data) || {}; return d.message || d.hint || (e && e.message) || 'Unbekannter Fehler'; }
  function lsGet(){ try{ return localStorage.getItem(LS_PLAN) || ''; }catch(e){ return ''; } }
  function lsSet(v){ try{ localStorage.setItem(LS_PLAN,v); }catch(e){} }
  function when(iso){ if(!iso) return ''; var d=new Date(iso); return isNaN(d) ? '' : d.toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
  function clip(s,max){ s=String(s==null ? '' : s); return s.length>max ? s.slice(0,max-1)+'…' : s; }
  function isAct(id){ if(typeof active!=='function') return false; try{ return active(id) || active('buy-'+String(id).replace(/^agent-/,'')); }catch(e){ return false; } }
  function planOfId(id){ return (PLANS || []).find(function(p){ return p.id===id; }) || null; }
  function planName(id){ var p=planOfId(id); return p ? p.name : id; }
  function cfgPlans(){ var m={}; ((ME && ME.configs) || []).forEach(function(c){ m[c.plan]=1; }); return m; }
  function planList(){
    var cfg=cfgPlans(), l=(PLANS || []).filter(function(p){ return p.kind==='agent'; });
    if(PLAN && !l.some(function(p){ return p.id===PLAN; }) && planOfId(PLAN) && planOfId(PLAN).kind!=='buy') l.push(planOfId(PLAN));
    return l.sort(function(a,b){ return (isAct(b.id)-isAct(a.id)) || ((cfg[b.id] ? 1 : 0)-(cfg[a.id] ? 1 : 0)) || String(a.name).localeCompare(String(b.name),'de'); });
  }
  function qs(p){ return '?plan='+encodeURIComponent(PLAN)+(p || ''); }
  function post(path,o,plan){ o=o || {}; o.plan=plan || PLAN; return api(path,{method:'POST',body:JSON.stringify(o)}); }
  function balance(){ if(typeof refreshMe==='function'){ try{ refreshMe(); }catch(e){} } }
  function buyHtml(d){ d=d || {}; return 'Nicht genug Tokens'+(d.need!=null ? ' – benötigt '+n(d.need)+', verfügbar '+n(d.balance || 0) : '')+'. <a href="/#preise" target="_blank" rel="noopener"><b>Tokens kaufen</b></a>'; }
  function showErr(box,e){ var d=(e && e.data) || {}; if((e && e.status===402) || d.error==='insufficient_tokens') msg(box,buyHtml(d),'err'); else msg(box,h(errText(e)),'err'); }
  function busy(btn,on,label){ if(!btn) return; if(on){ if(!btn.dataset.l) btn.dataset.l=btn.textContent; btn.disabled=true; if(label) btn.textContent=label; } else { btn.disabled=false; if(btn.dataset.l){ btn.textContent=btn.dataset.l; delete btn.dataset.l; } } }
  function confirmLeave(){ return !(faqDirty || testDirty) || confirm('Es gibt ungespeicherte Änderungen bei FAQ oder Testfragen. Verwerfen?'); }

  /* ---------------------------------------------------------------- Agent-Auswahl & Laden */
  function renderPlans(){
    var sel=el('trPlan'), l=planList();
    if(!PLAN || !l.some(function(p){ return p.id===PLAN; })){
      var want=lsGet(), cfg=cfgPlans();
      if(!l.some(function(p){ return p.id===want; })){ var a=l.find(function(p){ return isAct(p.id); }), c=l.find(function(p){ return cfg[p.id]; }); want=a ? a.id : (c ? c.id : (l[0] ? l[0].id : '')); }
      PLAN=want;
    }
    sel.innerHTML=l.map(function(p){ return '<option value="'+h(p.id)+'">'+h(p.name)+(isAct(p.id) ? ' · aktiv' : '')+'</option>'; }).join('') || '<option value="">Keine Agenten verfügbar</option>';
    sel.value=PLAN; head();
  }
  function head(){
    var act=isAct(PLAN), p=planOfId(PLAN), r=el('trRent');
    el('trActive').classList.toggle('hidden',!act);
    r.classList.toggle('hidden',act || !p);
    if(p){ r.href='/chat/tokens-checkout.php?plan='+encodeURIComponent(PLAN)+'&_='+Date.now().toString(36); r.textContent='Agent mieten · '+(p.eur_month!=null ? Number(p.eur_month).toLocaleString('de-DE') : '–')+' €/Monat'; }
  }
  function load(){
    if(!PLAN){ msg(el('trTop'),'Keine Agenten verfügbar.','err'); return Promise.resolve(); }
    var my=++seq; msg(el('trTop'),'');
    return Promise.all([api('/train/overview'+qs()),api('/train/faq'+qs()),api('/train/rules'+qs()),api('/train/tests'+qs())]).then(function(r){
      if(my!==seq) return;
      OV=r[0]; renderOverview();
      FAQ=r[1].items || []; renderFaq(r[1].prompt); faqDirty=false; dirtyUI();
      RULES_EX=r[2].example || ''; el('trRules').value=r[2].rules || ''; el('trRules').placeholder=RULES_EX; rulesCount();
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
    el('trKbSub').innerHTML='von '+n(KB_LIMIT)+' Zeichen'+(kb>KB_PROMPT ? ' · <span style="color:var(--rose)">Agent liest die ersten '+n(KB_PROMPT)+'</span>' : '');
    var bar=el('trKbBar'); bar.style.width=(kb>0 ? Math.max(1.5,pct) : 0).toFixed(1)+'%'; bar.style.background=pct>=90 ? 'var(--rose)' : 'linear-gradient(90deg,#ecd08a,#c9972f)';
    el('trFaqNum').textContent=n(o.faq_count || 0);
    el('trFaqSub').textContent='verbindliche Antworten'+(o.corrections ? ' · '+o.corrections+' '+(o.corrections===1 ? 'Korrektur' : 'Korrekturen') : '');
    var t=o.tests || {};
    el('trScoreNum').textContent=t.score!=null ? t.score+' %' : '–';
    el('trScoreNum').style.color=t.score==null ? '' : (t.score>=80 ? 'var(--ok)' : (t.score>=50 ? 'var(--gold)' : 'var(--rose)'));
    el('trScoreSub').textContent=t.ran ? t.passed+' von '+t.ran+' bestanden'+(t.ran<t.total ? ' · '+(t.total-t.ran)+' offen' : '')+(t.last_run ? ' · '+when(t.last_run) : '') : (t.total ? t.total+' '+(t.total===1 ? 'Testfrage' : 'Testfragen')+' – noch nicht geprüft' : 'noch keine Testfragen');
    var s=o.last_source;
    el('trSrcNum').textContent=s ? clip(s.title || (s.kind==='url' ? 'Website' : 'Dokument'),60) : '–';
    el('trSrcSub').textContent=s ? ({url:'Website',file:'Dokument',text:'Text'}[s.kind] || 'Quelle')+' · '+n(s.chars)+' Zeichen · '+(s.mode==='replace' ? 'ersetzt' : 'angehängt')+' · '+when(s.created_at) : 'noch nichts importiert';
    el('trKbTag').textContent=n(kb)+' / '+n(KB_LIMIT)+' Zeichen';
    el('trKbWarn').innerHTML=kb>KB_PROMPT ? '<div class="msg err">Die Wissensbasis ist länger als '+n(KB_PROMPT)+' Zeichen – der Agent liest nur den Anfang. Wichtiges nach oben, Doppeltes kürzen oder Details als FAQ anlegen.</div>'
      : (kb===0 ? '<div class="msg info">Noch leer: Starte mit „Website importieren“ oder lade ein Dokument hoch – oder schreib die Fakten selbst hinein.</div>' : '');
    el('trGapTool').checked=!!(o.settings && +o.settings.gap_tool);
    if(o.prompt) faqInfo(o.prompt);
  }

  /* ---------------------------------------------------------------- Aufträge (Import, Testlauf) */
  function stopJobs(){ JOB.imp=null; JOB.tests=null; if(tick){ clearInterval(tick); tick=null; } setImportBusy(false); busy(el('trTestRun'),false); }
  function elapsed(j){ var t=j && j.created_at ? Math.max(0,Math.round((Date.now()-new Date(j.created_at).getTime())/1000)) : 0; return Math.floor(t/60)+':'+String(t%60).padStart(2,'0'); }
  function startTick(){ if(tick) return; tick=setInterval(function(){ var any=false; ['imp','tests'].forEach(function(k){ var j=JOB[k]; if(j && j.status==='running'){ any=true; var s=document.querySelector('[data-trel="'+k+'"]'); if(s) s.textContent=elapsed(j); } }); if(!any){ clearInterval(tick); tick=null; } },1000); }
  function impBox(j){ return el(j && j.kind==='url' ? 'trUrlMsg' : 'trFileMsg'); }
  function setImportBusy(on){ el('trUrlGo').disabled=on; el('trFileGo').disabled=on || !FILE; }
  function progressHtml(kind,j){ return '<span class="tag gold">läuft · <span data-trel="'+kind+'">'+elapsed(j)+'</span></span> '+h(j.progress || 'Wird bearbeitet …')+' <button class="btn ghost s" type="button" data-trcancel="'+kind+'">Abbrechen</button>'; }
  function onJob(kind,j){
    JOB[kind]=j;
    if(kind==='imp'){
      var box=impBox(j);
      if(j.status==='running'){ setImportBusy(true); msg(box,progressHtml(kind,j),'info'); startTick(); return; }
      setImportBusy(false);
      if(j.status==='done'){ msg(box,j.result && j.result.condensed ? 'Fertig ✓ – bitte die Vorschau prüfen und übernehmen.' : 'Text gelesen – bitte die Vorschau prüfen und kürzen.','ok'); renderPreview(j); balance(); refreshOverview(); return; }
      if(j.status==='cancelled'){ msg(box,j.error==='superseded' ? '' : 'Abgebrochen.','info'); return; }
      if(j.status==='closed'){ if(PREV && PREV.job===j.job) hidePreview(); msg(box,'Dieser Import wurde bereits übernommen oder verworfen.','info'); refreshOverview(); return; }   // z. B. in einem zweiten Studio-Tab
      if(j.error==='insufficient_tokens') msg(box,buyHtml(j),'err'); else msg(box,h(j.message || 'Der Import ist fehlgeschlagen.'),'err');
      return;
    }
    var tb=el('trTestMsg');
    if(j.status==='running'){ busy(el('trTestRun'),true,'Prüfung läuft …'); msg(tb,progressHtml(kind,j)+(j.total_tests ? ' <span class="mute">('+j.done_tests+' von '+j.total_tests+' fertig)</span>' : ''),'info'); startTick(); return; }
    busy(el('trTestRun'),false);
    if(j.status==='done' || j.status==='closed'){   // 'closed' = fertig und danach geschlossen (z. B. „Abbrechen“ im letzten Moment) – Ergebnisse sind gespeichert
      var r=j.result || {};
      msg(tb,'<b>'+(r.passed || 0)+' von '+(r.ran || 0)+' bestanden</b>'+(r.ran ? ' ('+Math.round((r.passed || 0)*100/r.ran)+' %)' : '')+' · berechnet: '+n(r.cost || 0)+' Tokens'+(r.note ? '<br>'+h(r.note) : ''),r.ran && r.passed===r.ran ? 'ok' : 'info');
      reloadTests(); balance(); refreshOverview(); return;
    }
    if(j.status==='cancelled'){ msg(tb,'Prüfung abgebrochen – bereits geprüfte Fragen sind gespeichert.','info'); reloadTests(); return; }
    if(j.error==='insufficient_tokens') msg(tb,buyHtml(j),'err'); else msg(tb,h(j.message || 'Die Prüfung ist fehlgeschlagen.'),'err');
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
    if(!u){ msg(box,'Bitte die Adresse deiner Website eingeben, z. B. www.meine-firma.de','err'); el('trUrl').focus(); return; }
    if(PREV && !confirm('Die aktuelle Vorschau wurde noch nicht übernommen und wird verworfen. Fortfahren?')) return;
    hidePreview(); busy(el('trUrlGo'),true,'Wird gestartet …'); msg(box,'Verbindung zur Website wird geprüft …','info');
    post('/train/import-url',{url:u}).then(function(j){ busy(el('trUrlGo'),false); onJob('imp',j); poll('imp'); })
      .catch(function(e){ busy(el('trUrlGo'),false); setImportBusy(false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 2 · Dokumente */
  function fileExt(name){ var m=/\.([a-z0-9]+)$/i.exec(name || ''); return m ? m[1].toLowerCase() : ''; }
  var RAW_MAX=120000;   // so viel Rohtext liest der Server höchstens → Import höchstens 80 Tokens (PDF 100)
  function fileCost(f){ var e=fileExt(f.name); if(e==='pdf') return '≈ 40–100 Tokens (PDF liest die KI)'; if(e==='docx') return '≈ 20–80 Tokens'; var c=Math.min(80,20+Math.ceil(Math.min(f.size,RAW_MAX)/2000)); return '≈ '+c+' Tokens'; }
  function pickFile(f){
    var box=el('trFileMsg'); msg(box,''); FILE=null; el('trFileGo').disabled=true; el('trFileGo').textContent='Hochladen & aufbereiten';
    if(!f){ el('trFileName').textContent=''; return; }
    var e=fileExt(f.name), lim=(OV && OV.upload_limit) || 10485760;
    if(!EXT[e]){ el('trFileName').textContent=''; msg(box,e==='doc' ? 'Alte Word-Dateien (.doc) bitte als .docx oder PDF speichern.' : (e==='xlsx' || e==='xls' ? 'Tabellen bitte als CSV speichern (Datei → Speichern unter → CSV).' : 'Erlaubt sind PDF, Word (.docx), TXT, Markdown (.md) und CSV.'),'err'); return; }
    if(f.size>lim){ el('trFileName').textContent=''; msg(box,'Die Datei ist zu groß ('+(f.size/1048576).toFixed(1).replace('.',',')+' MB) – höchstens '+(lim/1048576).toFixed(1).replace('.',',')+' MB.','err'); return; }
    if(e==='pdf' && OV && OV.pdf===false){ el('trFileName').textContent=''; msg(box,'PDF-Dateien liest unsere KI – sie ist gerade nicht verfügbar. Bitte als Word- (.docx) oder Textdatei hochladen.','err'); return; }
    FILE=f; el('trFileName').innerHTML='<span class="tag indigo">'+h(EXT[e])+'</span> <b>'+h(clip(f.name,70))+'</b> · '+(f.size<1048576 ? Math.max(1,Math.round(f.size/1024))+' KB' : (f.size/1048576).toFixed(1).replace('.',',')+' MB');
    el('trFileCost').textContent=e==='pdf' ? 'PDF-Dateien liest die KI (+20 Tokens).' : 'Berechnet erst, wenn die Vorschau fertig ist.';
    el('trFileGo').textContent='Hochladen & aufbereiten · '+fileCost(f); el('trFileGo').disabled=!!(JOB.imp && JOB.imp.status==='running');
  }
  function uploadFile(){
    if(!FILE) return; var box=el('trFileMsg'), btn=el('trFileGo');
    if(PREV && !confirm('Die aktuelle Vorschau wurde noch nicht übernommen und wird verworfen. Fortfahren?')) return;
    hidePreview(); busy(btn,true,'Wird hochgeladen …'); msg(box,'Datei wird hochgeladen …','info');
    var fd=new FormData(); fd.append('plan',PLAN); fd.append('file',FILE,FILE.name);
    fetch('/chat/tokens-api.php/v1/train/import-file?_='+Date.now(),{method:'POST',cache:'no-store',headers:{'Authorization':'Bearer '+KEY},body:fd})
      .then(function(r){ return r.json().catch(function(){ return {}; }).then(function(j){ if(!r.ok){ var e=new Error(j.message || j.error || ('HTTP '+r.status)); e.status=r.status; e.data=j; throw e; } return j; }); })
      .then(function(j){ busy(btn,false); onJob('imp',j); poll('imp'); })
      .catch(function(e){ busy(btn,false); setImportBusy(false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- Vorschau */
  function renderPreview(j,restored){
    var r=j.result || {}; PREV={job:j.job,kind:r.kind,title:r.title || ''};
    el('trPrev').classList.remove('hidden');
    var tag=el('trPrevTag'); tag.textContent=r.condensed ? 'KI-aufbereitet' : 'Rohtext (ohne KI)'; tag.className='tag '+(r.condensed ? 'ok' : 'rose');
    var src=r.kind==='url' ? 'Website <b>'+h(r.title)+'</b>' : 'Dokument <b>'+h(clip(r.title,80))+'</b>'+(r.file_kind && EXT[r.file_kind] ? ' ('+EXT[r.file_kind]+')' : '');
    var parts=[src];
    if(r.pages && r.pages.length) parts.push(r.pages.length+' '+(r.pages.length===1 ? 'Seite' : 'Seiten')+' gelesen');
    parts.push(n(r.raw_chars || 0)+' Zeichen Rohtext'); parts.push('berechnet: '+n(r.cost || 0)+' Tokens');
    if(restored && j.updated_at) parts.push('vom '+when(j.updated_at));
    el('trPrevMeta').innerHTML=parts.join(' · ')+(r.note ? '<div class="msg info">'+h(r.note)+'</div>' : '')+(r.truncated ? '<div class="msg info">Das Dokument war sehr lang – es wurde nur der Anfang gelesen.</div>' : '');
    var pg=el('trPrevPages');
    if(r.pages && r.pages.length){
      pg.classList.remove('hidden'); el('trPrevPagesSum').textContent='Gelesene Seiten ('+r.pages.length+')'+(r.errors && r.errors.length ? ' · '+r.errors.length+' übersprungen' : '');
      el('trPrevList').innerHTML=r.pages.map(function(p){ return '<li><b>'+h(clip(p.title || p.url,70))+'</b> <span class="mute">· '+n(p.chars)+' Zeichen</span><br><span class="mute" style="word-break:break-all">'+h(p.url)+'</span></li>'; }).join('')
        +(r.errors || []).map(function(x){ return '<li class="mute">Übersprungen: <span style="word-break:break-all">'+h(x.url)+'</span> – '+h(x.hint || x.error)+'</li>'; }).join('');
    } else pg.classList.add('hidden');
    el('trPrevText').value=r.preview || ''; prevCount(); msg(el('trPrevMsg'),'');
    if(!restored) setTimeout(function(){ el('trPrev').scrollIntoView({behavior:'smooth',block:'start'}); },80);
  }
  function hidePreview(){ el('trPrev').classList.add('hidden'); PREV=null; }
  function prevCount(){
    var t=el('trPrevText').value.trim().length, kb=(OV && OV.kb_chars) || 0, after=kb ? kb+2+t : t, over=after>KB_LIMIT;
    el('trPrevCount').innerHTML=n(t)+' Zeichen · Wissensbasis danach (Anhängen): <b style="color:'+(over ? 'var(--rose)' : 'var(--ink)')+'">'+n(after)+'</b> / '+n(KB_LIMIT)+(over ? ' – bitte kürzen oder „Ersetzen“ wählen' : '')+(t>KB_LIMIT ? ' · auch zum Ersetzen zu lang' : '');
  }
  function applyPreview(mode){
    var t=el('trPrevText').value, box=el('trPrevMsg');
    if(!t.trim()){ msg(box,'Die Vorschau ist leer.','err'); return; }
    if(mode==='replace' && OV && OV.kb_chars>0 && !confirm('Die bisherige Wissensbasis ('+n(OV.kb_chars)+' Zeichen) wird vollständig durch diesen Text ersetzt. Fortfahren?')) return;
    var b1=el('trPrevAppend'), b2=el('trPrevReplace'); busy(b1,true); busy(b2,true);
    post('/train/apply',{text:t,mode:mode,job:PREV ? PREV.job : '',title:PREV ? PREV.title : ''}).then(function(r){
      busy(b1,false); busy(b2,false); var kind=PREV ? PREV.kind : 'url'; hidePreview(); JOB.imp=null;
      msg(el(kind==='url' ? 'trUrlMsg' : 'trFileMsg'),(mode==='replace' ? 'Ersetzt ✓' : 'Angehängt ✓')+' – die Wissensbasis hat jetzt '+n(r.kb_chars)+' Zeichen. Dein Agent nutzt sie ab sofort. <a href="#" data-tropen="1">Jetzt testen →</a>','ok');
      if(kind==='file'){ FILE=null; el('trFile').value=''; el('trFileName').textContent=''; el('trFileGo').disabled=true; el('trFileGo').textContent='Hochladen & aufbereiten'; }
      refreshOverview();
    }).catch(function(e){ busy(b1,false); busy(b2,false); showErr(box,e); });
  }
  function dropPreview(){
    if(!confirm('Vorschau verwerfen? Die gelesenen Inhalte gehen verloren (bereits berechnete Tokens werden nicht erstattet).')) return;
    var j=PREV; hidePreview(); JOB.imp=null; if(j && j.job) post('/train/job/cancel',{id:j.job}).catch(function(){});
  }

  /* ---------------------------------------------------------------- 3 · FAQ */
  var SRC={correction:['Korrektur','rose'],import:['Import','indigo'],gap:['aus Lücke','teal']};
  function faqRow(f,focus){
    var d=document.createElement('div'); d.className='call trFrow'; d.dataset.id=String(f.id || 0); d.dataset.src=f.source || 'manual';
    var t=SRC[f.source];
    d.innerHTML='<div class="row" style="gap:8px;align-items:flex-start"><input type="text" class="trFq" maxlength="500" placeholder="Frage, z. B. Haben Sie samstags geöffnet?" style="flex:1;min-width:160px" value="'+h(f.question)+'"><button class="btn s ghost trFdel" type="button" title="Eintrag löschen" aria-label="Eintrag löschen">✕</button></div>'
      +'<textarea class="trFa" maxlength="2000" placeholder="Verbindliche Antwort – genau so soll der Agent antworten." style="min-height:72px;margin-top:8px">'+h(f.answer)+'</textarea>'
      +'<div class="row" style="justify-content:space-between;margin-top:6px"><label class="small" style="margin:0;display:inline-flex;gap:6px;align-items:center;color:var(--ink2)"><input type="checkbox" class="trFon"'+(f.active===0 ? '' : ' checked')+'> aktiv</label>'+(t ? '<span class="tag '+t[1]+'">'+t[0]+'</span>' : '')+'</div>'
      +(f.wrong ? '<div class="mute small" style="margin-top:6px">Statt: „'+h(clip(f.wrong,180))+'“</div>' : '');
    d.addEventListener('input',function(){ d.style.borderColor=''; faqDirty=true; dirtyUI(); });
    d.querySelector('.trFdel').onclick=function(){ delFaq(d); };
    el('trFaqList').appendChild(d);
    if(focus){ setTimeout(function(){ d.scrollIntoView({behavior:'smooth',block:'center'}); var x=focus==='a' ? d.querySelector('.trFa') : d.querySelector('.trFq'); x.focus({preventScroll:true}); },60); }
    return d;
  }
  function renderFaq(prompt){
    var l=el('trFaqList'); l.innerHTML='';
    FAQ.forEach(function(f){ faqRow(f); });
    if(!FAQ.length) l.innerHTML='<div class="mute small trFempty" style="margin-top:10px">Noch keine Einträge. Typisch: Preise, Öffnungszeiten an Feiertagen, Parken, Zahlarten, Sonderfälle.</div>';
    if(prompt) faqInfo({faq_in_prompt:prompt.faq_in,faq_active:prompt.faq_total,chars:prompt.chars,limit:6000});
  }
  function faqInfo(p){
    var i=el('trFaqInfo'); if(!p || !p.faq_active){ i.innerHTML=''; return; }
    i.innerHTML=p.faq_in_prompt<p.faq_active ? '<span style="color:var(--rose)">'+p.faq_in_prompt+' von '+p.faq_active+' aktiven Einträgen passen in den Agenten-Prompt</span> (Platz für ca. 6.000 Zeichen, Korrekturen zuerst). Längere Inhalte besser in die Wissensbasis.'
      : 'Alle '+p.faq_active+' aktiven Einträge sind im Agenten aktiv.';
  }
  function faqCollect(){ return [].slice.call(document.querySelectorAll('#trFaqList .trFrow')).map(function(r){ return {id:+r.dataset.id || 0,question:r.querySelector('.trFq').value,answer:r.querySelector('.trFa').value,active:r.querySelector('.trFon').checked ? 1 : 0,source:r.dataset.src}; }); }
  function addFaq(f,focus){ var e=document.querySelector('#trFaqList .trFempty'); if(e) e.remove(); faqDirty=true; dirtyUI(); return faqRow(f || {question:'',answer:'',source:'manual'},focus || 'q'); }
  function delFaq(row){
    var id=+row.dataset.id || 0;
    if(!id){ row.remove(); faqDirty=faqCollect().some(function(x){ return !x.id; }) || faqDirty; dirtyUI(); return; }
    if(!confirm('Diesen Eintrag endgültig löschen?')) return;
    post('/train/faq/delete',{id:id}).then(function(){ row.remove(); FAQ=FAQ.filter(function(f){ return f.id!==id; }); msg(el('trFaqMsg'),'Eintrag gelöscht.','ok'); refreshOverview(); })
      .catch(function(e){ showErr(el('trFaqMsg'),e); });
  }
  function saveFaq(){
    var btn=el('trFaqSave'), box=el('trFaqMsg'); busy(btn,true,'Speichert …');
    return post('/train/faq',{items:faqCollect(),known:FAQ.map(function(f){ return f.id; })}).then(function(r){
      busy(btn,false); FAQ=r.items || []; renderFaq(); faqDirty=false; dirtyUI(); msg(box,'Gespeichert ✓ – gilt ab sofort im Chat, am Telefon und per WhatsApp.','ok'); refreshOverview(); return true;
    }).catch(function(e){
      busy(btn,false); showErr(box,e);
      var i=e && e.data ? e.data.index : null, rows=document.querySelectorAll('#trFaqList .trFrow');
      if(i!=null && rows[i]){ rows[i].style.borderColor='var(--rose)'; rows[i].scrollIntoView({behavior:'smooth',block:'center'}); }
      return false;
    });
  }
  function dirtyUI(){ el('trFaqDirty').textContent=faqDirty ? 'Ungespeicherte Änderungen' : ''; el('trTestDirty').textContent=testDirty ? 'Ungespeicherte Änderungen' : ''; }

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
    if(!b.question.trim()){ msg(box,'Bitte die Frage des Kunden eintragen.','err'); el('trCorrQ').focus(); return; }
    if(!b.correct.trim()){ msg(box,'Bitte die richtige Antwort eintragen.','err'); el('trCorrRight').focus(); return; }
    busy(btn,true,'Speichert …');
    post('/train/correct',b,plan).then(function(r){
      busy(btn,false); msg(box,(r.updated ? 'Bestehende Antwort aktualisiert ✓' : 'Korrektur gespeichert ✓')+' – der Agent antwortet ab sofort so.'+(r.test_id ? ' Als Testfrage angelegt.' : ''),'ok');
      if(plan===PLAN){ if(!faqDirty){ api('/train/faq'+qs()).then(function(x){ FAQ=x.items || []; renderFaq(x.prompt); }).catch(function(){}); } if(r.test_id && !testDirty) reloadTests(); refreshOverview(); }
      setTimeout(function(){ el('trSecCorr').classList.add('hidden'); },2600);
    }).catch(function(e){ busy(btn,false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 4 · Regeln */
  function rulesCount(){ var c=el('trRules').value.length; el('trRulesCount').innerHTML='<span style="color:'+(c>8000 ? 'var(--rose)' : 'inherit')+'">'+n(c)+' / 8.000 Zeichen</span>'+(OV && OV.prompt && OV.prompt.rules_cut ? ' · <span style="color:var(--rose)">im Prompt gekürzt</span>' : ''); }
  function saveRules(){
    var btn=el('trRulesSave'), box=el('trRulesMsg'); busy(btn,true,'Speichert …');
    post('/train/rules',{rules:el('trRules').value}).then(function(r){ busy(btn,false); el('trRules').value=r.rules || ''; rulesCount(); msg(box,'Gespeichert ✓ – der Agent hält sich ab der nächsten Antwort daran.','ok'); refreshOverview(); })
      .catch(function(e){ busy(btn,false); showErr(box,e); });
  }

  /* ---------------------------------------------------------------- 5 · Prüfung */
  function testRow(t,focus){
    var d=document.createElement('div'); d.className='call trTrow'; d.dataset.id=String(t.id || 0);
    var st=t.last_pass===1 ? '<span class="tag ok" title="bestanden">✓</span>' : (t.last_pass===0 ? '<span class="tag rose" title="nicht bestanden">✗</span>' : '<span class="tag" title="noch nicht geprüft">–</span>');
    d.innerHTML='<div class="row" style="gap:8px;align-items:center">'+st+'<input type="text" class="trTq" maxlength="500" placeholder="Testfrage, z. B. Was kostet eine Zahnreinigung?" style="flex:1;min-width:160px" value="'+h(t.question)+'">'
      +(t.id ? '<button class="btn s trTone" type="button" title="Nur diese Frage prüfen (≈ 8 Tokens)">Prüfen</button>' : '')+'<button class="btn s ghost trTdel" type="button" title="Testfrage entfernen" aria-label="Testfrage entfernen">✕</button></div>'
      +'<input type="text" class="trTe" maxlength="2000" placeholder="Muss enthalten, z. B.: 89 €, ca. 60 Minuten" style="margin-top:8px" value="'+h(t.expect)+'">'
      +(t.last_pass!=null || t.last_reason ? '<div class="small" style="margin-top:8px;color:'+(t.last_pass===1 ? 'var(--ok)' : (t.last_pass===0 ? 'var(--rose)' : 'var(--mute)'))+'"><b>'+(t.last_pass===1 ? 'Bestanden' : (t.last_pass===0 ? 'Nicht bestanden' : 'Nicht bewertet'))+'</b> · '+h(t.last_reason || '')+(t.last_run_at ? ' <span class="mute">· '+when(t.last_run_at)+'</span>' : '')+'</div>' : '')
      +(t.last_answer ? '<details style="margin-top:6px"><summary class="small" style="cursor:pointer;color:var(--gold)">Antwort des Agenten</summary><div class="small" style="white-space:pre-wrap;margin-top:6px;padding:10px 12px;border-radius:12px;background:var(--sur2)">'+h(t.last_answer)+'</div>'
        +'<button class="btn ghost s trTfix" type="button" style="margin-top:6px">✎ Antwort korrigieren</button></details>' : '');
    d.addEventListener('input',function(){ d.style.borderColor=''; testDirty=true; dirtyUI(); });
    d.querySelector('.trTdel').onclick=function(){ d.remove(); testDirty=true; dirtyUI(); if(!document.querySelector('#trTestList .trTrow')) emptyTests(); };
    var one=d.querySelector('.trTone'); if(one) one.onclick=function(){ runTests([+d.dataset.id]); };
    var fx=d.querySelector('.trTfix'); if(fx) fx.onclick=function(){ openCorr({plan:PLAN,question:t.question,wrong:t.last_answer || ''}); };
    el('trTestList').appendChild(d);
    if(focus) setTimeout(function(){ d.scrollIntoView({behavior:'smooth',block:'center'}); d.querySelector('.trTq').focus({preventScroll:true}); },60);
  }
  function emptyTests(){ el('trTestList').innerHTML='<div class="mute small trTempty" style="margin-top:10px">Noch keine Testfragen. Tipp: 5–10 echte Kundenfragen mit den Fakten, die in der Antwort stehen müssen.</div>'; }
  function renderTests(sum){
    el('trTestList').innerHTML=''; TESTS.forEach(function(t){ testRow(t); }); if(!TESTS.length) emptyTests();
    var s=sum || {}, sc=el('trTestScore');
    sc.innerHTML=s.ran ? '<div class="row" style="gap:10px"><span class="num" style="font-size:28px;color:'+(s.score>=80 ? 'var(--ok)' : (s.score>=50 ? 'var(--gold)' : 'var(--rose)'))+'">'+s.score+' %</span><span class="mute small">'+s.passed+' von '+s.ran+' bestanden'+(s.total>s.ran ? ' · '+(s.total-s.ran)+' noch nicht geprüft' : '')+(s.last_run ? '<br>zuletzt '+when(s.last_run) : '')+'</span></div>' : '';
    var nT=TESTS.length; el('trTestRun').textContent=nT ? 'Test starten · ≈ '+n(nT*8)+' Tokens' : 'Test starten';
  }
  function testsCollect(){ return [].slice.call(document.querySelectorAll('#trTestList .trTrow')).map(function(r){ return {id:+r.dataset.id || 0,question:r.querySelector('.trTq').value,expect:r.querySelector('.trTe').value}; }); }
  function reloadTests(){ var my=seq; return api('/train/tests'+qs()).then(function(r){ if(my!==seq) return; TESTS=r.items || []; if(!testDirty) renderTests(r.summary); }).catch(function(){}); }
  function saveTests(){
    var btn=el('trTestSave'), box=el('trTestMsg'); busy(btn,true,'Speichert …');
    return post('/train/tests',{items:testsCollect(),known:TESTS.map(function(t){ return t.id; })}).then(function(r){ busy(btn,false); TESTS=r.items || []; testDirty=false; dirtyUI(); renderTests(r.summary); msg(box,'Testfragen gespeichert ✓','ok'); refreshOverview(); return true; })
      .catch(function(e){ busy(btn,false); showErr(box,e); var i=e && e.data ? e.data.index : null, rows=document.querySelectorAll('#trTestList .trTrow'); if(i!=null && rows[i]){ rows[i].style.borderColor='var(--rose)'; rows[i].scrollIntoView({behavior:'smooth',block:'center'}); } return false; });
  }
  function runTests(ids){
    var box=el('trTestMsg');
    (testDirty ? saveTests() : Promise.resolve(true)).then(function(ok){
      if(!ok) return;
      if(!TESTS.length){ msg(box,'Bitte zuerst Testfragen anlegen.','err'); return; }
      var cnt=ids ? ids.length : TESTS.length;
      busy(el('trTestRun'),true,'Wird gestartet …'); msg(box,'Prüfung von '+cnt+' '+(cnt===1 ? 'Frage' : 'Fragen')+' wird gestartet (≈ '+n(cnt*8)+' Tokens) …','info');
      post('/train/run',ids ? {ids:ids} : {}).then(function(j){ onJob('tests',j); poll('tests'); })
        .catch(function(e){ busy(el('trTestRun'),false); showErr(box,e); });
    });
  }

  /* ---------------------------------------------------------------- 6 · Lücken */
  function findGaps(){
    var btn=el('trGapGo'), box=el('trGapMsg'), list=el('trGapList'); busy(btn,true,'Gespräche werden durchsucht …'); msg(box,''); list.innerHTML='';
    api('/train/gaps'+qs()).then(function(r){
      busy(btn,false); var sc=r.scanned || {};
      var stats='Durchsucht (30 Tage): '+n(sc.calls || 0)+' Anrufe · '+n(sc.whatsapp || 0)+' WhatsApp-Verläufe · '+n(sc.reported || 0)+' gemeldete Fragen'+(r.covered ? ' · '+r.covered+' bereits per FAQ beantwortet' : '')+(r.cost ? ' · berechnet: '+r.cost+' Tokens' : ' · kostenlos');
      if(!(r.suggestions || []).length){ msg(box,stats+'<br><b>Keine offenen Fragen gefunden.</b> '+(sc.conversations || sc.reported ? 'Sehr gut – dein Agent konnte alles beantworten.' : 'Sobald Gespräche über Telefon oder WhatsApp laufen, erscheinen hier Fragen, die der Agent nicht beantworten konnte.'),'ok'); return; }
      msg(box,stats+(r.ai_note ? '<br>'+h(r.ai_note) : ''),'info');
      var html='';
      if((r.clusters || []).length){
        html+='<div class="small mute" style="margin-top:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Vorschläge der KI</div>'+r.clusters.map(function(c,i){ return gapItem(c.question,c.count,null,c.examples,'','c'+i); }).join('');
        html+='<details class="guide"><summary>Alle gefundenen Fragen ('+r.suggestions.length+')</summary><div style="padding:0 12px 12px">'+r.suggestions.map(function(s,i){ return gapItem(s.question,s.count,s.channels,s.examples,s.answer_was,'s'+i,s.last_at); }).join('')+'</div></details>';
      } else html+=r.suggestions.map(function(s,i){ return gapItem(s.question,s.count,s.channels,s.examples,s.answer_was,'s'+i,s.last_at); }).join('');
      list.innerHTML=html;
      [].slice.call(list.querySelectorAll('[data-trfaq]')).forEach(function(b){ b.onclick=function(){ var q=b.getAttribute('data-q') || ''; addFaq({question:q,answer:'',source:'gap'},'a'); b.disabled=true; b.textContent='✓ als FAQ-Zeile angelegt'; msg(el('trFaqMsg'),'Neue FAQ-Zeile angelegt – Antwort ergänzen und „Speichern“ klicken.','info'); }; });
      balance();
    }).catch(function(e){ busy(btn,false); showErr(box,e); });
  }
  function gapItem(q,count,channels,examples,answer,key,last){
    var ch=(channels || []).map(function(c){ return '<span class="tag'+(c==='reported' ? ' teal' : '')+'">'+h(CH[c] || c)+'</span>'; }).join(' ');
    var ex=(examples || []).filter(function(x){ return x!==q; }).slice(0,3);
    return '<div class="call"><div class="row" style="justify-content:space-between;align-items:flex-start;gap:10px"><div style="flex:1;min-width:180px"><b>'+h(q)+'</b><div class="mute small" style="margin-top:4px">'+count+'× gefragt'+(last ? ' · zuletzt '+when(last) : '')+'</div>'
      +(ex.length ? '<div class="mute small" style="margin-top:4px">Auch: '+ex.map(function(x){ return '„'+h(clip(x,90))+'“'; }).join(' · ')+'</div>' : '')
      +(answer ? '<div class="small" style="margin-top:6px;padding:8px 10px;border-radius:10px;background:var(--sur2);color:var(--ink2)">Agent sagte: „'+h(answer)+'“</div>' : '')
      +'</div><div class="row" style="gap:6px">'+ch+'<button class="btn s" type="button" data-trfaq="'+h(key)+'" data-q="'+h(q)+'">Als FAQ übernehmen</button></div></div></div>';
  }
  function saveGapTool(){
    var cb=el('trGapTool'), on=cb.checked;
    post('/train/settings',{gap_tool:on ? 1 : 0}).then(function(r){ cb.checked=!!+r.settings.gap_tool; msg(el('trGapMsg'),on ? 'Eingeschaltet ✓ – offene Fragen deiner Agenten erscheinen künftig hier.' : 'Ausgeschaltet – Agenten melden keine offenen Fragen mehr.','ok'); })
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
    el('trRulesEx').onclick=function(){ var t=el('trRules'); if(t.value.trim() && !confirm('Vorhandene Regeln durch das Beispiel ersetzen?')) return; t.value=RULES_EX; rulesCount(); t.focus(); };
    el('trTestAdd').onclick=function(){ var e=document.querySelector('#trTestList .trTempty'); if(e) e.remove(); testRow({id:0,question:'',expect:'',last_pass:null},true); testDirty=true; dirtyUI(); };
    el('trTestSave').onclick=saveTests; el('trTestRun').onclick=function(){ runTests(null); };
    el('trGapGo').onclick=findGaps; el('trGapTool').onchange=saveGapTool;
    el('trCorrSave').onclick=saveCorr; el('trCorrClose').onclick=function(){ el('trSecCorr').classList.add('hidden'); CORR=null; };
    el(PANEL).addEventListener('click',function(e){
      var c=e.target.closest('[data-trcancel]'); if(c){ e.preventDefault(); if(confirm('Laufenden Auftrag abbrechen?')) cancelJob(c.getAttribute('data-trcancel')); return; }
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
