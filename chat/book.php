<?php
/** ChatHelp — Öffentliche Buchungsseite eines Betriebs: book.php?k=<Buchungsschlüssel>  (k=demo → Salon Luna) */
require_once __DIR__.'/pagever.php'; ch_page_version(__FILE__,[__DIR__.'/i18n.php']);
require_once __DIR__.'/i18n.php';
$L=ch_page_lang();   // ?lang= → Cookie → Accept-Language → CF-IPCountry → Deutsch
$h=fn($k,$v=[])=>htmlspecialchars((string)t($k,$L,$v),ENT_QUOTES,'UTF-8');
header('Content-Type: text/html; charset=UTF-8'); header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
$k = preg_replace('/[^a-z0-9_]/','',(string)($_GET['k'] ?? 'demo')) ?: 'demo';
?><!DOCTYPE html><html lang="<?= CH_LANGS[$L]['tag'] ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $h('b.title') ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--line2:#d6d9e4;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee}
*{box-sizing:border-box}html{overflow-x:hidden}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:640px;margin:26px auto 60px;padding:0 16px}.hd{text-align:center;margin:10px 0 22px}.hd .eb{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}.hd h1{font:600 clamp(26px,5vw,36px) Fraunces,Georgia,serif;margin:8px 0 4px}.hd p{color:var(--mute);margin:0}
.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.steps{display:flex;gap:6px;margin-bottom:8px}.stepno{font:600 12.5px Inter;color:var(--mute);margin:0 0 14px}.steps span{flex:1;height:5px;border-radius:5px;background:var(--line)}.steps span.on{background:var(--gold)}
h2{font:600 20px Fraunces,Georgia,serif;margin:0 0 12px}
.svc{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--line2);border-radius:14px;margin-bottom:8px;cursor:pointer;transition:.15s;background:#fff}.svc:hover{border-color:var(--gold)}.svc.on{border-color:var(--gold);background:var(--goldl)}.svc b{display:block;font-size:15px}.svc small{color:var(--mute)}.svc .pr{font:700 15px Inter;white-space:nowrap}
.days{display:flex;gap:8px;overflow:auto;padding-bottom:6px;margin-bottom:12px}.day{min-width:72px;padding:10px 8px;border:1px solid var(--line2);border-radius:14px;text-align:center;cursor:pointer;background:#fff}.day b{display:block;font-size:17px}.day small{color:var(--mute);font-size:11.5px}.day.on{background:var(--ink);color:#fff;border-color:var(--ink)}.day.on small{color:#c6c9d9}.day.off{opacity:.4;cursor:default}
.svc,.day,.slot{min-height:44px}.btn{min-height:44px}.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:8px}.slot{padding:10px 6px;border:1px solid var(--line2);border-radius:12px;text-align:center;font-weight:600;cursor:pointer;background:#fff}.slot:hover{border-color:var(--gold)}.slot.on{background:var(--gold);color:#1a1407;border-color:var(--gold)}
label{display:block;font:600 12.5px Inter;color:var(--mute);margin:12px 0 6px}input,textarea{width:100%;padding:12px 13px;border-radius:12px;border:1px solid var(--line2);font:inherit;outline:0}input:focus,textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.12)}textarea{min-height:70px;resize:vertical}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 18px;border-radius:12px;border:1px solid var(--line2);background:#fff;color:var(--ink);font:600 15px Inter;cursor:pointer;text-decoration:none}.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.gold{background:linear-gradient(135deg,#ecd08a,#c9972f);color:#1a1407;border-color:transparent}.btn:disabled{opacity:.5;cursor:not-allowed}
.row{display:flex;gap:10px;justify-content:space-between;margin-top:18px;flex-wrap:wrap}.sum{padding:16px;border-radius:16px;background:var(--goldl);margin:12px 0}.sum b{font-size:17px}.m{color:var(--mute);font-size:13px}
.msg{padding:10px 12px;border-radius:12px;font-size:14px;margin-top:12px}.err{background:var(--rosel);color:var(--rose)}.ok{background:var(--okl);color:#15703f}
.done{text-align:center;padding:10px 0}.done .big{width:64px;height:64px;border-radius:50%;background:var(--okl);color:var(--ok);display:grid;place-items:center;font-size:30px;margin:0 auto 12px}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:18px}.foot a{color:var(--gold)}
.chat{margin-top:16px;border:1px dashed var(--line2);border-radius:16px;padding:14px;background:#fff}.chat .t{font:700 13px Inter;color:var(--ink2)}.chat form{display:flex;gap:8px;margin-top:8px}.chat input{flex:1}.chat .ms{display:flex;flex-direction:column;gap:6px;margin-top:8px;max-height:220px;overflow:auto}.chat .ms div{padding:8px 11px;border-radius:12px;font-size:13.5px;max-width:92%}.chat .u{align-self:flex-end;background:var(--ink);color:#fff}.chat .a{align-self:flex-start;background:#f1f2f7}
</style></head><body><?= ch_lang_switcher($L) ?><div class="w">
<div class="hd"><div class="eb"><?= $h('b.eb') ?></div><h1 id="company">…</h1><p id="addr"></p></div>
<div class="c">
  <div class="steps" aria-hidden="true"><span class="on" id="s1"></span><span id="s2"></span><span id="s3"></span></div><p class="stepno" id="stepno" aria-live="polite"><?= $h('step',['n'=>1,'m'=>3]) ?></p>
  <div id="step1"><h2>1 · <?= $h('b.s1') ?></h2><div id="svcs"></div></div>
  <div id="step2" hidden><h2>2 · <?= $h('b.s2') ?></h2><div class="days" id="days"></div><div class="slots" id="slots"></div><div class="m" id="slotNote" style="margin-top:8px" aria-live="polite"></div></div>
  <div id="step3" hidden><h2>3 · <?= $h('b.s3') ?></h2><div class="sum" id="sum"></div><label for="fName"><?= $h('f.name') ?></label><input id="fName" autocomplete="name" placeholder="<?= $h('f.name_ph') ?>"><label for="fPhone"><?= $h('f.phone') ?></label><input id="fPhone" type="tel" autocomplete="tel" placeholder="+49 …"><label for="fEmail"><?= $h('b.email') ?></label><input id="fEmail" type="email" autocomplete="email" placeholder="<?= $h('f.email_ph') ?>"><label for="fNote"><?= $h('b.note') ?></label><textarea id="fNote" placeholder="<?= $h('b.note_ph') ?>"></textarea><div id="msg" role="alert" aria-live="polite"></div></div>
  <div id="step4" hidden class="done"><div class="big">✓</div><h2><?= $h('b.done') ?></h2><div class="sum" id="doneSum" style="text-align:left"></div><p class="m" id="doneNote"></p><div class="row" style="justify-content:center"><a class="btn p" id="icsBtn" href="#"><?= $h('cal') ?></a><a class="btn" id="manageBtn" href="#"><?= $h('b.manage') ?></a></div></div>
  <div class="row" id="nav"><button class="btn" id="back" hidden><?= $h('b.back') ?></button><span></span><button class="btn gold" id="next" disabled><?= $h('b.next') ?></button></div>
</div>
<div class="chat c" id="chat" hidden><div class="t"><?= $h('b.chat_t') ?></div><div class="ms" id="cms" aria-live="polite"></div><form id="cform"><input id="cin" placeholder="<?= $h('b.chat_ph') ?>" maxlength="500" aria-label="<?= $h('b.chat_t') ?>"><button class="btn p" type="submit"><?= $h('b.send') ?></button></form></div>
<div class="foot"><?= t('b.foot',$L,['link'=>'<a href="https://chat-help.com">ChatHelp</a>']) ?></div>
</div>
<script>
const K=<?= json_encode($k) ?>, API='/chat/appt-api.php?k='+encodeURIComponent(K), LANG=<?= json_encode($L) ?>, LOC=<?= json_encode(CH_LANGS[$L]['tag']) ?>; const $=s=>document.querySelector(s);
const T=<?= json_encode(ch_t_prefix('b.',$L)+['min'=>t('min',$L),'step'=>t('step',$L)],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>; const tt=(k,v)=>String(T[k]||k).replace(/\{(\w+)\}/g,(m,x)=>v&&v[x]!=null?v[x]:m);
let INFO=null, svc=null, date=null, time=null, step=1;
const eur=c=>c?(c/100).toLocaleString(LOC,{style:'currency',currency:'EUR'}):'';
const wd=dt=>dt.toLocaleDateString(LOC,{weekday:'short'});
async function j(u,o){const r=await fetch(u+(u.includes('?')?'&':'?')+'_='+Date.now(),o);return r.json();}
async function init(){INFO=await j(API+'&a=info');if(INFO.error){$('#company').textContent=INFO.company||tt('unavail');$('.c').innerHTML='<p>'+esc(LANG==='de'?(INFO.message||tt('na')):tt('na'))+'</p>';return;}
  $('#company').textContent=INFO.company;$('#addr').textContent=INFO.address||'';document.title=tt('title')+' · '+INFO.company;
  $('#svcs').innerHTML=INFO.services.map(s=>`<div class="svc" data-id="${s.id}" role="button" tabindex="0" aria-pressed="false"><div><b>${esc(s.name)}</b><small>${esc(String(T.min||'{n} Min').replace('{n}',s.duration_min))}</small></div><div class="pr">${eur(s.price_cents)}</div></div>`).join('')||'<p class="m">'+esc(tt('nosvc'))+'</p>';
  document.querySelectorAll('.svc').forEach(el=>{el.onclick=()=>{document.querySelectorAll('.svc').forEach(x=>{x.classList.remove('on');x.setAttribute('aria-pressed','false');});el.classList.add('on');el.setAttribute('aria-pressed','true');svc=INFO.services.find(s=>s.id==el.dataset.id);$('#next').disabled=false;};el.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();el.click();}};});
  if(INFO.chat){$('#chat').hidden=false;cmsg('a',tt('chat_hi'));}}
