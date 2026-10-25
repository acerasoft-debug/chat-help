/* ChatHelp Studio — Übersicht (dash-lib): Kennzahlen mit Vorperiode, Verlauf je Tag/Stunde und Kanal, Stoßzeiten, Kanäle, Ergebnisse,
   offene Vorgänge, Stammkunden, Guthaben-Reichweite und alle Vorgänge (Filter, Suche, Einzelheiten, CSV).
   Wird in #pHome geladen (nicht als eigener Reiter). Nutzt die Studio-Globals api, esc, msg, go, loadPanel, ME, PLANS, I18N. Aktualisiert sich jede Minute, solange sichtbar. */
(function(){
  'use strict';
  const g=id=>document.getElementById(id);
  const h=v=>esc(v==null?'':String(v));
  const X=v=>h(v==null?'':I18N.fx(_tx(String(v))));
  const visible=()=>{const el=g('panel-dash');return !!el&&!el.closest('.hidden')&&!document.hidden;};
  const N=n=>I18N.num(n||0);
  const EUR=c=>I18N.eur((c||0)/100,(c||0)%100?2:0);
  const PCT=x=>I18N.num(x||0,{style:'percent',maximumFractionDigits:0});
  const S={days:30,metric:'answers',type:'all',chan:'',q:'',off:0,items:[],sum:null,seq:0,fseq:0,timer:null,qt:null,last:null};
  try{const d=+localStorage.getItem('ch_dash_days');if([1,7,30,90].includes(d))S.days=d;}catch(e){}
  const CH=['phone','whatsapp','web'];
  const CHC={phone:'var(--c-phone)',whatsapp:'var(--c-whatsapp)',web:'var(--c-web)',studio:'#9094a6'};
  const CHL={phone:_t('dsj.ch.phone'),whatsapp:'WhatsApp',web:_t('dsj.ch.web'),studio:'Studio'};
  const ICON={
    phone:'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    whatsapp:'<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5.1A8.5 8.5 0 1 1 21 11.5Z"/><path d="M9 9.5c.3 2 2 4 4.5 4.8"/>',
    web:'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    order:'<path d="M6 8h12l-1 12H7Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
    appt:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    ride:'<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/><circle cx="7.5" cy="16" r="1.5"/><circle cx="16.5" cy="16" r="1.5"/>',
    hotel:'<path d="M3 18V8M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/>',
    callback:'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/><path d="M15 3v6h6"/>',
    ledger:'<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5c0-1.4 1.3-2 3-2s3 .7 3 2-1.3 1.8-3 2.3-3 1-3 2.4 1.3 2 3 2 3-.6 3-2"/>',
    plan:'<path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.9Z"/>',
    onb:'<path d="M5 12l4 4 10-10"/>',num:'<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/>',
    msg:'<path d="M4 5h16v11H8l-4 4Z"/>',done:'<path d="M5 12l4 4 10-10"/>',coin:'<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5c0-1.4 1.3-2 3-2s3 .7 3 2-1.3 1.8-3 2.3-3 1-3 2.4 1.3 2 3 2 3-.6 3-2"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',moon:'<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>'
  };
  const ic=(k,s)=>'<svg width="'+(s||18)+'" height="'+(s||18)+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(ICON[k]||ICON.msg)+'</svg>';
  const KIND_IC={call:'phone',whatsapp:'whatsapp',web:'web',order:'order',appt:'appt',ride:'ride',hotel:'hotel',hotel_request:'hotel',callback:'callback',ledger:'ledger',plan:'plan',onb:'onb',num:'num'};
  const PANEL_OF={order:'bestellungen',appt:'termine',ride:'taxi',hotel:'hotel',hotel_request:'hotel',callback:'rueckruf'};
  const tabOk=v=>{const t=document.querySelector('#tabs .tab[data-v="'+v+'"]');return !!t&&(typeof STATIC_TABS!=='undefined'&&STATIC_TABS[v]||typeof PANELS!=='undefined'&&PANELS[v]);};
  const planName=id=>{if(!id)return '';if(I18N.has('pl.'+id+'.n'))return _t('pl.'+id+'.n');const p=(typeof PLANS!=='undefined'&&PLANS||[]).find(x=>x.id===id);return p?p.name:id;};
  const tv=(pre,v)=>I18N.has(pre+v)?_t(pre+v):v;

  /* ---------- Zeit ---------- */
  function rel(iso){if(!iso)return '';const d=new Date(iso),n=new Date();const day=x=>new Date(x.getFullYear(),x.getMonth(),x.getDate()).getTime();const diff=Math.round((day(n)-day(d))/86400000);
    if(diff===0){const m=Math.round((n-d)/60000);if(m<1)return _t('dsj.now');if(m<60)return _t('dsj.minAgo',{n:m});return I18N.time(d);}
    if(diff===1)return _t('dsj.yesterday',{t:I18N.time(d)});
    return I18N.date(d,{day:'2-digit',month:'2-digit',year:diff>300?'2-digit':undefined})+' '+I18N.time(d);}
  const wall=v=>{if(!v)return '';const d=new Date(String(v).replace(' ','T'));return isNaN(d)?String(v):I18N.dt(d,{dateStyle:'medium',timeStyle:'short'});};
  const dayLbl=(d,long)=>{const x=d.length>10?new Date(d):new Date(d+'T12:00');return d.length>10?(long?I18N.date(x,{weekday:'short',day:'2-digit',month:'2-digit'})+' ':'')+I18N.time(x):I18N.date(x,long?{weekday:'short',day:'2-digit',month:'2-digit'}:{day:'2-digit',month:'2-digit'});};
  const hello=()=>{const hr=new Date().getHours();return _t(hr<11?'dsj.hi.m':hr<18?'dsj.hi.d':'dsj.hi.e');};

  /* ---------- Kennzahlen ---------- */
  function delta(v,p,inv){if(!v&&!p)return '<span class="ds-delta">–</span>';if(!p)return '';   /* ohne Vorperiode kein Vergleich */
    const r=(v-p)/p,up=r>=0;const cls=Math.abs(r)<0.005?'':((up!==!!inv)?'up':'down');
    return '<span class="ds-delta '+cls+'" title="'+h(_t('dsj.prev',{v:N(p)}))+'">'+(up?'▲':'▼')+' '+PCT(Math.abs(r))+'</span>';}
  function spark(vals){if(!vals.length||!vals.some(x=>x>0))return '';const w=88,hh=28,mx=Math.max(...vals,1),st=vals.length>1?w/(vals.length-1):w;
    const pts=vals.map((v,i)=>[(i*st).toFixed(1),(hh-2-(v/mx)*(hh-6)).toFixed(1)]);
    return '<svg class="ds-spark" width="'+w+'" height="'+hh+'" viewBox="0 0 '+w+' '+hh+'" aria-hidden="true"><path d="M0 '+hh+' L'+pts.map(p=>p.join(' ')).join(' L')+' L'+w+' '+hh+' Z" fill="rgba(79,70,229,.10)"/><polyline points="'+pts.map(p=>p.join(',')).join(' ')+'" fill="none" stroke="var(--c-one)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>';}
  function kpi(icon,label,val,meta,dl,sp,info){return '<div class="ds-kpi"><div class="lbl">'+ic(icon,16)+'<span>'+h(label)+'</span>'+(info?'<button type="button" class="ds-info" title="'+h(info)+'" aria-label="'+h(info)+'">ⓘ</button>':'')+'</div><div class="val">'+val+'</div><div class="meta"><span>'+meta+'</span>'+(sp||'')+'</div>'+(dl?'<div>'+dl+'</div>':'')+'</div>';}
  function renderKpis(s){const k=s.kpi,ser=s.series;
    const sAns=ser.map(d=>d.phone+d.whatsapp+d.web),sCalls=ser.map(d=>d.calls),sAct=ser.map(d=>d.actions);
    const hrs=k.saved_min.v/60,hrsP=k.saved_min.prev/60;
    const avg=k.calls.avg_sec?_t('dsj.avg',{m:Math.floor(k.calls.avg_sec/60),s:String(k.calls.avg_sec%60).padStart(2,'0')}):'';
    const A=s.assumptions||{};
    g('dsKpis').innerHTML=
      kpi('msg',_t('dsj.k.answers'),N(k.answers.v),h(_t('dsj.k.answersSub',{w:N(k.chats.web),wa:N(k.chats.whatsapp)})),delta(k.answers.v,k.answers.prev),spark(sAns))+
      kpi('phone',_t('dsj.k.calls'),N(k.calls.v),h(_t('dsj.k.callsSub',{m:N(k.calls.min)})+(avg?' · '+avg:'')),delta(k.calls.v,k.calls.prev),spark(sCalls))+
      kpi('done',_t('dsj.k.actions'),N(k.actions.v),h(_t('dsj.k.actionsSub')),delta(k.actions.v,k.actions.prev),spark(sAct))+
      kpi('coin',_t('dsj.k.revenue'),EUR(k.revenue_cents.v),h(_t('dsj.k.revenueSub')),delta(k.revenue_cents.v,k.revenue_cents.prev))+
      kpi('clock',_t('dsj.k.saved'),hrs>=1?h(_t('dsj.hours',{h:I18N.num(hrs,{maximumFractionDigits:hrs<10?1:0})})):h(_t('dsj.mins',{m:N(k.saved_min.v)})),h(_t('dsj.k.savedSub')),delta(hrs,hrsP),'',
        _t('dsj.k.savedInfo',{c:I18N.num(A.chat||1.5),a:I18N.num(A.action||3),k:I18N.num(A.call||1)}))+
      kpi('moon',_t('dsj.k.after'),PCT(k.after_hours.share),h(_t('dsj.k.afterSub',{n:N(k.after_hours.n)})),'');}

  /* ---------- Verlauf (gestapelte Balken, eine Achse) ---------- */
  function nice(m){if(m<=4)return 4;const p=Math.pow(10,Math.floor(Math.log10(m))),f=m/p;return (f<=1?1:f<=2?2:f<=2.5?2.5:f<=5?5:10)*p;}
  function topRect(x,y,w,hh,r){r=Math.min(r,w/2,hh);return 'M'+x+' '+(y+hh)+'V'+(y+r)+'Q'+x+' '+y+' '+(x+r)+' '+y+'H'+(x+w-r)+'Q'+(x+w)+' '+y+' '+(x+w)+' '+(y+r)+'V'+(y+hh)+'Z';}
  function renderChart(s){const ser=s.series,m=S.metric;
    const keys=m==='answers'?CH:[m];const col=k=>m==='answers'?CHC[k]:'var(--c-one)';const lab=k=>m==='answers'?CHL[k]:_t('dsj.m.'+m);
    g('dsLegend').innerHTML=m==='answers'?keys.map(k=>'<span><i style="background:'+col(k)+'"></i>'+h(lab(k))+'</span>').join(''):'';
    const tot=ser.map(d=>keys.reduce((a,k)=>a+(d[k]||0),0));const mx=nice(Math.max(...tot,0));
    const el=g('dsChart');
    if(!tot.some(x=>x>0)){el.innerHTML='<div class="ds-empty">'+h(_t('dsj.noData.'+m))+'</div>';g('dsTable').innerHTML='';return;}
    const W=720,H=230,pl=34,pr=8,pt=10,pb=26,iw=W-pl-pr,ih=H-pt-pb,n=ser.length,band=iw/n,bw=Math.max(2,Math.min(band*0.64,26));
    let o='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+h(_t('dsj.chartAria',{m:_t('dsj.m.'+m)}))+'">';
    for(let i=0;i<=4;i++){const v=mx*i/4,y=pt+ih-ih*i/4;o+='<line class="gl" x1="'+pl+'" x2="'+(W-pr)+'" y1="'+y.toFixed(1)+'" y2="'+y.toFixed(1)+'"/><text class="ax" x="'+(pl-6)+'" y="'+(y+3.5).toFixed(1)+'" text-anchor="end">'+h(I18N.num(v,{notation:v>=10000?'compact':'standard',maximumFractionDigits:1}))+'</text>';}
    const every=Math.max(1,Math.ceil(n/(window.innerWidth<600?4:7)));
    ser.forEach((d,i)=>{const x0=pl+i*band,cx=x0+band/2,x=cx-bw/2;let y=pt+ih;
      o+='<rect class="band" x="'+x0.toFixed(1)+'" y="'+pt+'" width="'+band.toFixed(1)+'" height="'+ih+'" fill="transparent" data-i="'+i+'"/>';
      const segs=keys.map(k=>[k,d[k]||0]).filter(z=>z[1]>0);
      segs.forEach((z,j)=>{const hh=z[1]/mx*ih;const top=j===segs.length-1;const gap=j>0?2:0;const yy=y-hh;
        o+=top?'<path d="'+topRect(x.toFixed(1)*1,yy+gap,bw,Math.max(0,hh-gap),4)+'" fill="'+col(z[0])+'"/>':'<rect x="'+x.toFixed(1)+'" y="'+(yy+gap).toFixed(1)+'" width="'+bw.toFixed(1)+'" height="'+Math.max(0,hh-gap).toFixed(1)+'" fill="'+col(z[0])+'"/>';y=yy;});
      if(i%every===0||i===n-1&&n<=8)o+='<text class="ax" x="'+cx.toFixed(1)+'" y="'+(H-8)+'" text-anchor="middle">'+h(dayLbl(d.d))+'</text>';});
    ser.forEach((d,i)=>{o+='<rect class="hit" x="'+(pl+i*band).toFixed(1)+'" y="'+pt+'" width="'+band.toFixed(1)+'" height="'+ih+'" data-i="'+i+'"/>';});
    o+='</svg><div class="ds-tip" hidden></div>';el.innerHTML=o;
    const tip=el.querySelector('.ds-tip'),svg=el.querySelector('svg');
    const showTip=i=>{const d=ser[i];if(!d)return;el.querySelectorAll('.band').forEach(b=>b.classList.toggle('on',+b.dataset.i===i));
      tip.innerHTML='<b>'+h(dayLbl(d.d,true))+'</b>'+keys.map(k=>'<div class="r"><span><i style="background:'+col(k)+'"></i>'+h(lab(k))+'</span><span>'+N(d[k])+'</span></div>').join('')+(keys.length>1?'<div class="r" style="border-top:1px solid rgba(255,255,255,.2);margin-top:3px;padding-top:3px"><span>'+h(_t('dsj.total'))+'</span><b style="margin:0">'+N(tot[i])+'</b></div>':'');
      const r=svg.getBoundingClientRect(),sc=r.width/W;let left=(pl+(i+.5)*band)*sc;left=Math.max(70,Math.min(r.width-70,left));
      tip.style.left=left+'px';tip.style.top=Math.max(54,(pt+ih-tot[i]/mx*ih)*sc-6)+'px';tip.hidden=false;};
    el.querySelectorAll('.hit').forEach(r=>{r.addEventListener('mouseenter',()=>showTip(+r.dataset.i));r.addEventListener('touchstart',()=>showTip(+r.dataset.i),{passive:true});});
    svg.addEventListener('mouseleave',()=>{tip.hidden=true;el.querySelectorAll('.band.on').forEach(b=>b.classList.remove('on'));});
    g('dsTable').innerHTML='<thead><tr><th>'+h(S.days===1?_t('dsj.hour'):_t('dsj.day'))+'</th>'+keys.map(k=>'<th>'+h(lab(k))+'</th>').join('')+(keys.length>1?'<th>'+h(_t('dsj.total'))+'</th>':'')+'</tr></thead><tbody>'+
      ser.slice().reverse().map((d,ri)=>'<tr><td>'+h(dayLbl(d.d,true))+'</td>'+keys.map(k=>'<td>'+N(d[k])+'</td>').join('')+(keys.length>1?'<td><b>'+N(tot[ser.length-1-ri])+'</b></td>':'')+'</tr>').join('')+'</tbody>';}

  /* ---------- Guthaben + Agenten ---------- */
  function renderSide(s){const b=s.balance,q=s.quota;
    let o='<span class="tag gold">'+h(_t('dsj.bal'))+'</span><div class="big">'+N(b.tokens)+'</div><div class="mute small">'+h(_t('dsj.balSub'))+'</div>';
    if(b.byok)o+='<div class="small" style="margin-top:8px">'+h(_t('dsj.byok'))+'</div>';
    else if(b.days_left!=null){const d=b.days_left,warn=d<14;o+='<div class="small" style="margin-top:8px;color:'+(warn?'var(--rose)':'var(--ink2)')+'">'+h(d>365?_t('dsj.reachYear'):_t('dsj.reach',{d:N(d)}))+' · '+h(_t('dsj.perDay',{n:I18N.num(b.per_day,{maximumFractionDigits:1})}))+'</div>';}
    if(q&&q.pct!=null){const p=Math.max(0,Math.min(100,Math.round(q.pct)));o+='<div class="small" style="margin-top:12px;display:flex;justify-content:space-between"><span>'+h(_t('dsj.quota'))+'</span><b>'+p+' %</b></div><div class="ds-bar" role="progressbar" aria-valuenow="'+p+'" aria-valuemin="0" aria-valuemax="100"><div style="width:'+p+'%;background:'+(p>=100?'var(--rose)':p>=80?'var(--gold)':'var(--ok)')+'"></div></div>';}
    o+='<div class="small mute" style="margin-top:10px">'+h(_t('dsj.used',{n:N(s.kpi.tokens.used)}))+'</div><div class="ds-btns"><a class="btn s gold" href="/#preise" target="_blank" rel="noopener">'+h(_t('dsj.buy'))+'</a><button class="btn s" type="button" data-go="tokens">'+h(_t('dsj.usage'))+'</button></div>';
    g('dsBal').innerHTML=o;
    const plans=(ME&&ME.plans||[]).filter(p=>p.active),ph=(ME&&ME.configs||[]).find(c=>+c.phone_on),wd=(ME&&ME.configs||[]).some(c=>+c.widget_on);
    g('dsAg').innerHTML='<span class="tag indigo">'+h(_t('dsj.agents'))+'</span><div class="big" style="font:700 28px/1.1 var(--sans);margin:8px 0 6px">'+N(plans.length)+'</div>'+
      '<ul class="ds-list"><li><span>'+ic('phone',15)+' '+h(_t('dsj.ch.phone'))+'</span><b>'+h(ph?(ph.phone_number||_t('dsj.ph.pending')):_t('dsj.off'))+'</b></li><li><span>'+ic('web',15)+' '+h(_t('dsj.widget'))+'</span><b>'+h(wd?_t('dsj.on'):_t('dsj.off'))+'</b></li></ul>'+
      '<div class="ds-btns"><button class="btn s" type="button" data-go="agents">'+h(_t('dsj.agentsOpen'))+'</button>'+(tabOk('phone')?'<button class="btn s" type="button" data-go="phone">'+h(_t('dsj.ch.phone'))+'</button>':'')+'</div>';}

  /* ---------- Kanäle, Stoßzeiten, Ergebnisse ---------- */
  function renderChan(s){const list=s.channels.filter(c=>CH.includes(c.key)),tot=list.reduce((a,c)=>a+c.n,0);const st=s.channels.find(c=>c.key==='studio');
    if(!tot){g('dsChan').innerHTML='<div class="ds-empty">'+h(_t('dsj.noData.answers'))+'</div>';return;}
    g('dsChan').innerHTML='<ul>'+list.map(c=>{const p=tot?c.n/tot:0;return '<li><div class="t"><span>'+ic(c.key==='phone'?'phone':c.key,15)+' '+h(CHL[c.key])+'</span><span><b>'+N(c.n)+'</b> <span class="mute">· '+PCT(p)+'</span> '+delta(c.n,c.prev)+'</span></div><div class="ds-bar" style="margin:0"><div style="width:'+(p*100).toFixed(1)+'%;background:'+CHC[c.key]+'"></div></div></li>';}).join('')+'</ul>'+
      (st&&st.n?'<div class="small mute">'+h(_t('dsj.studioTests',{n:N(st.n)}))+'</div>':'');}
  const HEAT=['#cde2fb','#9ec5f4','#6da7ec','#3987e5','#256abf','#184f95'];
  function renderHeat(s){const H=s.heat,mx=Math.max(...H.flat(),0);
    if(!mx){g('dsHeat').innerHTML='<div class="ds-empty">'+h(_t('dsj.noData.answers'))+'</div>';return;}
    let o='<div class="ds-heat" role="img" aria-label="'+h(_t('dsj.heatAria'))+'">';
    let best=[0,0,0];
    for(let d=0;d<7;d++){o+='<span class="d">'+h(I18N.wd((d+1)%7,'short'))+'</span>';for(let hr=0;hr<24;hr++){const v=H[d][hr];if(v>best[2])best=[d,hr,v];
      const c=v?HEAT[Math.min(HEAT.length-1,Math.floor(v/mx*(HEAT.length-0.001)))]:'';o+='<span class="c"'+(c?' style="background:'+c+'"':'')+' title="'+h(I18N.wd((d+1)%7,'short')+' '+String(hr).padStart(2,'0')+':00 – '+String((hr+1)%24).padStart(2,'0')+':00 · '+_t('dsj.nAnswers',{n:N(v)}))+'"></span>';}}
    o+='<span></span>';for(let hr=0;hr<24;hr++)o+='<span class="h" style="grid-column:'+(hr+2)+'">'+([0,6,12,18].includes(hr)?String(hr).padStart(2,'0'):'')+'</span>';
    o+='</div><div class="ds-scale"><span>'+h(_t('dsj.less'))+'</span>'+HEAT.map(c=>'<i style="background:'+c+'"></i>').join('')+'<span>'+h(_t('dsj.more'))+'</span></div>';
    o+='<div class="small" style="margin-top:8px;color:var(--ink2)">'+h(_t('dsj.peak',{d:I18N.wd((best[0]+1)%7,'long'),h:String(best[1]).padStart(2,'0')+':00'}))+'</div>';
    g('dsHeat').innerHTML=o;}
  function renderOut(s){const k=s.kpi,oc=s.calls.outcomes||{},acts=s.actions.filter(a=>a.n>0);
    let o='<div class="ds-stat">'+ic('moon',22)+'<div><b>'+N(k.after_hours.n)+'</b><br><span>'+h(_t('dsj.afterTxt'))+'</span></div></div>';
    o+=acts.length?'<ul class="ds-list">'+acts.map(a=>'<li><span>'+ic(KIND_IC[a.kind]||'done',15)+' '+h(_t('dsj.kind.'+a.kind))+'</span><span><b>'+N(a.n)+'</b>'+(a.cents?' <span class="mute">· '+EUR(a.cents)+'</span>':'')+'</span></li>').join('')+'</ul>':'<div class="small mute">'+h(_t('dsj.noActions'))+'</div>';
    const ok=Object.entries(oc);if(ok.length)o+='<div class="small" style="margin:12px 0 4px;font-weight:600">'+h(_t('dsj.callRes'))+'</div><ul class="ds-list">'+ok.map(([c,n])=>'<li><span>'+h(tv('dsj.oc.',c))+'</span><b>'+N(n)+'</b></li>').join('')+'</ul>';
    g('dsOut').innerHTML=o;}
  function renderOpen(s){const o=s.open||{};const kinds=Object.keys(o).filter(k=>k!=='total'&&o[k]>0);
    g('dsOpen').innerHTML=kinds.length?'<p class="small mute" style="margin:0 0 10px">'+h(_t('dsj.openTxt',{n:N(o.total)}))+'</p><div class="ds-open">'+kinds.map(k=>'<button type="button" data-go="'+h(PANEL_OF[k]||'kunden')+'">'+ic(KIND_IC[k]||'done',16)+h(_t('dsj.kind.'+k))+' <b>'+N(o[k])+'</b></button>').join('')+'</div>':
      '<div class="ds-empty">✓ '+h(_t('dsj.openNone'))+'</div>';}
  function initials(n,p){const s=String(n||'').trim();if(!s)return String(p||'').slice(-2);const w=s.split(/\s+/);return ((w[0]||'')[0]||'').toUpperCase()+((w.length>1?w[w.length-1][0]:'')||'').toUpperCase();}
  function renderTop(s){const t=s.top||[];
    g('dsTop').innerHTML=t.length?'<ul class="ds-items">'+t.map(c=>'<li><button type="button" class="ds-cust" data-phone="'+h(c.phone)+'"><span class="ds-av" aria-hidden="true">'+h(initials(c.name,c.display))+'</span><span style="min-width:0"><span class="nm">'+h(c.name||c.display)+'</span><span class="sm">'+h(c.name?c.display+' · ':'')+h(_t('dsj.lastSeen',{t:rel(c.last)}))+'</span></span><span class="n">'+h(_t('dsj.nContacts',{n:N(c.n)}))+'</span></button></li>').join('')+'</ul>':
      '<div class="ds-empty">'+h(_t('dsj.topNone'))+'</div>';}
  function renderSummary(s){S.sum=s;const per=_t('dsj.per.'+s.days);
    const comp=(ME&&ME.configs||[]).map(c=>c.company).find(Boolean);g('dsHello').textContent=comp?hello()+', '+comp:_t('dsj.title');
    g('dsSub').textContent=_t('dsj.sub',{p:per});
    renderKpis(s);renderChart(s);renderSide(s);renderChan(s);renderHeat(s);renderOut(s);renderOpen(s);renderTop(s);
    S.last=new Date();g('dsLive').title=_t('dsj.updated',{t:I18N.time(S.last)});}

  /* ---------- Alle Vorgänge ---------- */
  const TYPES=['all','conv','order','booking','ride','request','billing','system'];
  const TONE={order:{new:'gold',confirmed:'indigo',preparing:'indigo',ready:'teal',out_for_delivery:'teal',delivered:'ok',cancelled:'rose'},appt:{booked:'indigo',done:'ok',noshow:'rose',cancelled:'rose'},
    ride:{new:'gold',assigned:'indigo',en_route:'indigo',arrived:'teal',onboard:'teal',completed:'ok',cancelled:'rose',no_show:'rose'},hotel:{requested:'gold',confirmed:'ok',declined:'rose',cancelled:'rose',checked_in:'teal',checked_out:'',no_show:'rose'},
    hotel_request:{open:'gold',in_progress:'indigo',done:'ok',cancelled:''},callback:{open:'gold',scheduled:'indigo',calling:'teal',done:'ok',failed:'rose',cancelled:''},
    plan:{active:'ok',trialing:'gold',past_due:'rose',canceled:'',expired:''},onb:{open:'gold',done:'ok'},num:{active:'ok',released:''}};
  const stLabel=(k,st)=>I18N.has('dsj.s.'+k+'.'+st)?_t('dsj.s.'+k+'.'+st):(I18N.has('dsj.st.'+st)?_t('dsj.st.'+st):st);
  const mmss=sec=>_t('dsj.avg',{m:Math.floor(sec/60),s:String(sec%60).padStart(2,'0')});
  const dOnly=v=>v?I18N.date(new Date(String(v).slice(0,10)+'T12:00'),{day:'2-digit',month:'2-digit'}):'';
  function itemText(it){const J=a=>a.filter(Boolean).join(' · ');const who=[it.name,it.phone_display].filter(Boolean).join(' · ');
    switch(it.kind){
      case 'call':return [_t(it.direction==='out'?'dsj.ttl.callOut':'dsj.ttl.call'),J([_t('dsj.nAnswers',{n:N(it.turns)}),it.sec!=null?mmss(it.sec):'',it.outcome?tv('dsj.oc.',it.outcome):'']),who];
      case 'whatsapp':return [_t('dsj.ttl.whatsapp'),it.text?'„'+it.text+'“':'',J([who,_t('dsj.nMsgs',{n:N(it.msgs)})])];
      case 'web':return [_t('dsj.kind.web'),it.text?'„'+it.text+'“':'',J([_t('dsj.nAnswers',{n:N(it.turns||0)}),it.lang?String(it.lang).toUpperCase():'',(it.tools||[]).map(x=>tv('dsj.tool.',x)).join(', ')])];
      case 'order':return [_t('dsj.ttl.order',{c:it.code}),J([it.items,EUR(it.cents),tv('dsj.v.',it.type)+(it.when?' '+I18N.time(new Date(String(it.when).replace(' ','T'))):'')]),who];
      case 'appt':return [_t('dsj.ttl.appt',{c:it.code}),J([it.service,wall(it.when)]),who];
      case 'ride':return [_t('dsj.ttl.ride',{c:it.code}),J([it.pickup+(it.dest?' → '+it.dest:''),it.asap?_t('dsj.v.asap'):wall(it.when),it.cents?EUR(it.cents):'']),who];
      case 'hotel':return [_t('dsj.ttl.hotel',{c:it.code}),J([it.room,dOnly(it.from)+' – '+dOnly(it.to),_t('dsj.guests',{n:N(it.guests)}),it.cents?EUR(it.cents):'']),who];
      case 'hotel_request':return [_t('dsj.ttl.hotel_request',{k:tv('dsj.hk.',it.request)}),J([it.room?_t('dsj.room',{n:it.room}):'',it.details]),who];
      case 'callback':return [_t('dsj.ttl.callback',{c:it.code})+(it.mode?' · '+tv('dsj.v.',it.mode):''),J([it.topic,it.when?wall(it.when):_t('dsj.v.asapCb'),it.result]),who];
      case 'ledger':return [tv('dsj.lr.',it.reason),(it.tokens>0?'+':'')+N(it.tokens)+' Tokens',''];
      case 'plan':return [planName(it.plan),_t('dsj.kind.plan'),''];
      case 'onb':return [_t('dsj.kind.onb'),planName(it.plan),''];
      case 'num':return [_t('dsj.kind.num'),it.phone_display,''];}
    return [_t('dsj.kind.'+it.kind),'',who];}
  function statusOf(it){if(it.kind==='call')return it.live?[_t('dsj.live'),'teal']:['',''];if(it.status&&TONE[it.kind])return [stLabel(it.kind,it.status),TONE[it.kind][it.status]||''];return ['',''];}
  function row(it){const [t,s,w]=itemText(it),[sl,tone]=statusOf(it);const icn=KIND_IC[it.kind]||'msg';const cls=it.kind==='call'?'phone':it.kind;
    return '<li><button type="button" class="ds-it" data-id="'+h(it.id)+'"><span class="ds-ic '+h(cls)+'">'+ic(icn,18)+'</span><span style="min-width:0"><span class="tt">'+h(t)+(it.direction==='out'?' ↗':'')+'</span>'+(s?'<span class="ss">'+h(s)+'</span>':'')+(w?'<span class="mm">'+h(w)+'</span>':'')+'</span>'+
      '<span class="rt"><span>'+h(rel(it.at))+'</span>'+(sl?'<span class="ds-pill '+h(tone||'')+'">'+h(sl)+'</span>':'')+(it.channel&&CHL[it.channel]?'<span>'+h(CHL[it.channel])+'</span>':'')+'</span></button></li>';}
  function renderTypes(c){const cur=S.type;g('dsTypes').innerHTML=TYPES.filter(t=>t==='all'||t===cur||(c[t]||0)>0).map(t=>'<button type="button" data-t="'+t+'" aria-pressed="'+(t===cur)+'">'+h(_t('dsj.t.'+t))+'<span>'+N(c[t]||0)+'</span></button>').join('');}
  function feedQ(){return 'days='+S.days+'&type='+encodeURIComponent(S.type)+'&channel='+encodeURIComponent(S.chan)+'&q='+encodeURIComponent(S.q);}
  function loadFeed(reset,silent){if(reset){S.off=0;}const my=++S.fseq;const box=g('dsItems');
    if(reset&&!silent)box.innerHTML='<li style="padding:12px 4px"><div class="ds-skel" style="width:70%"></div></li><li style="padding:12px 4px"><div class="ds-skel" style="width:55%"></div></li>';
    return api('/dash/activity?'+feedQ()+'&limit=30&offset='+S.off).then(j=>{if(my!==S.fseq)return;
      S.items=reset?j.items:S.items.concat(j.items);renderTypes(j.counts||{});
      box.innerHTML=S.items.length?S.items.map(row).join(''):'<li class="ds-empty">'+h(S.q||S.chan||S.type!=='all'?_t('dsj.feedNoHit'):_t('dsj.feedNone'))+'</li>';
      const m=g('dsMore');m.hidden=!j.more;m.textContent=_t('dsj.moreN',{n:N(j.total-S.items.length)});
    }).catch(e=>{if(my===S.fseq&&!silent)box.innerHTML='<li><div class="msg err">'+h(_te(e))+'</div></li>';});}

  /* ---------- Einzelheiten ---------- */
  let lastFocus=null;
  function fval(k,v,t){
    if(v==null||v==='')return '';
    switch(t){case 'eur':return h(EUR(v));case 'dt':return h(I18N.dt(v,{dateStyle:'medium',timeStyle:'short'}));case 'wall':return h(wall(v));
      case 'date':return h(I18N.date(new Date(String(v)+'T12:00'),{dateStyle:'medium'}));case 'num':return h(N(v));case 'km':return h(I18N.num(v,{maximumFractionDigits:1})+' km');
      case 'sec':return h(_t('dsj.avg',{m:Math.floor(v/60),s:String(v%60).padStart(2,'0')}));
      case 'phone':return '<a href="tel:'+h(String(v).replace(/[^\d+]/g,''))+'">'+h(v)+'</a>';case 'plan':return h(planName(v));
      case 'tools':return h(String(v).split(/,\s*/).map(x=>tv('dsj.tool.',x)).join(', '));case 'hk':return h(tv('dsj.hk.',v));}
    if(k==='status')return h(stLabel(S.dk||'',v));
    if(['outcome','type','payment','source','mode','reason','when'].includes(k)){const p=k==='reason'?'dsj.lr.':k==='outcome'?'dsj.oc.':'dsj.v.';return h(I18N.has(p+v)?_t(p+v):_tx(String(v)));}
    return h(_tx(String(v)));}
  function openItem(id,btn){lastFocus=btn||document.activeElement;const dr=g('dsDrawer'),pn=g('dsDet');dr.hidden=false;document.body.style.overflow='hidden';
    pn.innerHTML='<div class="hd"><h3 id="dsDetT">'+h(_t('dsj.loading'))+'</h3><button type="button" class="x" data-close="1" aria-label="'+h(_t('dsj.close'))+'">✕</button></div><div class="ds-skel" style="width:80%;margin:8px 0"></div><div class="ds-skel" style="width:60%"></div>';
    pn.querySelector('.x').focus();
    api('/dash/item?id='+encodeURIComponent(id)).then(d=>{S.dk=d.kind;const it=S.items.find(x=>x.id===id)||{kind:d.kind,title:'',sub:''};const [t]=itemText(it);
      const st=d.status&&TONE[d.kind]?['<span class="ds-pill '+h(TONE[d.kind][d.status]||'')+'">'+h(stLabel(d.kind,d.status))+'</span>']:[];
      let o='<div class="hd"><div style="display:flex;gap:12px;align-items:flex-start;min-width:0"><span class="ds-ic '+h(d.kind==='call'?'phone':d.kind)+'">'+ic(KIND_IC[d.kind]||'msg',18)+'</span><div style="min-width:0"><h3 id="dsDetT">'+h(t||_t('dsj.kind.'+d.kind))+'</h3><div class="small mute" style="margin-top:4px">'+h(d.ts?I18N.dt(d.ts,{dateStyle:'full',timeStyle:'short'}):'')+' '+st.join('')+'</div></div></div><button type="button" class="x" data-close="1" aria-label="'+h(_t('dsj.close'))+'">✕</button></div>';
      const F=(d.fields||[]).map(([k,v,ty])=>[k,fval(k,v,ty)]).filter(z=>z[1]!=='');
      if(F.length)o+='<dl>'+F.map(([k,v])=>'<dt>'+h(tv('dsj.f.',k))+'</dt><dd>'+v+'</dd>').join('')+'</dl>';
      if(d.lines&&d.lines.length)o+='<table class="lines"><tbody>'+d.lines.map(l=>'<tr><td><b>'+N(l.qty)+'×</b> '+h(l.name)+(l.options&&l.options.length?' <span class="mute">('+h(l.options.join(', '))+')</span>':'')+(l.note?'<div class="small mute">'+h(l.note)+'</div>':'')+'</td><td>'+(l.cents?h(EUR(l.cents)):'')+'</td></tr>').join('')+'</tbody></table>';
      if(d.messages&&d.messages.length)o+='<div class="chat"><h4>'+h(d.kind==='call'?_t('dsj.transcript'):_t('dsj.messages'))+'</h4>'+d.messages.map(m=>'<div class="bub '+(m.role==='user'?'user':'assistant')+'"><small>'+h(m.role==='user'?_t('dsj.customer'):_t('dsj.agent'))+'</small>'+h(m.content)+'</div>').join('')+'</div>';
      const acts=[];const pnl=d.panel==='website'?'website':d.panel;
      if(pnl&&tabOk(pnl))acts.push('<button class="btn p" type="button" data-go="'+h(pnl)+'">'+h(_t('dsj.openIn',{p:_t('dsj.panel.'+pnl)}))+'</button>');
      const ph=it.phone||'';if(ph&&tabOk('kunden'))acts.push('<button class="btn" type="button" data-phone="'+h(ph)+'">'+h(_t('dsj.history'))+'</button>');
      if(acts.length)o+='<div class="act">'+acts.join('')+'</div>';
      pn.innerHTML=o;const x=pn.querySelector('.x');if(x)x.focus();
    }).catch(e=>{pn.innerHTML='<div class="hd"><h3 id="dsDetT">'+h(_t('dsj.err'))+'</h3><button type="button" class="x" data-close="1" aria-label="'+h(_t('dsj.close'))+'">✕</button></div><div class="msg err">'+h(_te(e))+'</div>';});}
  function closeItem(){const dr=g('dsDrawer');if(dr.hidden)return;dr.hidden=true;document.body.style.overflow='';if(lastFocus&&lastFocus.focus)try{lastFocus.focus();}catch(e){}}
  function openCustomer(phone){closeItem();go('kunden');loadPanel('kunden').then(()=>{const k=window.STUDIO_PANELS&&STUDIO_PANELS.kunden;if(k&&k.open)k.open(phone);});}

  /* ---------- Laden ---------- */
  function load(silent){const my=++S.seq;
    document.querySelectorAll('#dsDays button').forEach(b=>b.setAttribute('aria-pressed',String(+b.dataset.d===S.days)));
    return api('/dash/summary?days='+S.days).then(s=>{if(my===S.seq)renderSummary(s);}).catch(e=>{if(my===S.seq&&!silent)g('dsKpis').innerHTML='<div class="msg err" style="grid-column:1/-1">'+h(_te(e))+'</div>';});}
  function csv(){const b=g('dsCsv');b.disabled=true;api('/dash/export?'+feedQ()).then(j=>{const blob=new Blob([j.csv],{type:'text/csv;charset=utf-8'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=j.filename||'chathelp.csv';document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},800);})
    .catch(e=>alert(_te(e))).finally(()=>{b.disabled=false;});}
  function bind(){const root=g('dsRoot');
    g('dsDays').addEventListener('click',e=>{const b=e.target.closest('button[data-d]');if(!b)return;S.days=+b.dataset.d;try{localStorage.setItem('ch_dash_days',String(S.days));}catch(_){}load();loadFeed(true);});
    g('dsMetric').addEventListener('click',e=>{const b=e.target.closest('button[data-m]');if(!b)return;S.metric=b.dataset.m;g('dsMetric').querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));if(S.sum)renderChart(S.sum);});
    g('dsTypes').addEventListener('click',e=>{const b=e.target.closest('button[data-t]');if(!b)return;S.type=b.dataset.t;loadFeed(true);});
    g('dsChanSel').addEventListener('change',e=>{S.chan=e.target.value;loadFeed(true);});
    g('dsQ').addEventListener('input',e=>{clearTimeout(S.qt);S.qt=setTimeout(()=>{S.q=e.target.value.trim();loadFeed(true);},300);});
    g('dsMore').addEventListener('click',()=>{S.off=S.items.length;loadFeed(false);});
    g('dsCsv').addEventListener('click',csv);
    root.addEventListener('click',e=>{const it=e.target.closest('.ds-it');if(it){openItem(it.dataset.id,it);return;}
      const c=e.target.closest('[data-close]');if(c){closeItem();return;}
      const gg=e.target.closest('[data-go]');if(gg){const v=gg.dataset.go;closeItem();go(v);return;}
      const ph=e.target.closest('[data-phone]');if(ph){openCustomer(ph.dataset.phone);}});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!g('dsDrawer').hidden)closeItem();
      if(e.key==='Tab'&&!g('dsDrawer').hidden){const f=[...g('dsDet').querySelectorAll('button,a[href]')];if(!f.length)return;const a=f[0],z=f[f.length-1];
        if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus();}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus();}}});
    let rt=null;window.addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(()=>{if(S.sum&&visible())renderChart(S.sum);},200);});}
  function timers(){if(S.timer)return;S.timer=setInterval(()=>{if(!visible()||!g('dsDrawer').hidden)return;load(true);if(!S.off&&document.activeElement!==g('dsQ'))loadFeed(true,true);},60000);}
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS.dash={init(){bind();load();loadFeed(true);timers();},show(){load(true);loadFeed(true,true);timers();}};
})();
