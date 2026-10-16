<?php
/** ChatHelp — Öffentliche Buchungsseite eines Betriebs: book.php?k=<Buchungsschlüssel>  (k=demo → Salon Luna) */
header('Content-Type: text/html; charset=UTF-8'); header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
$k = preg_replace('/[^a-z0-9_]/','',(string)($_GET['k'] ?? 'demo')) ?: 'demo';
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Termin buchen</title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--line2:#d6d9e4;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee}
*{box-sizing:border-box}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:640px;margin:26px auto 60px;padding:0 16px}.hd{text-align:center;margin:10px 0 22px}.hd .eb{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}.hd h1{font:600 clamp(26px,5vw,36px) Fraunces,Georgia,serif;margin:8px 0 4px}.hd p{color:var(--mute);margin:0}
.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.steps{display:flex;gap:6px;margin-bottom:18px}.steps span{flex:1;height:5px;border-radius:5px;background:var(--line)}.steps span.on{background:var(--gold)}
h2{font:600 20px Fraunces,Georgia,serif;margin:0 0 12px}
.svc{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--line2);border-radius:14px;margin-bottom:8px;cursor:pointer;transition:.15s;background:#fff}.svc:hover{border-color:var(--gold)}.svc.on{border-color:var(--gold);background:var(--goldl)}.svc b{display:block;font-size:15px}.svc small{color:var(--mute)}.svc .pr{font:700 15px Inter;white-space:nowrap}
.days{display:flex;gap:8px;overflow:auto;padding-bottom:6px;margin-bottom:12px}.day{min-width:72px;padding:10px 8px;border:1px solid var(--line2);border-radius:14px;text-align:center;cursor:pointer;background:#fff}.day b{display:block;font-size:17px}.day small{color:var(--mute);font-size:11.5px}.day.on{background:var(--ink);color:#fff;border-color:var(--ink)}.day.on small{color:#c6c9d9}.day.off{opacity:.4;cursor:default}
.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:8px}.slot{padding:10px 6px;border:1px solid var(--line2);border-radius:12px;text-align:center;font-weight:600;cursor:pointer;background:#fff}.slot:hover{border-color:var(--gold)}.slot.on{background:var(--gold);color:#1a1407;border-color:var(--gold)}
label{display:block;font:600 12.5px Inter;color:var(--mute);margin:12px 0 6px}input,textarea{width:100%;padding:12px 13px;border-radius:12px;border:1px solid var(--line2);font:inherit;outline:0}input:focus,textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(184,134,43,.12)}textarea{min-height:70px;resize:vertical}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 18px;border-radius:12px;border:1px solid var(--line2);background:#fff;color:var(--ink);font:600 15px Inter;cursor:pointer;text-decoration:none}.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.gold{background:linear-gradient(135deg,#ecd08a,#c9972f);color:#1a1407;border-color:transparent}.btn:disabled{opacity:.5;cursor:not-allowed}
.row{display:flex;gap:10px;justify-content:space-between;margin-top:18px;flex-wrap:wrap}.sum{padding:16px;border-radius:16px;background:var(--goldl);margin:12px 0}.sum b{font-size:17px}.m{color:var(--mute);font-size:13px}
.msg{padding:10px 12px;border-radius:12px;font-size:14px;margin-top:12px}.err{background:var(--rosel);color:var(--rose)}.ok{background:var(--okl);color:#15703f}
.done{text-align:center;padding:10px 0}.done .big{width:64px;height:64px;border-radius:50%;background:var(--okl);color:var(--ok);display:grid;place-items:center;font-size:30px;margin:0 auto 12px}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:18px}.foot a{color:var(--gold)}
.chat{margin-top:16px;border:1px dashed var(--line2);border-radius:16px;padding:14px;background:#fff}.chat .t{font:700 13px Inter;color:var(--ink2)}.chat form{display:flex;gap:8px;margin-top:8px}.chat input{flex:1}.chat .ms{display:flex;flex-direction:column;gap:6px;margin-top:8px;max-height:220px;overflow:auto}.chat .ms div{padding:8px 11px;border-radius:12px;font-size:13.5px;max-width:92%}.chat .u{align-self:flex-end;background:var(--ink);color:#fff}.chat .a{align-self:flex-start;background:#f1f2f7}
</style></head><body><div class="w">
<div class="hd"><div class="eb">Online-Termin</div><h1 id="company">…</h1><p id="addr"></p></div>
<div class="c">
  <div class="steps"><span class="on" id="s1"></span><span id="s2"></span><span id="s3"></span></div>
  <div id="step1"><h2>1 · Leistung wählen</h2><div id="svcs"></div></div>
  <div id="step2" hidden><h2>2 · Tag &amp; Uhrzeit</h2><div class="days" id="days"></div><div class="slots" id="slots"></div><div class="m" id="slotNote" style="margin-top:8px"></div></div>
  <div id="step3" hidden><h2>3 · Ihre Daten</h2><div class="sum" id="sum"></div><label>Name</label><input id="fName" autocomplete="name" placeholder="Vor- und Nachname"><label>Telefon</label><input id="fPhone" type="tel" autocomplete="tel" placeholder="+49 …"><label>E-Mail (für die Bestätigung)</label><input id="fEmail" type="email" autocomplete="email" placeholder="name@beispiel.de"><label>Hinweis (optional)</label><textarea id="fNote" placeholder="z. B. Wunsch-Mitarbeiter, Allergien, Rückfragen"></textarea><div id="msg"></div></div>
  <div id="step4" hidden class="done"><div class="big">✓</div><h2>Termin bestätigt</h2><div class="sum" id="doneSum" style="text-align:left"></div><p class="m" id="doneNote"></p><div class="row" style="justify-content:center"><a class="btn p" id="icsBtn" href="#">📅 In den Kalender</a><a class="btn" id="manageBtn" href="#">Termin verwalten</a></div></div>
  <div class="row" id="nav"><button class="btn" id="back" hidden>← Zurück</button><span></span><button class="btn gold" id="next" disabled>Weiter →</button></div>
</div>
<div class="chat c" id="chat" hidden><div class="t">Lieber im Chat buchen? Frag die Assistentin.</div><div class="ms" id="cms"></div><form id="cform"><input id="cin" placeholder="z. B. „Donnerstag nachmittags Herrenschnitt“" maxlength="500"><button class="btn p" type="submit">Senden</button></form></div>
<div class="foot">Buchung über <a href="https://chat-help.com">ChatHelp</a> · Ihre Daten werden nur für diesen Termin verwendet.</div>
</div>
<script>
const K=<?= json_encode($k) ?>, API='/chat/appt-api.php?k='+encodeURIComponent(K); const $=s=>document.querySelector(s);
let INFO=null, svc=null, date=null, time=null, step=1;
const eur=c=>c?(c/100).toLocaleString('de-DE',{style:'currency',currency:'EUR'}):'';
const WD=['So','Mo','Di','Mi','Do','Fr','Sa'];
async function j(u,o){const r=await fetch(u+(u.includes('?')?'&':'?')+'_='+Date.now(),o);return r.json();}
async function init(){INFO=await j(API+'&a=info');if(INFO.error){$('#company').textContent=INFO.company||'Buchung nicht verfügbar';$('.c').innerHTML='<p>'+(INFO.message||'Nicht verfügbar.')+'</p>';return;}
  $('#company').textContent=INFO.company;$('#addr').textContent=INFO.address||'';document.title='Termin buchen · '+INFO.company;
  $('#svcs').innerHTML=INFO.services.map(s=>`<div class="svc" data-id="${s.id}"><div><b>${esc(s.name)}</b><small>${s.duration_min} Min</small></div><div class="pr">${eur(s.price_cents)}</div></div>`).join('')||'<p class="m">Noch keine Leistungen hinterlegt – bitte Termin per Chat anfragen.</p>';
  document.querySelectorAll('.svc').forEach(el=>el.onclick=()=>{document.querySelectorAll('.svc').forEach(x=>x.classList.remove('on'));el.classList.add('on');svc=INFO.services.find(s=>s.id==el.dataset.id);$('#next').disabled=false;});
  if(INFO.demo||INFO.agent_key){$('#chat').hidden=false;cmsg('a','Hallo! Ich helfe gern beim Termin. Welche Leistung und wann passt es dir?');}}
function esc(s){return String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function show(n){step=n;[1,2,3,4].forEach(i=>{$('#step'+i).hidden=i!==n;$('#s'+Math.min(i,3)).classList.toggle('on',i<=n);});$('#back').hidden=n===1||n===4;$('#nav').hidden=n===4;$('#next').textContent=n===3?'Verbindlich buchen':'Weiter →';$('#next').disabled=n===2?!(date&&time):n===3?false:!svc;}
async function loadDays(){const d=await j(API+'&a=days&service='+(svc?svc.id:''));const el=$('#days');el.innerHTML='';const list=d.days||[];if(!list.length){el.innerHTML='<p class="m">In den nächsten 14 Tagen sind keine freien Zeiten – bitte per Chat anfragen.</p>';return;}
  list.forEach((x,i)=>{const dt=new Date(x.date+'T12:00:00');const b=document.createElement('div');b.className='day';b.innerHTML=`<small>${WD[dt.getDay()]}</small><b>${dt.getDate()}</b><small>${dt.toLocaleDateString('de-DE',{month:'short'})}</small>`;b.onclick=()=>{document.querySelectorAll('.day').forEach(y=>y.classList.remove('on'));b.classList.add('on');date=x.date;time=null;loadSlots();};el.appendChild(b);if(i===0)b.click();});}
async function loadSlots(){const r=await j(API+'&a=slots&date='+date+'&service='+(svc?svc.id:''));const el=$('#slots');el.innerHTML='';(r.slots||[]).forEach(t=>{const b=document.createElement('div');b.className='slot';b.textContent=t;b.onclick=()=>{document.querySelectorAll('.slot').forEach(y=>y.classList.remove('on'));b.classList.add('on');time=t;$('#next').disabled=false;};el.appendChild(b);});
  $('#slotNote').textContent=r.closed?'An diesem Tag geschlossen.':(r.slots&&r.slots.length?`${r.slots.length} freie Zeiten · ${svc?svc.duration_min+' Min':''}`:'Keine freien Zeiten an diesem Tag.');$('#next').disabled=true;}
$('#next').onclick=async()=>{if(step===1){show(2);loadDays();}else if(step===2){show(3);$('#sum').innerHTML=`<b>${esc(svc.name)}</b><br>${new Date(date+'T12:00:00').toLocaleDateString('de-DE',{weekday:'long',day:'2-digit',month:'long',year:'numeric'})} um ${time} Uhr · ${svc.duration_min} Min${svc.price_cents?' · '+eur(svc.price_cents):''}`;}
  else if(step===3){const name=$('#fName').value.trim(),phone=$('#fPhone').value.trim(),email=$('#fEmail').value.trim();if(!name||(!phone&&!email)){$('#msg').innerHTML='<div class="msg err">Bitte Name und Telefon oder E-Mail angeben.</div>';return;}$('#next').disabled=true;
    const r=await j(API+'&a=book',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({service_id:svc.id,start:date+' '+time,name,phone,email,note:$('#fNote').value})});
    if(r.error){$('#msg').innerHTML='<div class="msg err">'+(r.error==='slot_unavailable'?'Diese Zeit wurde gerade vergeben – bitte eine andere wählen.':esc(r.message||r.hint||r.error))+'</div>';$('#next').disabled=false;if(r.error==='slot_unavailable'){show(2);loadSlots();}return;}
    const b=r.booking;$('#doneSum').innerHTML=`<b>${esc(b.service)}</b><br>${esc(b.human)} – ${esc(b.end)} Uhr<br><span class="m">Buchungscode ${b.code}${b.address?' · '+esc(b.address):''}</span>`;$('#doneNote').textContent=INFO.demo?'Demo: Im echten Betrieb erhalten Sie jetzt eine Bestätigung per E-Mail und eine Erinnerung am Vortag.':(email?'Eine Bestätigung ist unterwegs an '+email+'. Erinnerung kommt am Vortag.':'Bitte notieren Sie Ihren Buchungscode.');$('#icsBtn').href='/chat/appt.php?c='+b.code+'&ics=1';$('#manageBtn').href=b.manage_url;show(4);}};
$('#back').onclick=()=>{if(step===2)show(1);else if(step===3)show(2);};
/* Chat-Buchung über die Demo-/Agent-API (Termin-Agent mit Werkzeugen) */
let hist=[];function cmsg(r,t){const d=document.createElement('div');d.className=r;d.textContent=t;$('#cms').appendChild(d);$('#cms').scrollTop=1e9;return d;}
$('#cform').onsubmit=async e=>{e.preventDefault();const t=$('#cin').value.trim();if(!t)return;$('#cin').value='';cmsg('u',t);hist.push({role:'user',content:t});const w=cmsg('a','…');
  try{const r=await j('/chat/concierge.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({agent:'agent-termine',messages:hist.slice(-10),lang:'de'})});w.textContent=r.reply||'Gerade nicht erreichbar.';if(r.reply)hist.push({role:'assistant',content:r.reply});}catch(x){w.textContent='Verbindungsfehler.';}};
init();
</script></body></html>