function esc(s){return String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function show(n){step=n;[1,2,3,4].forEach(i=>{$('#step'+i).hidden=i!==n;$('#s'+Math.min(i,3)).classList.toggle('on',i<=n);});$('#stepno').hidden=n===4;$('#stepno').textContent=tt('step',{n:Math.min(n,3),m:3});$('#back').hidden=n===1||n===4;$('#nav').hidden=n===4;$('#next').textContent=n===3?tt('book'):tt('next');$('#next').disabled=n===2?!(date&&time):n===3?false:!svc;}
async function loadDays(){const d=await j(API+'&a=days&service='+(svc?svc.id:''));const el=$('#days');el.innerHTML='';const list=d.days||[];if(!list.length){el.innerHTML='<p class="m">'+esc(tt('nodays'))+'</p>';return;}
  list.forEach((x,i)=>{const dt=new Date(x.date+'T12:00:00');const b=document.createElement('div');b.className='day';b.setAttribute('role','button');b.tabIndex=0;b.setAttribute('aria-label',dt.toLocaleDateString(LOC,{weekday:'long',day:'numeric',month:'long'}));b.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();b.click();}};b.innerHTML=`<small>${esc(wd(dt))}</small><b>${dt.getDate()}</b><small>${esc(dt.toLocaleDateString(LOC,{month:'short'}))}</small>`;b.onclick=()=>{document.querySelectorAll('.day').forEach(y=>y.classList.remove('on'));b.classList.add('on');date=x.date;time=null;loadSlots();};el.appendChild(b);if(i===0)b.click();});}
