<?php
/**
 * ChatHelp — Küchen-Board (Tablet, ohne Login): board.php?t=<board_token>
 * Offene Bestellungen live (alle 15 s), Status mit einem Tipp (Bestätigen → Zubereitung → Fertig → Unterwegs → Zugestellt),
 * Fahrer zuweisen (Fahrer bekommt WhatsApp/SMS mit Route), Ton bei neuer Bestellung. Daten: order-api.php?a=board|status.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/pagever.php'; th_nocache(); ch_page_version(__FILE__);
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? ''));
$acc=null; if(strlen($t)===24){ th_bootstrap(); ord_bootstrap(); $s=th_db()->prepare("SELECT account_id FROM order_settings WHERE board_token=?"); $s->execute([$t]); $a=$s->fetchColumn(); if($a!==false) $acc=(int)$a; }
if($acc===null){ header('Content-Type: text/plain; charset=UTF-8'); http_response_code(404); exit("Board-Link ungültig. Im Studio → Bestellungen → Küchen-Board findest du den aktuellen Link.\n"); }
$os=ord_settings($acc);
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Küche · <?= htmlspecialchars($os['name'] ?: 'Bestellungen') ?></title><meta name="robots" content="noindex">
<style>
:root{--bg:#0f1222;--sur:#171b33;--line:#2a2f4a;--mute:#9aa0c0;--gold:#e3c27a;--ok:#1a9e5a;--rose:#e0486f;--indigo:#6d64ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#fff;font:15px/1.45 Inter,system-ui,sans-serif}
.hd{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--line);position:sticky;top:0;background:rgba(15,18,34,.92);backdrop-filter:blur(10px);z-index:2}.hd b{font-size:19px}.hd .st{color:var(--mute);font-size:13px}.hd .pill{padding:6px 12px;border-radius:999px;background:var(--sur);border:1px solid var(--line);font-size:13px}
.cols{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:16px 20px}@media(max-width:1000px){.cols{grid-template-columns:1fr}}
.col h2{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);margin:0 0 10px;display:flex;justify-content:space-between}.col h2 span{color:#fff;background:var(--line);border-radius:999px;padding:1px 9px}
.o{background:var(--sur);border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:12px;animation:in .3s}.o.new{border-color:var(--gold);box-shadow:0 0 0 2px rgba(227,194,122,.25)}@keyframes in{from{opacity:0;transform:translateY(6px)}}
.o .top{display:flex;justify-content:space-between;align-items:center;gap:8px}.o .code{font:800 20px Inter}.o .time{color:var(--gold);font-weight:700}.o .typ{font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;padding:3px 8px;border-radius:999px;background:var(--line)}.o .typ.del{background:#2b3bd9}
.o .items{margin:10px 0 6px;font-size:16px;line-height:1.5}.o .items div{display:flex;gap:8px}.o .items b{min-width:30px;color:var(--gold)}.o .opt{color:var(--mute);font-size:13px}
.o .meta{color:#c6c9d9;font-size:13.5px;margin-top:4px}.o .note{margin-top:6px;padding:8px 10px;border-radius:10px;background:#2a2340;color:#f3d9ff;font-size:13.5px}.o .tot{display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:16px}
.acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}.b{flex:1;padding:12px;border-radius:12px;border:0;font:700 14px Inter;cursor:pointer;color:#fff;background:var(--line)}.b.p{background:var(--indigo)}.b.ok{background:var(--ok)}.b.x{flex:0 0 auto;background:transparent;border:1px solid var(--line);color:var(--mute)}
select{padding:10px;border-radius:12px;border:1px solid var(--line);background:#0f1222;color:#fff;font:600 14px Inter}
.empty{color:var(--mute);text-align:center;padding:30px 10px;border:1px dashed var(--line);border-radius:18px}
.toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);background:var(--gold);color:#1a1407;padding:12px 18px;border-radius:999px;font-weight:800;box-shadow:0 10px 30px rgba(0,0,0,.4);display:none}
</style></head>
<body>
<div class="hd"><div><b>🍕 <?= htmlspecialchars($os['name'] ?: 'Küche') ?></b><div class="st" id="st">verbinde …</div></div><div style="display:flex;gap:8px;align-items:center"><span class="pill" id="snd" title="Ton bei neuer Bestellung" style="cursor:pointer">🔔 Ton an</span><span class="pill" id="clock"></span></div></div>
<div class="cols">
  <div class="col"><h2>Neu <span id="n1">0</span></h2><div id="c1"></div></div>
  <div class="col"><h2>In Zubereitung <span id="n2">0</span></h2><div id="c2"></div></div>
  <div class="col"><h2>Fertig · Unterwegs <span id="n3">0</span></h2><div id="c3"></div></div>
</div>
<div class="toast" id="toast">Neue Bestellung!</div>
<script>
const T=<?= json_encode($t) ?>, API='/chat/order-api.php'; const $=s=>document.querySelector(s);
let seen=null, drivers=[], sound=localStorage.getItem('kb_sound')!=='0'; const mine=new Set();   // mine: hier am Board selbst stornierte Bestellungen (kein Storno-Hinweis)
const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
$('#snd').textContent=sound?'🔔 Ton an':'🔕 Ton aus';$('#snd').onclick=()=>{sound=!sound;localStorage.setItem('kb_sound',sound?'1':'0');$('#snd').textContent=sound?'🔔 Ton an':'🔕 Ton aus';if(sound)beep();};
function beep(){try{const a=new (window.AudioContext||window.webkitAudioContext)();[0,.18,.36].forEach(t=>{const o=a.createOscillator(),g=a.createGain();o.type='sine';o.frequency.value=880;o.connect(g);g.connect(a.destination);g.gain.setValueAtTime(.0001,a.currentTime+t);g.gain.exponentialRampToValueAtTime(.4,a.currentTime+t+.02);g.gain.exponentialRampToValueAtTime(.0001,a.currentTime+t+.16);o.start(a.currentTime+t);o.stop(a.currentTime+t+.18);});}catch(e){}}
setInterval(()=>$('#clock').textContent=new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}),1000);
function card(o){const del=o.type==='delivery';
  const items=(o.items||[]).map(i=>`<div><b>${i.qty}×</b><span>${esc(i.name)}${i.options&&i.options.length?`<div class="opt">${esc(i.options.join(', '))}</div>`:''}${i.note?`<div class="opt">📝 ${esc(i.note)}</div>`:''}</span></div>`).join('');
  let acts='';
  if(o.status==='new')acts=`<button class="b p" onclick="st(${o.id},'confirmed')">✓ Bestätigen</button><button class="b x" onclick="if(confirm('Bestellung ${o.code} stornieren?'))st(${o.id},'cancelled')">✕</button>`;
  else if(o.status==='confirmed')acts=`<button class="b p" onclick="st(${o.id},'preparing')">👨‍🍳 In Zubereitung</button><button class="b x" onclick="if(confirm('Stornieren?'))st(${o.id},'cancelled')">✕</button>`;
  else if(o.status==='preparing')acts=`<button class="b p" onclick="st(${o.id},'ready')">✓ Fertig</button>`;
  else if(o.status==='ready'&&del)acts=`<select id="d${o.id}">${drivers.map(d=>`<option value="${d.id}" ${o.driver&&o.driver.name===d.name?'selected':''}>${esc(d.name)}</option>`).join('')||'<option value="">kein Fahrer</option>'}</select><button class="b p" onclick="st(${o.id},'out_for_delivery',document.getElementById('d${o.id}').value)">🚗 Unterwegs</button>`;
  else if(o.status==='ready')acts=`<button class="b ok" onclick="st(${o.id},'delivered')">🛍️ Abgeholt</button>`;
  else if(o.status==='out_for_delivery')acts=`<button class="b ok" onclick="st(${o.id},'delivered')">✅ Zugestellt</button>`;
  return `<div class="o ${o.status==='new'?'new':''}"><div class="top"><span class="code">${esc(o.code)}</span><span class="typ ${del?'del':''}">${del?'Lieferung':'Abholung'}</span><span class="time">⏱ ${o.eta_day?esc(o.eta_day)+' ':''}${esc(o.eta||'')}</span></div>
   <div class="items">${items}</div>
   <div class="meta">👤 ${esc(o.name)} · <a href="tel:${esc(o.phone)}" style="color:#fff">${esc(o.phone)}</a>${del?`<br>📍 ${esc(o.address)}${o.zone?' · '+esc(o.zone):''}${o.distance_km!=null?' · '+o.distance_km+' km':''}`:''}${o.driver?`<br>🚗 ${esc(o.driver.name)}`:''} · ${esc(o.channel)} · ${esc(o.created)} Uhr</div>
   ${o.note?`<div class="note">📝 ${esc(o.note)}</div>`:''}
   <div class="tot"><span>${esc(o.payment)}${o.payment==='bar'?' · kassieren':''}</span><span>${esc(o.total)}</span></div><div class="acts">${acts}</div></div>`;}
async function load(){try{const r=await fetch(`${API}?a=board&t=${T}&_=${Date.now()}`,{cache:'no-store'});const j=await r.json();if(!r.ok)throw new Error(j.error||r.status);drivers=j.drivers||[];
  const o=j.orders||[];const c1=o.filter(x=>x.status==='new'||x.status==='confirmed'),c2=o.filter(x=>x.status==='preparing'),c3=o.filter(x=>x.status==='ready'||x.status==='out_for_delivery');
  $('#c1').innerHTML=c1.map(card).join('')||'<div class="empty">Keine neuen Bestellungen</div>';$('#c2').innerHTML=c2.map(card).join('')||'<div class="empty">–</div>';$('#c3').innerHTML=c3.map(card).join('')||'<div class="empty">–</div>';
  $('#n1').textContent=c1.length;$('#n2').textContent=c2.length;$('#n3').textContent=c3.length;
  const ids=new Set(o.map(x=>x.id));if(seen){const fresh=o.filter(x=>!seen.has(x.id)&&x.status==='new'),gone=(j.cancelled||[]).filter(c=>seen.has(c.id)&&!ids.has(c.id)&&!mine.has(c.id));   // offene Bestellung inzwischen storniert (Kunde/Studio) → Hinweis statt stillem Verschwinden
    const msg=[fresh.length?`Neue Bestellung ${fresh.map(f=>f.code).join(', ')}!`:'',gone.length?`Storniert: ${gone.map(c=>c.code).join(', ')}`:''].filter(Boolean).join(' · ');
    if(msg){if(sound)beep();const t=$('#toast');t.textContent=msg;t.style.background=gone.length?'var(--rose)':'';t.style.color=gone.length?'#fff':'';t.style.display='block';clearTimeout(t._h);t._h=setTimeout(()=>t.style.display='none',gone.length?15000:6000);}}seen=ids;
  $('#st').textContent=`${o.length} offen · aktualisiert ${new Date().toLocaleTimeString('de-DE')}`;}catch(e){$('#st').textContent='⚠ '+e.message;}}
async function st(id,status,driver_id){if(status==='cancelled')mine.add(id);try{const r=await fetch(`${API}?a=status&t=${T}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,status,driver_id:driver_id||null})});const j=await r.json();if(!r.ok)throw new Error(j.hint||j.error||r.status);load();}catch(e){alert('Fehler: '+e.message);load();}}
load();setInterval(load,15000);
</script></body></html>
