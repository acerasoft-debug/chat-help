/* ChatHelp Studio — Panel „Website erstellen“ (site-lib): Vorlagen mit Live-Vorschau, Editor (Abschnitte an/aus + Reihenfolge per Drag & Tastatur,
   Texte je Sprache, Farben/Schriften, Bilder, Öffnungszeiten, Kontakt, Impressum/Datenschutz, SEO, Adresse), KI-Entwurf + Übersetzung (Tokens),
   Vorschau im iframe (Desktop/Handy), Veröffentlichen (Plan nötig), eigene Domain, ZIP-Export, Anfragen.
   Nutzt die Studio-Globals api, esc, msg, go, ME, KEY, I18N, _t, _te, refreshMe. */
(function(){
  'use strict';
  var g=function(id){return document.getElementById(id);};
  var h=function(v){return esc(v==null?'':String(v));};
  var LANGN={de:'Deutsch',fr:'Français',tr:'Türkçe',az:'Azərbaycanca',es:'Español',it:'Italiano',en:'English',ru:'Русский',nl:'Nederlands',sv:'Svenska',no:'Norsk',fi:'Suomi',ja:'日本語'};
  var SECN={hero:'wbj.sec.hero',services:'wbj.sec.services',about:'wbj.sec.about',team:'wbj.sec.team',gallery:'wbj.sec.gallery',hours:'wbj.sec.hours',location:'wbj.sec.location',reviews:'wbj.sec.reviews',faq:'wbj.sec.faq',contact:'wbj.sec.contact'};
  var TXT=[['hero_kicker','wbj.f.kicker',80],['hero_title','wbj.f.htitle',120],['hero_text','wbj.f.htext',420,'ta'],['cta_label','wbj.f.cta',40]];
  var SECTXT={services:[['services_title','wbj.f.title',100],['services_text','wbj.f.intro',600,'ta']],about:[['about_title','wbj.f.title',100],['about_text','wbj.f.about',2400,'lg']],team:[['team_title','wbj.f.title',100],['team_text','wbj.f.intro',600,'ta']],
    gallery:[['gallery_title','wbj.f.title',100]],hours:[['hours_title','wbj.f.title',100],['hours_note','wbj.f.hnote',300,'ta']],location:[['location_title','wbj.f.title',100],['location_text','wbj.f.ltext',600,'ta']],
    reviews:[['reviews_title','wbj.f.title',100]],faq:[['faq_title','wbj.f.title',100]],contact:[['contact_title','wbj.f.title',100],['contact_text','wbj.f.ctext',600,'ta']]};
  var META=null, LIST=[], S=null, LANG='de', TAB='design', saveT=null, dirty=false, saving=false, pvT=null, mob=false, dragSrc=null, LEADS=null, busy=false;
  var PH=/\[\[.{1,80}?\]\]/;
  function live(t){var l=g('wbLive');if(l){l.textContent='';setTimeout(function(){l.textContent=t;},30);}}
  function fmtDt(iso){return iso?I18N.dt(iso,{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
  function err(e){return _te(e,_t('wbj.err'));}
  function thumbUrl(tpl){return '/chat/site.php?demo='+encodeURIComponent(tpl)+'&thumb=1&lang='+encodeURIComponent(I18N.lang);}

  /* ---------- Übersicht ---------- */
  function load(){
    return api('/sites').then(function(j){META=j;LIST=j.sites||[];if(S){var cur=LIST.find(function(x){return x.id===S.id;});if(!cur){S=null;}}renderHome();}).catch(function(e){g('wbMain').innerHTML='<div class="card"><div class="msg err">'+h(err(e))+'</div></div>';});
  }
  function tplCard(t,sel){
    return '<button type="button" class="wb-tpl" data-tpl="'+h(t.id)+'" aria-pressed="'+(sel?'true':'false')+'">'+(t.recommended?'<span class="wb-rec">'+_t('wbj.rec')+'</span>':'')
      +'<div class="wb-thumb"><iframe loading="lazy" tabindex="-1" aria-hidden="true" title="'+h(t.name)+'" data-src="'+h(thumbUrl(t.id))+'"></iframe></div>'
      +'<div class="wb-tb"><b>'+h(t.name)+'</b><span class="wb-tg">'+h(_tx(t.tag))+'</span></div></button>';
  }
  function fitThumbs(root){
    root.querySelectorAll('.wb-thumb').forEach(function(th){var f=th.querySelector('iframe');if(!f)return;var w=th.clientWidth||230;f.style.transform='scale('+(w/1280)+')';if(!f.src&&f.dataset.src){f.src=f.dataset.src;}});
  }
  function renderHome(){
    var m=g('wbMain'); var can=META.can_publish; var plan=META.plan||{};
    var html='';
    if(!LIST.length){
      html+='<div class="card wb-intro"><div><span class="tag gold">'+_t('wbj.intro.tag')+'</span><h3>'+_t('wbj.intro.h')+'</h3><p class="mute" style="margin:8px 0 0">'+_t('wbj.intro.p')+'</p><ul>'
        +['wbj.intro.b1','wbj.intro.b2','wbj.intro.b3','wbj.intro.b4','wbj.intro.b5'].map(function(k){return '<li>'+_t(k)+'</li>';}).join('')+'</ul></div>'
        +'<div class="wb-cta"><b>'+_t('wbj.intro.pick')+'</b><span class="mute small">'+_t('wbj.intro.pickT',{n:META.prices.generate})+'</span><span class="mute small">'+_t(can?'wbj.intro.canPub':'wbj.intro.needPlan',{p:I18N.eur(plan.eur_month)})+'</span></div></div>';
    } else {
      html+='<div class="row" style="justify-content:space-between;margin-bottom:12px"><h3 style="font-size:20px">'+_t('wbj.mySites')+'</h3><span class="mute small">'+_t('wbj.count',{n:LIST.length,max:META.limits.sites})+'</span></div><div class="wb-sites">';
      LIST.forEach(function(s){
        var st=s.status==='published'?'<span class="wb-pill ok">● '+_t('wbj.st.pub')+'</span>':'<span class="wb-pill">'+_t('wbj.st.draft')+'</span>';
        if(s.status==='published'&&s.dirty)st+=' <span class="wb-pill warn">'+_t('wbj.st.dirty')+'</span>';
        html+='<div class="card wb-site"><div class="row" style="justify-content:space-between">'+st+'<span class="mute small">'+h(fmtDt(s.updated_at))+'</span></div><h3>'+h(s.name||_t('wbj.untitled'))+'</h3><div class="wb-url">'+h(s.url)+'</div>'
          +'<div class="row" style="margin-top:auto;padding-top:8px"><button class="btn p s" data-open="'+s.id+'">'+_t('wbj.edit')+'</button>'+(s.status==='published'?'<a class="btn s" href="'+h(s.url)+'" target="_blank" rel="noopener">'+_t('wbj.open')+' ↗</a>':'')+'</div></div>';
      });
      html+='</div>';
      if(LIST.length<META.limits.sites) html+='<h3 style="font-size:20px;margin-top:26px">'+_t('wbj.newSite')+'</h3><p class="mute small" style="margin:4px 0 0">'+_t('wbj.newSiteT')+'</p>';
    }
    if(LIST.length<META.limits.sites){
      html+='<div class="wb-tpls" id="wbTpls">'+META.templates.map(function(t){return tplCard(t,false);}).join('')+'</div>';
      html+='<div class="card" style="margin-top:14px" id="wbNew"><div class="wb-g2"><div><label for="wbNewName">'+_t('wbj.name')+'</label><input type="text" id="wbNewName" maxlength="120" placeholder="'+h(_t('wbj.namePh'))+'"></div><div><label for="wbNewLang">'+_t('wbj.lang')+'</label><select id="wbNewLang">'
        +META.langs.map(function(l){return '<option value="'+l.id+'"'+(l.id===(I18N.lang||'de')?' selected':'')+'>'+h(l.name)+'</option>';}).join('')+'</select></div></div>'
        +'<div class="row" style="justify-content:space-between;margin-top:14px;align-items:center"><span class="mute small" id="wbNewTpl">'+_t('wbj.pickTpl')+'</span><button class="btn p" id="wbCreate" disabled>'+_t('wbj.create')+'</button></div><div id="wbNewMsg"></div></div>';
    }
    html+='<p class="mute small" style="margin-top:18px">'+_t('wbj.widgetHint')+' <a href="#website" onclick="go(\'website\');return false">'+_t('wbj.widgetLink')+'</a></p>';
    m.innerHTML=html; var sel=null;
    m.querySelectorAll('[data-open]').forEach(function(b){b.onclick=function(){openSite(+b.dataset.open);};});
    m.querySelectorAll('.wb-tpl').forEach(function(b){b.onclick=function(){sel=b.dataset.tpl;m.querySelectorAll('.wb-tpl').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});var t=META.templates.find(function(x){return x.id===sel;});g('wbNewTpl').textContent=_t('wbj.tplSel',{n:t?t.name:sel});g('wbCreate').disabled=false;};});
    var c=g('wbCreate'); if(c) c.onclick=function(){if(!sel)return;c.disabled=true;msg(g('wbNewMsg'),_t('wbj.creating'));
      api('/sites',{method:'POST',body:JSON.stringify({template:sel,lang:g('wbNewLang').value,name:g('wbNewName').value.trim()})}).then(function(j){META=Object.assign(META,j);LIST=LIST;S=j.site;LANG=S.lang_default;TAB='design';renderEditor();openWizard(true);})
      .catch(function(e){msg(g('wbNewMsg'),h(err(e)),'err');c.disabled=false;});};
    var tp=g('wbTpls'); if(tp){fitThumbs(tp);var rec=META.templates.find(function(t){return t.recommended;});if(rec&&!LIST.length){var b=tp.querySelector('[data-tpl="'+rec.id+'"]');if(b)b.click();}}
  }
  function openSite(id){ g('wbMain').innerHTML='<div class="card mute">'+_t('sj.loading')+'</div>';
    api('/sites/'+id).then(function(j){META=Object.assign(META||{},j);S=j.site;LANG=S.lang_default;TAB='design';renderEditor();}).catch(function(e){g('wbMain').innerHTML='<div class="card"><div class="msg err">'+h(err(e))+'</div></div>';}); }

  /* ---------- Editor ---------- */
  function issuesOf(){return (S&&S.issues)||[];}
  function issueCount(tab){var m={content:['placeholder','biz_name'],contact:['no_contact'],legal:['legal_missing'],seo:['seo_desc'],design:[]};return issuesOf().filter(function(i){return (m[tab]||[]).indexOf(i.code)>=0&&(tab!=='content'||i.field.indexOf('seo.')!==0)&&(tab!=='seo'||i.field.indexOf('seo.')===0||i.code==='seo_desc');}).length;}
  function renderEditor(){
    var m=g('wbMain'); var pub=S.status==='published';
    var st=pub?'<span class="wb-pill ok">● '+_t('wbj.st.pub')+'</span>':'<span class="wb-pill">'+_t('wbj.st.draft')+'</span>'; if(pub&&S.dirty) st+=' <span class="wb-pill warn">'+_t('wbj.st.dirty')+'</span>';
    var tabs=[['design','wbj.t.design'],['content','wbj.t.content'],['images','wbj.t.images'],['contact','wbj.t.contact'],['legal','wbj.t.legal'],['seo','wbj.t.seo'],['publish','wbj.t.publish'],['leads','wbj.t.leads']];
    m.innerHTML='<div class="wb-bar"><button class="btn s" id="wbBack">← '+_t('wbj.all')+'</button><h2 id="wbTitle">'+h(S.name||_t('wbj.untitled'))+'</h2>'+st+'<span class="wb-save" id="wbSave" aria-live="polite"></span>'
      +'<button class="btn s gold" id="wbAI">✦ '+_t('wbj.ai')+'</button>'+(pub?'<a class="btn s" href="'+h(S.url)+'" target="_blank" rel="noopener">'+_t('wbj.open')+' ↗</a>':'')+'</div>'
      +'<div id="wbIssues"></div>'
      +'<div class="wb-ed"><div><div class="wb-tabs" role="tablist" id="wbTabs">'+tabs.map(function(t){return '<button class="wb-tab" role="tab" data-tab="'+t[0]+'" aria-selected="'+(t[0]===TAB?'true':'false')+'">'+_t(t[1])+'<span class="wb-n hidden"></span></button>';}).join('')+'</div><div class="wb-pane card" id="wbPane"></div></div>'
      +'<div class="wb-prev"><div class="wb-pv-bar"><span class="pill small">'+_t('wbj.preview')+'</span><div class="row" style="gap:6px"><div class="wb-seg" role="group" aria-label="'+h(_t('wbj.device'))+'"><button type="button" data-dev="desk" aria-pressed="'+(mob?'false':'true')+'">'+_t('wbj.desktop')+'</button><button type="button" data-dev="mob" aria-pressed="'+(mob?'true':'false')+'">'+_t('wbj.mobile')+'</button></div><a class="btn s" id="wbPvOpen" href="'+h(S.preview_url)+'" target="_blank" rel="noopener">↗</a></div></div>'
      +'<div class="wb-frame'+(mob?' mob':'')+'" id="wbFrame"><iframe id="wbIframe" title="'+h(_t('wbj.preview'))+'" src="'+h(S.preview_url+'&lang='+LANG)+'"></iframe></div><div class="wb-note">'+_t('wbj.pvNote')+'</div></div></div>';
    g('wbBack').onclick=function(){if(dirty)flush();S=null;load();};
    g('wbAI').onclick=function(){openWizard(false);};
    m.querySelectorAll('.wb-tab').forEach(function(b){b.onclick=function(){TAB=b.dataset.tab;m.querySelectorAll('.wb-tab').forEach(function(x){x.setAttribute('aria-selected',x===b?'true':'false');});renderPane();};});
    m.querySelectorAll('[data-dev]').forEach(function(b){b.onclick=function(){mob=b.dataset.dev==='mob';m.querySelectorAll('[data-dev]').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});g('wbFrame').classList.toggle('mob',mob);};});
    renderIssues(); renderPane();
  }
  function renderIssues(){
    var el=g('wbIssues'); if(!el) return; var is=issuesOf(); var blk=is.filter(function(i){return i.block;});
    document.querySelectorAll('#wbTabs .wb-tab').forEach(function(t){var n=issueCount(t.dataset.tab);var s=t.querySelector('.wb-n');s.textContent=n;s.classList.toggle('hidden',!n);});
    if(!is.length){el.innerHTML='';return;}
    el.innerHTML='<details class="wb-issues"'+(blk.length?' open':'')+'><summary>'+_t(blk.length?'wbj.issues.blk':'wbj.issues.soft',{n:is.length})+'</summary><ul>'+is.slice(0,30).map(function(i){
      var tab=i.code==='legal_missing'?'legal':(i.code==='no_contact'?'contact':(i.field.indexOf('seo.')===0||i.code==='seo_desc'?'seo':'content'));
      var lab=i.code==='placeholder'?_t('wbj.issue.ph',{t:i.hint||'',l:(i.lang||'').toUpperCase()}):_t('wbj.issue.'+i.code);
      return '<li>'+h(lab)+' <button type="button" data-goto="'+tab+'" data-lang="'+h(i.lang||'')+'" data-field="'+h(i.field)+'">'+_t('wbj.issue.fix')+'</button></li>';}).join('')+'</ul></details>';
    el.querySelectorAll('[data-goto]').forEach(function(b){b.onclick=function(){if(b.dataset.lang)LANG=b.dataset.lang;TAB=b.dataset.goto;document.querySelectorAll('#wbTabs .wb-tab').forEach(function(x){x.setAttribute('aria-selected',x.dataset.tab===TAB?'true':'false');});renderPane();
      setTimeout(function(){var f=b.dataset.field.split('.');var sec=f[0]==='texts'?null:f[0];var t=document.querySelector('#wbPane [data-path="'+b.dataset.field+'"]')||document.querySelector('#wbPane [data-sec="'+sec+'"]');if(t){var d=t.closest('details');if(d)d.open=true;t.scrollIntoView({block:'center'});if(t.focus)t.focus();}},60);};});
  }
  function langTabs(){
    return '<div class="wb-langs" role="group" aria-label="'+h(_t('wbj.lang'))+'">'+S.langs.map(function(l){var miss=!(S.data.texts&&S.data.texts[l]&&Object.keys(S.data.texts[l]).length);return '<button type="button" class="wb-lb" data-lang="'+l+'" aria-pressed="'+(l===LANG?'true':'false')+'">'+h(LANGN[l]||l)+(l===S.lang_default?' <small>★</small>':(miss?' <small>⚠</small>':''))+'</button>';}).join('')
      +'<button type="button" class="wb-lb" id="wbAddLang" style="border-style:dashed">+ '+_t('wbj.addLang')+'</button></div>';
  }
  function bindLangTabs(root){
    root.querySelectorAll('.wb-lb[data-lang]').forEach(function(b){b.onclick=function(){LANG=b.dataset.lang;renderPane();pvRefresh();};});
    var a=root.querySelector('#wbAddLang'); if(a) a.onclick=function(){openTranslate();};
  }
  function field(path,label,val,max,kind,hint){
    var v=val==null?'':String(val); var ph=PH.test(v); var id='wbf_'+path.replace(/[^a-z0-9]/gi,'_');
    var inp=kind==='ta'||kind==='lg'?'<textarea id="'+id+'" data-path="'+h(path)+'" maxlength="'+max+'" class="'+(kind==='lg'?'lg ':'')+(ph?'ph':'')+'">'+h(v)+'</textarea>':'<input type="text" id="'+id+'" data-path="'+h(path)+'" maxlength="'+max+'" value="'+h(v)+'" class="'+(ph?'ph':'')+'">';
    return '<label for="'+id+'">'+h(label)+(max>=100?'<span class="wb-cnt" data-cnt="'+id+'">'+v.length+'/'+max+'</span>':'')+'</label>'+inp+'<div class="wb-phn">'+_t('wbj.phHint')+'</div>'+(hint?'<div class="mute small" style="margin-top:4px">'+h(hint)+'</div>':'');
  }
  function getPath(obj,path){var p=path.split('.');var o=obj;for(var i=0;i<p.length;i++){if(o==null)return undefined;o=o[p[i]];}return o;}
  function setPath(obj,path,v){var p=path.split('.');var o=obj;for(var i=0;i<p.length-1;i++){if(o[p[i]]==null||typeof o[p[i]]!=='object')o[p[i]]=/^\d+$/.test(p[i+1])?[]:{};o=o[p[i]];}o[p[p.length-1]]=v;}
  function renderPane(){
    var p=g('wbPane'); if(!p) return; var d=S.data; var html='';
    if(TAB==='design'){
      html+='<h3 style="font-size:18px">'+_t('wbj.d.tpl')+'</h3><div class="wb-tpls" id="wbTplPick">'+META.templates.map(function(t){return tplCard(t,t.id===S.template);}).join('')+'</div>';
      html+='<h3 style="font-size:18px;margin-top:22px">'+_t('wbj.d.colors')+'</h3><div class="wb-sw" id="wbPresets">'+Object.keys(META.presets).map(function(k){var pr=META.presets[k];return '<button type="button" class="wb-swatch" data-preset="'+k+'" title="'+h(_tx(pr.label))+'" aria-label="'+h(_tx(pr.label))+'" aria-pressed="'+(S.theme.preset===k?'true':'false')+'" style="background:'+h(pr.accent)+'"><i style="background:'+h(pr.bg)+'"></i></button>';}).join('')+'</div>'
        +'<div class="wb-colors" style="margin-top:12px">'+[['accent','wbj.c.accent'],['accent2','wbj.c.accent2'],['bg','wbj.c.bg'],['ink','wbj.c.ink']].map(function(c){return '<div><label for="wbc_'+c[0]+'">'+_t(c[1])+'</label><input type="color" id="wbc_'+c[0]+'" data-color="'+c[0]+'" value="'+h(S.theme[c[0]]||'#000000')+'"></div>';}).join('')+'</div>'
        +'<label for="wbFont">'+_t('wbj.d.font')+'</label><select id="wbFont">'+Object.keys(META.fonts).map(function(k){return '<option value="'+k+'"'+(S.theme.font===k?' selected':'')+' style="font-family:'+h(META.fonts[k].h)+'">'+h(_tx(META.fonts[k].label))+'</option>';}).join('')+'</select>';
      html+='<h3 style="font-size:18px;margin-top:22px">'+_t('wbj.d.secs')+'</h3><p class="mute small" style="margin:4px 0 10px">'+_t('wbj.d.secsT')+'</p><ul class="wb-secs" id="wbSecs">'+d.sections.map(function(s,i){var on=!!s.on;
        return '<li class="wb-sec'+(on?'':' off')+'" data-sec="'+s.id+'" draggable="true"><div class="wb-sh"><button type="button" class="wb-grip" aria-hidden="true" tabindex="-1">⋮⋮</button><b>'+_t(SECN[s.id])+'</b>'
          +'<button type="button" class="wb-ib" data-mv="-1" data-i="'+i+'" aria-label="'+h(_t('wbj.up'))+'"'+(i===0?' disabled':'')+'>↑</button><button type="button" class="wb-ib" data-mv="1" data-i="'+i+'" aria-label="'+h(_t('wbj.down'))+'"'+(i===d.sections.length-1?' disabled':'')+'>↓</button>'
          +'<label class="wb-tog"><input type="checkbox" data-on="'+i+'"'+(on?' checked':'')+' aria-label="'+h(_t(SECN[s.id]))+'"><span></span></label></div></li>';}).join('')+'</ul>';
      html+='<h3 style="font-size:18px;margin-top:22px">'+_t('wbj.d.cta')+'</h3><select id="wbCta">'+META.ctas.map(function(c){return '<option value="'+c+'"'+((d.cta&&d.cta.primary)===c?' selected':'')+'>'+_t('wbj.cta.'+c)+'</option>';}).join('')+'</select>'
        +'<div class="sw" style="margin-top:12px"><input type="checkbox" id="wbWidget"'+(d.widget?' checked':'')+'><div><b>'+_t('wbj.d.widget')+'</b><div class="mute small">'+_t('wbj.d.widgetT')+'</div></div></div><div id="wbWidgetState" class="mute small" style="margin-top:6px"></div>';
    }
    else if(TAB==='content'){
      html+=langTabs();
      var tx=(d.texts&&d.texts[LANG])||{};
      html+='<div class="wb-f"><h3 style="font-size:18px">'+_t('wbj.sec.hero')+'</h3>'+TXT.map(function(f){return field('texts.'+LANG+'.'+f[0],_t(f[1]),tx[f[0]],f[2],f[3]);}).join('')+'</div>';
      d.sections.forEach(function(s){ if(s.id==='hero'||!s.on) return; var fs=SECTXT[s.id]||[];
        var inner='<div class="wb-f">'+fs.map(function(f){return field('texts.'+LANG+'.'+f[0],_t(f[1]),tx[f[0]],f[2],f[3]);}).join('');
        if(s.id==='services'){ var ms=S.module_services||{}; var auto=(ms.menu&&ms.menu.length)||(ms.services&&ms.services.length)||(ms.rooms&&ms.rooms.length);
          if(auto) inner+='<div class="sw"><input type="checkbox" id="wbSvcAuto"'+(d.services_auto!==false?' checked':'')+'><div><b>'+_t('wbj.svc.auto')+'</b><div class="mute small">'+_t('wbj.svc.autoT',{n:(ms.menu||[]).length+(ms.services||[]).length+(ms.rooms||[]).length})+'</div></div></div>';
          inner+='<div class="wb-items" id="wbSvc">'+(d.services||[]).map(function(x,i){return '<div class="wb-item"><div class="row"><b class="mute small">'+_t('wbj.svc.item',{n:i+1})+'</b><button type="button" class="btn s wb-danger" data-del="services" data-i="'+i+'">'+_t('wbj.del')+'</button></div><div class="wb-g3">'
            +'<div>'+field('services.'+i+'.name.'+LANG,_t('wbj.svc.name'),x.name&&x.name[LANG],100)+'</div><div>'+field('services.'+i+'.price',_t('wbj.svc.price'),x.price,40)+'</div></div>'+field('services.'+i+'.desc.'+LANG,_t('wbj.svc.desc'),x.desc&&x.desc[LANG],400,'ta')+'</div>';}).join('')+'</div>'
            +'<button type="button" class="btn s" style="margin-top:10px" data-add="services">+ '+_t('wbj.svc.add')+'</button>'; }
        if(s.id==='faq') inner+='<div class="wb-items">'+(d.faq||[]).map(function(x,i){return '<div class="wb-item"><div class="row"><b class="mute small">'+_t('wbj.faq.item',{n:i+1})+'</b><button type="button" class="btn s wb-danger" data-del="faq" data-i="'+i+'">'+_t('wbj.del')+'</button></div>'+field('faq.'+i+'.q.'+LANG,_t('wbj.faq.q'),x.q&&x.q[LANG],200)+field('faq.'+i+'.a.'+LANG,_t('wbj.faq.a'),x.a&&x.a[LANG],900,'ta')+'</div>';}).join('')+'</div><button type="button" class="btn s" style="margin-top:10px" data-add="faq">+ '+_t('wbj.faq.add')+'</button>';
        if(s.id==='team') inner+='<div class="wb-items">'+(d.team||[]).map(function(x,i){return '<div class="wb-item"><div class="row"><b class="mute small">'+_t('wbj.team.item',{n:i+1})+'</b><button type="button" class="btn s wb-danger" data-del="team" data-i="'+i+'">'+_t('wbj.del')+'</button></div><div class="wb-g2"><div>'+field('team.'+i+'.name',_t('wbj.team.name'),x.name,80)+'</div><div>'+field('team.'+i+'.role.'+LANG,_t('wbj.team.role'),x.role&&x.role[LANG],100)+'</div></div><label>'+_t('wbj.team.img')+'</label>'+assetSelect('team.'+i+'.img',x.img)+'</div>';}).join('')+'</div><button type="button" class="btn s" style="margin-top:10px" data-add="team">+ '+_t('wbj.team.add')+'</button>';
        if(s.id==='reviews') inner+='<p class="mute small">'+_t('wbj.rev.note')+'</p><div class="wb-items">'+(d.reviews||[]).map(function(x,i){return '<div class="wb-item"><div class="row"><b class="mute small">'+_t('wbj.rev.item',{n:i+1})+'</b><button type="button" class="btn s wb-danger" data-del="reviews" data-i="'+i+'">'+_t('wbj.del')+'</button></div><div class="wb-g2"><div>'+field('reviews.'+i+'.author',_t('wbj.rev.author'),x.author,80)+'</div><div><label>'+_t('wbj.rev.stars')+'</label><select data-path="reviews.'+i+'.stars" data-num="1">'+[5,4,3,2,1].map(function(n){return '<option value="'+n+'"'+(+x.stars===n?' selected':'')+'>'+'★'.repeat(n)+'</option>';}).join('')+'</select></div></div>'+field('reviews.'+i+'.text',_t('wbj.rev.text'),x.text,600,'ta')+'</div>';}).join('')+'</div><button type="button" class="btn s" style="margin-top:10px" data-add="reviews">+ '+_t('wbj.rev.add')+'</button>';
        if(s.id==='hours') inner+=hoursEditor();
        if(s.id==='gallery') inner+='<p class="mute small" style="margin-top:10px">'+_t('wbj.gal.hint')+'</p>';
        html+='<details class="guide" data-sec="'+s.id+'" style="margin-top:12px"'+(s.id==='about'||s.id==='services'?' open':'')+'><summary>'+_t(SECN[s.id])+'</summary><div style="padding:0 14px 14px">'+inner+'</div></div></details>';
      });
      html+='<div class="wb-f"><h3 style="font-size:18px;margin-top:18px">'+_t('wbj.f.footer')+'</h3>'+field('texts.'+LANG+'.footer_text',_t('wbj.f.footerT'),tx.footer_text,300)+'</div>';
    }
    else if(TAB==='images'){
      html+='<p class="mute small">'+_t('wbj.img.hint',{mb:2})+'</p><div class="wb-drop" id="wbDrop" tabindex="0" role="button">'+_t('wbj.img.drop')+'<br><input type="file" id="wbFile" accept="image/jpeg,image/png,image/webp" multiple style="margin-top:10px"></div><div id="wbUpMsg"></div>'
        +'<div class="wb-g3" style="margin-top:14px"><div><label>'+_t('wbj.img.hero')+'</label>'+assetSelect('hero_img',d.hero_img)+'</div><div><label>'+_t('wbj.img.about')+'</label>'+assetSelect('about_img',d.about_img)+'</div><div><label>'+_t('wbj.img.logo')+'</label>'+assetSelect('logo',d.logo)+'</div></div>'
        +'<h3 style="font-size:18px;margin-top:18px">'+_t('wbj.img.gallery')+'</h3><p class="mute small">'+_t('wbj.img.galleryT')+'</p><div class="wb-assets">'+(S.assets||[]).map(function(a){var inG=(d.gallery||[]).find(function(x){return x.img===a.id;});
          return '<div class="wb-asset"><img src="'+h(a.url)+'" alt="" loading="lazy"><div class="wb-ab"><label class="sw" style="margin:0;padding:6px 8px"><input type="checkbox" data-gal="'+a.id+'"'+(inG?' checked':'')+'><span class="small">'+_t('wbj.img.inGallery')+'</span></label>'+(inG?'<input type="text" data-path="gallery.'+(d.gallery.indexOf(inG))+'.alt.'+LANG+'" maxlength="160" placeholder="'+h(_t('wbj.img.alt'))+'" value="'+h(inG.alt&&inG.alt[LANG]||'')+'" style="padding:7px 9px;font-size:12.5px">':'')+'<span class="mute small">'+a.w+'×'+a.h+' · '+Math.round(a.bytes/1024)+' KB</span><button type="button" class="btn s wb-danger" data-adel="'+a.id+'">'+_t('wbj.del')+'</button></div></div>';}).join('')+'</div>';
    }
    else if(TAB==='contact'){
      var b=d.biz||{};
      html+='<div class="wb-f"><h3 style="font-size:18px">'+_t('wbj.ct.biz')+'</h3>'+field('biz.name',_t('wbj.name'),b.name,120)+'<div class="wb-g2"><div>'+field('biz.phone',_t('wbj.ct.phone'),b.phone,32)+'</div><div>'+field('biz.whatsapp',_t('wbj.ct.wa'),b.whatsapp,32,null,_t('wbj.ct.waHint'))+'</div></div>'+field('biz.email',_t('wbj.ct.email'),b.email,190)
        +'<h3 style="font-size:18px;margin-top:18px">'+_t('wbj.ct.addr')+'</h3>'+field('biz.street',_t('wbj.ct.street'),b.street,160)+'<div class="wb-g3"><div>'+field('biz.city',_t('wbj.ct.city'),b.city,80)+'</div><div>'+field('biz.zip',_t('wbj.ct.zip'),b.zip,12)+'</div><div><label for="wbCountry">'+_t('wbj.ct.country')+'</label><select id="wbCountry" data-path="biz.country">'+['DE','AT','CH','TR','AZ','FR','ES','IT','NL','BE','LU','GB','SE','NO','FI','PL','JP','US'].map(function(c){return '<option value="'+c+'"'+(b.country===c?' selected':'')+'>'+c+'</option>';}).join('')+'</select></div></div>'
        +'<div class="wb-g2"><div>'+field('biz.lat',_t('wbj.ct.lat'),b.lat,12)+'</div><div>'+field('biz.lon',_t('wbj.ct.lon'),b.lon,12)+'</div></div><p class="mute small">'+_t('wbj.ct.geoHint')+'</p>'
        +'<h3 style="font-size:18px;margin-top:18px">'+_t('wbj.sec.hours')+'</h3>'+hoursEditor()+'</div>';
    }
    else if(TAB==='legal'){
      var l=d.legal||{};
      html+='<div class="wb-box wb-warn"><h4>'+_t('wbj.lg.h')+'</h4><div class="small">'+_t('wbj.lg.note')+'</div></div><div class="wb-f">'
        +field('legal.owner',_t('wbj.lg.owner'),l.owner,160)+'<div class="wb-g2"><div>'+field('legal.form',_t('wbj.lg.form'),l.form,80)+'</div><div>'+field('legal.rep',_t('wbj.lg.rep'),l.rep,200)+'</div></div>'
        +field('legal.street',_t('wbj.ct.street'),l.street,160)+'<div class="wb-g3"><div>'+field('legal.city',_t('wbj.ct.city'),l.city,80)+'</div><div>'+field('legal.zip',_t('wbj.ct.zip'),l.zip,12)+'</div><div><label for="wbLCountry">'+_t('wbj.ct.country')+'</label><select id="wbLCountry" data-path="legal.country">'+['DE','AT','CH','TR','AZ','FR','ES','IT','NL','BE','LU','GB','SE','NO','FI','PL','JP','US'].map(function(c){return '<option value="'+c+'"'+(l.country===c?' selected':'')+'>'+c+'</option>';}).join('')+'</select></div></div>'
        +'<div class="wb-g2"><div>'+field('legal.email',_t('wbj.ct.email'),l.email,190)+'</div><div>'+field('legal.phone',_t('wbj.ct.phone'),l.phone,32)+'</div></div>'
        +'<div class="wb-g2"><div>'+field('legal.register',_t('wbj.lg.register'),l.register,200)+'</div><div>'+field('legal.vat',_t('wbj.lg.vat'),l.vat,40)+'</div></div>'
        +field('legal.authority',_t('wbj.lg.authority'),l.authority,300,'ta')+field('legal.responsible',_t('wbj.lg.responsible'),l.responsible,200)
        +field('legal.extra',_t('wbj.lg.extra'),l.extra,3000,'ta')+field('legal.privacy_extra',_t('wbj.lg.privacyExtra'),l.privacy_extra,4000,'ta')+'</div>'
        +'<div class="row" style="margin-top:14px"><a class="btn s" href="'+h(S.preview_url+'&page=impressum&lang='+LANG)+'" target="_blank" rel="noopener">'+_t('wbj.lg.viewIm')+' ↗</a><a class="btn s" href="'+h(S.preview_url+'&page=datenschutz&lang='+LANG)+'" target="_blank" rel="noopener">'+_t('wbj.lg.viewPv')+' ↗</a></div>';
    }
    else if(TAB==='seo'){
      var se=(S.seo&&S.seo[LANG])||{};
      html+=langTabs()+'<div class="wb-f">'+field('seo.'+LANG+'.title',_t('wbj.seo.title'),se.title,70)+field('seo.'+LANG+'.desc',_t('wbj.seo.desc'),se.desc,170,'ta')+'</div>'
        +'<div class="wb-serp" aria-hidden="true"><div class="u">'+h(S.url)+'</div><div class="t" id="wbSerpT">'+h(se.title||S.name||'')+'</div><div class="d" id="wbSerpD">'+h(se.desc||'')+'</div></div>'
        +'<h3 style="font-size:18px;margin-top:18px">'+_t('wbj.seo.slug')+'</h3><p class="mute small">'+_t('wbj.seo.slugT')+'</p><div class="row"><span class="mute small" style="white-space:nowrap">chat-help.com/chat/site.php?s=</span><input type="text" id="wbSlug" value="'+h(S.slug)+'" maxlength="40" style="flex:1;min-width:160px" pattern="[a-z0-9-]{3,40}"><button class="btn s" id="wbSlugSave">'+_t('wbj.seo.slugSave')+'</button></div><div id="wbSlugMsg"></div>'
        +'<p class="mute small" style="margin-top:14px">'+_t('wbj.seo.auto')+'</p>';
    }
    else if(TAB==='publish'){
      var can=META.can_publish, pub=S.status==='published', plan=META.plan||{}, blk=issuesOf().filter(function(i){return i.block;});
      html+='<div class="wb-box '+(pub?'wb-okb':'')+'"><h4>'+_t(pub?'wbj.pb.isPub':'wbj.pb.isDraft')+'</h4><div class="small">'+(pub?_t('wbj.pb.pubT',{d:fmtDt(S.published_at)})+' <a href="'+h(S.url)+'" target="_blank" rel="noopener">'+h(S.url)+'</a>':_t('wbj.pb.draftT'))+'</div>'
        +(pub&&S.dirty?'<div class="msg info" style="margin-top:10px">'+_t('wbj.pb.dirty')+'</div>':'')+'</div>';
      if(!can) html+='<div class="wb-box wb-warn"><h4>'+_t('wbj.pb.needPlan')+'</h4><div class="small">'+_t('wbj.pb.needPlanT',{p:I18N.eur(plan.eur_month)})+'</div><div class="row" style="margin-top:10px"><a class="btn gold s" href="'+h(plan.checkout+(ME&&ME.email?'&email='+encodeURIComponent(ME.email):''))+'" target="_blank" rel="noopener">'+_t('wbj.pb.buy',{p:I18N.eur(plan.eur_month)})+'</a><button class="btn s" onclick="go(\'agents\')">'+_t('wbj.pb.agents')+'</button></div></div>';
      if(blk.length) html+='<div class="wb-box wb-warn"><h4>'+_t('wbj.pb.blocked',{n:blk.length})+'</h4><div class="small">'+_t('wbj.pb.blockedT')+'</div></div>';
      html+='<div class="row" style="margin-top:14px"><button class="btn p" id="wbPublish"'+(!can||blk.length?' disabled':'')+'>'+_t(pub?'wbj.pb.republish':'wbj.pb.publish')+'</button>'+(pub?'<button class="btn" id="wbUnpublish">'+_t('wbj.pb.unpublish')+'</button>':'')+'</div><div id="wbPubMsg"></div>';
      html+='<div class="wb-box"><h4>'+_t('wbj.pb.domain')+'</h4><div class="small mute">'+_t('wbj.pb.domainT')+'</div><label for="wbDomain">'+_t('wbj.pb.domainL')+'</label><div class="row"><input type="text" id="wbDomain" value="'+h(S.custom_domain||'')+'" placeholder="www.mein-betrieb.de" maxlength="190" style="flex:1;min-width:160px"><button class="btn s" id="wbDomainSave">'+_t('wbj.save')+'</button></div><div id="wbDomMsg"></div>'
        +'<details class="guide" style="margin-top:12px"><summary>'+_t('wbj.pb.domainHow')+'</summary><ol><li>'+_t('wbj.pb.dh1')+'</li><li>'+_t('wbj.pb.dh2',{u:'<code>'+h(S.url)+'</code>'})+'</li><li>'+_t('wbj.pb.dh3')+'</li></ol><div class="tip">'+_t('wbj.pb.dhTip')+'</div></details></div>';
      html+='<div class="wb-box"><h4>'+_t('wbj.pb.export')+'</h4><div class="small mute">'+_t('wbj.pb.exportT')+'</div><div class="row" style="margin-top:10px"><button class="btn s" id="wbExport"'+(!can||blk.length?' disabled':'')+'>⬇ '+_t('wbj.pb.exportBtn')+'</button>'+(S.exported_at?'<span class="mute small">'+_t('wbj.pb.exportedAt',{d:fmtDt(S.exported_at)})+'</span>':'')+'</div><div id="wbExpMsg"></div></div>';
      html+='<div class="wb-box"><h4>'+_t('wbj.pb.sitemap')+'</h4><div class="small mute">'+_t('wbj.pb.sitemapT')+'</div><div class="snip" style="font-size:12px">'+h(S.sitemap)+'</div></div>';
      html+='<div class="wb-box" style="border-color:#f7c6d5"><h4>'+_t('wbj.pb.delete')+'</h4><div class="small mute">'+_t('wbj.pb.deleteT')+'</div><button class="btn s wb-danger" id="wbDelete" style="margin-top:10px">'+_t('wbj.pb.deleteBtn')+'</button></div>';
    }
    else if(TAB==='leads'){
      html+='<div class="row" style="justify-content:space-between"><h3 style="font-size:18px">'+_t('wbj.ld.h')+'</h3><button class="btn s" id="wbLeadsRe">↻</button></div><p class="mute small">'+_t('wbj.ld.t')+'</p><div id="wbLeads" class="mute small">'+_t('sj.loading')+'</div>';
    }
    p.innerHTML=html; bindPane(p);
  }
  function assetSelect(path,val){
    return '<select data-path="'+h(path)+'" data-num="1"><option value="">'+_t('wbj.img.none')+'</option>'+(S.assets||[]).map(function(a){return '<option value="'+a.id+'"'+(+val===a.id?' selected':'')+'>#'+a.id+' · '+a.w+'×'+a.h+'</option>';}).join('')+'</select>';
  }
  function hoursEditor(){
    var H=S.data.hours||null; var days=[[1,'wbj.d1'],[2,'wbj.d2'],[3,'wbj.d3'],[4,'wbj.d4'],[5,'wbj.d5'],[6,'wbj.d6'],[0,'wbj.d0']];
    return '<div class="sw"><input type="checkbox" id="wbHoursOn"'+(H?' checked':'')+'><div><b>'+_t('wbj.h.show')+'</b><div class="mute small">'+_t('wbj.h.showT')+'</div></div></div><div class="wb-hours" id="wbHours"'+(H?'':' style="display:none"')+'>'+days.map(function(dd){var iv=(H&&H[String(dd[0])])||[];var a=iv[0]||[];var b2=iv[1]||[];
      return '<div class="wb-hr" data-day="'+dd[0]+'"><b>'+_t(dd[1])+'</b><div class="wb-hi"><label><input type="checkbox" class="wb-hon"'+(iv.length?' checked':'')+'> '+_t('wbj.h.open')+'</label><input type="time" class="wb-h1a" value="'+h(a[0]||'09:00')+'"> – <input type="time" class="wb-h1b" value="'+h(a[1]||'18:00')+'"> <label><input type="checkbox" class="wb-h2on"'+(b2.length?' checked':'')+'> +</label><input type="time" class="wb-h2a" value="'+h(b2[0]||'')+'"> – <input type="time" class="wb-h2b" value="'+h(b2[1]||'')+'"></div></div>';}).join('')+'</div>';
  }
  function readHours(root){
    var on=root.querySelector('#wbHoursOn'); if(!on) return undefined; if(!on.checked) return null; var o={};
    root.querySelectorAll('.wb-hr').forEach(function(r){var d=r.dataset.day;var rows=[];if(r.querySelector('.wb-hon').checked){var a=r.querySelector('.wb-h1a').value,b=r.querySelector('.wb-h1b').value;if(a&&b)rows.push([a,b]);if(r.querySelector('.wb-h2on').checked){var c=r.querySelector('.wb-h2a').value,e=r.querySelector('.wb-h2b').value;if(c&&e)rows.push([c,e]);}}o[d]=rows;});
    return o;
  }
  function bindPane(p){
    bindLangTabs(p);
    p.querySelectorAll('[data-path]').forEach(function(el){
      var ev=el.tagName==='SELECT'?'change':'input';
      el.addEventListener(ev,function(){var v=el.value; if(el.dataset.num){v=v===''?null:+v;}
        var path=el.dataset.path; if(path.indexOf('seo.')===0){setPath(S,path,v);} else setPath(S.data,path,v);
        if(el.classList.contains('ph')||PH.test(String(v||''))) el.classList.toggle('ph',PH.test(String(v||'')));
        var c=p.querySelector('[data-cnt="'+el.id+'"]'); if(c){c.textContent=String(v||'').length+'/'+el.maxLength;c.classList.toggle('bad',String(v||'').length>=el.maxLength);}
        if(path.indexOf('seo.')===0){var t=g('wbSerpT'),d2=g('wbSerpD');if(t&&path.endsWith('.title'))t.textContent=v||S.name||'';if(d2&&path.endsWith('.desc'))d2.textContent=v||'';}
        if(path==='biz.name'){var tt=g('wbTitle');if(tt)tt.textContent=v||_t('wbj.untitled');}
        touch();});
    });
    p.querySelectorAll('[data-add]').forEach(function(b){b.onclick=function(){var k=b.dataset.add;S.data[k]=S.data[k]||[];var o={services:{name:{},desc:{},price:''},faq:{q:{},a:{}},team:{name:'',role:{},img:null},reviews:{author:'',text:'',stars:5}}[k];S.data[k].push(o);touch();renderPane();};});
    p.querySelectorAll('[data-del]').forEach(function(b){b.onclick=function(){S.data[b.dataset.del].splice(+b.dataset.i,1);touch();renderPane();};});
    var sa=p.querySelector('#wbSvcAuto'); if(sa) sa.onchange=function(){S.data.services_auto=sa.checked;touch();};
    var ho=p.querySelector('#wbHoursOn'); if(ho){ ho.onchange=function(){g('wbHours').style.display=ho.checked?'':'none';hoursChanged(p);}; p.querySelectorAll('#wbHours input').forEach(function(i){i.addEventListener('change',function(){hoursChanged(p);});}); }
    /* Design */
    var tp=p.querySelector('#wbTplPick'); if(tp){fitThumbs(tp);tp.querySelectorAll('.wb-tpl').forEach(function(b){b.onclick=function(){S.template=b.dataset.tpl;tp.querySelectorAll('.wb-tpl').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});var t=META.templates.find(function(x){return x.id===S.template;});if(t){S.theme.preset=t.preset;S.theme.font=t.font;var pr=META.presets[t.preset];if(pr){['bg','sur','ink','accent','accent2'].forEach(function(k){S.theme[k]=pr[k];});}}save(true).then(renderPane);};});}
    p.querySelectorAll('[data-preset]').forEach(function(b){b.onclick=function(){var pr=META.presets[b.dataset.preset];S.theme.preset=b.dataset.preset;['bg','sur','ink','accent','accent2'].forEach(function(k){S.theme[k]=pr[k];});p.querySelectorAll('[data-preset]').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});p.querySelectorAll('[data-color]').forEach(function(c){c.value=S.theme[c.dataset.color]||c.value;});touch();};});
    p.querySelectorAll('[data-color]').forEach(function(c){c.addEventListener('input',function(){S.theme[c.dataset.color]=c.value;touch();});});
    var f=p.querySelector('#wbFont'); if(f) f.onchange=function(){S.theme.font=f.value;touch();};
    var cta=p.querySelector('#wbCta'); if(cta) cta.onchange=function(){S.data.cta={primary:cta.value};touch();};
    var w=p.querySelector('#wbWidget'); if(w){ w.onchange=function(){S.data.widget=w.checked;touch();}; var ws=g('wbWidgetState'); var st=(S.widget&&S.widget.state)||'none'; ws.innerHTML=st==='ok'?'<span class="wb-pill ok">'+_t('wbj.wg.ok')+'</span>':'<span class="wb-pill warn">'+_t('wbj.wg.'+st)+'</span> <a href="#website" onclick="go(\'website\');return false">'+_t('wbj.widgetLink')+'</a>'; }
    var secs=p.querySelector('#wbSecs'); if(secs){
      secs.querySelectorAll('[data-on]').forEach(function(c){c.onchange=function(){S.data.sections[+c.dataset.on].on=c.checked;c.closest('.wb-sec').classList.toggle('off',!c.checked);touch();};});
      secs.querySelectorAll('[data-mv]').forEach(function(b){b.onclick=function(){var i=+b.dataset.i,j=i+(+b.dataset.mv);if(j<0||j>=S.data.sections.length)return;var a=S.data.sections;var x=a[i];a[i]=a[j];a[j]=x;touch();renderPane();var nb=g('wbPane').querySelector('[data-mv="'+b.dataset.mv+'"][data-i="'+j+'"]');if(nb&&!nb.disabled)nb.focus();else{var alt=g('wbPane').querySelector('[data-mv="'+(-b.dataset.mv)+'"][data-i="'+j+'"]');if(alt)alt.focus();}live(_t('wbj.moved',{n:_t(SECN[x.id]),p:j+1}));};});
      secs.querySelectorAll('.wb-sec').forEach(function(li){
        li.addEventListener('dragstart',function(e){dragSrc=li;li.classList.add('drag');try{e.dataTransfer.setData('text/plain',li.dataset.sec);e.dataTransfer.effectAllowed='move';}catch(x){}});
        li.addEventListener('dragend',function(){li.classList.remove('drag');secs.querySelectorAll('.over').forEach(function(x){x.classList.remove('over');});});
        li.addEventListener('dragover',function(e){e.preventDefault();if(li!==dragSrc)li.classList.add('over');});
        li.addEventListener('dragleave',function(){li.classList.remove('over');});
        li.addEventListener('drop',function(e){e.preventDefault();li.classList.remove('over');if(!dragSrc||dragSrc===li)return;var a=S.data.sections;var from=a.findIndex(function(s){return s.id===dragSrc.dataset.sec;}),to=a.findIndex(function(s){return s.id===li.dataset.sec;});var x=a.splice(from,1)[0];a.splice(to,0,x);touch();renderPane();});
      });
    }
    /* Bilder */
    var fi=p.querySelector('#wbFile'); if(fi){ fi.onchange=function(){uploadFiles(fi.files);}; var dz=p.querySelector('#wbDrop');
      dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('over');}); dz.addEventListener('dragleave',function(){dz.classList.remove('over');}); dz.addEventListener('drop',function(e){e.preventDefault();dz.classList.remove('over');uploadFiles(e.dataTransfer.files);});
      p.querySelectorAll('[data-gal]').forEach(function(c){c.onchange=function(){var id=+c.dataset.gal;S.data.gallery=S.data.gallery||[];if(c.checked){if(!S.data.gallery.find(function(x){return x.img===id;}))S.data.gallery.push({img:id,alt:{}});}else S.data.gallery=S.data.gallery.filter(function(x){return x.img!==id;});touch();renderPane();};});
      p.querySelectorAll('[data-adel]').forEach(function(b){b.onclick=function(){if(!confirm(_t('wbj.img.delQ')))return;api('/sites/'+S.id+'/asset-delete',{method:'POST',body:JSON.stringify({asset_id:+b.dataset.adel})}).then(function(j){S=j.site;renderPane();pvRefresh();}).catch(function(e){msg(g('wbUpMsg'),h(err(e)),'err');});};});
    }
    /* SEO/Slug */
    var ss=p.querySelector('#wbSlugSave'); if(ss){ var si=p.querySelector('#wbSlug'); var chk=null;
      si.addEventListener('input',function(){clearTimeout(chk);chk=setTimeout(function(){api('/sites/slug-check?slug='+encodeURIComponent(si.value)+'&id='+S.id).then(function(j){msg(g('wbSlugMsg'),j.ok?_t('wbj.seo.slugOk',{s:j.slug}):h(_tx(j.message||'')),j.ok?'ok':'err');}).catch(function(){});},350);});
      ss.onclick=function(){flush().then(function(){return api('/sites/'+S.id,{method:'POST',body:JSON.stringify({slug:si.value})});}).then(function(j){S=j.site;msg(g('wbSlugMsg'),_t('wbj.seo.slugSaved'),'ok');renderEditor();}).catch(function(e){msg(g('wbSlugMsg'),h(err(e)),'err');});}; }
    /* Veröffentlichen */
    var pb=p.querySelector('#wbPublish'); if(pb) pb.onclick=function(){pb.disabled=true;msg(g('wbPubMsg'),_t('wbj.pb.working'));flush().then(function(){return api('/sites/'+S.id+'/publish',{method:'POST',body:'{}'});}).then(function(j){S=j.site;live(_t('wbj.pb.done'));renderEditor();TAB='publish';renderPane();msg(g('wbPubMsg'),_t('wbj.pb.done')+' <a href="'+h(S.url)+'" target="_blank" rel="noopener">'+h(S.url)+'</a>','ok');load();}).catch(function(e){pb.disabled=false;var d=e.data||{};if(d.issues){S.issues=d.issues;renderIssues();}msg(g('wbPubMsg'),h(err(e))+(d.checkout?' <a href="'+h(d.checkout)+'" target="_blank" rel="noopener">'+_t('wbj.pb.buyNow')+'</a>':''),'err');});};
    var up=p.querySelector('#wbUnpublish'); if(up) up.onclick=function(){if(!confirm(_t('wbj.pb.unpubQ')))return;api('/sites/'+S.id+'/unpublish',{method:'POST',body:'{}'}).then(function(j){S=j.site;renderEditor();TAB='publish';renderPane();load();}).catch(function(e){msg(g('wbPubMsg'),h(err(e)),'err');});};
    var ds=p.querySelector('#wbDomainSave'); if(ds) ds.onclick=function(){api('/sites/'+S.id,{method:'POST',body:JSON.stringify({custom_domain:g('wbDomain').value.trim()})}).then(function(j){S=j.site;msg(g('wbDomMsg'),_t('wbj.savedOk'),'ok');}).catch(function(e){msg(g('wbDomMsg'),h(err(e)),'err');});};
    var ex=p.querySelector('#wbExport'); if(ex) ex.onclick=function(){ex.disabled=true;msg(g('wbExpMsg'),_t('wbj.pb.working'));flush().then(function(){return fetch(API+'/sites/'+S.id+'/export?_='+Date.now(),{headers:{'Authorization':'Bearer '+KEY},cache:'no-store'});}).then(function(r){if(!r.ok)return r.json().then(function(j){throw Object.assign(new Error(j.message||('HTTP '+r.status)),{data:j});});return r.blob();})
      .then(function(bl){var a=document.createElement('a');a.href=URL.createObjectURL(bl);a.download=S.slug+'-website.zip';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},2000);msg(g('wbExpMsg'),_t('wbj.pb.exportOk'),'ok');ex.disabled=false;}).catch(function(e){msg(g('wbExpMsg'),h(err(e)),'err');ex.disabled=false;});};
    var del=p.querySelector('#wbDelete'); if(del) del.onclick=function(){if(!confirm(_t('wbj.pb.deleteQ',{n:S.name||S.slug})))return;api('/sites/'+S.id+'/delete',{method:'POST',body:'{}'}).then(function(){S=null;dirty=false;load();}).catch(function(e){alert(err(e));});};
    /* Anfragen */
    if(p.querySelector('#wbLeads')){loadLeads();p.querySelector('#wbLeadsRe').onclick=loadLeads;}
  }
  function hoursChanged(p){var hh=readHours(p);if(hh!==undefined){S.data.hours=hh;touch();}}
  function loadLeads(){api('/sites/'+S.id+'/leads').then(function(j){LEADS=j.leads||[];var el=g('wbLeads');if(!el)return;if(!LEADS.length){el.innerHTML='<div class="card" style="padding:14px">'+_t('wbj.ld.none')+'</div>';return;}
    el.innerHTML=LEADS.map(function(l){return '<div class="wb-lead'+(l.status==='done'?' done':'')+'"><div class="row" style="justify-content:space-between"><b>'+h(l.name)+'</b><span class="mute small">'+h(fmtDt(l.created_at))+' · '+h((l.lang||'').toUpperCase())+'</span></div><div class="small">'+(/@/.test(l.contact)?'<a href="mailto:'+h(l.contact)+'">'+h(l.contact)+'</a>':'<a href="tel:'+h(l.contact.replace(/[^0-9+]/g,''))+'">'+h(l.contact)+'</a>')+'</div><div class="wb-lm">'+h(l.message)+'</div>'
      +'<div class="row" style="margin-top:8px"><button class="btn s" data-ls="'+(l.status==='done'?'new':'done')+'" data-id="'+l.id+'">'+_t(l.status==='done'?'wbj.ld.reopen':'wbj.ld.done')+'</button><button class="btn s wb-danger" data-ls="delete" data-id="'+l.id+'">'+_t('wbj.del')+'</button></div></div>';}).join('');
    el.querySelectorAll('[data-ls]').forEach(function(b){b.onclick=function(){if(b.dataset.ls==='delete'&&!confirm(_t('wbj.ld.delQ')))return;api('/sites/'+S.id+'/lead-status',{method:'POST',body:JSON.stringify({lead_id:+b.dataset.id,status:b.dataset.ls})}).then(loadLeads).catch(function(e){alert(err(e));});};});
  }).catch(function(e){var el=g('wbLeads');if(el)el.innerHTML='<div class="msg err">'+h(err(e))+'</div>';});}
  function uploadFiles(files){
    var list=Array.prototype.slice.call(files||[]); if(!list.length) return; var m=g('wbUpMsg'); var i=0;
    var next=function(){ if(i>=list.length){msg(m,_t('wbj.img.done',{n:list.length}),'ok');renderPane();pvRefresh();return;} var f=list[i++];
      if(f.size>2*1024*1024){msg(m,_t('wbj.img.tooBig',{n:f.name}),'err');return next();}
      msg(m,_t('wbj.img.uploading',{n:f.name,i:i,c:list.length}));
      var r=new FileReader(); r.onload=function(){api('/sites/'+S.id+'/upload',{method:'POST',body:JSON.stringify({data:r.result,kind:'image'})}).then(function(j){S.assets=j.assets;next();}).catch(function(e){msg(m,h(f.name)+': '+h(err(e)),'err');});}; r.readAsDataURL(f); };
    next();
  }
  /* ---------- Speichern & Vorschau ---------- */
  function touch(){dirty=true;var s=g('wbSave');if(s)s.textContent=_t('wbj.unsaved');clearTimeout(saveT);saveT=setTimeout(function(){save(false);},900);}
  function save(reRender){
    if(!S) return Promise.resolve(); clearTimeout(saveT); if(saving){dirty=true;return Promise.resolve();} saving=true; var s=g('wbSave'); if(s)s.textContent=_t('wbj.saving');
    var body={template:S.template,theme:S.theme,data:S.data,seo:S.seo};
    return api('/sites/'+S.id,{method:'POST',body:JSON.stringify(body)}).then(function(j){saving=false;var wasDirty=dirty;dirty=false;var keep=S;S=j.site;
        if(wasDirty&&saveT===null){}
        if(s)s.textContent=_t('wbj.saved'); renderIssues(); if(reRender)renderPane(); pvRefresh(); if(dirty)save(false);
      }).catch(function(e){saving=false;if(s)s.textContent='⚠ '+err(e);});
  }
  function flush(){clearTimeout(saveT);return dirty?save(false):Promise.resolve();}
  function pvRefresh(){clearTimeout(pvT);pvT=setTimeout(function(){var f=g('wbIframe');if(f&&S)f.src=S.preview_url+'&lang='+LANG+'&_='+Date.now();},250);}
  /* ---------- KI-Assistent ---------- */
  function openWizard(fresh){
    var host=g('wbModalHost'); var A={tone:'',audience:'',offer:'',usp:'',extra:''}; var step=0; var price=META.prices.generate;
    var tones=['wbj.w.t1','wbj.w.t2','wbj.w.t3','wbj.w.t4'];
    function render(){
      var html='<div class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="wbWh"><div class="wb-mc">';
      if(step===0) html+='<span class="wb-step">'+_t('wbj.w.step',{n:1,c:3})+'</span><h3 id="wbWh">'+_t('wbj.w.h1')+'</h3><p class="mute">'+_t('wbj.w.p1')+'</p><label>'+_t('wbj.w.tone')+'</label><div class="wb-chips" id="wbTones">'+tones.map(function(k){return '<button type="button" class="wb-chip" data-tone="'+_t(k)+'" aria-pressed="'+(A.tone===_t(k)?'true':'false')+'">'+_t(k)+'</button>';}).join('')+'</div><label for="wbWaud">'+_t('wbj.w.aud')+'</label><input type="text" id="wbWaud" maxlength="300" value="'+h(A.audience)+'" placeholder="'+h(_t('wbj.w.audPh'))+'">';
      else if(step===1) html+='<span class="wb-step">'+_t('wbj.w.step',{n:2,c:3})+'</span><h3 id="wbWh">'+_t('wbj.w.h2')+'</h3><p class="mute">'+_t('wbj.w.p2')+'</p><label for="wbWoff">'+_t('wbj.w.offer')+'</label><textarea id="wbWoff" maxlength="600" placeholder="'+h(_t('wbj.w.offerPh'))+'">'+h(A.offer)+'</textarea><label for="wbWusp">'+_t('wbj.w.usp')+'</label><textarea id="wbWusp" maxlength="600" placeholder="'+h(_t('wbj.w.uspPh'))+'">'+h(A.usp)+'</textarea>';
      else html+='<span class="wb-step">'+_t('wbj.w.step',{n:3,c:3})+'</span><h3 id="wbWh">'+_t('wbj.w.h3')+'</h3><p class="mute">'+_t('wbj.w.p3')+'</p><label for="wbWext">'+_t('wbj.w.extra')+'</label><textarea id="wbWext" maxlength="600" placeholder="'+h(_t('wbj.w.extraPh'))+'">'+h(A.extra)+'</textarea><div class="wb-box"><b>'+_t('wbj.w.cost',{n:price})+'</b><div class="small mute">'+_t('wbj.w.costT',{b:I18N.num(META.balance||0)})+'</div></div><div id="wbWbusy"></div>';
      html+='<div class="wb-mf"><button type="button" class="btn" id="wbWx">'+_t(fresh&&step===0?'wbj.w.skip':'wbj.w.cancel')+'</button><div class="row">'+(step>0?'<button type="button" class="btn" id="wbWb">← '+_t('wbj.w.back')+'</button>':'')+'<button type="button" class="btn '+(step===2?'gold':'p')+'" id="wbWn">'+(step===2?'✦ '+_t('wbj.w.go',{n:price}):_t('wbj.w.next')+' →')+'</button></div></div></div></div>';
      host.innerHTML=html; var mc=host.querySelector('.wb-mc');
      host.querySelectorAll('[data-tone]').forEach(function(b){b.onclick=function(){A.tone=A.tone===b.dataset.tone?'':b.dataset.tone;host.querySelectorAll('[data-tone]').forEach(function(x){x.setAttribute('aria-pressed',x.dataset.tone===A.tone?'true':'false');});};});
      function collect(){var e;if((e=g('wbWaud')))A.audience=e.value;if((e=g('wbWoff')))A.offer=e.value;if((e=g('wbWusp')))A.usp=e.value;if((e=g('wbWext')))A.extra=e.value;}
      g('wbWx').onclick=function(){host.innerHTML='';};
      var bb=g('wbWb'); if(bb) bb.onclick=function(){collect();step--;render();};
      g('wbWn').onclick=function(){collect();if(step<2){step++;render();return;}
        var btn=g('wbWn');btn.disabled=true;g('wbWbusy').innerHTML='<div class="wb-busy"><i></i>'+_t('wbj.w.busy')+'</div>';
        flush().then(function(){return api('/sites/'+S.id+'/generate',{method:'POST',body:JSON.stringify({lang:S.lang_default,answers:A})});}).then(function(j){S=j.site;LANG=S.lang_default;META.balance=j.balance;host.innerHTML='';if(typeof refreshMe==='function')refreshMe();live(_t('wbj.w.done',{n:j.tokens_used}));TAB='content';renderEditor();
          if(S.langs.length>1&&S.langs.some(function(l){return l!==S.lang_default&&!(S.data.texts&&S.data.texts[l]&&Object.keys(S.data.texts[l]).length);}))setTimeout(function(){openTranslate();},300);})
        .catch(function(e){btn.disabled=false;var d=e.data||{};g('wbWbusy').innerHTML='<div class="msg err">'+h(err(e))+(d.buy||d.error==='insufficient_tokens'?' <a href="/#preise" target="_blank">'+_t('wbj.buyTokens')+'</a>':'')+'</div>';});};
      setTimeout(function(){var f=mc.querySelector('input,textarea,button.wb-chip');if(f)f.focus();},30);
      host.onkeydown=function(e){if(e.key==='Escape')host.innerHTML='';};
    }
    render();
  }
  function openTranslate(){
    var host=g('wbModalHost'); var price=META.prices.translate; var sel={}; var have=S.data.texts||{};
    var langs=META.langs.filter(function(l){return l.id!==S.lang_default;});
    host.innerHTML='<div class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="wbTh"><div class="wb-mc"><h3 id="wbTh">'+_t('wbj.tr.h')+'</h3><p class="mute">'+_t('wbj.tr.p',{n:price,l:LANGN[S.lang_default]||S.lang_default})+'</p><div class="wb-chips">'
      +langs.map(function(l){var has=have[l.id]&&Object.keys(have[l.id]).length;return '<button type="button" class="wb-chip" data-l="'+l.id+'" aria-pressed="false">'+h(l.name)+(has?' ✓':'')+'</button>';}).join('')+'</div>'
      +'<div class="wb-box" style="margin-top:14px"><b id="wbTrCost">'+_t('wbj.tr.cost',{n:0,t:0})+'</b><div class="small mute">'+_t('wbj.w.costT',{b:I18N.num(META.balance||0)})+'</div></div><div id="wbTrBusy"></div>'
      +'<div class="wb-mf"><button type="button" class="btn" id="wbTx">'+_t('wbj.w.cancel')+'</button><button type="button" class="btn gold" id="wbTgo" disabled>✦ '+_t('wbj.tr.go')+'</button></div></div></div>';
    var upd=function(){var n=Object.keys(sel).length;g('wbTrCost').textContent=_t('wbj.tr.cost',{n:n,t:n*price});g('wbTgo').disabled=!n;};
    host.querySelectorAll('[data-l]').forEach(function(b){b.onclick=function(){if(sel[b.dataset.l])delete sel[b.dataset.l];else sel[b.dataset.l]=1;b.setAttribute('aria-pressed',sel[b.dataset.l]?'true':'false');upd();};});
    g('wbTx').onclick=function(){host.innerHTML='';}; host.onkeydown=function(e){if(e.key==='Escape')host.innerHTML='';};
    g('wbTgo').onclick=function(){var btn=g('wbTgo');btn.disabled=true;g('wbTrBusy').innerHTML='<div class="wb-busy"><i></i>'+_t('wbj.tr.busy')+'</div>';
      flush().then(function(){return api('/sites/'+S.id+'/translate',{method:'POST',body:JSON.stringify({langs:Object.keys(sel)})});}).then(function(j){S=j.site;META.balance=j.balance;host.innerHTML='';if(typeof refreshMe==='function')refreshMe();live(_t('wbj.tr.done',{n:(j.done||[]).length,t:j.tokens_used}));if(j.failed&&j.failed.length)alert(_t('wbj.tr.failed',{l:j.failed.join(', ')}));renderEditor();})
      .catch(function(e){btn.disabled=false;g('wbTrBusy').innerHTML='<div class="msg err">'+h(err(e))+'</div>';});};
    setTimeout(function(){var f=host.querySelector('.wb-chip');if(f)f.focus();},30);
  }
  window.addEventListener('beforeunload',function(e){if(dirty&&S){flush();}});
  window.addEventListener('resize',function(){var r=g('wbRoot');if(r)fitThumbs(r);});
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS['website-builder']={
    init:function(){load();},
    show:function(){if(!S)load();},
    open:function(id){openSite(id);}
  };
})();