async function loadSlots(){const r=await j(API+'&a=slots&date='+date+'&service='+(svc?svc.id:''));const el=$('#slots');el.innerHTML='';(r.slots||[]).forEach(t=>{const b=document.createElement('div');b.className='slot';b.textContent=t;b.setAttribute('role','button');b.tabIndex=0;b.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();b.click();}};b.onclick=()=>{document.querySelectorAll('.slot').forEach(y=>y.classList.remove('on'));b.classList.add('on');time=t;$('#next').disabled=false;};el.appendChild(b);});
  $('#slotNote').textContent=r.closed?tt('closed'):(r.slots&&r.slots.length?tt('free',{n:r.slots.length})+(svc?' · '+String(T.min||'{n} Min').replace('{n}',svc.duration_min):''):tt('nofree'));$('#next').disabled=true;}
$('#next').onclick=async()=>{if(step===1){show(2);loadDays();}else if(step===2){show(3);$('#sum').innerHTML=`<b>${esc(svc.name)}</b><br>${esc(tt('at',{date:new Date(date+'T12:00:00').toLocaleDateString(LOC,{weekday:'long',day:'numeric',month:'long',year:'numeric'}),time:time}))} · ${esc(String(T.min||'{n} Min').replace('{n}',svc.duration_min))}${svc.price_cents?' · '+eur(svc.price_cents):''}`;}
  else if(step===3){const name=$('#fName').value.trim(),phone=$('#fPhone').value.trim(),email=$('#fEmail').value.trim();if(!name||(!phone&&!email)){$('#msg').innerHTML='<div class="msg err">'+esc(tt('need'))+'</div>';(name?$('#fPhone'):$('#fName')).focus();return;}$('#next').disabled=true;
    const r=await j(API+'&a=book',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({service_id:svc.id,start:date+' '+time,name,phone,email,note:$('#fNote').value,lang:LANG})});
    if(r.error){$('#msg').innerHTML='<div class="msg err">'+(r.error==='slot_unavailable'?esc(tt('taken')):(r.error==='unknown_service'||r.error==='service_required')?esc(tt('gone')):esc(LANG==='de'?(r.message||r.hint||r.error):tt('offline')))+'</div>';$('#next').disabled=false;if(r.error==='slot_unavailable'){show(2);loadSlots();}return;}
    const b=r.booking, when=LANG==='de'?esc(b.human)+' – '+esc(b.end)+' Uhr':esc(tt('at',{date:new Date(date+'T12:00:00').toLocaleDateString(LOC,{weekday:'long',day:'numeric',month:'long'}),time:time}))+' – '+esc(b.end);
    $('#doneSum').innerHTML=`<b>${esc(b.service)}</b><br>${when}<br><span class="m">${esc(tt('code',{code:b.code}))}${b.address?' · '+esc(b.address):''}</span>`;$('#doneNote').textContent=INFO.demo?tt('demo'):(email?tt('mailed',{email:email}):tt('keep'));$('#icsBtn').href='/chat/appt.php?c='+b.code+'&ics=1';$('#manageBtn').href=b.manage_url;show(4);}};
