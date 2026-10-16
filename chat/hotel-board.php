<?php
/**
 * ChatHelp — Rezeptions-Board für Hotels (Tablet/Großbildschirm, ohne Login): hotel-board.php?t=<board_token>
 * Gästewünsche live (Housekeeping, Weckruf, Taxi, Late Check-out …) mit Ton + Hinweis bei neuen und fälligen Wünschen,
 * Anreisen heute (Check-in mit Zimmernummer), Abreisen (Check-out), Gäste im Haus, Buchungsanfragen (bestätigen/ablehnen).
 * Daten: hotel-api.php?a=board|request|booking (alle 10 s, mit Cache-Buster). Dunkel oder hell umschaltbar. t=demo → Live-Demo.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/hotel-lib.php';
th_nocache();
$t=is_string($_GET['t'] ?? null) ? $_GET['t'] : ''; $t=$t==='demo' ? 'demo' : preg_replace('/[^a-f0-9]/','',$t);
th_bootstrap(); hotel_bootstrap();
$acc=($t==='demo' || strlen($t)===24) ? hotel_account_by_board($t) : null;
if($acc===null){
    http_response_code(404); header('Content-Type: text/html; charset=UTF-8');
    exit('<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Board-Link ungültig</title><meta name="robots" content="noindex"><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0c0f1d;color:#f4f5fb;font:16px/1.55 Inter,system-ui,sans-serif}div{max-width:460px;padding:30px;border:1px solid #2a3050;border-radius:22px;background:#161b33;margin:16px}b{font-size:20px}p{color:#c6c9d9}</style></head><body><div><b>🛎 Board-Link ungültig</b><p>Dieser Link ist unvollständig oder wurde erneuert. Den aktuellen Link finden Sie im ChatHelp Studio unter <b>Hotel-Rezeption → Rezeptions-Board</b>.</p></div></body></html>');
}
th_fresh();
$s=hotel_settings($acc); $name=$s['name'] ?: 'Rezeption';
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de" data-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Rezeption · <?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></title><meta name="robots" content="noindex"><meta name="theme-color" content="#0c0f1d">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<script>try{var th=localStorage.getItem('hb_theme');if(th==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}</script>
<style>
:root{--bg:#0b0e1b;--bgg:radial-gradient(1200px 600px at 85% -10%,rgba(227,194,122,.10),transparent 60%),radial-gradient(900px 500px at -10% 110%,rgba(139,132,255,.10),transparent 60%);--sur:#151a31;--sur2:#1c2240;--line:#283050;--ink:#f4f5fb;--ink2:#c9ccdc;--mute:#8e94b4;
  --gold:#e3c27a;--goldd:#1a1407;--ok:#2bbf74;--okl:rgba(43,191,116,.15);--rose:#f0618a;--rosel:rgba(240,97,138,.15);--ind:#9a93ff;--indl:rgba(154,147,255,.15);--teal:#38c7c2;--teall:rgba(56,199,194,.14);--sh:0 12px 34px rgba(0,0,0,.35);--hd:rgba(11,14,27,.86)}
:root[data-theme=light]{--bg:#f3f4f8;--bgg:radial-gradient(1200px 600px at 85% -10%,rgba(184,134,43,.08),transparent 60%);--sur:#fff;--sur2:#f1f2f7;--line:#e2e5ee;--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;
  --gold:#b8862b;--goldd:#fff;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#d9336b;--rosel:#fde7ee;--ind:#4f46e5;--indl:#eceafe;--teal:#0f9f9c;--teall:#e2f6f4;--sh:0 1px 2px rgba(15,18,34,.05),0 10px 28px rgba(15,18,34,.07);--hd:rgba(255,255,255,.88)}
*{box-sizing:border-box}html,body{margin:0}body{min-height:100vh;background:var(--bgg),var(--bg);background-attachment:fixed;color:var(--ink);font:15px/1.45 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
button,input{font:inherit;color:inherit}a{color:inherit}
.hd{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;background:var(--hd);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
.brand{display:flex;align-items:center;gap:12px;min-width:0}.mk{flex:0 0 40px;height:40px;border-radius:13px;display:grid;place-items:center;font-size:20px;background:linear-gradient(135deg,#f3dfa6,#c9972f);box-shadow:0 6px 18px rgba(201,151,47,.35)}
.brand b{display:block;font:600 19px/1.2 Fraunces,Georgia,serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.brand .st{color:var(--mute);font-size:12.5px;display:flex;align-items:center;gap:6px}
.dot{width:8px;height:8px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 0 rgba(43,191,116,.6);animation:pl 2s infinite}.dot.off{background:var(--rose);animation:none}@keyframes pl{70%{box-shadow:0 0 0 8px rgba(43,191,116,0)}100%{box-shadow:0 0 0 0 rgba(43,191,116,0)}}
.tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.pill{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:var(--sur);border:1px solid var(--line);font-size:13px;font-weight:600;color:var(--ink2);cursor:default;white-space:nowrap}
button.pill{cursor:pointer}button.pill:hover{border-color:var(--gold)}.clock{font:800 15px Inter;color:var(--ink);letter-spacing:.02em}
.demo{margin:12px 20px 0;padding:10px 14px;border-radius:14px;background:var(--indl);color:var(--ind);font-size:13.5px;font-weight:600}
.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;padding:16px 20px 4px}
.kpi{background:var(--sur);border:1px solid var(--line);border-radius:18px;padding:14px 16px;box-shadow:var(--sh);position:relative;overflow:hidden}
.kpi::after{content:"";position:absolute;right:-30px;top:-30px;width:90px;height:90px;border-radius:50%;background:var(--c,var(--gold));opacity:.10}
.kpi span{display:block;color:var(--mute);font:700 11px Inter;letter-spacing:.1em;text-transform:uppercase}.kpi b{display:block;font:800 34px/1.1 Inter;margin-top:6px}.kpi small{color:var(--mute);font-size:12.5px}
.cols{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;padding:16px 20px 40px;align-items:start}
@media(max-width:1380px){.cols{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:980px){.kpis{grid-template-columns:repeat(3,1fr)}}
@media(max-width:720px){.cols{grid-template-columns:1fr;padding:12px 14px 40px}.kpis{grid-template-columns:repeat(2,1fr);padding:12px 14px 0}.hd{padding:10px 14px;flex-wrap:wrap}.demo{margin:10px 14px 0}#fs,#date{display:none}}
.col h2{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 0 10px;font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--mute)}
.col h2 .n{color:var(--ink);background:var(--sur2);border:1px solid var(--line);border-radius:999px;padding:1px 10px;font-size:12px;letter-spacing:0}
.seg{display:inline-flex;border:1px solid var(--line);border-radius:999px;overflow:hidden;background:var(--sur)}.seg button{border:0;background:transparent;padding:4px 10px;font:700 11px Inter;letter-spacing:.06em;text-transform:uppercase;color:var(--mute);cursor:pointer}.seg button.on{background:var(--sur2);color:var(--ink)}
.cd{background:var(--sur);border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:12px;box-shadow:var(--sh);animation:in .3s ease}@keyframes in{from{opacity:0;transform:translateY(6px)}}
.cd.fresh{border-color:var(--gold);box-shadow:0 0 0 2px rgba(227,194,122,.25),var(--sh)}.cd.soon{border-color:var(--gold);animation:glow 1.8s infinite}.cd.over{border-color:var(--rose);box-shadow:0 0 0 2px rgba(240,97,138,.25),var(--sh)}
@keyframes glow{50%{box-shadow:0 0 0 4px rgba(227,194,122,.25),var(--sh)}}.cd.done{opacity:.55;box-shadow:none}.cd.done .acts .b:not(.x){display:none}
.top{display:flex;align-items:center;justify-content:space-between;gap:10px}.nm{font-weight:700;font-size:16px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.room{flex:0 0 auto;font:800 20px Inter;padding:4px 11px;border-radius:12px;background:var(--sur2);border:1px solid var(--line);letter-spacing:.02em}.room.sm{font-size:15px;padding:3px 9px}
.kind{display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:14.5px}.kind i{font-style:normal;width:30px;height:30px;border-radius:10px;display:grid;place-items:center;background:var(--sur2);font-size:16px}
.det{margin:10px 0 4px;font-size:16px;line-height:1.45}.meta{color:var(--ink2);font-size:13.5px;margin-top:6px}.meta.s{color:var(--mute);font-size:12.5px}.meta a{text-decoration:none;border-bottom:1px dotted var(--mute)}
.note{margin-top:8px;padding:8px 10px;border-radius:10px;background:var(--indl);color:var(--ink);font-size:13.5px}
.due{display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:5px 10px;border-radius:999px;background:var(--sur2);font-weight:700;font-size:13px}.cd.soon .due{background:var(--gold);color:var(--goldd)}.cd.over .due{background:var(--rose);color:#fff}
.tg{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font:700 10.5px Inter;letter-spacing:.07em;text-transform:uppercase;background:var(--sur2);color:var(--ink2);white-space:nowrap}
.tg.ok{background:var(--okl);color:var(--ok)}.tg.gold{background:rgba(227,194,122,.16);color:var(--gold)}.tg.ind{background:var(--indl);color:var(--ind)}.tg.rose{background:var(--rosel);color:var(--rose)}.tg.teal{background:var(--teall);color:var(--teal)}
.dates{margin-top:8px;font-weight:700;font-size:14.5px}.amt{font:800 16px Inter;white-space:nowrap}
.acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center}
.b{flex:1;min-height:44px;padding:10px 12px;border-radius:12px;border:1px solid transparent;font:700 14px Inter;cursor:pointer;color:#fff;background:var(--sur2);border-color:var(--line);color:var(--ink);transition:.15s}
.b:hover{filter:brightness(1.08)}.b:disabled{opacity:.5;cursor:wait}.b.p{background:var(--ind);border-color:var(--ind);color:#fff}.b.ok{background:var(--ok);border-color:var(--ok);color:#fff}.b.x{flex:0 0 auto;background:transparent;color:var(--mute)}
.rn{flex:0 0 112px;min-height:44px;padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:var(--bg);font-weight:700;font-size:15px;outline:0}.rn:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(227,194,122,.18)}
.rn.shake{animation:sh .35s}@keyframes sh{25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}
.chips{display:flex;flex-wrap:wrap;gap:8px}.chip{display:inline-flex;align-items:center;gap:8px;padding:7px 10px 7px 7px;border-radius:12px;background:var(--sur);border:1px solid var(--line);font-size:13px}.chip b{font:800 13.5px Inter;background:var(--sur2);padding:2px 7px;border-radius:8px}.chip span{color:var(--mute)}
.empty{color:var(--mute);text-align:center;padding:26px 12px;border:1px dashed var(--line);border-radius:18px;font-size:14px}
.toasts{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);display:flex;flex-direction:column;gap:8px;align-items:center;z-index:9;pointer-events:none;width:min(92vw,560px)}
.toast{pointer-events:auto;background:var(--gold);color:#1a1407;padding:12px 18px;border-radius:16px;font-weight:800;box-shadow:0 14px 34px rgba(0,0,0,.4);animation:in .25s ease;text-align:center}.toast.err{background:var(--rose);color:#fff}
</style></head>
<body>
<header class="hd">
  <div class="brand"><span class="mk">🛎</span><div style="min-width:0"><b><?= htmlspecialchars($name,ENT_QUOTES,'UTF-8') ?></b><div class="st"><span class="dot off" id="dot"></span><span id="st">verbinde …</span></div></div></div>
  <div class="tools"><span class="pill" id="date"></span><span class="pill clock" id="clock">--:--</span><button class="pill" id="snd" type="button" title="Ton bei neuen Wünschen und Anfragen">🔔 Ton an</button><button class="pill" id="thm" type="button" title="Hell / Dunkel" aria-label="Hell / Dunkel"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button><button class="pill" id="fs" type="button" title="Vollbild" aria-label="Vollbild"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button></div>
</header>
<?php if($acc===0): ?><div class="demo">Live-Demo · fiktive Gäste, Kontaktdaten ausgeblendet. Probieren Sie Check-in, Check-out und Gästewünsche aus – so sieht Ihre Rezeption alles, was der Agent per Telefon, WhatsApp und Chat aufnimmt.</div><?php endif; ?>
<section class="kpis">
  <div class="kpi" style="--c:var(--ok)"><span>Anreisen heute</span><b id="kA">–</b><small id="kA2">&nbsp;</small></div>
  <div class="kpi" style="--c:var(--teal)"><span>Abreisen heute</span><b id="kD">–</b><small id="kD2">&nbsp;</small></div>
  <div class="kpi" style="--c:var(--ind)"><span>Im Haus</span><b id="kH">–</b><small id="kH2">&nbsp;</small></div>
  <div class="kpi" style="--c:var(--gold)"><span>Offene Wünsche</span><b id="kR">–</b><small id="kR2">&nbsp;</small></div>
  <div class="kpi" style="--c:var(--rose)"><span>Offene Anfragen</span><b id="kP">–</b><small>Buchungen bestätigen</small></div>
</section>
<main class="cols">
  <section class="col"><h2><span>Gästewünsche</span><span style="display:flex;gap:8px;align-items:center"><span class="seg" id="rf"><button type="button" data-f="open">Offen</button><button type="button" data-f="all">+ Erledigt</button></span><span class="n" id="nR">0</span></span></h2><div id="cR"></div></section>
  <section class="col"><h2><span>Anreisen heute</span><span class="n" id="nA">0</span></h2><div id="cA"></div></section>
  <section class="col"><h2><span>Abreisen heute</span><span class="n" id="nD">0</span></h2><div id="cD"></div><h2 style="margin-top:20px"><span>Im Haus</span><span class="n" id="nH">0</span></h2><div id="cH" class="chips"></div></section>
  <section class="col"><h2><span>Buchungsanfragen</span><span class="n" id="nP">0</span></h2><div id="cP"></div></section>
</main>
<div class="toasts" id="toasts"></div>
<script>
(function(){
'use strict';
var T=<?= json_encode($t) ?>, TZ=<?= json_encode($s['tz']) ?>, API='/chat/hotel-api.php', DEMO=<?= $acc===0 ? 'true' : 'false' ?>;
var q=function(s){return document.querySelector(s);};
var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
var ls={get:function(k){try{return localStorage.getItem(k);}catch(e){return null;}},set:function(k,v){try{localStorage.setItem(k,v);}catch(e){}}};
var ICON={housekeeping:'🧹',amenities:'🧺',roomservice:'🍽',wakeup:'⏰',maintenance:'🔧',taxi:'🚕',late_checkout:'🕐',info:'ℹ️',other:'💬'};
var CH={chat:'Chat',widget:'Website',phone:'Telefon',whatsapp:'WhatsApp',studio:'Studio',board:'Board',demo:'Demo',seed:'Demo',selftest:'Test'};
var sound=ls.get('hb_sound')!=='0', filter=ls.get('hb_filter')==='all'?'all':'open', seenR=null, seenP=null, alerted={}, RN={}, last=null, ac=null, busy=false;

function setSound(){q('#snd').textContent=sound?'🔔 Ton an':'🔕 Ton aus';}
function audio(){try{if(!ac)ac=new (window.AudioContext||window.webkitAudioContext)();if(ac.state==='suspended')ac.resume();}catch(e){}return ac;}
function chime(n){if(!sound)return;var a=audio();if(!a)return;try{for(var r=0;r<(n||1);r++){[[1318.5,0],[987.8,.16]].forEach(function(p){var t=a.currentTime+p[1]+r*.5,o=a.createOscillator(),g=a.createGain();o.type='sine';o.frequency.value=p[0];o.connect(g);g.connect(a.destination);g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(.35,t+.015);g.gain.exponentialRampToValueAtTime(.0001,t+.9);o.start(t);o.stop(t+.95);});}}catch(e){}}
function toast(msg,err){var el=document.createElement('div');el.className='toast'+(err?' err':'');el.textContent=msg;q('#toasts').appendChild(el);setTimeout(function(){el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(function(){el.remove();},450);},err?5000:7000);}
setSound();
q('#snd').onclick=function(){sound=!sound;ls.set('hb_sound',sound?'1':'0');setSound();if(sound){audio();chime(1);}};
q('#thm').onclick=function(){var l=document.documentElement.getAttribute('data-theme')!=='light';document.documentElement.setAttribute('data-theme',l?'light':'dark');ls.set('hb_theme',l?'light':'dark');};
q('#fs').onclick=function(){var d=document.documentElement;if(document.fullscreenElement){document.exitFullscreen();}else if(d.requestFullscreen){d.requestFullscreen().catch(function(){});}};
document.addEventListener('pointerdown',function(){audio();},{once:true});
function segUI(){document.querySelectorAll('#rf button').forEach(function(b){b.classList.toggle('on',b.dataset.f===filter);});}
segUI();q('#rf').onclick=function(e){var b=e.target.closest('button');if(!b)return;filter=b.dataset.f;ls.set('hb_filter',filter);segUI();if(last)render(last);};
function local(d,o){try{o.timeZone=TZ;return o.weekday?d.toLocaleDateString('de-DE',o):d.toLocaleTimeString('de-DE',o);}catch(e){delete o.timeZone;return o.weekday?d.toLocaleDateString('de-DE',o):d.toLocaleTimeString('de-DE',o);}}   // Uhrzeit des Hotels, unabhängig vom Gerät
function tick(){var d=new Date();q('#clock').textContent=local(d,{hour:'2-digit',minute:'2-digit'});q('#date').textContent=local(d,{weekday:'short',day:'2-digit',month:'2-digit'});}
tick();setInterval(tick,1000);

function rel(iso){if(!iso)return '';var m=Math.round((new Date(iso).getTime()-Date.now())/60000);if(m<=-1)return 'fällig seit '+fmtMin(-m);if(m<1)return 'jetzt fällig';return 'in '+fmtMin(m);}
function fmtMin(m){if(m<60)return m+' Min';var h=Math.floor(m/60),r=m%60;if(h<24)return h+' Std'+(r&&h<5?' '+r+' Min':'');return Math.round(h/24)+' Tg';}
function dueState(r){if(!r.due_iso||r.status==='done'||r.status==='cancelled')return '';var m=(new Date(r.due_iso).getTime()-Date.now())/60000;if(m<-1)return 'over';if(m<=15)return 'soon';return '';}
function contact(b){var o=[];if(b.phone)o.push(DEMO?'📞 '+esc(b.phone):'📞 <a href="tel:'+esc(String(b.phone).replace(/[^0-9+]/g,''))+'">'+esc(b.phone)+'</a>');if(b.email)o.push('✉ <a href="mailto:'+esc(b.email)+'">'+esc(b.email)+'</a>');return o.join(' · ');}
function nightsTxt(n){return n+' '+(n===1?'Nacht':'Nächte');}

function reqCard(r){
  var ds=dueState(r), cls='cd'+(r.status==='done'||r.status==='cancelled'?' done':'')+(ds?' '+ds:'')+(r._fresh?' fresh':'');
  var tag=r.status==='in_progress'?'<span class="tg ind">In Arbeit</span>':(r.status==='done'?'<span class="tg ok">Erledigt</span>':(r.status==='cancelled'?'<span class="tg">Storniert</span>':'<span class="tg gold">Neu</span>'));
  var acts='';
  if(r.status==='open')acts='<button class="b p" data-k="request" data-id="'+r.id+'" data-v="in_progress">Übernehmen</button><button class="b ok" data-k="request" data-id="'+r.id+'" data-v="done">✓ Erledigt</button>';
  else if(r.status==='in_progress')acts='<button class="b ok" data-k="request" data-id="'+r.id+'" data-v="done">✓ Erledigt</button><button class="b x" data-k="request" data-id="'+r.id+'" data-v="open" title="Zurück auf offen">↺</button>';
  else acts='<button class="b x" data-k="request" data-id="'+r.id+'" data-v="open">↺ Wieder öffnen</button>';
  return '<div class="'+cls+'"><div class="top"><span class="kind"><i>'+(ICON[r.kind]||'💬')+'</i>'+esc(r.kind_label)+'</span>'+(r.room_no?'<span class="room">'+esc(r.room_no)+'</span>':'')+'</div>'
   +(String(r.details).trim().toLowerCase()!==String(r.kind_label).toLowerCase()?'<div class="det">'+esc(r.details)+'</div>':'<div style="height:6px"></div>')
   +(r.due_human?'<div class="due">⏱ '+esc(r.due_human)+(ds||r.status==='open'||r.status==='in_progress'?' · '+esc(rel(r.due_iso)):'')+'</div>':'')
   +'<div class="meta s">'+tag+' '+(r.guest_name?'👤 '+esc(r.guest_name)+' · ':'')+(r.contact?esc(r.contact)+' · ':'')+esc(CH[r.channel]||r.channel)+' · '+esc(r.created)+' Uhr'+(r.ride_url?' · <a href="'+esc(r.ride_url)+'" target="_blank" rel="noopener">Fahrt '+esc(r.ref)+'</a>':'')+'</div>'
   +'<div class="acts">'+acts+'</div></div>';
}
function arrCard(b,today){
  var over=b.checkin<today, done=b.status==='checked_in';
  var tag=done?'<span class="tg ok">✓ Zimmer '+esc(b.room_no)+'</span>':(b.status==='requested'?'<span class="tg rose">Anfrage offen</span>':(over?'<span class="tg rose">erwartet seit '+esc(b.checkin_short)+'</span>':'<span class="tg ok">Bestätigt</span>'));
  var acts='';
  if(b.status==='requested')acts='<button class="b ok" data-k="booking" data-id="'+b.id+'" data-v="confirm">✓ Bestätigen</button><button class="b x" data-k="booking" data-id="'+b.id+'" data-v="decline">Ablehnen</button>';
  else if(!done)acts='<input class="rn" data-id="'+b.id+'" placeholder="Zimmer-Nr." inputmode="text" maxlength="20" value="'+esc(RN[b.id]||'')+'" aria-label="Zimmernummer"><button class="b ok" data-k="booking" data-id="'+b.id+'" data-v="checkin">Check-in</button>'+(over?'<button class="b x" data-k="booking" data-id="'+b.id+'" data-v="noshow" title="Nicht angereist">No-Show</button>':'');
  return '<div class="cd'+(done?' done':'')+'"><div class="top"><span class="nm">'+esc(b.name)+'</span>'+tag+'</div>'
   +'<div class="meta">'+esc(b.room)+' · '+b.guests+' '+(b.guests===1?'Gast':'Gäste')+' · '+nightsTxt(b.nights)+' (bis '+esc(b.checkout_short)+')'+(b.breakfast?' · Frühstück':'')+'</div>'
   +(b.note?'<div class="note">📝 '+esc(b.note)+'</div>':'')
   +'<div class="meta s">'+(contact(b)?contact(b)+' · ':'')+'Code '+esc(b.code)+' · '+esc(CH[b.channel]||b.channel)+' · '+esc(b.total)+'</div>'
   +(acts?'<div class="acts">'+acts+'</div>':'')+'</div>';
}
function depCard(b,today){
  var done=b.status==='checked_out', over=b.checkout<today;
  return '<div class="cd'+(done?' done':'')+'"><div class="top">'+(b.room_no?'<span class="room">'+esc(b.room_no)+'</span>':'')+'<span class="nm" style="flex:1">'+esc(b.name)+'</span>'+(done?'<span class="tg ok">✓ Abgereist</span>':(over?'<span class="tg rose">überfällig</span>':'<span class="tg teal">bis '+esc(b.checkout_until)+' Uhr</span>'))+'</div>'
   +'<div class="meta">'+esc(b.room)+' · '+b.guests+' '+(b.guests===1?'Gast':'Gäste')+' · seit '+esc(b.checkin_short)+' · '+esc(b.total)+'</div>'
   +(done?'':'<div class="acts"><button class="b ok" data-k="booking" data-id="'+b.id+'" data-v="checkout">Check-out</button></div>')+'</div>';
}
function pendCard(b){
  return '<div class="cd'+(b._fresh?' fresh':'')+'"><div class="top"><span class="nm">'+esc(b.name)+'</span><span class="amt">'+esc(b.total)+'</span></div>'
   +'<div class="dates">'+esc(b.checkin_human)+' → '+esc(b.checkout_human)+' · '+nightsTxt(b.nights)+'</div>'
   +'<div class="meta">'+esc(b.room)+' · '+b.guests+' '+(b.guests===1?'Gast':'Gäste')+(b.breakfast?' · Frühstück':'')+'</div>'
   +(b.note?'<div class="note">📝 '+esc(b.note)+'</div>':'')
   +'<div class="meta s">'+(contact(b)?contact(b)+' · ':'')+esc(CH[b.channel]||b.channel)+' · eingegangen '+esc(b.created)+'</div>'
   +'<div class="acts"><button class="b ok" data-k="booking" data-id="'+b.id+'" data-v="confirm">✓ Bestätigen</button><button class="b x" data-k="booking" data-id="'+b.id+'" data-v="decline">Ablehnen</button></div></div>';
}
function render(j){
  last=j; var st=j.stats||{}, today=j.today;
  q('#kA').textContent=st.arrivals_today;q('#kA2').textContent=st.arrivals_open?st.arrivals_open+' noch einchecken':'alle eingecheckt';
  q('#kD').textContent=st.departures_today;q('#kD2').textContent=st.departures_open?st.departures_open+' noch auschecken':'alle ausgecheckt';
  q('#kH').textContent=st.in_house;q('#kH2').textContent=(st.guests_in_house||0)+' Gäste';
  var open=(j.requests||[]).filter(function(r){return r.status==='open';}).length, prog=(j.requests||[]).filter(function(r){return r.status==='in_progress';}).length;
  q('#kR').textContent=st.open_requests;q('#kR2').textContent=open+' neu · '+prog+' in Arbeit';q('#kP').textContent=st.pending_requests;
  var reqs=(j.requests||[]).filter(function(r){return filter==='all'||r.status==='open'||r.status==='in_progress';});
  var focus=document.activeElement&&document.activeElement.classList.contains('rn')?document.activeElement.dataset.id:null;
  q('#cR').innerHTML=reqs.map(reqCard).join('')||'<div class="empty">Keine offenen Wünsche – alles erledigt ✨</div>';q('#nR').textContent=reqs.filter(function(r){return r.status==='open'||r.status==='in_progress';}).length;
  q('#cA').innerHTML=(j.arrivals||[]).map(function(b){return arrCard(b,today);}).join('')||'<div class="empty">Heute keine Anreisen</div>';q('#nA').textContent=(j.arrivals||[]).length;
  q('#cD').innerHTML=(j.departures||[]).map(function(b){return depCard(b,today);}).join('')||'<div class="empty">Heute keine Abreisen</div>';q('#nD').textContent=(j.departures||[]).length;
  q('#cH').innerHTML=(j.in_house||[]).map(function(b){return '<span class="chip"><b>'+esc(b.room_no||'–')+'</b>'+esc(b.name)+'<span>bis '+esc(b.checkout_short.slice(4))+'</span></span>';}).join('')||'<div class="empty" style="width:100%">Niemand eingecheckt</div>';q('#nH').textContent=(j.in_house||[]).length;
  q('#cP').innerHTML=(j.pending||[]).map(pendCard).join('')||'<div class="empty">Keine offenen Buchungsanfragen</div>';q('#nP').textContent=(j.pending||[]).length;
  if(focus){var el=document.querySelector('.rn[data-id="'+focus+'"]');if(el){el.focus();var v=el.value;el.value='';el.value=v;}}
}
function diff(j){
  var rIds={},pIds={},freshR=[],freshP=[],due=[];
  (j.requests||[]).forEach(function(r){rIds[r.id]=1;if(seenR&&!seenR[r.id]&&(r.status==='open'||r.status==='in_progress')){r._fresh=true;freshR.push(r);}if(dueState(r)==='soon'&&!alerted[r.id]&&(r.kind==='wakeup'||r.kind==='taxi')){alerted[r.id]=1;if(seenR)due.push(r);}});
  (j.pending||[]).forEach(function(b){pIds[b.id]=1;if(seenP&&!seenP[b.id]){b._fresh=true;freshP.push(b);}});
  seenR=rIds;seenP=pIds;
  freshR.forEach(function(r){toast((ICON[r.kind]||'🛎')+' '+r.kind_label+(r.room_no?' · Zimmer '+r.room_no:'')+': '+r.details.slice(0,70));});
  freshP.forEach(function(b){toast('📩 Neue Buchungsanfrage: '+b.name+' · '+b.checkin_human+' · '+b.total);});
  due.forEach(function(r){toast('⏱ '+(r.kind==='wakeup'?'Weckruf':'Taxi')+' fällig'+(r.room_no?' · Zimmer '+r.room_no:'')+' · '+r.due_human);});
  if(freshR.length||freshP.length)chime(1);if(due.length)chime(2);
}
function load(){
  return fetch(API+'?a=board&t='+encodeURIComponent(T)+'&_='+Date.now(),{cache:'no-store'}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||j.error||('HTTP '+r.status));return j;});})
  .then(function(j){diff(j);render(j);q('#dot').classList.remove('off');q('#st').textContent='live · aktualisiert '+local(new Date(),{hour:'2-digit',minute:'2-digit',second:'2-digit'});})
  .catch(function(e){q('#dot').classList.add('off');q('#st').textContent='⚠ '+e.message+' – neuer Versuch in 10 s';});
}
function post(a,body){return fetch(API+'?a='+a+'&t='+encodeURIComponent(T)+'&_='+Date.now(),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json().then(function(j){if(!r.ok){var e=new Error(j.hint||j.message||j.error||('HTTP '+r.status));throw e;}return j;});});}
document.addEventListener('input',function(e){if(e.target.classList&&e.target.classList.contains('rn'))RN[e.target.dataset.id]=e.target.value;});
document.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.classList&&e.target.classList.contains('rn')){var b=e.target.parentNode.querySelector('[data-v="checkin"]');if(b)b.click();}});
document.addEventListener('click',function(e){
  var b=e.target.closest('button[data-k]');if(!b||busy)return;var id=+b.dataset.id,v=b.dataset.v,k=b.dataset.k,body;
  if(k==='request')body=['request',{id:id,status:v}];
  else{
    if(v==='checkin'){var inp=b.parentNode.querySelector('.rn'),no=inp?inp.value.trim():'';if(!no){if(inp){inp.classList.remove('shake');void inp.offsetWidth;inp.classList.add('shake');inp.focus();}toast('Bitte zuerst die Zimmernummer eintragen.',true);return;}body=['booking',{id:id,action:v,room_no:no}];}
    else{if((v==='decline'||v==='noshow'||v==='cancel')&&!confirm(v==='decline'?'Anfrage wirklich ablehnen? Der Gast wird informiert.':'Gast als nicht angereist markieren?'))return;body=['booking',{id:id,action:v}];}
  }
  busy=true;b.disabled=true;post(body[0],body[1]).then(function(){if(v==='checkin')delete RN[id];return load();}).catch(function(err){toast(err.message,true);}).then(function(){busy=false;b.disabled=false;});
});
load();setInterval(function(){if(!busy)load();},10000);
setInterval(function(){if(last)render(last);},30000);   // Fälligkeiten („in 5 Min“) aktuell halten
setTimeout(function(){location.replace('?t='+encodeURIComponent(T)+'&_='+Date.now().toString(36));},6*3600*1000);   // Dauerbetrieb: alle 6 h frisch laden
})();
</script>
</body></html>
