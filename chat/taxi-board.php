<?php
/**
 * ChatHelp — Dispositions-Board der Taxi-Zentrale (Großbildschirm/Tablet, ohne Login): taxi-board.php?t=<board_token>
 * Spalten: „Neu“ (Sofortfahrten + Vorbestellungen der nächsten 60 Min, darunter weitere Vorbestellungen), „Unterwegs“ (zugeteilt, Anfahrt,
 * Taxi ist da, Fahrt läuft) und „Fahrer“ (Status frei/besetzt/pause per Tipp wechseln). Fahrer zuteilen (freie, passende Fahrer zuerst, Vorschlag ★),
 * Status-Knöpfe, Storno. Lädt alle 10 s über ride-api.php?a=board, Ton + Hinweis bei neuer Fahrt, Uhr, Vollbild, Bildschirm bleibt an.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/ride-lib.php';
require_once __DIR__.'/pagever.php'; th_nocache(); ch_page_version(__FILE__);
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? ''));
$acc=null; if(strlen($t)===24){ th_bootstrap(); $acc=ride_account_by_board_token($t); }
header('Content-Type: text/html; charset=UTF-8');
if($acc===null){ http_response_code(404); ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Board-Link ungültig</title><meta name="robots" content="noindex">
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f1222;color:#fff;font:16px/1.55 Inter,system-ui,sans-serif;padding:16px}.b{max-width:460px;background:#171b33;border:1px solid #2a2f4a;border-radius:22px;padding:28px}h1{font-size:22px;margin:0 0 8px}p{color:#9aa0c0;margin:0}</style></head>
<body><div class="b"><h1>🚕 Board-Link ungültig</h1><p>Dieser Link zum Dispositions-Board ist nicht (mehr) gültig. Den aktuellen Link finden Sie im ChatHelp Studio unter <b style="color:#e3c27a">Taxi → Dispositions-Board</b>.</p></div></body></html>
<?php exit; }
$s=ride_settings($acc);
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zentrale · <?= htmlspecialchars($s['name'] ?: 'Taxi',ENT_QUOTES) ?></title><meta name="robots" content="noindex"><meta name="theme-color" content="#0f1222">
<style>
:root{--bg:#0f1222;--sur:#171b33;--sur2:#1d2240;--line:#2a2f4a;--mute:#9aa0c0;--gold:#e3c27a;--taxi:#f5c518;--ok:#1a9e5a;--okl:#163b2b;--rose:#e0486f;--rosel:#3a1a28;--indigo:#6d64ff;--teal:#19b3ad}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#fff;font:15px/1.45 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
button,select{font:inherit}
.hd{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 20px;border-bottom:1px solid var(--line);position:sticky;top:0;background:rgba(15,18,34,.94);backdrop-filter:blur(10px);z-index:3}
.hd .brand{display:flex;align-items:center;gap:12px}.hd .logo{width:38px;height:38px;border-radius:12px;background:var(--taxi);color:#111;display:grid;place-items:center;font-size:20px}.hd b{font-size:19px}.hd .st{color:var(--mute);font-size:12.5px}
.kpis{display:flex;gap:8px;flex-wrap:wrap}.kpi{padding:6px 12px;border-radius:12px;background:var(--sur);border:1px solid var(--line);font-size:12.5px;color:var(--mute)}.kpi b{color:#fff;font-size:15px;margin-right:4px}
.tools{display:flex;gap:8px;align-items:center}.pill{padding:7px 12px;border-radius:999px;background:var(--sur);border:1px solid var(--line);font-size:13px;cursor:pointer;color:#fff;user-select:none}.clock{font:800 22px Inter;letter-spacing:.02em;padding:4px 12px}
.cols{display:grid;grid-template-columns:1.15fr 1.15fr .85fr;gap:16px;padding:16px 20px 40px;align-items:start}@media(max-width:1180px){.cols{grid-template-columns:1fr 1fr}.col.drv{grid-column:1/-1}}@media(max-width:760px){.cols{grid-template-columns:1fr;padding:12px 16px 40px}.hd{padding:10px 16px}}
.col h2{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);margin:0 0 10px;display:flex;justify-content:space-between;align-items:center}.col h2 span{color:#fff;background:var(--line);border-radius:999px;padding:1px 10px}
.o{background:var(--sur);border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:12px;animation:in .3s}@keyframes in{from{opacity:0;transform:translateY(6px)}}
.o.new{border-color:var(--gold);box-shadow:0 0 0 2px rgba(227,194,122,.22)}.o.urgent{border-color:var(--rose);box-shadow:0 0 0 2px rgba(224,72,111,.3);animation:in .3s,glow 1.6s infinite}@keyframes glow{50%{box-shadow:0 0 0 5px rgba(224,72,111,.12)}}
.o.arrived{border-color:var(--ok)}.o .top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.o .code{font:800 20px Inter;letter-spacing:.04em}.o .age{margin-left:auto;color:var(--mute);font-size:12.5px;white-space:nowrap}.o.urgent .age{color:#ff9bb3;font-weight:700}
.bd{font:800 11px Inter;letter-spacing:.08em;text-transform:uppercase;padding:4px 9px;border-radius:999px;background:var(--line);color:#fff}.bd.asap{background:var(--taxi);color:#111}.bd.pre{background:#2b3bd9}.bd.st{background:var(--sur2);border:1px solid var(--line);color:var(--gold)}.bd.st.arrived{color:#7be3a8}.bd.st.onboard{color:#9fd8ff}
.adr{margin:10px 0 6px;display:grid;gap:6px}.adr div{display:flex;gap:8px;font-size:16px;font-weight:600;line-height:1.3}.adr small{display:block;color:var(--mute);font-weight:400;font-size:12.5px;margin-top:2px}.adr .warn{color:#ff9bb3}
.meta{color:#c6c9d9;font-size:13.5px;margin-top:4px}.meta a{color:#fff}.note{margin-top:8px;padding:8px 10px;border-radius:10px;background:#2a2340;color:#f3d9ff;font-size:13.5px}
.tot{display:flex;justify-content:space-between;gap:10px;margin-top:8px;font-weight:800;font-size:15.5px}.tot span:first-child{color:var(--mute);font-weight:600}
.drvline{margin-top:8px;padding:8px 10px;border-radius:12px;background:var(--sur2);font-size:13.5px;display:flex;gap:8px;align-items:center}.drvline b{color:var(--gold)}
.acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}.b{flex:1;min-width:110px;padding:11px 12px;border-radius:12px;border:0;font:700 14px Inter;cursor:pointer;color:#fff;background:var(--line)}.b:disabled{opacity:.5;cursor:wait}
.b.p{background:var(--indigo)}.b.ok{background:var(--ok)}.b.g{background:var(--taxi);color:#111}.b.x{flex:0 0 auto;min-width:0;background:transparent;border:1px solid var(--line);color:var(--mute)}
select{flex:2;min-width:150px;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0f1222;color:#fff;font:600 14px Inter}
.empty{color:var(--mute);text-align:center;padding:26px 10px;border:1px dashed var(--line);border-radius:18px;font-size:14px}
.pre{margin-top:18px}.pre h3{font:700 11.5px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);margin:0 0 8px}
.pr{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:14px;margin-bottom:8px;background:rgba(23,27,51,.6);flex-wrap:wrap}.pr .tm{font:800 15px Inter;color:var(--gold);min-width:92px}.pr .tx{flex:1;min-width:160px;font-size:13.5px}.pr .tx small{display:block;color:var(--mute)}
.pr select{flex:0 1 170px;min-width:120px;padding:7px 8px;font-size:13px}.pr .b{flex:0 0 auto;min-width:0;padding:8px 10px;font-size:13px}
.dr{display:flex;align-items:center;gap:10px;padding:11px 12px;border:1px solid var(--line);border-radius:14px;margin-bottom:8px;background:var(--sur)}.dr .nm{font-weight:700}.dr .vh{color:var(--mute);font-size:12.5px}.dr .job{font-size:12.5px;color:var(--gold);margin-top:2px}.dr>div{flex:1;min-width:0}
.sp{flex:0 0 auto;padding:7px 12px;border-radius:999px;font:800 12px Inter;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;border:0;min-width:86px}.sp.frei{background:var(--ok);color:#fff}.sp.besetzt{background:var(--rose);color:#fff}.sp.pause{background:#4a4f6e;color:#d7d9e8}
.dsum{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}.dsum span{font-size:12px;padding:3px 9px;border-radius:999px;background:var(--sur);border:1px solid var(--line);color:var(--mute)}.dsum b{color:#fff}
.toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);background:var(--taxi);color:#111;padding:13px 20px;border-radius:999px;font-weight:800;box-shadow:0 10px 30px rgba(0,0,0,.45);display:none;z-index:9;max-width:calc(100% - 32px);text-align:center}
.toast.err{background:var(--rose);color:#fff}
</style></head>
<body>
<div class="hd">
  <div class="brand"><div class="logo">🚕</div><div><b><?= htmlspecialchars($s['name'] ?: 'Taxi-Zentrale',ENT_QUOTES) ?></b><div class="st" id="st">verbinde …</div></div></div>
  <div class="kpis" id="kpis"></div>
  <div class="tools"><span class="pill" id="snd" title="Ton bei neuer Fahrt">🔔 Ton an</span><span class="pill" id="fs" title="Vollbild">⛶</span><span class="pill clock" id="clock">--:--</span></div>
</div>
<div class="cols">
  <div class="col"><h2>Neu <span id="n1">0</span></h2><div id="c1"></div><div class="pre" id="preWrap"><h3>Vorbestellt · später</h3><div id="c4"></div></div></div>
  <div class="col"><h2>Unterwegs <span id="n2">0</span></h2><div id="c2"></div></div>
  <div class="col drv"><h2>Fahrer <span id="n3">0</span></h2><div class="dsum" id="dsum"></div><div id="c3"></div></div>
</div>
<div class="toast" id="toast"></div>
<script>
(function(){
var T=<?= json_encode($t) ?>, API='/chat/ride-api.php';
var $=function(s){return document.querySelector(s);};
var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
var D={rides:[],drivers:[]}, seen=null, sel={}, pending=false, busy=false, skew=0, sound=true, lock=null;
try{sound=localStorage.getItem('tx_board_sound')!=='0';}catch(e){}
function setSnd(){$('#snd').textContent=sound?'🔔 Ton an':'🔕 Ton aus';}
setSnd();$('#snd').onclick=function(){sound=!sound;try{localStorage.setItem('tx_board_sound',sound?'1':'0');}catch(e){}setSnd();if(sound)beep();};
$('#fs').onclick=function(){try{if(document.fullscreenElement)document.exitFullscreen();else document.documentElement.requestFullscreen();}catch(e){}};
function wake(){try{if(navigator.wakeLock&&!lock)navigator.wakeLock.request('screen').then(function(l){lock=l;l.addEventListener('release',function(){lock=null;});}).catch(function(){});}catch(e){}}
document.addEventListener('visibilitychange',function(){if(!document.hidden){wake();load();}});wake();
function beep(){try{var a=new (window.AudioContext||window.webkitAudioContext)();[0,.2,.4].forEach(function(t,i){var o=a.createOscillator(),g=a.createGain();o.type='sine';o.frequency.value=i===2?1175:880;o.connect(g);g.connect(a.destination);g.gain.setValueAtTime(.0001,a.currentTime+t);g.gain.exponentialRampToValueAtTime(.45,a.currentTime+t+.02);g.gain.exponentialRampToValueAtTime(.0001,a.currentTime+t+.17);o.start(a.currentTime+t);o.stop(a.currentTime+t+.19);});}catch(e){}}
function toast(t,err){var x=$('#toast');x.textContent=t;x.className='toast'+(err?' err':'');x.style.display='block';clearTimeout(toast.h);toast.h=setTimeout(function(){x.style.display='none';},6000);}
function tick(){$('#clock').textContent=new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});document.querySelectorAll('[data-age]').forEach(function(el){var m=Math.max(0,Math.floor((Date.now()+skew-Date.parse(el.dataset.age))/60000));el.textContent=m<1?'gerade eben':'seit '+m+' Min';});}
setInterval(tick,1000);
var DS={frei:'frei',besetzt:'besetzt',pause:'Pause'}, NEXT={frei:'besetzt',besetzt:'pause',pause:'frei'}, CH={phone:'☎ Telefon',whatsapp:'WhatsApp',chat:'Chat',widget:'Website',studio:'Studio',hotel:'Hotel',demo:'Demo','phone-test':'Telefon-Test'};
function drvById(id){for(var i=0;i<D.drivers.length;i++)if(D.drivers[i].id===id)return D.drivers[i];return null;}
function driverSelect(r,cls){var order=(r.driver_order&&r.driver_order.length)?r.driver_order:D.drivers.map(function(d){return d.id;});var unfit=r.unfit_driver_ids||[];
  var cur=sel[r.id]!=null?sel[r.id]:(r.driver_id||r.suggest_driver_id||'');
  var opts=order.map(function(id){var d=drvById(id);if(!d)return '';var tag=(r.suggest_driver_id===id?'★ ':'')+d.name+' · '+(DS[d.status]||d.status)+(d.stand?' · '+d.stand:'')+(unfit.indexOf(id)>=0?' · ⚠ Fahrzeug':'');return '<option value="'+d.id+'"'+(String(cur)===String(d.id)?' selected':'')+'>'+esc(tag)+'</option>';}).join('');
  return '<select class="'+(cls||'')+'" data-sel="'+r.id+'">'+(opts||'<option value="">kein Fahrer angelegt</option>')+'</select>';}
function acts(r){var id=r.id, x='<button class="b x" data-act="cancelled" data-id="'+id+'" title="Stornieren">✕</button>';
  if(r.status==='new')return driverSelect(r)+'<button class="b g" data-act="assign" data-id="'+id+'">Zuteilen</button>'+x;
  if(r.status==='assigned')return '<button class="b p" data-act="en_route" data-id="'+id+'">▶ Anfahrt</button><button class="b x" data-act="new" data-id="'+id+'" title="Fahrer abziehen">↺</button>'+x;
  if(r.status==='en_route')return '<button class="b p" data-act="arrived" data-id="'+id+'">📍 Taxi ist da</button><button class="b x" data-act="new" data-id="'+id+'" title="Fahrer abziehen">↺</button>'+x;
  if(r.status==='arrived')return '<button class="b ok" data-act="onboard" data-id="'+id+'">👤 Kunde an Bord</button><button class="b x" data-act="no_show" data-id="'+id+'">Nicht da</button>';
  if(r.status==='onboard')return '<button class="b ok" data-act="completed" data-id="'+id+'">🏁 Fahrt beendet</button>';return '';}
function badge(r){if(r.asap)return '<span class="bd asap">Sofort</span>';var due=r.due_in_min;return '<span class="bd pre">'+esc(r.when)+'</span>'+(due!=null&&due<=60&&due>=0?'<span class="bd st">in '+due+' Min</span>':'');}
function card(r){var age=r.status==='new'&&r.asap&&r.created_iso?'<span class="age" data-age="'+esc(r.created_iso)+'"></span>':'';
  var waited=r.created_iso?(Date.now()+skew-Date.parse(r.created_iso))/60000:0, urgent=r.status==='new'&&((r.asap&&waited>=3)||(!r.asap&&r.due_in_min!=null&&r.due_in_min<=20));
  var cls='o '+(r.status==='new'?(urgent?'urgent':'new'):'')+(r.status==='arrived'?' arrived':'');
  var st=r.status!=='new'?'<span class="bd st '+esc(r.status)+'">'+esc(r.status_label)+'</span>':'';
  var dl=r.driver?'<div class="drvline">🚖 <b>'+esc(r.driver.name)+'</b> '+esc(r.driver.vehicle||'')+(r.driver.plate?' · '+esc(r.driver.plate):'')+'</div>':'';
  var extras=[r.passengers+' Pers.',r.vehicle_name].concat(r.extras?[r.extras]:[]).concat(r.luggage?['🧳 '+r.luggage]:[]);
  return '<div class="'+cls+'"><div class="top"><span class="code">'+esc(r.code)+'</span>'+badge(r)+st+age+'</div>'+
   '<div class="adr"><div>📍<span>'+esc(r.pickup)+(r.pickup_checked?(r.pickup_geo&&r.pickup_geo!==r.pickup?'<small>'+esc(r.pickup_geo)+'</small>':''):'<small class="warn">⚠ Adresse nicht auf der Karte gefunden</small>')+'</span></div>'+
   '<div>🏁<span>'+(r.destination?esc(r.destination)+(r.distance_text?'<small>'+esc(r.distance_text)+'</small>':''):'<span style="color:var(--mute);font-weight:400">Ziel sagt der Fahrgast</span>')+'</span></div></div>'+
   '<div class="meta">👥 '+esc(extras.join(' · '))+'</div>'+
   '<div class="meta">👤 '+esc(r.name)+(r.phone?' · <a href="tel:'+esc(r.phone.replace(/[^0-9+]/g,''))+'">'+esc(r.phone)+'</a>':'')+' · '+esc(CH[r.channel]||r.channel)+' · '+esc(r.created)+' Uhr</div>'+
   (r.note?'<div class="note">📝 '+esc(r.note)+'</div>':'')+dl+
   '<div class="tot"><span>'+esc(r.payment_label)+'</span><span>'+esc(r.price_text.replace(' (Richtpreis, es gilt der Taxameter)',' · Richtpreis'))+'</span></div><div class="acts">'+acts(r)+'</div></div>';}
function preRow(r){var d=r.driver?'<small>🚖 '+esc(r.driver.name)+(r.driver.plate?' · '+esc(r.driver.plate):'')+'</small>':'';
  var a=r.status==='new'?driverSelect(r)+'<button class="b g" data-act="assign" data-id="'+r.id+'">Zuteilen</button>':'<button class="b x" data-act="new" data-id="'+r.id+'" title="Fahrer abziehen">↺</button>';
  return '<div class="pr"><span class="tm">'+esc(r.when)+'</span><div class="tx"><b>'+esc(r.code)+'</b> · '+esc(r.pickup)+(r.destination?' → '+esc(r.destination):'')+'<small>'+esc(r.passengers+' Pers. · '+r.vehicle_name+(r.extras?' · '+r.extras:'')+' · '+r.name)+'</small>'+d+'</div>'+a+'<button class="b x" data-act="cancelled" data-id="'+r.id+'" title="Stornieren">✕</button></div>';}
function drvRow(d){var job=d.job?'<div class="job">'+esc(d.job.code)+' · '+esc(d.job.status_label)+(d.job.when&&d.job.when!=='sofort'?' · '+esc(d.job.when):'')+'</div>':'';
  return '<div class="dr"><div><div class="nm">'+esc(d.name)+'</div><div class="vh">'+esc([d.vehicle,d.plate,d.seats+' Plätze',d.stand?'Stand '+d.stand:''].filter(Boolean).join(' · '))+'</div>'+job+'</div><button class="sp '+esc(d.status)+'" data-drv="'+d.id+'" title="Tippen zum Wechseln">'+esc(DS[d.status]||d.status)+'</button></div>';}
function render(){if(document.activeElement&&document.activeElement.tagName==='SELECT'){pending=true;return;}pending=false;
  var R=D.rides||[], neu=[], go=[], later=[];
  R.forEach(function(r){var soon=r.asap||(r.due_in_min!=null&&r.due_in_min<=60);if(r.status==='new'&&soon)neu.push(r);else if(!soon&&(r.status==='new'||r.status==='assigned'))later.push(r);else go.push(r);});
  neu.sort(function(a,b){return (b.asap-a.asap)||String(a.pickup_at).localeCompare(String(b.pickup_at))||a.id-b.id;});
  var ord={arrived:0,en_route:1,onboard:2,assigned:3};go.sort(function(a,b){return (ord[a.status]-ord[b.status])||String(a.pickup_at).localeCompare(String(b.pickup_at));});
  later.sort(function(a,b){return String(a.pickup_at).localeCompare(String(b.pickup_at));});
  $('#c1').innerHTML=neu.map(card).join('')||'<div class="empty">Keine neuen Fahrten – alles disponiert ✓</div>';
  $('#c2').innerHTML=go.map(card).join('')||'<div class="empty">Gerade keine Fahrt unterwegs</div>';
  $('#c4').innerHTML=later.map(preRow).join('')||'<div class="empty" style="padding:14px">Keine weiteren Vorbestellungen</div>';
  var dr=(D.drivers||[]).slice().sort(function(a,b){var o={frei:0,besetzt:1,pause:2};return (o[a.status]-o[b.status])||a.name.localeCompare(b.name);});
  $('#c3').innerHTML=dr.map(drvRow).join('')||'<div class="empty">Noch keine Fahrer angelegt – im Studio unter Taxi → Fahrer.</div>';
  var cnt={frei:0,besetzt:0,pause:0};dr.forEach(function(d){cnt[d.status]=(cnt[d.status]||0)+1;});
  $('#dsum').innerHTML='<span><b>'+cnt.frei+'</b> frei</span><span><b>'+cnt.besetzt+'</b> besetzt</span><span><b>'+cnt.pause+'</b> Pause</span>';
  $('#n1').textContent=neu.length;$('#n2').textContent=go.length;$('#n3').textContent=dr.length;
  var k=D.stats||{};$('#kpis').innerHTML='<span class="kpi"><b>'+(k.today||0)+'</b>heute</span><span class="kpi"><b>'+(k.open||0)+'</b>offen</span><span class="kpi"><b>'+(k.prebooked||0)+'</b>vorbestellt</span><span class="kpi"><b>'+(k.completed_today||0)+'</b>erledigt</span><span class="kpi"><b>'+esc(k.revenue_today||'0,00 €')+'</b>Umsatz (Richtwert)</span>';
  tick();}
function load(){return fetch(API+'?a=board&t='+T+'&_='+Date.now(),{cache:'no-store'}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||j.error||r.status);return j;});}).then(function(j){
    D=j;if(j.server_iso)skew=Date.parse(j.server_iso)-Date.now();
    var ids={};(j.rides||[]).forEach(function(r){ids[r.id]=1;});
    if(seen){var fresh=(j.rides||[]).filter(function(r){return !seen[r.id]&&r.status==='new';});if(fresh.length){if(sound)beep();toast('🚕 Neue Fahrt '+fresh.map(function(f){return f.code+' · '+(f.asap?'sofort':f.when)+' · '+f.pickup;}).join(' | '));}}
    seen=ids;render();$('#st').textContent=(j.rides||[]).length+' aktiv · aktualisiert '+new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }).catch(function(e){$('#st').textContent='⚠ Verbindung: '+e.message+' – neuer Versuch in 10 s';});}
function post(a,body){return fetch(API+'?a='+a+'&t='+T,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.hint||j.message||j.error||r.status);return j;});});}
document.addEventListener('change',function(e){var s=e.target.closest('select[data-sel]');if(s)sel[s.dataset.sel]=s.value;});
document.addEventListener('focusout',function(e){if(e.target.tagName==='SELECT')setTimeout(function(){if(pending)render();},50);});
document.addEventListener('click',function(e){var b=e.target.closest('[data-act]'),p=e.target.closest('[data-drv]');
  if(b&&!busy){var id=+b.dataset.id,act=b.dataset.act,body={id:id,status:act};
    if(act==='assign'){var s=document.querySelector('select[data-sel="'+id+'"]');if(!s||!s.value){toast('Bitte zuerst einen Fahrer anlegen bzw. wählen.',true);return;}body.status='assigned';body.driver_id=+s.value;}
    if(act==='cancelled'&&!confirm('Fahrt wirklich stornieren?'))return;if(act==='no_show'&&!confirm('Kunde nicht angetroffen – Fahrt abschließen?'))return;if(act==='new'&&!confirm('Fahrer von dieser Fahrt abziehen?'))return;
    busy=true;b.disabled=true;post('status',body).then(function(j){delete sel[id];if(j.warnings&&j.warnings.length)toast('⚠ '+j.warnings.join(' '),true);return load();}).catch(function(err){toast('Fehler: '+err.message,true);b.disabled=false;}).then(function(){busy=false;});}
  if(p&&!busy){var d=drvById(+p.dataset.drv);if(!d)return;busy=true;post('driver',{driver_id:d.id,status:NEXT[d.status]||'frei'}).then(load).catch(function(err){toast('Fehler: '+err.message,true);}).then(function(){busy=false;});}});
load();setInterval(function(){if(!busy)load();},10000);
})();
</script></body></html>