$('#back').onclick=()=>{if(step===2)show(1);else if(step===3)show(2);};
/* Chat-Buchung: Demo → concierge.php, echter Betrieb → appt-api.php?a=chat (Termin-Agent dieses Betriebs, Werkzeuge) */
function loadDaysIfOpen(){if(step===2&&svc){loadDays();if(date)loadSlots();}}
let hist=[];function cmsg(r,t){const d=document.createElement('div');d.className=r;d.textContent=t;$('#cms').appendChild(d);$('#cms').scrollTop=1e9;return d;}
$('#cform').onsubmit=async e=>{e.preventDefault();const t=$('#cin').value.trim();if(!t)return;$('#cin').value='';cmsg('u',t);hist.push({role:'user',content:t});const w=cmsg('a','…');
  try{const r=INFO.demo?await j('/chat/concierge.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({agent:'agent-termine',messages:hist.slice(-10),lang:LANG})})
      :await j(API+'&a=chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:hist.slice(-10),lang:LANG})});
    if(!INFO.demo&&r.reply)loadDaysIfOpen();w.textContent=r.reply||(LANG==='de'&&r.message)||tt('offline');if(r.reply)hist.push({role:'assistant',content:r.reply});}catch(x){w.textContent=<?= json_encode(t('netfail',$L),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG) ?>;}};
init();
</script></body></html>
