<?php
// ── IP DETECTION ─────────────────────────────────
$country = 'DE';
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Skip for localhost
    if(!in_array($ip, ['127.0.0.1','::1','localhost'])){
        $geo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false,
            stream_context_create(['http'=>['timeout'=>2]]));
        if($geo) $country = json_decode($geo,true)['countryCode'] ?? 'DE';
    }
} catch(Exception $e) {}

// Germany → redirect to .de
if($country === 'DE') {
    header('Location: https://chat-help.de/chat/');
    exit;
}

// Set language based on country
$lang_map = [
    'FR'=>'fr','BE'=>'fr','LU'=>'fr','CH'=>'fr','MC'=>'fr',
    'NL'=>'nl','MA'=>'fr','DZ'=>'fr','TN'=>'fr',
    'TR'=>'tr','AT'=>'de','GB'=>'en','US'=>'en',
];
$default_lang = $lang_map[$country] ?? 'fr';

// Pass to JS
$js_country = json_encode($country);
$js_lang = json_encode($default_lang);
?>
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Chat-Help — Legal Documents Europe</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// ── AUTO LANG FROM SERVER ─────────────────────────
(function(){
  const serverCountry = <?php echo $js_country; ?>;
  const serverLang = <?php echo $js_lang; ?>;
  if(!localStorage.getItem('ch_lm')) UL = serverLang;
  if(!localStorage.getItem('ch_ldi')) CC = serverCountry;
})();


function doLogout(){
  localStorage.removeItem('ch_token');
  localStorage.removeItem('ch_user');
  P.plan='free'; P.f1=''; P.f2=''; P.f7='';
  const lbl=document.getElementById('tb-auth-label');
  if(lbl) lbl.textContent='Einloggen';
  const btn=document.getElementById('tb-auth-btn');
  if(btn){ btn.style.background='rgba(212,168,74,.1)'; btn.style.borderColor='rgba(212,168,74,.28)'; }
  gP('chat');
  addMsg('ai','Sie wurden erfolgreich abgemeldet.');
}


</script>
<script>

// ══════════════════════════════════════════════════
// AUTH SYSTEM
// ══════════════════════════════════════════════════
let _authTab = 'login';
const AUTH_API = 'auth.php';

function openAuthModal(tab='login'){
  const m = document.getElementById('auth-modal');
  m.style.display = 'flex';
  switchTab(tab);
  setTimeout(()=>document.getElementById('auth-email').focus(), 100);
}

function closeAuthModal(){
  document.getElementById('auth-modal').style.display = 'none';
  document.getElementById('auth-error').style.display = 'none';
}

function switchTab(tab){
  _authTab = tab;
  const isLogin = tab === 'login';
  document.getElementById('auth-name-field').style.display = isLogin ? 'none' : 'block';
  document.getElementById('auth-pass2-field').style.display = isLogin ? 'none' : 'block';
  document.getElementById('auth-forgot').style.display = isLogin ? 'block' : 'none';
  document.getElementById('auth-subtitle').textContent = isLogin ? 'Mit Ihrem Konto anmelden' : 'Neues Konto erstellen';
  document.getElementById('auth-submit-btn').textContent = isLogin ? 'Anmelden' : 'Registrieren';
  const base='flex:1;padding:8px;border-radius:10px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;';
  document.getElementById('tab-login').style.cssText=base+(isLogin?'background:rgba(212,168,74,.18);border:0.5px solid rgba(212,168,74,.35);color:#d4a84a;':'background:none;border:0.5px solid transparent;color:#60607a;');
  document.getElementById('tab-register').style.cssText=base+(!isLogin?'background:rgba(212,168,74,.18);border:0.5px solid rgba(212,168,74,.35);color:#d4a84a;':'background:none;border:0.5px solid transparent;color:#60607a;');
  document.getElementById('tab-login').style.cssText = isLogin
    ? 'flex:1;padding:9px;background:rgba(212,168,74,.15);border:1px solid rgba(212,168,74,.3);border-radius:9px;color:#d4a84a;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit'
    : 'flex:1;padding:9px;background:none;border:none;color:#606080;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit';
  document.getElementById('tab-register').style.cssText = !isLogin
    ? 'flex:1;padding:9px;background:rgba(212,168,74,.15);border:1px solid rgba(212,168,74,.3);border-radius:9px;color:#d4a84a;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit'
    : 'flex:1;padding:9px;background:none;border:none;color:#606080;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit';
}

async function submitAuth(){
  const btn   = document.getElementById('auth-submit-btn');
  const email = document.getElementById('auth-email').value.trim();
  const pass  = document.getElementById('auth-pass').value;
  const name  = document.getElementById('auth-name').value.trim();
  const errEl = document.getElementById('auth-error');

  errEl.style.display = 'none';
  if(!email || !pass){ showAuthError('Bitte alle Felder ausfüllen'); return; }

  btn.disabled = true;
  btn.textContent = '⏳ Bitte warten…';

  try{
    const body = _authTab === 'login'
      ? {email, password: pass}
      : {email, password: pass, name};

    const res  = await fetch(AUTH_API + '?action=' + _authTab, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    });
    const d = await res.json();

    if(d.error){ showAuthError(d.error); return; }

    if(_authTab === 'register'){
      showAuthError('✅ Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail.', true);
      return;
    }

    // Login success
    localStorage.setItem('ch_token', d.token);
    localStorage.setItem('ch_user', JSON.stringify(d.user));
    P.plan = d.user.plan;
    P.f1   = d.user.name.split(' ')[0] || '';
    P.f2   = d.user.name.split(' ').slice(1).join(' ') || '';
    P.f7   = d.user.email;
    closeAuthModal();
    updateAuthUI(d.user);
    addMsg('ai', '👋 Willkommen zurück, <strong>' + (d.user.name||d.user.email) + '</strong>! Plan: <strong>' + d.user.plan.toUpperCase() + '</strong>');

  }catch(e){
    showAuthError('Verbindungsfehler. Bitte versuchen Sie es erneut.');
  } finally {
    btn.disabled = false;
    btn.textContent = _authTab === 'login' ? 'Anmelden' : 'Registrieren';
  }
}

function showAuthError(msg, isSuccess=false){
  const el = document.getElementById('auth-error');
  el.style.display = 'block';
  el.style.background = isSuccess ? 'rgba(66,223,148,.1)' : 'rgba(224,72,88,.1)';
  el.style.borderColor = isSuccess ? 'rgba(66,223,148,.3)' : 'rgba(224,72,88,.3)';
  el.style.color = isSuccess ? '#42df94' : '#e04858';
  el.textContent = msg;
}

async function showForgot(){
  const email = document.getElementById('auth-email').value.trim();
  if(!email){ showAuthError('Bitte zuerst E-Mail eingeben'); return; }
  try{
    const res = await fetch(AUTH_API + '?action=reset_request', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({email})
    });
    const d = await res.json();
    showAuthError(d.message || 'Reset-E-Mail gesendet', true);
  }catch(e){ showAuthError('Fehler beim Senden'); }
}

function updateAuthUI(user){
  if(!user) return;
  const lbl = document.getElementById('tb-auth-label');
  if(lbl) lbl.textContent = (user.name||user.email||'Konto').split(' ')[0];
  const btn = document.getElementById('tb-auth-btn');
  if(btn){ btn.style.background='rgba(212,168,74,.18)'; btn.style.borderColor='rgba(212,168,74,.4)'; }
  renderKontoPlanSelector();
}

// ── AUTO LOGIN on page load ────────────────────────
(async function autoLogin(){
  const token = localStorage.getItem('ch_token');
  if(!token) return;
  try{
    const res = await fetch(AUTH_API + '?action=me', {
      headers: {'Authorization': 'Bearer ' + token}
    });
    const d = await res.json();
    if(d.success && d.user){
      P.plan = d.user.plan;
      P.f1   = d.user.name.split(' ')[0] || '';
      P.f2   = d.user.name.split(' ').slice(1).join(' ') || '';
      P.f7   = d.user.email;
      updateAuthUI(d.user);
    } else {
      localStorage.removeItem('ch_token');
      localStorage.removeItem('ch_user');
    }
  }catch(e){}
})();

// ── User button in topbar opens auth or konto ─────
function handleUserBtn(){
  const token = localStorage.getItem('ch_token');
  if(token) gP('konto');
  else window.location.href='login.php';
}

// Check email verified return
(function checkVerified(){
  const params = new URLSearchParams(location.search);
  if(params.get('verified') === '1'){
    history.replaceState({}, '', location.pathname);
    setTimeout(()=>{
      openAuthModal('login');
      showAuthError('✅ E-Mail bestätigt! Sie können sich jetzt anmelden.', true);
    }, 500);
  }
})();


// ── GOOGLE LOGIN ──────────────────────────────────
function initGoogleLogin(){
  if(!window.google) return;
  google.accounts.id.initialize({
    client_id: '485883163366-uv4v94k6el45eipnvqh21k74vjsfn68g.apps.googleusercontent.com',
    callback: handleGoogleLogin,
    auto_select: false,
  });
  const btn = document.getElementById('google-signin-btn');
  if(btn){
    google.accounts.id.renderButton(btn, {
      theme: 'filled_black',
      size: 'large',
      width: 320,
      text: 'continue_with',
      shape: 'rectangular',
    });
  }
}

async function handleGoogleLogin(response){
  const idToken = response.credential;
  try{
    const res = await fetch('auth.php?action=google', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id_token: idToken})
    });
    const d = await res.json();
    if(d.error){ showAuthError(d.error); return; }
    localStorage.setItem('ch_token', d.token);
    localStorage.setItem('ch_user', JSON.stringify(d.user));
    P.plan = d.user.plan;
    P.f1   = d.user.name.split(' ')[0] || '';
    P.f2   = d.user.name.split(' ').slice(1).join(' ') || '';
    P.f7   = d.user.email;
    closeAuthModal();
    updateAuthUI(d.user);
    addMsg('ai', '👋 Willkommen, <strong>' + (d.user.name||d.user.email) + '</strong>!');
  } catch(e){
    showAuthError('Google-Anmeldung fehlgeschlagen.');
  }
}

// Init when modal opens
const _origOpen = openAuthModal;
openAuthModal = function(tab){
  _origOpen(tab);
  setTimeout(initGoogleLogin, 300);
};


</script>
<script>

function renderKontoPlanSelector(){
  const sel = document.getElementById('k-plan-selector');
  if(!sel) return;
  const userPlan = P.plan||'free';

  const plans = [
    {key:'free',   ic:'🆓', name:'Free',  price:'Kostenlos',  color:'#808090',
     feats:['3 Dokumente','PDF-Download'],
     miss:['KI-Recherche','Ton-Anpassung']},
    {key:'basic',  ic:'🥉', name:'Basic', price:'9,99€/Mo',   color:'#d4a84a',
     feats:['20 Dokumente/Mo','2 Überarbeitungen','KI-Recherche','DSGVO-konform'],
     miss:['Ton-Anpassung','Strategie']},
    {key:'pro',    ic:'🥈', name:'Pro',   price:'29,99€/Mo',  color:'#6094ff',
     feats:['75 Dokumente/Mo','5 Überarbeitungen','Ton-Anpassung','Strategie 5 Fälle','KI-Recherche','Prioritäts-Support'],
     miss:[]},
    {key:'elite',  ic:'👑', name:'Elite', price:'99,99€/Mo',  color:'#c090ff',
     feats:['300+ Dokumente/Mo','10 Überarbeitungen','Ton-Anpassung','Strategie alle Fälle','KI-Rechtsrecherche','VIP-Support','Vollst. Fallanalyse','DSGVO · EU-weit'],
     miss:[]},
  ];

  // User info
  const user = JSON.parse(localStorage.getItem('ch_user')||'null');
  const uinfo = document.getElementById('konto-user-info');
  if(uinfo && user){
    uinfo.style.display='flex';
    const av=document.getElementById('konto-avatar');
    if(av) av.textContent=(user.name||user.email||'U')[0].toUpperCase();
    const nm=document.getElementById('konto-name');
    if(nm) nm.textContent=user.name||'-';
    const em=document.getElementById('konto-email');
    if(em) em.textContent=user.email||'-';
    const pb=document.getElementById('konto-plan-badge');
    if(pb) pb.textContent=(userPlan).toUpperCase();
  }

  sel.innerHTML = '';
  plans.forEach(p => {
    const isActive = userPlan === p.key;
    const div = document.createElement('div');
    div.style.cssText = `padding:14px;background:${isActive?'rgba('+hexToRgb(p.color)+',.08)':'rgba(255,255,255,.03)'};border:${isActive?'1px solid '+p.color+'44':'0.5px solid rgba(255,255,255,.08)'};border-radius:14px;margin-bottom:8px;cursor:${isActive?'default':'pointer'}`;

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:${p.feats.length?'10px':'0'}">`;
    html += `<div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">${p.ic}</span><div><div style="font-size:14px;font-weight:700;color:#fff">${p.name}</div><div style="font-size:11px;color:${p.color}">${p.price}</div></div></div>`;
    if(isActive){
      html += `<div style="font-size:10px;font-weight:700;color:#30c87a;background:rgba(48,200,122,.1);border:0.5px solid rgba(48,200,122,.3);padding:4px 10px;border-radius:100px">✓ Aktiv</div>`;
    } else if(p.key!=='free'){
      html += `<button onclick="stripeCheckout('${p.key}')" style="padding:7px 13px;background:linear-gradient(135deg,${p.color}99,${p.color});border:none;border-radius:9px;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit">Wählen</button>`;
    }
    html += '</div>';

    if(p.feats.length){
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:5px">';
      p.feats.forEach(f => { html += `<div style="font-size:11px;color:#9090a8;display:flex;align-items:center;gap:4px"><span style="color:${p.color}">✓</span>${f}</div>`; });
      p.miss.forEach(f => { html += `<div style="font-size:11px;color:#404055;display:flex;align-items:center;gap:4px"><span>✗</span>${f}</div>`; });
      html += '</div>';
    }

    div.innerHTML = html;
    sel.appendChild(div);
  });
}

function hexToRgb(hex){
  const r=parseInt(hex.slice(1,3),16);
  const g=parseInt(hex.slice(3,5),16);
  const b=parseInt(hex.slice(5,7),16);
  return `${r},${g},${b}`;
}


</script>
<script>

// ══════════════════════════════════════════════════
// TONE SELECTOR (Pro/Elite)
// ══════════════════════════════════════════════════
const TONES=[
  {k:'standard',   ic:'📄', t:'Standard',     d:'Sachlich und professionell'},
  {k:'diplomatisch',ic:'🤝',t:'Diplomatisch',  d:'Höflich und verständnisvoll'},
  {k:'sert',       ic:'⚡', t:'Bestimmt',      d:'Klar, direkt und fordernd'},
  {k:'nazik',      ic:'🌿', t:'Freundlich',    d:'Verständnisvoll und kooperativ'},
  {k:'iyimser',    ic:'✨', t:'Konstruktiv',   d:'Lösungsorientiert und positiv'},
];

function showToneSelector(dk,ans,plan){
  document.getElementById('tone-modal')?.remove();
  const modal=document.createElement('div');
  modal.id='tone-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:750;background:rgba(0,0,0,.7);display:flex;align-items:flex-end;justify-content:center';

  let html='<div id="tone-sheet" style="background:#0d0d1f;border-radius:22px 22px 0 0;width:100%;max-width:500px;padding:0 0 24px;transform:translateY(100%);transition:transform .3s">';
  html+='<div style="display:flex;justify-content:center;padding:10px 0 0"><div style="width:40px;height:4px;border-radius:2px;background:rgba(255,255,255,.15)"></div></div>';
  html+='<div style="padding:16px 20px 12px"><div style="font-size:17px;font-weight:700;color:#fff;margin-bottom:4px">Dokumenten-Ton wählen</div>';
  html+='<div style="font-size:12px;color:#606080">Wie soll Ihr Dokument klingen?</div></div>';
  html+='<div style="padding:0 14px">';

  TONES.forEach(t=>{
    html+=`<div onclick="selectTone('${t.k}',this)" data-tone="${t.k}" style="display:flex;align-items:center;gap:14px;padding:13px 16px;margin-bottom:7px;background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.08);border-radius:14px;cursor:pointer;transition:all .15s;-webkit-tap-highlight-color:transparent">`;
    html+=`<span style="font-size:22px;width:32px;text-align:center;flex-shrink:0">${t.ic}</span>`;
    html+=`<div style="flex:1"><div style="font-size:14px;font-weight:600;color:#fff">${t.t}</div><div style="font-size:11.5px;color:#606080;margin-top:2px">${t.d}</div></div>`;
    html+=`<div class="tone-check" style="width:20px;height:20px;border-radius:50%;border:1.5px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>`;
    html+=`</div>`;
  });

  // Elite: Strategie button
  if(plan==='elite'){
    html+=`<div style="height:1px;background:rgba(255,255,255,.06);margin:8px 0 12px"></div>`;
    html+=`<div onclick="showStrategie()" style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:linear-gradient(135deg,rgba(144,96,212,.12),rgba(192,144,255,.06));border:1.5px solid rgba(144,96,212,.3);border-radius:14px;cursor:pointer;-webkit-tap-highlight-color:transparent">`;
    html+=`<span style="font-size:22px;width:32px;text-align:center;flex-shrink:0">🧠</span>`;
    html+=`<div style="flex:1"><div style="font-size:14px;font-weight:600;color:#c090ff">Strategie entwickeln</div><div style="font-size:11.5px;color:#7060a0;margin-top:2px">KI analysiert Ihren Fall & empfiehlt Vorgehen</div></div>`;
    html+=`<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c090ff" stroke-width="2" stroke-linecap="round"><polyline points="9,18 15,12 9,6"/></svg>`;
    html+=`</div>`;
  }

  html+=`</div>`;
  html+=`<div style="padding:16px 14px 0"><button id="tone-confirm-btn" onclick="confirmTone('${dk}',${JSON.stringify(ans).replace(/'/g,"\\'")})" disabled style="width:100%;padding:14px;background:rgba(212,168,74,.15);border:1.5px solid rgba(212,168,74,.2);border-radius:14px;color:rgba(212,168,74,.4);font-size:14px;font-weight:700;cursor:not-allowed;font-family:inherit;transition:all .2s">Ton auswählen</button></div>`;
  html+=`</div>`;

  modal.innerHTML=html;
  modal.onclick=e=>{if(e.target===modal){modal.remove();}};
  document.body.appendChild(modal);
  requestAnimationFrame(()=>{document.getElementById('tone-sheet').style.transform='translateY(0)';});
  window._toneSelected=null;
}

function selectTone(key,el){
  window._toneSelected=key;
  document.querySelectorAll('#tone-modal [data-tone]').forEach(d=>{
    const selected=d.dataset.tone===key;
    d.style.background=selected?'rgba(212,168,74,.1)':'rgba(255,255,255,.04)';
    d.style.borderColor=selected?'rgba(212,168,74,.4)':'rgba(255,255,255,.08)';
    const check=d.querySelector('.tone-check');
    if(check)check.innerHTML=selected?'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="3" stroke-linecap="round"><polyline points="20,6 9,17 4,12"/></svg>':'';
  });
  const btn=document.getElementById('tone-confirm-btn');
  if(btn){btn.disabled=false;btn.style.background='linear-gradient(135deg,#c49038,#e8b840)';btn.style.borderColor='rgba(212,168,74,.4)';btn.style.color='#1a0e00';btn.style.cursor='pointer';btn.textContent='\uD83D\uDCC4 Dokument erstellen \u2192';}
}

async function confirmTone(dk,ans){
  const tone=window._toneSelected||'standard';
  document.getElementById('tone-modal')?.remove();
  ans._tone=tone;
  await genDoc(dk,ans);
}

async function showStrategie(){
  document.getElementById('tone-modal')?.remove();
  const state=window._pfState||{dk:'widerspruch',ans:{}};
  addMsg('ai','\uD83E\uDDE0 <strong>Strategie-Analyse wird erstellt\u2026</strong>');
  try{
    const res=await fetch(API+'?action=strategie',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({docType:state.dk||'widerspruch',answers:state.ans||{},lang:dLang,country:CC})});
    const d=await res.json();
    if(d.strategie) addMsg('ai',d.strategie.replace(/\n/g,'<br>'));
    else addMsg('ai','\u274C Analyse fehlgeschlagen.');
  }catch(e){addMsg('ai','\u274C Verbindungsfehler.');}
}


// ══════════════════════════════════════════════════
// STRIPE PAYMENT SYSTEM
// ══════════════════════════════════════════════════
const STRIPE_PK='pk_live_51RZCPoAJdvZPpak7JOnMMHuwGeVFknKi8Wh8GMtreXlAZLXTTGwE8nI8D0Zi40nKBNqHUyfuXCXtgh7dMzCxzCQU00ze3mS4lt';
const PLANS={
  basic:  {name:'JuristAssist Basic', price:'9,99€/Mo', priceId:'price_1Tf6cIAJdvZPpak7my3fN8qF', type:'subscription', ic:'🥉', color:'#d4a84a', docs:20, rev:2},
  pro:    {name:'JuristAssist Pro',   price:'29,99€/Mo',priceId:'price_1Tf6i5AJdvZPpak7o3QIuHsF', type:'subscription', ic:'🥈', color:'#6094ff', docs:75,rev:5},
  elite:  {name:'JuristAssist Elite', price:'99,99€/Mo',priceId:'price_1Tf6jqAJdvZPpak7Vp8SvOPW', type:'subscription', ic:'👑', color:'#c090ff', docs:300,rev:10},
};
const DOC_PRICES={
  einfach:  {name:'Einzeldokument Einfach',  price:'1,99€', priceId:'price_1Tf71jAJdvZPpak76GX3MZID', ic:'📄'},
  standard: {name:'Einzeldokument Standard', price:'3,99€', priceId:'price_1Tf75LAJdvZPpak75eZIpoj6', ic:'📋'},
  komplex:  {name:'Einzeldokument Komplex',  price:'4,99€', priceId:'price_1Tf7H6AJdvZPpak7QSdWq61K', ic:'⚖️'},
};

// Doc type → price tier
const DOC_TIER={
  kuendigung_handy:'einfach', kuendigung_internet:'einfach', kuendigung_fitness:'einfach',
  kuendigung_streaming:'einfach', kuendigung_versicherung:'einfach', kuendigung_strom:'einfach',
  urlaubsantrag:'einfach', widerruf:'einfach', mahnung:'einfach',
  mietminderung:'standard', maengel_vermieter:'standard', widerspruch_mieterhoehung:'standard',
  kaution_rueckforderung:'standard', eigenkundigung:'standard', zeugnis:'standard',
  homeoffice_antrag:'standard', reklamation:'standard', ratenzahlung:'standard',
  bussgeldbescheid:'standard', mahnbescheid_einspruch:'standard',
  widerspruch:'komplex', widerspruch_ablehnung:'komplex', aufenthaltserlaubnis:'komplex',
  duldung_verlaengerung:'komplex', familiennachzug:'komplex', buergergeld:'komplex',
  einspruch_steuer:'komplex', krankenkasse_widerspruch:'komplex', fluggastrechte:'komplex',
};

function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }

async function stripeCheckout(planKey, docType, docKey){
  const isDoc = !!docKey;
  const plan  = isDoc ? DOC_PRICES[docKey] : PLANS[planKey];
  if(!plan) return;

  const sessId = document.cookie.match(/ch_sid=([^;]+)/)?.[1] || Math.random().toString(36).slice(2);

  try{
    const res = await fetch('stripe-checkout.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        type:    isDoc ? 'payment' : 'subscription',
        plan:    isDoc ? 'doc_'+docKey : planKey,
        doc_type: docType||'',
        session_id: sessId,
        email:   P.f7||'',
      })
    });
    const d = await res.json();
    if(d.url) window.location.href = d.url;
    else alert('Fehler: ' + (d.error||'Unbekannt'));
  } catch(e){
    alert('Verbindungsfehler. Bitte versuchen Sie es erneut.');
  }
}

function showPaywall(dk, ans){
  // Determine price for this doc
  const tier = getDocPrice(dk);
  const dp   = DOC_PRICES[tier];
  const userPlan = P.plan||'free';

  document.getElementById('paywall-modal')?.remove();
  const modal = document.createElement('div');
  modal.id = 'paywall-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:800;background:rgba(0,0,0,.75);display:flex;align-items:flex-end;justify-content:center';

  let html = '<div id="pw-sheet" style="background:#0d0d1f;border-radius:22px 22px 0 0;width:100%;max-width:500px;padding:0 0 30px;transform:translateY(100%);transition:transform .3s">';
  html += '<div style="display:flex;justify-content:center;padding:10px 0 0"><div style="width:40px;height:4px;border-radius:2px;background:rgba(255,255,255,.15)"></div></div>';

  // Header
  html += '<div style="padding:18px 20px 14px;text-align:center">';
  html += '<div style="font-size:28px;margin-bottom:8px">🔒</div>';
  html += '<div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:6px">Dokument freischalten</div>';
  html += '<div style="font-size:12.5px;color:#606080">Wählen Sie eine Option</div>';
  html += '</div>';

  // Option 1: Single doc
  html += '<div style="padding:0 16px">';
  html += `<div onclick="stripeCheckout(null,'${dk}','${tier}')" style="display:flex;align-items:center;gap:14px;padding:16px;margin-bottom:10px;background:rgba(212,168,74,.08);border:1.5px solid rgba(212,168,74,.3);border-radius:16px;cursor:pointer;-webkit-tap-highlight-color:transparent">`;
  html += `<span style="font-size:24px">${dp.ic}</span>`;
  html += `<div style="flex:1"><div style="font-size:14px;font-weight:700;color:#fff">${dp.name}</div><div style="font-size:12px;color:#808080;margin-top:2px">Nur dieses Dokument · Einmalig</div></div>`;
  html += `<div style="font-size:18px;font-weight:700;color:#d4a84a">${dp.price}</div>`;
  html += '</div>';

  // Divider
  html += '<div style="display:flex;align-items:center;gap:10px;margin:12px 0"><div style="flex:1;height:1px;background:rgba(255,255,255,.06)"></div><span style="font-size:11px;color:#404050">ODER ABONNEMENT</span><div style="flex:1;height:1px;background:rgba(255,255,255,.06)"></div></div>';

  // Plans
  Object.entries(PLANS).forEach(([key, p]) => {
    const isCurrent = userPlan === key;
    html += `<div onclick="${isCurrent ? '' : "stripeCheckout('"+key+"')"}" style="display:flex;align-items:center;gap:12px;padding:13px 16px;margin-bottom:8px;background:rgba(255,255,255,.03);border:1px solid ${isCurrent ? p.color : 'rgba(255,255,255,.07)'};border-radius:14px;cursor:${isCurrent ? 'default' : 'pointer'};-webkit-tap-highlight-color:transparent">`;
    html += `<span style="font-size:20px">${p.ic}</span>`;
    html += `<div style="flex:1"><div style="font-size:13.5px;font-weight:600;color:#fff">${p.name}</div><div style="font-size:11px;color:#606080;margin-top:2px">${p.docs} Dok/Mo · ${p.rev} Überarb.</div></div>`;
    html += isCurrent
      ? `<div style="font-size:11px;color:${p.color};font-weight:600">Aktiv</div>`
      : `<div style="font-size:15px;font-weight:700;color:${p.color}">${p.price}</div>`;
    html += '</div>';
  });

  html += '<button onclick="document.getElementById(\'paywall-modal\').remove()" style="width:100%;padding:12px;margin-top:8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;color:#606080;font-size:13px;cursor:pointer;font-family:inherit">Abbrechen</button>';
  html += '</div></div>';

  modal.innerHTML = html;
  modal.onclick = e => { if(e.target === modal) modal.remove(); };
  document.body.appendChild(modal);
  requestAnimationFrame(() => { document.getElementById('pw-sheet').style.transform='translateY(0)'; });

  // Store pending doc
  window._pendingDoc = {dk, ans};
}

// Check payment success on load
(function checkPaymentReturn(){
  const params = new URLSearchParams(location.search);
  if(params.get('payment') === 'success'){
    history.replaceState({}, '', location.pathname);
    addMsg('ai', '✅ <strong>Zahlung erfolgreich!</strong> Ihr Dokument wird jetzt erstellt…');
    if(window._pendingDoc){
      setTimeout(() => genDoc(window._pendingDoc.dk, window._pendingDoc.ans), 800);
    }
  }
})();


</script>
<script>

function openCat(key){
  oSB();
  const map={'behoerde':0,'aufenthalt':1,'einwanderung':1,'miete':2,'wohnen':2,'arbeit':3,'steuern':4,'bildung':4,'familie':5,'soziales':6,'gesundheit':6,'verbraucher':7};
  const idx=map[key];
  if(idx===undefined)return;
  setTimeout(()=>{
    const hdrs=document.querySelectorAll('.sb-cat-hdr');
    if(!hdrs[idx])return;
    document.querySelectorAll('.sb-cat-items').forEach(i=>i.classList.remove('open'));
    document.querySelectorAll('.sb-cat-arr').forEach(a=>a.classList.remove('open'));
    hdrs[idx].nextElementSibling?.classList.add('open');
    hdrs[idx].querySelector('.sb-cat-arr')?.classList.add('open');
    hdrs[idx].scrollIntoView({behavior:'smooth',block:'start'});
  },350);
}


// ── CATEGORY DOCS MODAL ───────────────────────────
const CAT_LABELS={
  'vertraege':'Verträge kündigen',
  'miete':'Mieten & Wohnen',
  'arbeit':'Arbeit & Beruf',
  'aufenthalt':'Aufenthalt & Visum',
  'behoerde':'Behörde & Recht',
  'verbraucher':'Verbraucher & Recht',
  'steuern':'Steuern & Finanzen',
  'gesundheit':'Gesundheit',
  'soziales':'Soziales & Familie',
};
const CAT_DOCS={
  'vertraege':[
    {k:'kuendigung_handy',ic:'📱',t:'Kündigung Handyvertrag'},
    {k:'kuendigung_internet',ic:'🌐',t:'Kündigung Internet-/DSL-Vertrag'},
    {k:'kuendigung_fitness',ic:'🏋️',t:'Kündigung Fitnessstudio'},
    {k:'kuendigung_streaming',ic:'📺',t:'Kündigung Streaming-Dienst'},
    {k:'kuendigung_versicherung',ic:'🚗',t:'Kündigung Kfz-Versicherung'},
    {k:'kuendigung_strom',ic:'⚡',t:'Kündigung Strom-/Gasvertrag'},
    {k:'kuendigung_mieter',ic:'🏠',t:'Kündigung Mietvertrag (Mieter)'},
    {k:'widerruf',ic:'↩️',t:'Widerruf (14-Tage-Frist)'},
  ],
  'miete':[
    {k:'mietminderung',ic:'📉',t:'Mietminderung'},
    {k:'maengel_vermieter',ic:'🔧',t:'Mängelanzeige an Vermieter'},
    {k:'widerspruch_mieterhoehung',ic:'📈',t:'Widerspruch gegen Mieterhöhung'},
    {k:'kaution_rueckforderung',ic:'💰',t:'Rückforderung Mietkaution'},
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch Nebenkostenabrechnung'},
  ],
  'arbeit':[
    {k:'eigenkundigung',ic:'🚪',t:'Eigenkündigung'},
    {k:'zeugnis',ic:'📄',t:'Arbeitszeugnis beantragen'},
    {k:'urlaubsantrag',ic:'🏖️',t:'Urlaubsantrag'},
    {k:'homeoffice_antrag',ic:'🏡',t:'Antrag auf Homeoffice'},
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch Abmahnung'},
  ],
  'aufenthalt':[
    {k:'duldung_verlaengerung',ic:'📋',t:'Duldung verlängern'},
    {k:'aufenthaltserlaubnis',ic:'🛂',t:'Aufenthaltserlaubnis beantragen'},
    {k:'widerspruch_ablehnung',ic:'⚖️',t:'Widerspruch gegen Ablehnung'},
    {k:'familiennachzug',ic:'👨‍👩‍👧',t:'Antrag Familiennachzug'},
  ],
  'behoerde':[
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch gegen Bescheid'},
    {k:'bussgeldbescheid',ic:'🚦',t:'Einspruch Bußgeldbescheid'},
    {k:'mahnbescheid_einspruch',ic:'📬',t:'Einspruch Mahnbescheid'},
    {k:'buergergeld',ic:'👥',t:'Widerspruch Bürgergeld/Jobcenter'},
  ],
  'verbraucher':[
    {k:'reklamation',ic:'🛒',t:'Reklamation / Gewährleistung'},
    {k:'widerruf',ic:'↩️',t:'Widerruf Online-Kauf'},
    {k:'mahnung',ic:'💰',t:'Mahnung schreiben'},
    {k:'mahnbescheid_einspruch',ic:'📬',t:'Auf Mahnbescheid reagieren'},
  ],
  'steuern':[
    {k:'einspruch_steuer',ic:'📊',t:'Einspruch gegen Steuerbescheid'},
    {k:'ratenzahlung',ic:'💳',t:'Ratenzahlungsantrag'},
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch Finanzamt'},
  ],
  'gesundheit':[
    {k:'krankenkasse_widerspruch',ic:'🏥',t:'Widerspruch Krankenkasse'},
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch Pflegegrad-Ablehnung'},
    {k:'widerspruch',ic:'💊',t:'Widerspruch Hilfsmittel-Ablehnung'},
  ],
  'soziales':[
    {k:'buergergeld',ic:'👥',t:'Widerspruch Bürgergeld-Bescheid'},
    {k:'familiennachzug',ic:'👨‍👩‍👧',t:'Antrag Familiennachzug'},
    {k:'widerspruch',ic:'⚖️',t:'Widerspruch Sozialamt'},
  ],
};

function showCatDocs(cat){
  const docs=CAT_DOCS[cat];
  if(!docs)return;
  const label=CAT_LABELS[cat]||cat;
  document.getElementById('cat-modal')?.remove();
  const modal=document.createElement('div');
  modal.id='cat-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:700;background:rgba(0,0,0,.65);display:flex;align-items:flex-end;justify-content:center;padding:0;animation:fadeIn .2s';
  modal.onclick=e=>{if(e.target===modal)closeCatModal();};

  let html=`<div id="cat-sheet" style="background:#0d0d1f;border-radius:22px 22px 0 0;width:100%;max-width:500px;max-height:85vh;overflow-y:auto;transform:translateY(0);transition:transform .3s">`;
  // Handle bar
  html+=`<div style="display:flex;justify-content:center;padding:10px 0 0"><div style="width:40px;height:4px;border-radius:2px;background:rgba(255,255,255,.15)"></div></div>`;
  // Header
  html+=`<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px 10px">`;
  html+=`<div style="font-size:17px;font-weight:700;color:#fff">${label}</div>`;
  html+=`<button onclick="closeCatModal()" style="width:30px;height:30px;background:rgba(255,255,255,.1);border:none;border-radius:50%;color:#8080a8;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;-webkit-tap-highlight-color:transparent">×</button>`;
  html+=`</div>`;
  // Doc list
  html+=`<div style="padding:6px 14px 24px">`;
  docs.forEach(d=>{
    html+=`<div onclick="closeCatModal();setTimeout(()=>qS('${d.k}'),200)" style="display:flex;align-items:center;gap:14px;padding:14px 16px;margin-bottom:8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;cursor:pointer;-webkit-tap-highlight-color:transparent;transition:background .15s" onmousedown="this.style.background='rgba(212,168,74,.1)'" onmouseup="this.style.background='rgba(255,255,255,.04)'" ontouchstart="this.style.background='rgba(212,168,74,.08)'" ontouchend="this.style.background='rgba(255,255,255,.04)'">`;
    html+=`<span style="font-size:22px;flex-shrink:0;width:32px;text-align:center">${d.ic}</span>`;
    html+=`<div style="font-size:14px;font-weight:600;color:#fff;flex:1">${d.t}</div>`;
    html+=`<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#505078" stroke-width="2.5" stroke-linecap="round"><polyline points="9,18 15,12 9,6"/></svg>`;
    html+=`</div>`;
  });
  html+=`</div></div>`;
  modal.innerHTML=html;
  document.body.appendChild(modal);
  // Animate in
  const sheet=document.getElementById('cat-sheet');
  sheet.style.transform='translateY(100%)';
  requestAnimationFrame(()=>{sheet.style.transform='translateY(0)';});
}
function closeCatModal(){
  const m=document.getElementById('cat-modal');
  const s=document.getElementById('cat-sheet');
  if(s)s.style.transform='translateY(100%)';
  setTimeout(()=>m?.remove(),280);
}


</script>
<style>
:root{
  --bg:#07070e;--s1:#0d0d1c;--s2:#131325;--s3:#191930;--s4:#21213a;
  --gold:#d4a84a;--gold2:#ecc060;--ga:rgba(212,168,74,.1);--gb:rgba(212,168,74,.28);
  --t1:#f8f8ff;--t2:#e0e0ff;--t3:#a8a8d0;--t4:#606095;
  --gr:#42df94;--rd:#e05050;
  --bdr:rgba(255,255,255,.09);
  --r:10px;--r2:14px;--r3:18px;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%;overflow:hidden;font-family:'Inter',system-ui,sans-serif;background:#07070e!important;color:var(--t1);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
#app{display:flex;height:100dvh;width:100vw}
#sb{width:272px;flex-shrink:0;background:var(--s1);border-right:1px solid var(--bdr);display:flex;flex-direction:column;transition:transform .27s cubic-bezier(.4,0,.2,1);z-index:300}
.sb-brand{padding:20px 16px 0;display:flex;align-items:center;gap:11px;margin-bottom:14px}
.sb-mark{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1.5px solid var(--gb);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,74,.15)}
.sb-mark svg{width:20px;height:20px}
.sb-logo-name{font-size:16px;font-weight:700;color:#fff;letter-spacing:-.4px}
.sb-logo-name em{font-style:normal;color:var(--gold)}
.sb-logo-sub{font-size:9px;color:var(--t4);letter-spacing:.8px;text-transform:uppercase;margin-top:2px}
.sb-country{margin:0 14px 12px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1px solid var(--gb);border-radius:var(--r);padding:10px 12px;display:flex;align-items:center;gap:9px;cursor:pointer;transition:all .18s;box-shadow:0 2px 8px rgba(212,168,74,.08)}
.sb-country:hover{background:var(--s4)}
.sb-cflag{font-size:18px;flex-shrink:0}
.sb-cname{font-size:12px;font-weight:600;color:var(--gold)}
.sb-clang{font-size:10px;color:var(--t4);margin-top:1px}
.sb-country-arrow{margin-left:auto;color:var(--t4);font-size:12px}
.sb-new{margin:0 14px 10px;background:var(--gold);color:#07070e;border:none;border-radius:var(--r);padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;transition:background .15s;width:calc(100% - 28px)}
.sb-new:hover{background:var(--gold2)}
.sb-new svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5}
.sb-plan{margin:0 14px 10px;background:var(--s3);border:1px solid var(--gb);border-radius:var(--r);padding:9px 11px;display:flex;align-items:center;gap:9px}
.sb-plan-ic{width:26px;height:26px;border-radius:6px;background:var(--ga);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-plan-ic svg{width:12px;height:12px;stroke:var(--gold);fill:none;stroke-width:2}
.sb-plan-tier{font-size:11.5px;font-weight:600;color:var(--gold)}
.sb-plan-hint{font-size:9px;color:var(--t4);margin-top:1px}
.sb-plan-btn{margin-left:auto;font-size:10px;font-weight:600;background:var(--gold);color:#07070e;border:none;border-radius:5px;padding:4px 9px;cursor:pointer;font-family:inherit}
.sb-nav{padding:0 10px 10px;border-bottom:1px solid var(--bdr)}
.sn{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:var(--r);cursor:pointer;transition:all .13s;border:1px solid transparent;margin-bottom:2px}
.sn:hover{background:var(--s2)}
.sn.on{background:linear-gradient(135deg,var(--ga),rgba(212,168,74,.05));border-color:var(--gb);box-shadow:0 2px 8px rgba(212,168,74,.1)}
.sn svg{width:15px;height:15px;stroke:var(--t3);fill:none;stroke-width:1.8;flex-shrink:0;transition:stroke .13s}
.sn:hover svg,.sn.on svg{stroke:var(--gold)}
.sn-lbl{font-size:13.5px;color:#eaeaff;font-weight:400;transition:color .13s}
.sn:hover .sn-lbl,.sn.on .sn-lbl{color:#fff;font-weight:500}
.sb-scroll{flex:1;overflow-y:auto;padding:6px 10px 80px;min-height:0}
.sb-scroll::-webkit-scrollbar{width:3px}
.sb-scroll::-webkit-scrollbar-thumb{background:linear-gradient(var(--gold),var(--gb));border-radius:2px}
.sb-sec{font-size:10.5px;font-weight:700;color:#a8a8e0;padding:14px 8px 5px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.6px}
.sb-sec svg{width:11px;height:11px;stroke:var(--t3);fill:none;stroke-width:2;flex-shrink:0}
.si{display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:var(--r);cursor:pointer;transition:background .12s;margin-bottom:1px}
.si:hover{background:rgba(255,255,255,.055)}.si:hover .si-ic{background:var(--s4)}.si:hover .si-ic svg{stroke:var(--gold)!important}
.si-ic{width:28px;height:28px;border-radius:7px;background:var(--s3);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .12s}
.si-tx{font-size:13px;color:#e0e0ff;flex:1;line-height:1.25}.si-ic svg{display:block;width:14px;height:14px;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;fill:none}
.si:hover .si-tx{color:#fff}
.si-tag{font-size:8px;font-weight:700;padding:1px 5px;border-radius:4px;flex-shrink:0}
.tg-n{background:rgba(66,223,148,.1);color:rgba(66,223,148,.9);border:1px solid rgba(66,223,148,.2)}
.tg-p{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.sb-foot{padding:11px 14px;border-top:1px solid var(--bdr);flex-shrink:0}
.sb-foot-txt{font-size:10px;color:var(--t4)}
.sb-badges{display:flex;gap:4px;margin-top:5px;flex-wrap:wrap}
.bdg{font-size:8.5px;font-weight:600;padding:2px 7px;border-radius:4px}
.bdg-g{background:rgba(66,223,148,.1);color:rgba(66,223,148,.95);border:1px solid rgba(66,223,148,.2)}
.bdg-o{background:rgba(212,168,74,.12);color:var(--gold2);border:1px solid var(--gb)}
#mn{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
#tb{height:54px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--bdr);background:var(--s1);flex-shrink:0}
.tb-l{display:flex;align-items:center;gap:11px}
.tb-ham{width:34px;height:34px;background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r);display:none;align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.tb-ham span{display:block;width:14px;height:1.5px;background:var(--t2);border-radius:1px}
.tb-name{font-size:15px;font-weight:700;color:#fff;letter-spacing:-.3px}
.tb-name em{font-style:normal;color:var(--gold)}
.tb-r{display:flex;align-items:center;gap:7px}
.tb-btn{display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:var(--r);font-size:11.5px;font-weight:500;cursor:pointer;transition:all .14s;white-space:nowrap;border:1px solid var(--bdr);background:var(--s2);color:#d0d0f8;font-family:inherit}
.tb-btn:hover{border-color:var(--gb);color:var(--gold)}
.tb-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.tb-btn.hi{border-color:var(--gb);color:var(--gold);background:var(--ga)}
.tb-btn span{display:inline}
.tb-flag{font-size:16px;cursor:pointer;padding:4px 8px;border-radius:var(--r);background:var(--s2);border:1px solid var(--bdr);transition:all .14s}
.tb-flag:hover{border-color:var(--gb)}
.tb-online{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--t3);padding:4px 9px;background:var(--s2);border:1px solid var(--bdr);border-radius:6px}
.tb-dot{width:6px;height:6px;border-radius:50%;background:var(--gr);box-shadow:0 0 6px rgba(66,223,148,.5);animation:pulse 2s infinite}@keyframes pulse{0%,100%{box-shadow:0 0 4px rgba(66,223,148,.4)}50%{box-shadow:0 0 10px rgba(66,223,148,.8)}}
.pnl{display:none;flex:1;flex-direction:column;overflow:hidden;min-height:0;background:#07070e}
.pnl.on{display:flex}
#msgs{flex:1;overflow-y:auto;min-height:0;overscroll-behavior:contain;width:100%;overflow-x:hidden;background:#07070e}
#msgs::-webkit-scrollbar{width:4px}
#msgs::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
#mi{max-width:740px;margin:0 auto;padding:28px 20px 16px;width:100%;box-sizing:border-box;background:#07070e}
.wlc{text-align:center;padding:36px 0 22px;max-width:520px;margin:0 auto;width:100%}
.wlc-logo{width:60px;height:60px;background:linear-gradient(135deg,var(--s2),var(--s3));border:1.5px solid var(--gb);border-radius:16px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(212,168,74,.2)}
.wlc-logo svg{width:26px;height:26px}
.wlc h2{font-size:27px;font-weight:800;color:#fff;margin-bottom:10px;letter-spacing:-.6px}
.wlc p{font-size:13.5px;color:#9898c8;line-height:1.7;margin-bottom:20px}
.wgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;text-align:left}
.wc{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px;cursor:pointer;transition:all .2s}
.wc:hover{border-color:var(--gb);background:var(--s3);transform:translateY(-3px);box-shadow:0 8px 24px rgba(212,168,74,.12)}
.wc-ic{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:10px}
.wc:hover .wc-ic{background:var(--s4)}
.wc-t{font-size:13px;font-weight:600;color:#fff;margin-bottom:2px}
.wc-s{font-size:11px;color:#9898c8}
.wphoto{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.04));border:1.5px dashed var(--gb);border-radius:var(--r2);padding:14px 18px;cursor:pointer;transition:all .22s;margin-bottom:14px}
.wphoto:hover{background:rgba(212,168,74,.14);transform:translateY(-1px)}
.wphoto-ic{width:40px;height:40px;border-radius:10px;background:rgba(212,168,74,.12);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wphoto-ic svg{width:19px;height:19px;stroke:var(--gold);fill:none;stroke-width:1.8}
.wphoto-t{font-size:13px;font-weight:600;color:var(--gold);margin-bottom:2px}
.wphoto-s{font-size:11px;color:#9898c8}
.recent-bar{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:12px 14px;margin-bottom:14px;text-align:left}
.recent-bar-t{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--t4);margin-bottom:8px}
.recent-item{display:flex;align-items:center;gap:9px;padding:6px 9px;border-radius:var(--r);cursor:pointer;transition:background .13s}
.recent-item:hover{background:var(--s3)}
.recent-item-ic{font-size:13px;flex-shrink:0}
.recent-item-n{font-size:12px;color:#d0d0f8;flex:1}
.recent-item-d{font-size:10px;color:var(--t4)}
.lang-bar{display:flex;align-items:center;gap:8px;justify-content:center}
.lang-txt{font-size:10px;color:var(--t4)}
.lflags{display:flex;gap:4px}
.lf{font-size:14px;opacity:.2;transition:opacity .2s}
.lf.on{opacity:1}
.msg{display:flex;gap:12px;margin-bottom:22px;animation:fadeUp .22s ease;width:100%}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.msg.user{flex-direction:row-reverse}
.av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.msg.ai .av{background:linear-gradient(135deg,var(--s3),var(--s4));border:1.5px solid var(--gb);color:var(--gold);font-weight:800;font-size:10px;letter-spacing:.5px}
.msg.user .av{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;font-size:12px;font-weight:800}
.mbody{flex:1;min-width:0}
.mname{font-size:9px;font-weight:600;color:var(--t4);margin-bottom:5px;letter-spacing:.5px;text-transform:uppercase}
.msg.user .mname{text-align:right}
.bbl{font-size:14.5px;line-height:1.75;color:#f5f5ff}
.msg.user .bbl{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:10px 14px;display:inline-block;max-width:100%}
.photo-prev{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;max-width:240px;margin-top:6px}
.photo-prev img{width:100%;display:block;max-height:160px;object-fit:cover}
.photo-prev-lbl{padding:6px 10px;font-size:10px;color:var(--t3)}
.typing{display:flex;gap:5px;padding:8px 0;align-items:center}
.typing span{width:6px;height:6px;border-radius:50%;background:var(--t4);animation:tDot 1s infinite}
.typing span:nth-child(2){animation-delay:.2s}
.typing span:nth-child(3){animation-delay:.4s}
@keyframes tDot{0%,70%,100%{transform:scale(.5);opacity:.3}35%{transform:scale(1);opacity:1}}
.pbox{background:linear-gradient(135deg,var(--s2),var(--s3));border:1px solid var(--bdr);border-radius:var(--r2);padding:16px;margin-top:8px;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.pbox-hdr{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--gold);opacity:.7;margin-bottom:10px}
.ps{display:flex;align-items:center;gap:10px;padding:7px 9px;border-radius:var(--r);font-size:13px;color:#a0a0d0;transition:all .26s;margin-bottom:3px}
.ps.on{background:var(--ga);border:1px solid var(--gb);color:#fff;font-weight:500}
.ps.done{color:var(--gr)}
.ps-ic{width:22px;height:22px;border-radius:6px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.ps.on .ps-ic{background:var(--ga)}
.ps.done .ps-ic{background:rgba(66,223,148,.1)}
.cgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:10px}
.ccard{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 9px;cursor:pointer;transition:all .18s;text-align:center}
.ccard:hover{border-color:var(--gb);background:var(--s3);transform:translateY(-1px)}
.ccard-ic{font-size:19px;margin-bottom:6px;filter:grayscale(0)}
.ccard-n{font-size:11.5px;font-weight:500;color:#d8d8ff}
.ccard:hover .ccard-n{color:#fff}
.dlist{display:flex;flex-direction:column;gap:3px;margin-top:8px}
.drow{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid transparent;background:var(--s2)}
.drow:hover{border-color:var(--gb);background:var(--s3)}
.drow-ic{width:30px;height:30px;border-radius:8px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.drow-n{font-size:13px;font-weight:600;color:#fff;flex:1}
.drow-s{font-size:10.5px;color:#9898c8;margin-top:1px}
.dprice{font-size:11px;font-weight:600;padding:2px 7px;border-radius:5px;flex-shrink:0}
.dp-free{background:rgba(66,223,148,.08);color:rgba(66,223,148,.9);border:1px solid rgba(66,223,148,.15)}
.dp-pay{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.dp-abo{background:rgba(100,136,255,.08);color:rgba(140,160,255,.9);border:1px solid rgba(100,136,255,.15)}
.fbox{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r3);overflow:hidden;margin-top:10px;width:100%}
.fhdr{padding:14px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.02)}
.fhdr-ic{width:30px;height:30px;border-radius:8px;background:var(--ga);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.fhdr-t{font-size:13px;font-weight:600;color:#fff}
.fhdr-cc{margin-left:auto;font-size:11px;color:var(--gold);background:var(--ga);border:1px solid var(--gb);padding:2px 8px;border-radius:5px}
.fbody{padding:14px 16px}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.fful{grid-column:1/-1}
.fi{display:flex;flex-direction:column;gap:4px}
.fi label{font-size:10px;font-weight:700;color:#b0b0e0;text-transform:uppercase;letter-spacing:.6px}
.fi input,.fi textarea{background:var(--bg);border:1px solid rgba(255,255,255,.09);border-radius:var(--r);padding:10px 12px;font-size:14px;color:#f8f8ff;outline:none;font-family:inherit;transition:all .18s;width:100%;-webkit-appearance:none}
.fi input:focus,.fi textarea:focus{border-color:rgba(212,168,74,.4);box-shadow:0 0 0 3px rgba(212,168,74,.06)}
.fi input::placeholder,.fi textarea::placeholder{color:#505090}
.fi input[type=date]{color-scheme:dark;cursor:pointer;min-height:44px;font-size:15px;padding:11px 12px}
.fi input[type=date]::-webkit-calendar-picker-indicator{filter:invert(.3) sepia(1) saturate(2) hue-rotate(8deg);cursor:pointer}
.prog-bar{height:3px;background:rgba(255,255,255,.06);border-radius:2px;margin-bottom:14px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold2),#f0e080);border-radius:2px;transition:width .4s cubic-bezier(.4,0,.2,1);box-shadow:0 0 8px rgba(212,168,74,.4)}
.dsgvo-n{display:flex;gap:7px;background:rgba(66,223,148,.04);border:1px solid rgba(66,223,148,.1);border-radius:var(--r);padding:9px 11px;font-size:11.5px;color:rgba(66,223,148,.8);margin-bottom:11px;line-height:1.6}
.btn-g{width:100%;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;border:none;border-radius:var(--r);padding:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:7px;letter-spacing:.2px;box-shadow:0 4px 14px rgba(212,168,74,.25)}
.btn-g:hover{background:linear-gradient(135deg,var(--gold2),#f0d070);transform:translateY(-1px);box-shadow:0 6px 20px rgba(212,168,74,.35)}
.btn-g:disabled{opacity:.5;cursor:default}
.btn-s{background:var(--s3);color:#d0d0f8;border:1px solid var(--bdr);border-radius:var(--r);padding:9px 14px;font-size:12.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .14s}
.btn-s:hover{border-color:var(--gb);color:var(--gold)}
.fubox{background:rgba(100,136,255,.05);border:1px solid rgba(100,136,255,.15);border-radius:var(--r2);padding:14px;margin-top:10px}
.fubox-t{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(140,170,255,.85);margin-bottom:10px}
.fubox-msg{font-size:12.5px;color:#d0d0ff;margin-bottom:12px;line-height:1.6}
.rbox{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r3);overflow:hidden;margin-top:10px;box-shadow:0 4px 24px rgba(0,0,0,.3);width:100%}
.rhdr{padding:11px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.rtitle{font-size:13px;font-weight:600;color:#fff}
.rcc{font-size:10px;color:var(--gold);background:var(--ga);border:1px solid var(--gb);padding:2px 8px;border-radius:5px}
.racts{display:flex;gap:5px;flex-wrap:wrap}
.act{display:flex;align-items:center;gap:4px;background:var(--s3);border:1px solid var(--bdr);color:#d0d0f8;font-size:11px;font-weight:500;padding:5px 11px;border-radius:var(--r);cursor:pointer;transition:all .14s;white-space:nowrap;font-family:inherit}
.act:hover{border-color:var(--gb);color:var(--gold)}
.act.gold{background:var(--ga);border-color:var(--gb);color:var(--gold)}
.act.kept{border-color:rgba(66,223,148,.2);color:rgba(66,223,148,.85);background:rgba(66,223,148,.05)}
.act svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.rcnt{padding:14px 16px;max-height:280px;overflow-y:auto}
.rcnt::-webkit-scrollbar{width:3px}
.rcnt::-webkit-scrollbar-thumb{background:var(--s4)}
.rpre{font-family:'Courier New',monospace;font-size:11px;line-height:1.9;color:#d8d8ff;white-space:pre-wrap}
.blink{display:inline-block;width:2px;height:1.1em;background:var(--gold);animation:bl .9s step-end infinite;vertical-align:text-bottom}
@keyframes bl{50%{opacity:0}}
.rqual{display:flex;align-items:center;gap:7px;padding:10px 16px;border-top:1px solid var(--bdr);font-size:11px;color:rgba(66,223,148,.85);background:linear-gradient(135deg,rgba(66,223,148,.04),rgba(66,223,148,.01));font-weight:500}
.rns{padding:11px 16px;border-top:1px solid var(--bdr)}
.rns-t{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);margin-bottom:7px}
.rns-item{font-size:12.5px;color:#d0d0f8;padding:4px 0;border-bottom:1px solid var(--bdr)}
.rns-item:last-child{border-bottom:none}
.offbox{background:var(--ga);border:1px solid var(--gb);border-radius:var(--r);padding:11px 13px;margin:0 16px 12px}
.offbox-t{font-size:11.5px;font-weight:600;color:var(--gold);margin-bottom:3px}
.offbox-d{font-size:11px;color:#d0d0f8;margin-bottom:7px;line-height:1.5}
.offbox-a{display:inline-flex;align-items:center;gap:5px;background:var(--gold);color:#07070e;border:none;padding:5px 11px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit}
.keep-row{padding:9px 16px;border-top:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.keep-row span{font-size:11.5px;color:var(--t3)}
.chips{display:flex;gap:5px;flex-wrap:wrap;padding:0 16px 12px}
.chip{background:var(--s3);border:1px solid var(--bdr);color:#c0c0e8;font-size:12px;padding:7px 14px;border-radius:100px;cursor:pointer;transition:all .18s;font-family:inherit;font-weight:500}
.chip:hover{border-color:var(--gb);color:var(--gold)}
.research-box{background:rgba(100,130,255,.04);border:1px solid rgba(100,130,255,.1);border-radius:var(--r);padding:11px 13px;margin-top:8px}
.research-t{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(130,160,255,.8);margin-bottom:6px}
.research-item{font-size:12px;color:#d0d0f8;padding:2px 0;line-height:1.55}
.pnl-scroll{flex:1;overflow-y:auto;padding:22px}
.pnl-scroll::-webkit-scrollbar{width:4px}
.pnl-scroll::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.sec-hdr{max-width:780px;margin:0 auto 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.sec-title{font-size:20px;font-weight:700;color:#fff;letter-spacing:-.4px}
.sec-sub{font-size:12px;color:var(--t3);margin-top:3px}
.ant-stats{display:flex;gap:8px;max-width:780px;margin:0 auto 16px;flex-wrap:wrap}
.astat{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 15px;flex:1;min-width:90px}
.astat-n{font-size:22px;font-weight:700;color:#fff;line-height:1}
.astat-n.gold{color:var(--gold)}
.astat-n.gr{color:var(--gr)}
.astat-l{font-size:10.5px;color:var(--t3);margin-top:3px}
.ant-list{max-width:780px;margin:0 auto;display:flex;flex-direction:column;gap:8px}
.acard{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;transition:border .2s}
.acard:hover{border-color:rgba(255,255,255,.12)}
.acard-hdr{padding:13px 15px;display:flex;align-items:center;gap:11px;border-bottom:1px solid var(--bdr);flex-wrap:wrap}
.acard-ic{width:36px;height:36px;border-radius:9px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.acard-info{flex:1;min-width:100px}
.acard-name{font-size:14px;font-weight:700;color:#fff;letter-spacing:-.2px}
.acard-meta{font-size:10.5px;color:var(--t3);margin-top:2px}
.acard-acts{display:flex;gap:5px;flex-wrap:wrap}
.ab{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:5px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);color:#d0d0f8;font-family:inherit}
.ab svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
.ab:hover{border-color:var(--gb);color:var(--gold)}
.ab.del:hover{border-color:rgba(224,80,80,.3);color:var(--rd)}
.ab.kept{border-color:rgba(66,223,148,.2);color:rgba(66,223,148,.85);background:rgba(66,223,148,.05)}
.acard-body{padding:11px 15px}
.aprev{font-family:'Courier New',monospace;font-size:11px;line-height:1.7;color:var(--t3);background:var(--bg);border-radius:var(--r);padding:9px 11px;max-height:60px;overflow:hidden;position:relative}
.aprev-fade{position:absolute;bottom:0;left:0;right:0;height:24px;background:linear-gradient(transparent,var(--bg))}
.aexp{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:500;padding:2px 8px;border-radius:100px;margin-top:9px}
.ae-soon{background:rgba(224,80,80,.08);color:rgba(224,80,80,.8);border:1px solid rgba(224,80,80,.15)}
.ae-kept{background:rgba(66,223,148,.08);color:rgba(66,223,148,.8);border:1px solid rgba(66,223,148,.15)}
.ae-norm{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.ant-empty{text-align:center;padding:50px 20px;max-width:340px;margin:0 auto}
.ant-empty-ic{width:56px;height:56px;border-radius:14px;background:var(--s2);border:1px solid var(--bdr);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;opacity:.45}
.ant-empty-ic svg{width:26px;height:26px;stroke:var(--t3);fill:none;stroke-width:1.5}
.ant-empty-t{font-size:15px;font-weight:600;color:#d0d0f8;margin-bottom:5px}
.ant-empty-s{font-size:12.5px;color:var(--t3);line-height:1.6}
.konto-wrap{max-width:580px;margin:0 auto}
.konto-plan{background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.04));border:1px solid var(--gb);border-radius:var(--r2);padding:22px;margin-bottom:14px;position:relative;overflow:hidden}.konto-plan::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
.kp-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.kp-name{font-size:15px;font-weight:700;color:var(--gold)}
.kp-badge{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;font-size:9px;font-weight:800;padding:4px 12px;border-radius:100px;letter-spacing:.5px}
.kp-feats{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.kp-feat{display:flex;align-items:center;gap:7px;font-size:12.5px;color:#d0d0f8}
.kp-feat svg{width:12px;height:12px;stroke:var(--gr);fill:none;stroke-width:2.5;flex-shrink:0}
.ksec{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;margin-bottom:11px}
.ksec-hdr{padding:14px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.02)}
.ksec-hdr svg{width:14px;height:14px;stroke:var(--gold);fill:none;stroke-width:1.8;flex-shrink:0}
.ksec-hdr-t{font-size:13px;font-weight:700;color:#fff;letter-spacing:-.2px}
.ksec-body{padding:13px 15px}
.krow{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bdr)}
.krow:last-child{border-bottom:none}
.krow-l{font-size:13px;color:#d0d0f8}
.krow-r{font-size:13px;font-weight:500;color:#fff}
.krow-r.gold{color:var(--gold)}
.lang-grid{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.lang-opt{display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);font-size:12px;color:#d0d0f8;font-family:inherit}
.lang-opt:hover{border-color:var(--gb);color:var(--gold)}
.lang-opt.on{background:var(--ga);border-color:var(--gb);color:var(--gold);font-weight:600}
.lang-flag{font-size:15px}
.country-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px}
.co{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3)}
.co:hover{border-color:var(--gb)}
.co.on{background:var(--ga);border-color:var(--gb)}
.co-flag{font-size:16px;flex-shrink:0}
.co-n{font-size:12px;color:#d0d0f8}
.co.on .co-n{color:var(--gold);font-weight:600}
.kdsgvo{background:linear-gradient(135deg,rgba(66,223,148,.06),rgba(66,223,148,.02));border:1px solid rgba(66,223,148,.18);border-radius:var(--r2);padding:18px;margin-bottom:11px;position:relative;overflow:hidden}
.kdsgvo::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(66,223,148,.5),transparent)}
.kdsgvo h4{font-size:13.5px;font-weight:700;color:rgba(66,223,148,.95);margin-bottom:12px;display:flex;align-items:center;gap:8px;letter-spacing:.2px}
.kdsgvo h4 svg{width:16px;height:16px;stroke:rgba(66,223,148,.9);fill:none;stroke-width:2;flex-shrink:0}
.kdsgvo-item{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:#d0d0f0;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);line-height:1.6}
.kdsgvo-item:last-child{border-bottom:none}
.kdsgvo-item svg{width:12px;height:12px;stroke:rgba(66,223,148,.8);fill:none;stroke-width:2.5;flex-shrink:0;margin-top:2px}
.pm{position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:500;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)}
.pm-inner{background:var(--s1);border:1px solid rgba(255,255,255,.08);border-radius:var(--r3);padding:28px;max-width:660px;width:calc(100% - 32px);max-height:92vh;overflow-y:auto;position:relative}
.pm-close{position:absolute;top:16px;right:16px;width:28px;height:28px;background:var(--s3);border:1px solid var(--bdr);border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);font-size:14px;font-family:inherit}
.pm-title{font-size:21px;font-weight:700;color:#fff;margin-bottom:4px;letter-spacing:-.4px}
.pm-sub{font-size:13px;color:var(--t3);margin-bottom:20px}
.pm-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}
.pc{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:20px;position:relative}
.pc.feat{border-color:var(--gold)}
.pc.feat::before{content:'Beliebteste';position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--gold);color:#07070e;font-size:8px;font-weight:700;padding:3px 11px;border-radius:100px;white-space:nowrap;letter-spacing:.5px}
.pc-tier{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--t3);margin-bottom:9px}
.pc-amt{font-size:30px;font-weight:700;color:#fff;line-height:1;margin-bottom:3px}
.pc.feat .pc-amt{color:var(--gold)}
.pc-per{font-size:11px;color:var(--t3);margin-bottom:12px}
.pc-sep{height:1px;background:var(--bdr);margin-bottom:12px}
.pc-feats{display:flex;flex-direction:column;gap:6px;margin-bottom:15px}
.pc-feat{display:flex;align-items:flex-start;gap:6px;font-size:11.5px;color:#c8c8f0}
.pc-feat svg{width:11px;height:11px;stroke:var(--gr);fill:none;stroke-width:2.5;flex-shrink:0;margin-top:2px}
.pc-btn{width:100%;padding:9px;border-radius:var(--r);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);color:#fff}
.pc-btn:hover{border-color:var(--gb);color:var(--gold)}
.pc-btn.g{background:var(--gold);color:#07070e;border-color:var(--gold)}
.pc-btn.g:hover{background:var(--gold2)}
#ia{padding:11px 17px;padding-bottom:max(11px,env(safe-area-inset-bottom));border-top:1px solid var(--bdr);flex-shrink:0;background:var(--s1)}
.ia-inner{max-width:740px;margin:0 auto;position:relative}
#inp{width:100%;background:var(--s2);border:1.5px solid var(--bdr);border-radius:14px;padding:13px 106px 13px 18px;font-size:14px;color:#f8f8ff;outline:none;font-family:inherit;resize:none;max-height:120px;min-height:52px;line-height:1.55;transition:all .18s;-webkit-appearance:none;display:block}
#inp:focus{border-color:rgba(212,168,74,.5);box-shadow:0 0 0 3px rgba(212,168,74,.08)}
#inp::placeholder{color:#5050a0}
.ia-btns{position:absolute;right:8px;bottom:8px;display:flex;gap:5px;align-items:center}
#pb{width:34px;height:34px;background:var(--s3);border:1px solid var(--gb);border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .14s;flex-shrink:0}
#pb:hover{background:var(--ga)}
#pb svg{width:16px;height:16px;stroke:var(--gold);fill:none;stroke-width:1.8}
#sbtn{width:38px;height:38px;background:linear-gradient(135deg,var(--gold),var(--gold2));border:none;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .14s;flex-shrink:0;box-shadow:0 3px 10px rgba(212,168,74,.3)}
#sbtn:hover{background:var(--gold2);transform:scale(1.06)}
#sbtn:disabled{background:var(--s3);cursor:default;transform:none}
#sbtn svg{width:14px;height:14px;fill:none;stroke:#07070e;stroke-width:2;flex-shrink:0}
#sbtn:disabled svg{stroke:var(--t4)}
.ia-foot{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:6px}
.ia-foot-txt{font-size:10px;color:var(--t4)}
.ia-lfs{display:flex;gap:3px}
.lf2{font-size:12px;opacity:.2;transition:opacity .2s}
.lf2.on{opacity:1}
input[type=file]{display:none}
#ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:200;backdrop-filter:blur(4px)}
@media(max-width:700px){
  /* Sidebar */
  #sb{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);box-shadow:8px 0 32px rgba(0,0,0,.9);width:290px}
  #sb.open{transform:translateX(0)}
  #ov.show{display:block}
  .tb-ham{display:flex}
  
  /* Topbar mobile */
  #tb{height:58px;padding:0 14px}
  .tb-btn span{display:none}
  .tb-btn{padding:8px 10px;min-width:38px;justify-content:center}
  .tb-online{display:none}
  .tb-name{font-size:17px}
  .tb-flag{font-size:18px;padding:5px 9px}
  
  /* Chat area */
  #mi{padding:16px 14px 10px;max-width:100%}
  
  /* Welcome */
  .wlc{padding:24px 0 16px}
  .wlc h2{font-size:22px}
  .wlc p{font-size:13px}
  .wgrid{grid-template-columns:1fr 1fr;gap:7px}
  .wc{padding:12px}
  .wc-ic{width:30px;height:30px}
  .wphoto{padding:12px 14px}
  
  /* Category grid */
  .cgrid{grid-template-columns:1fr 1fr}
  
  /* Forms */
  .fgrid{grid-template-columns:1fr}
  .fful{grid-column:1}
  .fbody{padding:12px 14px}
  .fhdr{padding:12px 14px}
  .fi input,.fi textarea{font-size:16px;padding:12px 14px}
  .fi label{font-size:11px}
  .btn-g{padding:14px;font-size:15px;border-radius:12px}
  
  /* Result */
  .racts,.acard-acts{flex-wrap:wrap}
  .act{flex:1;justify-content:center;padding:8px 10px}
  .rhdr{padding:12px 14px}
  .rcnt{padding:12px 14px;max-height:220px}
  .rns{padding:10px 14px}
  .chips{padding:0 14px 12px;gap:6px}
  .chip{font-size:12.5px;padding:8px 14px}
  .keep-row{padding:10px 14px}
  
  /* Pricing */
  .pm-grid{grid-template-columns:1fr}
  .pm-inner{padding:22px 18px}
  
  /* Input area */
  #ia{padding:10px 12px;padding-bottom:max(10px,env(safe-area-inset-bottom))}
  #inp{font-size:16px;min-height:48px;padding:13px 108px 13px 16px;border-radius:16px}
  .ia-btns{right:9px;bottom:8px}
  #sbtn{width:38px;height:38px;border-radius:10px}
  #pb{width:36px;height:36px;border-radius:9px}
  .ia-foot-txt{display:none}
  .ia-lfs{gap:4px}
  .lf2{font-size:13px}
  
  /* Konto/Settings */
  .country-grid{grid-template-columns:1fr 1fr}
  .lang-grid{gap:7px}
  .lang-opt{padding:8px 12px;font-size:12.5px}
  .konto-wrap{padding:0 4px}
  .pnl-scroll{padding:16px 14px}
  .sec-title{font-size:18px}
  .kdsgvo{padding:14px}
  .kdsgvo-item{font-size:13px}
  
  /* Anträge */
  .acard-hdr{padding:12px 13px;gap:9px}
  .acard-body{padding:10px 13px}
  .acard-name{font-size:13.5px}
  .ant-stats{gap:6px}
  .astat{padding:11px 13px}
  .astat-n{font-size:20px}
  
  /* Messages */
  .msg{gap:9px;margin-bottom:18px}
  .av{width:28px;height:28px;font-size:10px}
  .bbl{font-size:14px}
  .mname{font-size:8.5px}
  
  /* Sidebar mobile premium */
  .sb-brand{padding:18px 14px 0}
  .sb-new{margin:0 12px 10px;padding:11px 14px;font-size:14px}
  .sb-plan{margin:0 12px 10px;padding:10px 11px}
  .sb-nav{padding:0 9px 9px}
  .sn{padding:10px 11px}
  .sn-lbl{font-size:14px}
  .sb-scroll{padding:5px 9px 80px}
  .si{padding:8px 10px}
  .si-tx{font-size:13.5px}
  .sb-sec{font-size:11px;padding:14px 7px 5px}
  .si-ic{width:30px;height:30px}
  
  /* Process box */
  .ps{font-size:13.5px;padding:8px 10px}
  .pbox{padding:14px}
  
  /* Recent bar */
  .recent-bar{padding:11px 13px;margin-bottom:12px}
  .recent-item{padding:7px 10px}
  .recent-item-n{font-size:12.5px}
  
  /* Photo preview */
  .photo-prev{max-width:200px}
  
  /* Upgrade button mobile */
  .sb-plan-btn{font-size:11px;padding:5px 10px}
  .sb-country{margin:0 12px 11px;padding:10px 12px}
}

/* iPhone safe area */
@supports (padding-bottom: env(safe-area-inset-bottom)){
  #ia{padding-bottom:max(12px,env(safe-area-inset-bottom))}
}
@media(min-width:701px){
  #mi{padding-left:24px;padding-right:24px}
  .wlc{max-width:500px}
  .fbox,.rbox,.pbox,.fubox{max-width:740px}
  .msg.user{justify-content:flex-end}
  .msg.user .mbody{max-width:85%}
}

/* ── LEGAL MODALS ────────────────────────────────── */
.lm{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:600;display:none;align-items:flex-end;justify-content:center;backdrop-filter:blur(8px)}
.lm.on{display:flex}
.lm-box{background:var(--s1);border:1px solid var(--bdr);border-radius:var(--r3) var(--r3) 0 0;width:100%;max-width:760px;max-height:88vh;display:flex;flex-direction:column;animation:slideUp .28s cubic-bezier(.4,0,.2,1)}
@keyframes slideUp{from{transform:translateY(60px);opacity:0}to{transform:translateY(0);opacity:1}}
.lm-hdr{padding:16px 22px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.lm-title{font-size:16px;font-weight:700;color:#fff;letter-spacing:-.3px}
.lm-close{width:30px;height:30px;background:var(--s3);border:1px solid var(--bdr);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);font-size:14px;font-family:inherit;transition:all .15s}
.lm-close:hover{background:var(--s4);color:#fff}
.lm-body{flex:1;overflow-y:auto;padding:24px 22px;min-height:0}
.lm-body::-webkit-scrollbar{width:4px}
.lm-body::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.lm-sec{margin-bottom:24px}
.lm-sec-n{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);opacity:.8;margin-bottom:8px}
.lm-sec h2{font-size:16px;font-weight:700;color:#fff;margin-bottom:12px;letter-spacing:-.3px}
.lm-sec h3{font-size:13.5px;font-weight:600;color:var(--t2);margin:14px 0 6px}
.lm-sec p{font-size:13px;color:var(--t2);line-height:1.75;margin-bottom:8px}
.lm-sec ul{list-style:none;padding:0;margin:6px 0 10px}
.lm-sec li{font-size:13px;color:var(--t2);padding:4px 0 4px 18px;position:relative;line-height:1.6}
.lm-sec li::before{content:'';position:absolute;left:0;top:11px;width:5px;height:5px;border-radius:50%;background:var(--gold);opacity:.6}
.lm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0}
.lm-card{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 15px}
.lm-card-l{font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.lm-card-v{font-size:13px;color:var(--t1);font-weight:500;line-height:1.5}
.lm-card-v a{color:var(--gold);text-decoration:none}
.lm-note{background:rgba(66,223,148,.05);border:1px solid rgba(66,223,148,.15);border-radius:var(--r2);padding:13px 15px;margin:12px 0}
.lm-note p{color:rgba(66,223,148,.9);font-size:12.5px;margin:0}
.lm-warn{background:var(--ga);border:1px solid var(--gb);border-radius:var(--r2);padding:13px 15px;margin:12px 0}
.lm-warn p{color:var(--gold2);font-size:12.5px;margin:0}
.lm-table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12.5px}
.lm-table th{text-align:left;padding:8px 12px;color:var(--gold);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;border-bottom:1px solid var(--bdr)}
.lm-table td{padding:9px 12px;color:var(--t2);border-bottom:1px solid rgba(255,255,255,.04)}
.lm-table tr:last-child td{border-bottom:none}
.lm-table tr:hover td{background:rgba(255,255,255,.02)}
.lm-yes{color:var(--gr)}
.lm-no{color:var(--t4)}
.lm-diff{color:var(--gold)}
.lm-b2b{border-color:rgba(102,136,255,.25);background:linear-gradient(135deg,rgba(102,136,255,.06),var(--s2))}
.lm-b2b-badge{position:absolute;top:-10px;right:16px;background:rgba(102,136,255,.2);color:rgba(160,190,255,.9);border:1px solid rgba(102,136,255,.3);font-size:9px;font-weight:700;padding:2px 9px;border-radius:100px}
@media(max-width:700px){.lm-grid{grid-template-columns:1fr}.lm-box{border-radius:var(--r3) var(--r3) 0 0;max-height:92vh}}

/* ── PREMIUM WELCOME ─────────────────────────────── */
.wlc{text-align:center;padding:24px 0 16px;max-width:560px;margin:0 auto;width:100%}
.wlc-hero{margin-bottom:18px}
.wlc-mark{width:52px;height:52px;background:linear-gradient(135deg,var(--s2),var(--s3));border:1.5px solid var(--gb);border-radius:15px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(212,168,74,.18)}
.wlc h2{font-size:24px;font-weight:800;color:#fff;margin-bottom:8px;letter-spacing:-.5px}
.wlc p{font-size:13px;color:#9090b8;line-height:1.7;margin-bottom:14px}
.wlc-stats{display:flex;align-items:center;justify-content:center;gap:0;background:var(--s2);border:1px solid var(--bdr);border-radius:100px;padding:8px 18px;margin-bottom:0;width:fit-content;margin:0 auto}
.wlc-stat{display:flex;flex-direction:column;align-items:center;padding:0 12px}
.wlc-stat-n{font-size:14px;font-weight:800;color:#fff;line-height:1}
.wlc-stat-l{font-size:9px;color:var(--t4);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.wlc-stat-div{width:1px;height:28px;background:var(--bdr)}

/* ── CATEGORY GRID ───────────────────────────────── */
.wcat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin:16px 0;text-align:left}
.wcat{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 11px;cursor:pointer;transition:all .18s}
.wcat:hover{border-color:rgba(255,255,255,.15);background:var(--s3);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.wcat-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;margin-bottom:9px}
.wcat-ic svg{width:16px;height:16px}
.wcat-t{font-size:12.5px;font-weight:700;color:#fff;margin-bottom:2px;letter-spacing:-.2px}
.wcat-s{font-size:10.5px;color:var(--t3);line-height:1.4}

/* OLD wgrid — keep for fallback */
.wgrid{display:none}
.wc{display:none}

@media(max-width:700px){
  .wcat-grid{grid-template-columns:repeat(3,1fr);gap:6px}
  .wcat{padding:11px 9px}
  .wcat-ic{width:30px;height:30px;border-radius:8px;margin-bottom:7px}
  .wcat-t{font-size:11.5px}
  .wcat-s{font-size:9.5px}
  .wlc h2{font-size:20px}
  .wlc-stats{padding:7px 14px}
  .wlc-stat{padding:0 8px}
  .wlc-stat-n{font-size:13px}
}
@media(max-width:380px){
  .wcat-grid{grid-template-columns:repeat(2,1fr)}
}

/* ── PREMIUM MOBILE CATEGORIES ──────────────────── */
.wcat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:16px 0}
.wcat{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:14px 10px;cursor:pointer;transition:all .2s;text-align:left;position:relative;overflow:hidden}
.wcat::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.03),transparent);pointer-events:none}
.wcat:hover,.wcat:active{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.4);border-color:rgba(255,255,255,.15)}
.wcat-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;flex-shrink:0}
.wcat-ic svg{width:18px;height:18px}
.wcat-t{font-size:12px;font-weight:700;color:#fff;margin-bottom:2px;line-height:1.3}
.wcat-s{font-size:10px;color:var(--t3);line-height:1.4}
/* Mobile accordion */
@media(max-width:700px){
  .wcat-grid{grid-template-columns:repeat(2,1fr);gap:6px}
  .wcat{padding:12px 10px}
  .wcat-ic{width:32px;height:32px;border-radius:8px;margin-bottom:8px}
  .wcat-t{font-size:11.5px}
  .wcat-s{font-size:9.5px}
}
/* Sidebar accordion mobile */
.sb-cat-header{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:4px 0}
.sb-cat-items{overflow:hidden;max-height:0;transition:max-height .3s ease}
.sb-cat-items.open{max-height:1000px}
.sb-cat-arrow{transition:transform .3s;font-size:10px;color:var(--t4)}
.sb-cat-arrow.open{transform:rotate(180deg)}
/* Premium photo strip */
.photo-strip{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0;padding:10px;background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2)}
.photo-thumb{width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid var(--bdr);cursor:pointer;transition:all .15s}
.photo-thumb:hover{transform:scale(1.05);border-color:var(--gb)}
.photo-count{font-size:11px;color:var(--t3);padding:4px 8px;background:var(--s3);border-radius:6px;display:flex;align-items:center}
/* Microphone pulse */
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
/* Premium topbar icons */
.tb-btn{width:36px;height:36px;border-radius:10px;background:var(--s2);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;color:var(--t3)}
.tb-btn:hover{background:var(--s3);color:var(--gold);border-color:var(--gb)}
.tb-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.8}
.tb-btn.hi{background:var(--ga);border-color:var(--gb);color:var(--gold)}

/* ── SIDEBAR CATEGORIES ──────────────────────────── */
.sb-docs-label{font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:1px;padding:12px 14px 6px}
.sb-cats{flex:1;overflow-y:auto;padding:0 8px 8px}
.sb-cats::-webkit-scrollbar{width:3px}
.sb-cats::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.sb-cat{margin-bottom:2px}
.sb-cat-hdr{display:flex;align-items:center;gap:8px;padding:9px 8px;border-radius:var(--r);cursor:pointer;transition:all .15s;user-select:none}
.sb-cat-hdr:hover{background:var(--s2)}
.sb-cat-ic{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-cat-ic svg{width:14px;height:14px}
.sb-cat-name{flex:1;font-size:13px;font-weight:600;color:var(--t2)}
.sb-cat-arr{width:14px;height:14px;stroke:var(--t4);transition:transform .25s;flex-shrink:0}
.sb-cat-arr.open{transform:rotate(180deg)}
.sb-cat-items{max-height:0;overflow:hidden;transition:max-height .3s ease;padding-left:44px}
.sb-cat-items.open{max-height:600px}
.si{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:8px;cursor:pointer;transition:all .15s}
.si:hover{background:var(--s2)}
.si.active{background:var(--ga)}
.si-dot{width:5px;height:5px;border-radius:50%;background:var(--t4);flex-shrink:0}
.si:hover .si-dot,.si.active .si-dot{background:var(--gold)}
.si-tx{font-size:12.5px;color:var(--t3);flex:1;line-height:1.3}
.si:hover .si-tx,.si.active .si-tx{color:var(--t1)}
.si-tag{font-size:9px;font-weight:700;padding:2px 6px;border-radius:100px;flex-shrink:0}
.tg-p{background:rgba(212,168,74,.12);color:var(--gold);border:1px solid var(--gb)}
.tg-n{background:rgba(66,223,148,.1);color:var(--gr);border:1px solid rgba(66,223,148,.2)}
/* Photo button in sidebar */
.sb-photo-btn{display:flex;align-items:center;gap:10px;margin:8px;background:var(--ga);border:1px solid var(--gb);border-radius:var(--r2);padding:12px 14px;cursor:pointer;transition:all .15s}
.sb-photo-btn:hover{background:rgba(212,168,74,.14);transform:translateY(-1px)}
.sb-photo-btn svg{width:18px;height:18px;stroke:var(--gold);flex-shrink:0}
.sb-photo-t{font-size:12.5px;font-weight:600;color:#fff}
.sb-photo-s{font-size:10.5px;color:var(--t3)}
/* Nav items */
.sb-nav{display:flex;flex-direction:column;gap:2px;padding:0 8px 8px;border-bottom:1px solid var(--bdr);margin-bottom:4px}
.sb-nav-item{display:flex;align-items:center;gap:8px;padding:9px 8px;border-radius:var(--r);cursor:pointer;font-size:13px;color:var(--t3);transition:all .15s}
.sb-nav-item svg{width:16px;height:16px;stroke:currentColor;flex-shrink:0}
.sb-nav-item:hover{background:var(--s2);color:var(--t2)}
.sb-nav-item.active{background:var(--s2);color:var(--gold)}
.sb-nav-item.active svg{stroke:var(--gold)}

/* ── SIDEBAR REDESIGN ────────────────────────────── */
.sb-docs-label{font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:1px;padding:10px 14px 4px}
.sb-cats{flex:1;overflow-y:auto;padding:4px 6px 6px}
.sb-cats::-webkit-scrollbar{width:3px}
.sb-cats::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.sb-cat{margin-bottom:1px}
.sb-cat-hdr{display:flex;align-items:center;gap:9px;padding:9px 8px;border-radius:var(--r);cursor:pointer;transition:all .15s;user-select:none;-webkit-tap-highlight-color:transparent}
.sb-cat-hdr:hover{background:var(--s2)}
.sb-cat-hdr:active{background:var(--s3)}
.sb-cat-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-cat-ic svg{width:15px;height:15px}
.sb-cat-name{flex:1;font-size:13px;font-weight:600;color:var(--t2);letter-spacing:-.2px}
.sb-cat-arr{width:15px;height:15px;stroke:var(--t4);transition:transform .25s;flex-shrink:0}
.sb-cat-arr.open{transform:rotate(180deg);stroke:var(--gold)}
.sb-cat-items{max-height:0;overflow:hidden;transition:max-height .3s ease;padding-left:40px}
.sb-cat-items.open{max-height:800px}
.si{display:flex;align-items:center;gap:8px;padding:8px 8px;border-radius:8px;cursor:pointer;transition:all .15s;min-height:36px;-webkit-tap-highlight-color:transparent}
.si:hover{background:var(--s2)}
.si:active{background:var(--s3)}
.si.active{background:var(--ga)}
.si-dot{width:4px;height:4px;border-radius:50%;background:var(--t4);flex-shrink:0;transition:background .15s}
.si:hover .si-dot,.si.active .si-dot{background:var(--gold)}
.si-tx{font-size:12.5px;color:var(--t3);flex:1;line-height:1.3;transition:color .15s}
.si:hover .si-tx,.si.active .si-tx{color:var(--t1)}
.si-tag{font-size:9px;font-weight:700;padding:2px 6px;border-radius:100px;flex-shrink:0}
.tg-p{background:rgba(212,168,74,.12);color:var(--gold);border:1px solid rgba(212,168,74,.25)}
.tg-n{background:rgba(66,223,148,.1);color:var(--gr);border:1px solid rgba(66,223,148,.2)}
.sb-photo-btn{display:flex;align-items:center;gap:10px;margin:6px 8px;background:var(--ga);border:1px solid var(--gb);border-radius:var(--r2);padding:11px 12px;cursor:pointer;transition:all .15s;-webkit-tap-highlight-color:transparent}
.sb-photo-btn:hover{background:rgba(212,168,74,.15)}
.sb-photo-btn:active{transform:scale(.98)}
.sb-photo-btn svg{width:18px;height:18px;flex-shrink:0}
.sb-photo-t{font-size:12.5px;font-weight:600;color:#fff}
.sb-photo-s{font-size:10px;color:var(--t3);margin-top:1px}
.sb-nav{display:flex;flex-direction:column;gap:1px;padding:4px 6px 6px;border-bottom:1px solid var(--bdr);margin-bottom:2px}
.sb-nav-item{display:flex;align-items:center;gap:9px;padding:9px 8px;border-radius:var(--r);cursor:pointer;font-size:13px;color:var(--t3);transition:all .15s;min-height:38px;-webkit-tap-highlight-color:transparent}
.sb-nav-item svg{width:16px;height:16px;stroke:currentColor;flex-shrink:0}
.sb-nav-item:hover{background:var(--s2);color:var(--t2)}
.sb-nav-item.active{background:var(--s2);color:var(--gold)}
.sb-nav-item.active svg{stroke:var(--gold)}
/* Toggle JS */

.sb-logo-chat{font-size:19px;font-weight:800;color:#fff;letter-spacing:-.5px}
.sb-logo-help{font-size:19px;font-weight:800;letter-spacing:-.5px;
  background:linear-gradient(135deg,#d4a84a,#f0c860);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sb-logo-tag{font-size:8px;font-weight:700;letter-spacing:1px;
  background:rgba(212,168,74,.1);border:1px solid rgba(212,168,74,.22);
  color:#d4a84a;padding:2px 6px;border-radius:100px;margin-left:6px;
  -webkit-text-fill-color:#d4a84a;flex-shrink:0}

/* ── WELCOME FINAL ───────────────────────────────── */
.wlc{padding:20px 0 12px;max-width:580px;margin:0 auto;width:100%}
.wlc-hero{text-align:center;margin-bottom:18px}
.wlc-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.2);border-radius:100px;padding:6px 16px;font-size:11px;font-weight:600;color:var(--gold);letter-spacing:.4px;margin-bottom:16px}
.wlc-badge-dot{width:6px;height:6px;border-radius:50%;background:var(--gold);flex-shrink:0;box-shadow:0 0 10px rgba(212,168,74,.7);animation:bdot 1.8s ease-in-out infinite}
@keyframes bdot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.2;transform:scale(.6)}}
.wlc-h1{font-size:28px;font-weight:800;color:#fff;line-height:1.12;letter-spacing:-1px;margin-bottom:12px}
.wlc-h1 em{font-style:normal;background:linear-gradient(135deg,#d4a84a 0%,#f5d070 50%,#d4a84a 100%);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:shimmer 3s linear infinite}
@keyframes shimmer{0%{background-position:0% center}100%{background-position:200% center}}
.wlc-sub{font-size:13px;color:var(--t3);line-height:1.65;max-width:440px;margin:0 auto 16px}
.wlc-trust{display:inline-flex;align-items:center;background:var(--s2);border:1px solid var(--bdr);border-radius:100px;padding:8px 10px;gap:0}
.wlc-trust-item{display:flex;align-items:center;gap:5px;padding:0 9px;font-size:11px;color:var(--t3);font-weight:500}
.wlc-trust-item svg{width:13px;height:13px;stroke:var(--gold);flex-shrink:0}
.wlc-trust-sep{width:1px;height:14px;background:var(--bdr)}
.wlc-scan{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.04));border:1.5px solid rgba(212,168,74,.28);border-radius:16px;padding:16px 18px;cursor:pointer;transition:all .22s;margin:0 0 14px;position:relative;overflow:hidden;-webkit-tap-highlight-color:transparent}
.wlc-scan::before{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;background:linear-gradient(90deg,transparent,rgba(212,168,74,.07),transparent);animation:scanshine 4s ease-in-out 2s infinite}
@keyframes scanshine{0%,100%{left:-100%}50%{left:150%}}
.wlc-scan:hover{transform:translateY(-2px);box-shadow:0 10px 35px rgba(0,0,0,.5)}
.wlc-scan-ic{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#d4a84a,#f0c860);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 16px rgba(212,168,74,.32)}
.wlc-scan-ic svg{width:22px;height:22px}
.wlc-scan-txt{flex:1;min-width:0}
.wlc-scan-t{font-size:14px;font-weight:700;color:#fff;margin-bottom:3px}
.wlc-scan-s{font-size:11.5px;color:var(--t3);line-height:1.4}
.wlc-scan-arr{width:26px;height:26px;border-radius:8px;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wlc-scan-arr svg{width:14px;height:14px;stroke:var(--t3)}
.wlc-diff{display:flex;flex-direction:column;margin:0 0 16px;background:var(--s2);border:1px solid var(--bdr);border-radius:16px;overflow:hidden}
.wlc-diff-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px}
.wlc-diff-item:not(:last-child){border-bottom:1px solid rgba(255,255,255,.05)}
.wlc-diff-ic{width:32px;height:32px;border-radius:9px;background:var(--ga);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wlc-diff-ic svg{width:15px;height:15px;stroke:var(--gold)}
.wlc-diff-t{font-size:13px;font-weight:700;color:#fff;margin-bottom:2px}
.wlc-diff-s{font-size:11.5px;color:var(--t3);line-height:1.45}
.wlc-quick-label{font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:10px}
.wlc-quick{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}
.wlc-pill{display:inline-flex;align-items:center;gap:7px;background:var(--s2);border:1px solid var(--bdr);border-radius:100px;padding:8px 14px;font-size:12.5px;font-weight:500;color:var(--t2);cursor:pointer;transition:all .18s;-webkit-tap-highlight-color:transparent;white-space:nowrap}
.wlc-pill:hover,.wlc-pill:active{background:var(--s3);border-color:rgba(212,168,74,.3);color:#fff;transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.3)}
.pill-ic{width:14px;height:14px;stroke:var(--gold);fill:none;stroke-width:2;flex-shrink:0}
.wlc-prompts{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.wlc-prompt{display:flex;align-items:center;gap:10px;background:var(--s2);border:1px solid var(--bdr);border-radius:12px;padding:12px 14px;font-size:13px;color:var(--t3);cursor:pointer;transition:all .15s;-webkit-tap-highlight-color:transparent;line-height:1.4}
.wlc-prompt:hover{background:var(--s3);color:var(--t1);border-color:rgba(255,255,255,.12)}
@media(max-width:700px){
  .wlc-h1{font-size:24px}
  .wlc-trust-item{padding:0 6px;font-size:10px}
  .wlc-scan{padding:12px 14px}
  .wlc-scan-ic{width:40px;height:40px;border-radius:12px}
  .wlc-scan-t{font-size:13px}
  .wlc-pill{padding:7px 11px;font-size:12px;gap:6px}
}
/* Logo Premium */
.sb-logo-chat{font-size:19px;font-weight:800;color:#fff;letter-spacing:-.5px}
.sb-logo-help{font-size:19px;font-weight:800;letter-spacing:-.5px;background:linear-gradient(135deg,#d4a84a,#f0c860);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sb-logo-tag{font-size:8px;font-weight:700;letter-spacing:1px;background:rgba(212,168,74,.1);border:1px solid rgba(212,168,74,.22);color:#d4a84a;padding:2px 6px;border-radius:100px;margin-left:6px;-webkit-text-fill-color:#d4a84a;flex-shrink:0;align-self:center}

/* ── PREMIUM TOPBAR ICONS ────────────────────────── */
.tb-name em{font-style:normal;color:var(--gold)}
.tb-btn{width:36px;height:36px;border-radius:10px;background:var(--s2);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;color:var(--t3);-webkit-tap-highlight-color:transparent;flex-shrink:0}
.tb-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.8}
.tb-btn:hover,.tb-btn:active{background:var(--s3);color:var(--t1);border-color:var(--bdr2)}
.tb-btn.hi{background:rgba(212,168,74,.1);border-color:rgba(212,168,74,.25);color:var(--gold)}
.tb-btn.hi svg{stroke:var(--gold)}
.tb-btn.hi:hover{background:rgba(212,168,74,.18)}

  10%{opacity:1}
  90%{opacity:1}
  100%{left:130%;opacity:0}
}

/* ── KONTO PANEL FIX ─────────────────────────────── */


/* ── WLC MARK ────────────────────────────────────── */
.wlc-mark{
  width:52px;height:52px;margin:0 auto 16px;
  filter:drop-shadow(0 4px 16px rgba(212,168,74,.2));
}
.wlc-mark svg{width:56px;height:56px}

  5%{opacity:1}
  95%{opacity:1}
  100%{left:120%;opacity:0}
}

/* ── TOPBAR PREMIUM ──────────────────────────────── */
.tb-ham{
  width:36px;height:36px;display:flex;flex-direction:column;
  justify-content:center;gap:5px;padding:8px;
  background:var(--s2);border:1px solid var(--bdr);
  border-radius:10px;cursor:pointer;flex-shrink:0;
  transition:all .15s;-webkit-tap-highlight-color:transparent
}
.tb-ham span{
  display:block;height:1.5px;background:var(--t2);
  border-radius:2px;transition:all .25s
}
.tb-ham span:nth-child(1){width:16px}
.tb-ham span:nth-child(2){width:12px}
.tb-ham span:nth-child(3){width:14px}
.tb-ham:hover{background:var(--s3);border-color:var(--bdr2)}
.tb-ham:hover span{width:16px!important}
.tb-btn{
  width:36px;height:36px;border-radius:10px;
  background:var(--s2);border:1px solid var(--bdr);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .15s;flex-shrink:0;
  -webkit-tap-highlight-color:transparent
}
.tb-btn svg{width:17px;height:17px;stroke:var(--t3);fill:none;stroke-width:1.8;stroke-linecap:round}
.tb-btn:hover,.tb-btn:active{background:var(--s3);border-color:var(--bdr2)}
.tb-btn:hover svg{stroke:var(--t1)}
.tb-btn.hi{
  background:rgba(212,168,74,.1);
  border-color:rgba(212,168,74,.28);
}
.tb-btn.hi svg{stroke:var(--gold)}
.tb-btn.hi:hover{background:rgba(212,168,74,.18)}
.tb-online{display:flex;align-items:center;gap:4px;font-size:10.5px;color:var(--t4);margin-left:2px;white-space:nowrap}
.tb-dot{width:6px;height:6px;border-radius:50%;background:var(--gr);box-shadow:0 0 8px rgba(48,200,122,.5);animation:tbpulse 2s infinite;flex-shrink:0}
@keyframes tbpulse{0%,100%{opacity:1}50%{opacity:.4}}

/* ── KONTO + ANT FULLSCREEN ──────────────────────── */
#pnl-konto,#pnl-ant{
  position:fixed;
  inset:0;
  width:100%;
  height:100%;
  z-index:500;
  background:var(--bg);
  overflow-y:auto;
  display:none;
  flex-direction:column;
}
#pnl-konto.on,#pnl-ant.on{
  display:flex;
}
/* Add back button to panels */
.pnl-back{
  display:flex;align-items:center;gap:10px;
  padding:14px 16px;cursor:pointer;
  border-bottom:1px solid var(--bdr);
  font-size:13px;font-weight:600;color:var(--t2);
  background:var(--s1);position:sticky;top:0;z-index:10;
  -webkit-tap-highlight-color:transparent
}
.pnl-back svg{width:18px;height:18px;stroke:var(--t3);fill:none;stroke-width:2;stroke-linecap:round}
.pnl-back:hover{color:var(--t1)}
.pnl-back:hover svg{stroke:var(--gold)}

/* ── LOGO v2 ─────────────────────────────────────── */
.sb-logo{display:flex;align-items:center;gap:7px;cursor:pointer;margin-bottom:14px;-webkit-tap-highlight-color:transparent}
.sb-logo-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-logo-chat{font-size:17px;font-weight:800;color:#fff;letter-spacing:-.4px}
.sb-logo-help{font-size:17px;font-weight:800;letter-spacing:-.4px;
  background:linear-gradient(135deg,#d4a84a,#f0c860);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

/* ── COUNTRY BTN ─────────────────────────────────── */
.tb-country-btn{
  display:flex;align-items:center;gap:5px;
  background:var(--s2);border:1px solid var(--bdr);
  border-radius:10px;padding:0 10px;height:36px;
  cursor:pointer;transition:all .15s;
  -webkit-tap-highlight-color:transparent;flex-shrink:0
}
.tb-country-btn:hover{background:var(--s3);border-color:var(--bdr2)}
#tb-flag-emoji{font-size:18px;line-height:1}
.tb-country-name{font-size:12px;font-weight:700;color:var(--t2)}

/* ── PANEL TOPBAR ────────────────────────────────── */
.pnl-topbar{
  display:flex;align-items:center;gap:12px;
  padding:12px 16px;
  background:var(--s1);
  border-bottom:1px solid var(--bdr);
  position:sticky;top:0;z-index:10;
  flex-shrink:0
}
.pnl-back-btn{
  width:36px;height:36px;border-radius:10px;
  background:var(--s2);border:1px solid var(--bdr);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;flex-shrink:0;transition:all .15s;
  -webkit-tap-highlight-color:transparent
}
.pnl-back-btn svg{width:18px;height:18px;stroke:var(--t2)}
.pnl-back-btn:hover{background:var(--s3);border-color:var(--bdr2)}
.pnl-back-btn:hover svg{stroke:var(--gold)}
.pnl-topbar-title{font-size:16px;font-weight:700;color:#fff}

.wcat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:16px}
.wcat{background:var(--s2);border:1px solid var(--bdr);border-radius:14px;padding:13px 12px;cursor:pointer;transition:all .18s;-webkit-tap-highlight-color:transparent}
.wcat:active{transform:scale(.97)}
.wcat-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:9px}
.wcat-ic svg{width:17px;height:17px}
.wcat-t{font-size:12.5px;font-weight:700;color:#fff;margin-bottom:3px}
.wcat-s{font-size:11px;color:var(--t4)}

/* ── BOTTOM SHEET ANIMATION ─────────────────────── */
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
#cat-sheet{will-change:transform}
/* ── WCAT HOVER ─────────────────────────────────── */
.wcat:active{transform:scale(.97)!important;box-shadow:0 0 0 1px rgba(212,168,74,.3)}

.wlc-10x{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.03));border:1px solid rgba(212,168,74,.25);border-radius:14px;padding:12px 16px;margin-bottom:14px}
.wlc-10x-ic{font-size:22px;flex-shrink:0}
.wlc-10x-t{font-size:13px;font-weight:700;color:#fff;margin-bottom:3px}
.wlc-10x-s{font-size:11.5px;color:var(--t4);line-height:1.5}

.pm-usp{font-size:12px;color:var(--t4);text-align:center;padding:8px 20px;margin-bottom:8px;line-height:1.5}

/* ── PANEL OVERLAY (position:fixed, body dışına alındı) ── */
#pnl-ant, #pnl-konto {
  position: fixed !important;
  inset: 0 !important;
  z-index: 600 !important;
  background: var(--bg) !important;
  display: none !important;
  flex-direction: column !important;
  overflow: hidden !important;
}
#pnl-ant.on, #pnl-konto.on {
  display: flex !important;
}
</style>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
<input type="file" id="pf" accept="image/*,application/pdf" multiple>
<div id="ov" onclick="cSB()"></div>

<!-- PRICING -->
<div id="pm" class="pm" style="display:none">
<div class="pm-inner">
  <button class="pm-close" onclick="this.closest('.pm-overlay').remove()">&#x2715;</button>
  <div class="pm-usp">ChatHelp analysiert, recherchiert und erstellt — 10× strategischer als Standard-KI</div><div class="pm-title">Tarife &amp; Preise</div>
  <div class="pm-sub">Professionelle Rechtsdokumente &#8212; f&#252;r ganz Europa</div>
  <div class="pm-grid">
    <div class="pc"><div class="pc-tier">Einzeln</div><div class="pc-amt">ab 2,99&#8364;</div><div class="pc-per">pro Dokument</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>1 Dokument</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>PDF-Download</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle Sprachen</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>24h in Meine Antr&#228;ge</div></div>
      <button class="pc-btn" onclick="showProModal()">Jetzt erstellen</button></div>
    <div class="pc feat"><div class="pc-tier">Monatsabo</div><div class="pc-amt">9,99&#8364;</div><div class="pc-per">/ Monat &#183; k&#252;ndbar</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>300+ Dokumente / Monat</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle EU-L&#228;nder</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Foto-Analyse KI</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Antr&#228;ge dauerhaft speichern</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Follow-up Fragen</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>DeepSeek Recherche</div></div>
      <button class="pc-btn g" onclick="showProModal()">Jetzt starten</button></div>
    <div class="pc"><div class="pc-tier">Jahresabo</div><div class="pc-amt">79&#8364;</div><div class="pc-per">/ Jahr &#8212; 40% sparen</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alles aus Monatsabo</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Anwalt-Check 2x / Jahr</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Familien-Profile (3x)</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Dokumenten-Archiv</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Priorit&#228;t-Support</div></div>
      <button class="pc-btn" onclick="showProModal()">Jetzt sparen</button></div>
  </div>
  <div style="text-align:center;font-size:11px;color:var(--t4);margin-top:14px">Alle Preise inkl. MwSt. &#183; Sichere Zahlung via Stripe &#183; Jederzeit k&#252;ndbar</div>
</div></div>

<div id="app">
<div id="sb">
  <div class="sb-head">
    <div class="sb-logo" onclick="NC()"><span style="color:#fff;font-weight:800">Chat</span><span style="background:linear-gradient(135deg,#d4a84a,#f0c860);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800">Help</span></div>
    <div class="sb-country" id="sb-country" onclick="gP('konto')">
      <span id="sb-flag">🇩🇪</span>
      <span id="sb-cname">Deutschland</span>
      <span class="sb-country-arrow">›</span>
    </div>
    <div class="sb-new" onclick="NC()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Neues Gespräch
    </div>
    <div class="sb-plan-box"></div>
  </div>

  <div class="sb-nav">
    <div class="sb-nav-item active" onclick="NC()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Chat
    </div>
    <div class="sb-nav-item" onclick="gP('ant')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
      Meine Anträge
    </div>
    <div class="sb-nav-item" onclick="gP('konto')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Konto
    </div>
  </div>

  <div class="sb-docs-label">Dokumente</div>

  

  <div class="sb-photo-btn" onclick="document.getElementById('pf').click()">
    <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.8" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    <div><div class="sb-photo-t">Brief / Bescheid scannen</div><div class="sb-photo-s">Bis zu 15 Fotos · KI-Analyse</div></div>
    <span style="font-size:9px;background:var(--ga);border:1px solid var(--gb);color:var(--gold);padding:2px 7px;border-radius:100px;font-weight:700;flex-shrink:0">KI</span>
  </div>

  <div class="sb-foot">
    <div class="sb-foot-txt">chat-help.de · Legal Documents Europe</div>
    <div class="sb-badges"><span class="bdg bdg-g">✓ DSGVO</span><span class="bdg bdg-o">✓ EU-Server</span></div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <a href="#" onclick="oLM('lm-imp');return false" style="font-size:10px;color:var(--t4);text-decoration:none">Impressum</a>
      <span style="font-size:10px;color:var(--t4)">·</span>
      <a href="#" onclick="oLM('lm-dsc');return false" style="font-size:10px;color:var(--t4);text-decoration:none">Datenschutz</a>
    </div>
  </div>
</div>

<div id="mn">
  <div id="tb">
    <div class="tb-l">
      <div class="tb-ham" onclick="oSB()"><span></span><span></span><span></span></div>
      <div class="tb-name" onclick="NC()" style="cursor:pointer"><span style="color:#fff;font-weight:800">Chat</span><span style="background:linear-gradient(135deg,#d4a84a,#f0c860);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800">Help</span></div>
    </div>
    <div class="tb-r">
      <div class="tb-country-btn" id="tb-flag" onclick="handleUserBtn()" title="Land wechseln">
        <span id="tb-flag-emoji">&#x1F1E9;&#x1F1EA;</span>
        <span class="tb-country-name" id="tb-country-name">DE</span>
      </div>

      <div id="tb-auth-btn" onclick="handleUserBtn()" style="display:flex;align-items:center;gap:6px;padding:6px 12px;height:36px;background:rgba(212,168,74,.1);border:0.5px solid rgba(212,168,74,.3);border-radius:10px;cursor:pointer;-webkit-tap-highlight-color:transparent;transition:all .15s">
      <svg id="tb-auth-icon" viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round" width="15" height="15"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span id="tb-auth-label" style="font-size:12px;font-weight:600;color:#d4a84a">Anmelden</span>
    </div>
      <div class="tb-btn" onclick="gP('ant')" title="Meine Anträge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div class="tb-online"><div class="tb-dot"></div>Online</div>
    </div>
  </div>

  <div class="pnl on" id="pnl-chat" style="overflow:hidden;min-width:0;background:#07070e">
    <div id="msgs"><div id="mi">
      <div class="wlc" id="wlc">
  <div class="wlc-hero">
    <h1 class="wlc-h1">Ihr Recht.<br><em>Ihr Schutz.</em></h1>
    <p class="wlc-sub" id="wlc-p">Professionelle Rechtsdokumente in Sekunden.</p>
    <div class="wlc-trust"><div class="wlc-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="2" stroke-linecap="round" width="12" height="12"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>DSGVO</div><div class="wlc-trust-sep"></div><div class="wlc-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="2" stroke-linecap="round" width="12" height="12"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>100+ Vorlagen</div><div class="wlc-trust-sep"></div><div class="wlc-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="2" stroke-linecap="round" width="12" height="12"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>KI</div></div>
  </div>
  <div class="wlc-10x">
    <div class="wlc-10x-ic">&#9889;</div>
    <div class="wlc-10x-txt">
      <div class="wlc-10x-t">10&times; besser als Standard-KI</div>
      <div class="wlc-10x-s">Juristische Analyse &middot; Aktuelle Rechtslage &middot; Verifizierte Dokumente nach deutschem Recht</div>
    </div>
  </div>

  <div class="wlc-scan" onclick="document.getElementById('pf').click()">
    <div class="wlc-scan-ic"><svg viewBox="0 0 24 24" fill="none" stroke="#07070e" stroke-width="2" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
    <div class="wlc-scan-txt"><div class="wlc-scan-t">Brief oder Bescheid fotografieren</div><div class="wlc-scan-s">KI erkennt Beh&ouml;rde &middot; Frist &middot; Aktenzeichen</div></div>
    <div class="wlc-scan-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="9,18 15,12 9,6"/></svg></div>
  </div>
  <div class="wlc-quick-label">Kategorien</div>
  <div class="wcat-grid">
    <div class="wcat" onclick="showCatDocs('vertraege')">
      <div class="wcat-ic" style="background:rgba(212,168,74,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg></div>
      <div class="wcat-t">Verträge kündigen</div>
      <div class="wcat-s">7 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('miete')">
      <div class="wcat-ic" style="background:rgba(66,223,148,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#42df94" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></div>
      <div class="wcat-t">Mieten & Wohnen</div>
      <div class="wcat-s">4 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('arbeit')">
      <div class="wcat-ic" style="background:rgba(200,130,255,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#c882ff" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
      <div class="wcat-t">Arbeit & Beruf</div>
      <div class="wcat-s">4 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('aufenthalt')">
      <div class="wcat-ic" style="background:rgba(66,150,255,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#6694ff" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
      <div class="wcat-t">Aufenthalt & Visum</div>
      <div class="wcat-s">3 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('behoerde')">
      <div class="wcat-ic" style="background:rgba(255,210,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ffd232" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
      <div class="wcat-t">Behörde & Recht</div>
      <div class="wcat-s">3 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('verbraucher')">
      <div class="wcat-ic" style="background:rgba(66,223,200,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#42dfc8" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg></div>
      <div class="wcat-t">Verbraucher & Recht</div>
      <div class="wcat-s">3 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('steuern')">
      <div class="wcat-ic" style="background:rgba(255,210,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ffd232" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
      <div class="wcat-t">Steuern & Finanzen</div>
      <div class="wcat-s">2 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('gesundheit')">
      <div class="wcat-ic" style="background:rgba(255,80,100,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff5064" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div class="wcat-t">Gesundheit</div>
      <div class="wcat-s">1 Dokumente</div>
    </div>
    <div class="wcat" onclick="showCatDocs('soziales')">
      <div class="wcat-ic" style="background:rgba(255,150,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff9632" stroke-width="1.8" stroke-linecap="round" width="17" height="17"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      <div class="wcat-t">Soziales & Familie</div>
      <div class="wcat-s">2 Dokumente</div>
    </div>
  </div>
  <div class="wlc-prompts" id="wlc-prompts">
    <div class="wlc-prompt" onclick="setPrompt(this)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--t4)" stroke-width="1.8" stroke-linecap="round" width="13" height="13" style="flex-shrink:0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Mein Vermieter erh&ouml;ht die Miete</div>
    <div class="wlc-prompt" onclick="setPrompt(this)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--t4)" stroke-width="1.8" stroke-linecap="round" width="13" height="13" style="flex-shrink:0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Ich m&ouml;chte meinen Handyvertrag k&uuml;ndigen</div>
    <div class="wlc-prompt" onclick="setPrompt(this)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--t4)" stroke-width="1.8" stroke-linecap="round" width="13" height="13" style="flex-shrink:0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Jobcenter hat Leistungen gek&uuml;rzt</div>
  </div>
  <div class="lang-bar"><span class="lang-txt">Aktiv:</span><div class="lflags"><span class="lf on">&#x1F1E9;&#x1F1EA;</span><span class="lf">&#x1F1F9;&#x1F1F7;</span><span class="lf">&#x1F1F8;&#x1F1E6;</span><span class="lf">&#x1F1F7;&#x1F1FA;</span><span class="lf">&#x1F1EC;&#x1F1E7;</span><span class="lf">&#x1F1EB;&#x1F1F7;</span></div></div>
</div>
      </div>
    </div></div>
    <div id="ia">
      <div class="ia-inner">
        <textarea id="inp" rows="1" placeholder="Schreiben Sie Ihr Anliegen&#8230;" onkeydown="onKey(event)" oninput="onInp(this)"></textarea>
        <div class="ia-btns">
          <div id="pb" onclick="startVoice()" title="Spracheingabe" style="cursor:pointer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></div>
          <button id="sbtn" onclick="doSend()"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg></button>
        </div>
      </div>
      <div class="ia-foot"><span class="ia-foot-txt">Daten nur auf Ihrem Server &#183; ChatHelp AI &#183; DSGVO &#183; EU-Server</span><div class="ia-lfs"><span class="lf2 on" id="fl-de">&#x1F1E9;&#x1F1EA;</span><span class="lf2" id="fl-tr">&#x1F1F9;&#x1F1F7;</span><span class="lf2" id="fl-ar">&#x1F1F8;&#x1F1E6;</span><span class="lf2" id="fl-ru">&#x1F1F7;&#x1F1FA;</span><span class="lf2" id="fl-en">&#x1F1EC;&#x1F1E7;</span><span class="lf2" id="fl-fr">&#x1F1EB;&#x1F1F7;</span></div></div>
    </div>
  </div>

  
  </div>

  
</div>
</div>

<script>

// ── VOICE INPUT ──────────────────────────────────────
function startVoice(){
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){
    addMsg('ch','Spracherkennung wird in diesem Browser nicht unterstützt.');
    return;
  }
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  const r=new SR();
  r.lang=UL==='de'?'de-DE':UL==='tr'?'tr-TR':UL==='fr'?'fr-FR':UL==='es'?'es-ES':'de-DE';
  r.interimResults=false;
  r.maxAlternatives=1;
  const pb=document.getElementById('pb');
  pb.style.color='var(--rd)';
  pb.style.animation='pulse 1s infinite';
  r.onresult=e=>{
    const t=e.results[0][0].transcript;
    document.getElementById('inp').value=t;
    pb.style.color='';
    pb.style.animation='';
    doSend();
  };
  r.onerror=()=>{pb.style.color='';pb.style.animation='';};
  r.onend=()=>{pb.style.color='';pb.style.animation='';};
  r.start();
}


// ── SIDEBAR ACCORDION ────────────────────────────
function toggleCat(el){
  const items=el.nextElementSibling;
  const arrow=el.querySelector('.sb-cat-arrow');
  const isOpen=items.classList.contains('open');
  // Close all
  document.querySelectorAll('.sb-cat-items').forEach(i=>i.classList.remove('open'));
  document.querySelectorAll('.sb-cat-arrow').forEach(a=>a.classList.remove('open'));
  if(!isOpen){items.classList.add('open');if(arrow)arrow.classList.add('open');}
}


// ── SIDEBAR CATEGORY ACCORDION ────────────────────
function toggleSbCat(hdr){
  const items=hdr.nextElementSibling;
  const arr=hdr.querySelector('.sb-cat-arr');
  const isOpen=items.classList.contains('open');
  document.querySelectorAll('.sb-cat-items').forEach(i=>i.classList.remove('open'));
  document.querySelectorAll('.sb-cat-arr').forEach(a=>a.classList.remove('open'));
  if(!isOpen){items.classList.add('open');if(arr)arr.classList.add('open');}
}



function setPrompt(el){
  const txt=el.textContent.trim();
  const inp=document.getElementById('inp');
  if(inp){inp.value=txt;inp.focus();onInp(inp);}
  const wlc=document.getElementById('wlc');
  if(wlc)wlc.style.display='none';
}


function showProModal(){
  const m=document.createElement('div');
  m.style.cssText='position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:20px';
  m.onclick=function(e){if(e.target===m)m.remove()};
  m.innerHTML=`<div style="background:#0e0e20;border:1px solid rgba(212,168,74,.3);border-radius:20px;padding:28px;max-width:340px;width:100%;text-align:center">
    <div style="font-size:36px;margin-bottom:14px">⭐</div>
    <div style="font-size:18px;font-weight:800;color:#fff;margin-bottom:8px">ChatHelp Pro</div>
    <div style="font-size:13px;color:#8080a8;line-height:1.7;margin-bottom:20px">300+ Dokumente / Monat · Foto-Analyse<br>Follow-up · Dauerhaft speichern<br>Anwalt-Check · Priority Support</div>
    <div style="font-size:28px;font-weight:800;color:#d4a84a;margin-bottom:4px">9,99€<span style="font-size:14px;color:#8080a8">/Monat</span></div>
    <div style="font-size:11px;color:#505078;margin-bottom:20px">oder 79€/Jahr · 34% sparen</div>
    <button onclick="this.closest('div[style]').parentElement.remove()" style="width:100%;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#07070e;border:none;border-radius:12px;padding:14px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:10px">Bald verf&#252;gbar &#x2014; Benachrichtigen</button>
    <div onclick="this.closest('div[style*=fixed]').remove()" style="font-size:12px;color:#505078;cursor:pointer;padding:8px">Schlie&#223;en</div>
  </div>`;
  document.body.appendChild(m);
}

// ── LEGAL MODALS ─────────────────────────────────
function oLM(id){document.getElementById(id).classList.add('on');document.body.style.overflow='hidden';}
function cLM(id){document.getElementById(id).classList.remove('on');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){cLM('lm-imp');cLM('lm-dsc');}});



const API='api.php',CK='ch_conv_v3',PK='ch_pref_v3',FK='ch_prof_v3';
const CC_DATA={
  DE:{n:'Deutschland',f:'\u{1F1E9}\u{1F1EA}',l:'de',law:'BGB, StGB, VwGO'},
  AT:{n:'\u00d6sterreich',f:'\u{1F1E6}\u{1F1F9}',l:'de',law:'ABGB, StGB, VwGVG'},
  CH:{n:'Schweiz',f:'\u{1F1E8}\u{1F1ED}',l:'de',law:'OR, ZGB, StGB'},
  FR:{n:'France',f:'\u{1F1EB}\u{1F1F7}',l:'fr',law:'Code civil, Code du travail'},
  IT:{n:'Italia',f:'\u{1F1EE}\u{1F1F9}',l:'it',law:'Codice civile, Cod. penale'},
  ES:{n:'Espa\u00f1a',f:'\u{1F1EA}\u{1F1F8}',l:'es',law:'C\u00f3digo civil, Cod. penal'},
  NL:{n:'Nederland',f:'\u{1F1F3}\u{1F1F1}',l:'nl',law:'BW, WvSr, Awb'},
  BE:{n:'Belgique',f:'\u{1F1E7}\u{1F1EA}',l:'fr',law:'Code civil belge'},
  PL:{n:'Polska',f:'\u{1F1F5}\u{1F1F1}',l:'pl',law:'KC, KPC'},
  SE:{n:'Sverige',f:'\u{1F1F8}\u{1F1EA}',l:'sv',law:'BrB, JB'},
  PT:{n:'Portugal',f:'\u{1F1F5}\u{1F1F9}',l:'pt',law:'C\u00f3digo civil'},
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
};
const UI_TEXTS={
  de:{h:'Rechtsdokumente f\u00fcr Europa',p:'Schreiben Sie Ihr Anliegen \u2014 Sprache wird automatisch erkannt.',c1:'Beh\u00f6rde & Amt',c2:'Steuern',c3:'Miete & Wohnen',c4:'Arbeit & Beruf',you:'Sie',ph:'Schreiben Sie Ihr Anliegen\u2026'},
  en:{h:'Legal Documents for Europe',p:'Describe your issue \u2014 language is detected automatically.',c1:'Authorities',c2:'Taxes',c3:'Rent & Housing',c4:'Work & Career',you:'You',ph:'Describe your concern\u2026'},
  fr:{h:'Documents juridiques pour l\'Europe',p:'\u00c9crivez votre demande \u2014 la langue est d\u00e9tect\u00e9e automatiquement.',c1:'Autorit\u00e9s',c2:'Imp\u00f4ts',c3:'Logement',c4:'Travail',you:'Vous',ph:'D\u00e9crivez votre demande\u2026'},
  tr:{h:'Avrupa i\u00e7in Hukuki Belgeler',p:'Talebinizi yaz\u0131n \u2014 dil otomatik tespit edilir.',c1:'Resmi Kurumlar',c2:'Vergiler',c3:'Kira & Konut',c4:'\u0130\u015f & Kariyer',you:'Siz',ph:'Talebinizi yaz\u0131n\u2026'},
  ar:{h:'\u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a \u0627\u0644\u0642\u0627\u0646\u0648\u0646\u064a\u0629 \u0644\u0623\u0648\u0631\u0648\u0628\u0627',p:'\u0627\u0643\u062a\u0628 \u0637\u0644\u0628\u0643 \u2014 \u0633\u064a\u062a\u0645 \u0627\u0643\u062a\u0634\u0627\u0641 \u0627\u0644\u0644\u063a\u0629 \u062a\u0644\u0642\u0627\u0626\u064a\u064b\u0627.',c1:'\u0627\u0644\u0633\u0644\u0637\u0627\u062a',c2:'\u0627\u0644\u0636\u0631\u0627\u0626\u0628',c3:'\u0627\u0644\u0633\u0643\u0646',c4:'\u0627\u0644\u0639\u0645\u0644',you:'\u0623\u0646\u062a',ph:'\u0627\u0643\u062a\u0628 \u0637\u0644\u0628\u0643\u2026'},
  ru:{h:'\u042e\u0440\u0438\u0434\u0438\u0447\u0435\u0441\u043a\u0438\u0435 \u0434\u043e\u043a\u0443\u043c\u0435\u043d\u0442\u044b \u0434\u043b\u044f \u0415\u0432\u0440\u043e\u043f\u044b',p:'\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u0430\u0448 \u0432\u043e\u043f\u0440\u043e\u0441 \u2014 \u044f\u0437\u044b\u043a \u043e\u043f\u0440\u0435\u0434\u0435\u043b\u044f\u0435\u0442\u0441\u044f \u0430\u0432\u0442\u043e\u043c\u0430\u0442\u0438\u0447\u0435\u0441\u043a\u0438.',c1:'\u0412\u0435\u0434\u043e\u043c\u0441\u0442\u0432\u0430',c2:'\u041d\u0430\u043b\u043e\u0433\u0438',c3:'\u0410\u0440\u0435\u043d\u0434\u0430',c4:'\u0420\u0430\u0431\u043e\u0442\u0430',you:'\u0412\u044b',ph:'\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u0430\u0448 \u0432\u043e\u043f\u0440\u043e\u0441\u2026'},
};

const DOCS={

// ════════════════════════════════════════
// 1. VERTRÄGE KÜNDIGEN
// ════════════════════════════════════════
kuendigung_handy:{n:'Kündigung Handyvertrag',ic:'📱',cat:'vertraege',sub:'Telekommunikation',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Straße & Hausnummer',t:'text',r:1,ph:'Musterstraße 1'},
    {k:'plz_ort',l:'PLZ & Ort',t:'text',r:1,ph:'12345 Berlin'},
    {k:'anbieter',l:'Name des Mobilfunkanbieters',t:'text',r:1,ph:'z.B. Telekom, Vodafone, O2'},
    {k:'kundennummer',l:'Kundennummer / Vertragsnummer',t:'text',r:0,ph:'Steht auf der Rechnung'},
    {k:'kuendigung_zum',l:'Kündigung zum (Datum)',t:'date',r:1},
    {k:'kuendigung_art',l:'Art der Kündigung',t:'select',r:1,opts:[
      {v:'ordentlich',l:'Ordentlich zum nächstmöglichen Termin'},
      {v:'sonderkuendigung_preiserhoehung',l:'Sonderkündigung wegen Preiserhöhung'},
      {v:'sonderkuendigung_umzug',l:'Sonderkündigung wegen Umzug'},
      {v:'ausserordentlich',l:'Außerordentlich (wichtiger Grund)'},
    ]},
  ]},

kuendigung_internet:{n:'Kündigung Internet-/DSL-Vertrag',ic:'🌐',cat:'vertraege',sub:'Telekommunikation',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'anbieter',l:'Internetanbieter',t:'text',r:1,ph:'z.B. Telekom, Vodafone, 1&1'},
    {k:'kundennummer',l:'Kundennummer',t:'text',r:0,ph:'Optional'},
    {k:'kuendigung_zum',l:'Kündigung zum',t:'date',r:1},
    {k:'grund',l:'Kündigungsgrund',t:'select',r:1,opts:[
      {v:'ordentlich',l:'Ordentliche Kündigung (Fristende)'},
      {v:'sonderkuendigung_preiserhoehung',l:'Sonderkündigung — Preiserhöhung'},
      {v:'sonderkuendigung_stoerung',l:'Sonderkündigung — dauerhafter Störung'},
      {v:'umzug',l:'Umzug (Versorgung am neuen Ort nicht möglich)'},
    ]},
  ]},

kuendigung_fitness:{n:'Kündigung Fitnessstudio',ic:'🏋️',cat:'vertraege',sub:'Mitgliedschaften',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'studio_name',l:'Name des Fitnessstudios',t:'text',r:1,ph:'z.B. McFit, Clever Fit'},
    {k:'mitglieds_nr',l:'Mitgliedsnummer',t:'text',r:0,ph:'Steht auf der Mitgliedskarte'},
    {k:'kuendigung_zum',l:'Kündigung zum',t:'date',r:1},
    {k:'grund',l:'Kündigungsgrund',t:'select',r:1,opts:[
      {v:'ordentlich',l:'Ordentliche Kündigung'},
      {v:'krankheit',l:'Sonderkündigung — Krankheit / ärztliches Attest'},
      {v:'umzug',l:'Sonderkündigung — Umzug (mehr als 15 km)'},
      {v:'preiserhoehung',l:'Sonderkündigung — Preiserhöhung'},
      {v:'schliessung',l:'Sonderkündigung — Studioschliessung'},
    ]},
    {k:'attest',l:'Liegt ein ärztliches Attest vor? (bei Krankheit)',t:'select',r:0,opts:[
      {v:'ja',l:'Ja, Attest liegt vor'},
      {v:'nein',l:'Nein'},
    ]},
  ]},

kuendigung_streaming:{n:'Kündigung Streaming-Dienst',ic:'📺',cat:'vertraege',sub:'Mitgliedschaften',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'anbieter',l:'Streaming-Dienst',t:'select',r:1,opts:[
      {v:'Netflix',l:'Netflix'},
      {v:'Amazon Prime Video',l:'Amazon Prime Video'},
      {v:'Disney+',l:'Disney+'},
      {v:'Spotify',l:'Spotify'},
      {v:'Apple Music',l:'Apple Music'},
      {v:'DAZN',l:'DAZN'},
    ]},
    {k:'email_konto',l:'E-Mail des Streaming-Kontos',t:'text',r:1,ph:'ihre@email.de'},
    {k:'kuendigung_zum',l:'Kündigung zum',t:'date',r:1},
  ]},

kuendigung_versicherung:{n:'Kündigung Kfz-Versicherung',ic:'🚗',cat:'vertraege',sub:'Versicherungen',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'versicherung',l:'Name der Versicherung',t:'text',r:1,ph:'z.B. HUK-Coburg, Allianz, ADAC'},
    {k:'versicherungsschein_nr',l:'Versicherungsschein-Nr. / Vertragsnummer',t:'text',r:1,ph:'Steht auf dem Versicherungsschein'},
    {k:'kfz_kennzeichen',l:'Kfz-Kennzeichen',t:'text',r:0,ph:'z.B. B-AB 1234'},
    {k:'kuendigung_grund',l:'Kündigungsgrund',t:'select',r:1,opts:[
      {v:'jahresende',l:'Ordentliche Kündigung zum Jahresende (30.11.)'},
      {v:'sonderkuendigung_schaden',l:'Sonderkündigung nach Schadenfall'},
      {v:'sonderkuendigung_beitrag',l:'Sonderkündigung nach Beitragserhöhung'},
      {v:'fahrzeugverkauf',l:'Fahrzeugverkauf / Abmeldung'},
    ]},
  ]},

kuendigung_strom:{n:'Kündigung Strom- / Gasvertrag',ic:'⚡',cat:'vertraege',sub:'Energie',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Verbrauchsstelle (Ihre Adresse)',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'anbieter',l:'Energieversorger',t:'text',r:1,ph:'z.B. E.ON, Vattenfall, RWE'},
    {k:'kundennummer',l:'Kundennummer',t:'text',r:0,ph:'Steht auf der Rechnung'},
    {k:'sparte',l:'Was kündigen Sie?',t:'select',r:1,opts:[
      {v:'Strom',l:'Stromvertrag'},
      {v:'Gas',l:'Gasvertrag'},
      {v:'Strom und Gas',l:'Strom und Gas (Kombivertrag)'},
    ]},
    {k:'kuendigung_zum',l:'Kündigung zum',t:'date',r:1},
    {k:'grund',l:'Kündigungsgrund',t:'select',r:1,opts:[
      {v:'ordentlich',l:'Ordentliche Kündigung'},
      {v:'preiserhoehung',l:'Sonderkündigung — Preiserhöhung'},
      {v:'umzug',l:'Umzug'},
    ]},
  ]},

kuendigung_mieter:{n:'Kündigung Mietvertrag (durch Mieter)',ic:'🏠',cat:'vertraege',sub:'Wohnen',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'miet_adresse',l:'Adresse der Mietwohnung',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'vermieter',l:'Name des Vermieters / Hausverwaltung',t:'text',r:1,ph:'Max Vermieter / ABC Hausverwaltung GmbH'},
    {k:'vermieter_adresse',l:'Adresse des Vermieters',t:'text',r:1,ph:'Vermieterstraße 1, 12345 Berlin'},
    {k:'kuendigung_zum',l:'Kündigung zum (3 Monate Frist beachten)',t:'date',r:1},
    {k:'grund',l:'Kündigungsgrund',t:'select',r:1,opts:[
      {v:'eigenbedarfsende',l:'Eigene Entscheidung (Umzug, Neubau, etc.)'},
      {v:'eigenbedarfskauf',l:'Wohnungskauf'},
      {v:'zeitmietvertrag',l:'Ende Zeitmietvertrag'},
    ]},
  ]},

// ════════════════════════════════════════
// 2. MIETEN & WOHNEN
// ════════════════════════════════════════
mietminderung:{n:'Mietminderung',ic:'🏠',cat:'miete',sub:'Mängel',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'miet_adresse',l:'Adresse Ihrer Mietwohnung',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'vermieter',l:'Name des Vermieters / Hausverwaltung',t:'text',r:1,ph:'Max Vermieter'},
    {k:'vermieter_adresse',l:'Adresse des Vermieters',t:'text',r:1,ph:'Vermieterstraße 1, 12345 Berlin'},
    {k:'mangel_art',l:'Was ist der Mangel? (genau beschreiben)',t:'textarea',r:1,ph:'z.B. Schimmel im Schlafzimmer seit 3 Monaten, ca. 0,5 m² an der Außenwand'},
    {k:'mangel_seit',l:'Seit wann besteht der Mangel?',t:'date',r:1},
    {k:'angezeigt_am',l:'Wann haben Sie den Mangel erstmals angezeigt?',t:'date',r:0},
    {k:'minderung_prozent',l:'Gewünschte Mietminderung',t:'select',r:1,opts:[
      {v:'5',l:'5% (geringfügige Beeinträchtigung)'},
      {v:'10',l:'10% (spürbare Beeinträchtigung)'},
      {v:'15',l:'15% (erhebliche Beeinträchtigung)'},
      {v:'20',l:'20% (starke Beeinträchtigung)'},
      {v:'25',l:'25% (sehr starke Beeinträchtigung)'},
      {v:'33',l:'33% (ein Raum komplett unbenutzbar)'},
    ]},
    {k:'minderung_ab',l:'Mietminderung gültig ab',t:'date',r:1},
  ]},

maengel_vermieter:{n:'Mängelanzeige an Vermieter',ic:'📋',cat:'miete',sub:'Mängel',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'miet_adresse',l:'Adresse der Mietwohnung',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'vermieter',l:'Name Vermieter / Hausverwaltung',t:'text',r:1,ph:'Max Vermieter'},
    {k:'mangel_beschreibung',l:'Beschreibung des Mangels (genau)',t:'textarea',r:1,ph:'z.B. Heizung in Zimmer 2 funktioniert seit 15.10. nicht mehr, Raumtemperatur unter 16°C'},
    {k:'mangel_seit',l:'Seit wann?',t:'date',r:1},
    {k:'frist_beseitigung',l:'Frist zur Beseitigung (Tage)',t:'select',r:1,opts:[
      {v:'7',l:'7 Tage (dringend — Heizung, Wasser, Sicherheit)'},
      {v:'14',l:'14 Tage (normal)'},
      {v:'21',l:'21 Tage (weniger dringend)'},
    ]},
    {k:'androhung',l:'Was androhen bei Nichtbeseitigung?',t:'select',r:1,opts:[
      {v:'mietminderung',l:'Mietminderung'},
      {v:'selbstvornahme',l:'Selbstvornahme auf Kosten des Vermieters'},
      {v:'ruecktritt',l:'Außerordentliche Kündigung'},
    ]},
  ]},

widerspruch_mieterhoehung:{n:'Widerspruch gegen Mieterhöhung',ic:'📈',cat:'miete',sub:'Mieterhöhung',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'miet_adresse',l:'Adresse der Mietwohnung',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'vermieter',l:'Name des Vermieters',t:'text',r:1,ph:'Max Vermieter'},
    {k:'datum_erhoehung',l:'Datum des Mieterhöhungsschreibens',t:'date',r:1},
    {k:'aktuelle_miete',l:'Aktuelle Kaltmiete (€)',t:'text',r:1,ph:'z.B. 850,00'},
    {k:'neue_miete',l:'Geforderte neue Miete (€)',t:'text',r:1,ph:'z.B. 980,00'},
    {k:'widerspruch_grund',l:'Hauptgrund des Widerspruchs',t:'select',r:1,opts:[
      {v:'keine_begruendung',l:'Keine ausreichende Begründung im Schreiben'},
      {v:'ortsüblich_verfehlt',l:'Geforderte Miete übersteigt ortsübliche Vergleichsmiete'},
      {v:'kappungsgrenze',l:'Kappungsgrenze (20% / 15% in angespannten Märkten) überschritten'},
      {v:'form_fehler',l:'Formfehler im Erhöhungsschreiben'},
      {v:'sperrfrist',l:'Sperrfrist noch nicht abgelaufen (15 Monate)'},
    ]},
  ]},

kaution_rueckforderung:{n:'Rückforderung Mietkaution',ic:'💰',cat:'miete',sub:'Kaution',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'neue_adresse',l:'Ihre neue Adresse',t:'text',r:1,ph:'Neue Straße 1, 12345 Berlin'},
    {k:'vermieter',l:'Name des Vermieters / Hausverwaltung',t:'text',r:1,ph:'Max Vermieter'},
    {k:'vermieter_adresse',l:'Adresse des Vermieters',t:'text',r:1,ph:'Vermieterstraße 1'},
    {k:'alte_wohnung',l:'Adresse der alten Wohnung',t:'text',r:1,ph:'Alte Straße 1, 12345 Berlin'},
    {k:'auszug_datum',l:'Datum des Auszugs',t:'date',r:1},
    {k:'kaution_betrag',l:'Kautionsbetrag (€)',t:'text',r:1,ph:'z.B. 2.550,00'},
    {k:'iban',l:'Ihre IBAN für Rückzahlung',t:'text',r:1,ph:'DE89 3704 0044 0532 0130 00'},
    {k:'frist_tage',l:'Frist zur Rückzahlung',t:'select',r:1,opts:[
      {v:'14',l:'14 Tage'},
      {v:'21',l:'21 Tage'},
      {v:'30',l:'30 Tage'},
    ]},
  ]},

// ════════════════════════════════════════
// 3. ARBEIT & BERUF
// ════════════════════════════════════════
eigenkundigung:{n:'Ordentliche Eigenkündigung',ic:'💼',cat:'arbeit',sub:'Kündigung',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'arbeitgeber',l:'Name des Arbeitgebers / Firma',t:'text',r:1,ph:'Muster GmbH'},
    {k:'arbeitgeber_adresse',l:'Adresse des Arbeitgebers',t:'text',r:1,ph:'Firmenstraße 1, 12345 Berlin'},
    {k:'position',l:'Ihre Stellenbezeichnung',t:'text',r:1,ph:'z.B. Buchhalter/in'},
    {k:'eintrittsdatum',l:'Eintrittsdatum (Beginn Arbeitsverhältnis)',t:'date',r:1},
    {k:'kuendigung_zum',l:'Kündigung zum',t:'date',r:1},
    {k:'zeugnis',l:'Arbeitszeugnis beantragen?',t:'select',r:1,opts:[
      {v:'ja_qualifiziert',l:'Ja — qualifiziertes Arbeitszeugnis'},
      {v:'ja_einfach',l:'Ja — einfaches Arbeitszeugnis'},
      {v:'nein',l:'Nein, nicht nötig'},
    ]},
  ]},

urlaubsantrag:{n:'Urlaubsantrag',ic:'🏖️',cat:'arbeit',sub:'Anträge',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'abteilung',l:'Ihre Abteilung',t:'text',r:0,ph:'z.B. Buchhaltung'},
    {k:'vorgesetzter',l:'Name des Vorgesetzten',t:'text',r:1,ph:'Fr. Müller'},
    {k:'urlaub_von',l:'Urlaub von',t:'date',r:1},
    {k:'urlaub_bis',l:'Urlaub bis (einschließlich)',t:'date',r:1},
    {k:'urlaubstage',l:'Anzahl Werktage',t:'text',r:1,ph:'z.B. 5'},
    {k:'vertretung',l:'Vertretung übernimmt',t:'text',r:0,ph:'z.B. Herr Meier'},
  ]},

homeoffice_antrag:{n:'Antrag auf Homeoffice',ic:'🏡',cat:'arbeit',sub:'Anträge',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'position',l:'Ihre Stellenbezeichnung',t:'text',r:1,ph:'z.B. Sachbearbeiter/in'},
    {k:'arbeitgeber',l:'Name des Arbeitgebers',t:'text',r:1,ph:'Muster GmbH'},
    {k:'homeoffice_tage',l:'Gewünschte Homeoffice-Tage pro Woche',t:'select',r:1,opts:[
      {v:'1',l:'1 Tag pro Woche'},
      {v:'2',l:'2 Tage pro Woche'},
      {v:'3',l:'3 Tage pro Woche'},
      {v:'vollzeit',l:'Vollzeit Homeoffice'},
    ]},
    {k:'ab_datum',l:'Gewünschter Beginn',t:'date',r:1},
    {k:'begruendung',l:'Begründung (kurz)',t:'textarea',r:1,ph:'z.B. pflegebedürftiger Angehöriger, langer Arbeitsweg, höhere Produktivität nachweisbar'},
  ]},

zeugnis:{n:'Bitte um Arbeitszeugnis',ic:'📄',cat:'arbeit',sub:'Zeugnisse',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'arbeitgeber',l:'Arbeitgeber / Firma',t:'text',r:1,ph:'Muster GmbH'},
    {k:'position',l:'Ihre Stellenbezeichnung',t:'text',r:1,ph:'z.B. Kaufmännische/r Angestellte/r'},
    {k:'beschaeftigt_seit',l:'Beschäftigt seit',t:'date',r:1},
    {k:'beschaeftigt_bis',l:'Beschäftigt bis (Austrittsdatum)',t:'date',r:0},
    {k:'zeugnis_art',l:'Art des Zeugnisses',t:'select',r:1,opts:[
      {v:'qualifiziert',l:'Qualifiziertes Arbeitszeugnis (mit Beurteilung)'},
      {v:'einfach',l:'Einfaches Arbeitszeugnis (nur Tätigkeitsbeschreibung)'},
      {v:'zwischenzeugnis',l:'Zwischenzeugnis (während Beschäftigung)'},
    ]},
    {k:'frist',l:'Frist zur Ausstellung',t:'select',r:1,opts:[
      {v:'14',l:'14 Tage'},
      {v:'21',l:'21 Tage'},
    ]},
  ]},

// ════════════════════════════════════════
// 4. AUFENTHALT & VISUM
// ════════════════════════════════════════
duldung_verlaengerung:{n:'Antrag auf Verlängerung der Duldung',ic:'🌍',cat:'aufenthalt',sub:'Duldung',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Ahmet'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Yilmaz'},
    {k:'geburtsdatum',l:'Geburtsdatum',t:'date',r:1},
    {k:'adresse',l:'Ihre aktuelle Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Ausländerbehörde',t:'text',r:1,ph:'ABH Heidenheim'},
    {k:'az',l:'Aktenzeichen',t:'text',r:0,ph:'Steht auf dem Duldungsschein'},
    {k:'duldung_laeuft_ab',l:'Aktuelle Duldung läuft ab am',t:'date',r:1},
    {k:'aufenthalt_seit',l:'In Deutschland seit',t:'date',r:0},
    {k:'grund',l:'Hauptgrund für Verlängerung',t:'select',r:1,opts:[
      {v:'reisepass',l:'Kein gültiger Reisepass / Pass beantragt'},
      {v:'abschiebungshindernis_gesundheit',l:'Abschiebungshindernis — Gesundheit (§ 60a Abs.2c)'},
      {v:'abschiebungshindernis_familie',l:'Abschiebungshindernis — Familie / Kinder in DE'},
      {v:'ausbildung',l:'Ausbildung / Beschäftigung (Duldung nach § 60c/60d)'},
      {v:'vorbereitete_ausreise',l:'Ausreise vorbereitet, mehr Zeit benötigt'},
    ]},
    {k:'angehoerige_in_de',l:'Angehörige in Deutschland?',t:'select',r:0,opts:[
      {v:'ja_deutsch',l:'Ja — Ehegatte/Kind mit deutschem Pass'},
      {v:'ja_aufenthalt',l:'Ja — Ehegatte/Kind mit Aufenthaltserlaubnis'},
      {v:'nein',l:'Nein'},
    ]},
  ]},

aufenthaltserlaubnis:{n:'Antrag auf Aufenthaltserlaubnis',ic:'🛂',cat:'aufenthalt',sub:'Aufenthaltserlaubnis',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Ahmet'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Yilmaz'},
    {k:'geburtsdatum',l:'Geburtsdatum',t:'date',r:1},
    {k:'staatsangehoerigkeit',l:'Staatsangehörigkeit',t:'text',r:1,ph:'z.B. türkisch, syrisch'},
    {k:'adresse',l:'Ihre Adresse in Deutschland',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Zuständige Ausländerbehörde',t:'text',r:1,ph:'ABH Stuttgart'},
    {k:'az',l:'Aktenzeichen (falls vorhanden)',t:'text',r:0},
    {k:'grundlage',l:'Rechtsgrundlage / Antragszweck',t:'select',r:1,opts:[
      {v:'§ 28 AufenthG',l:'§ 28 AufenthG — Familiennachzug zu Deutschen'},
      {v:'§ 30 AufenthG',l:'§ 30 AufenthG — Ehegattennachzug'},
      {v:'§ 25 Abs.5 AufenthG',l:'§ 25 Abs.5 — Humanitäre Gründe'},
      {v:'§ 25a AufenthG',l:'§ 25a — Gut integrierte Jugendliche'},
      {v:'§ 18 AufenthG',l:'§ 18 AufenthG — Beschäftigung'},
      {v:'§ 16b AufenthG',l:'§ 16b AufenthG — Studium'},
    ]},
    {k:'aktuelle_status',l:'Aktueller Aufenthaltsstatus',t:'select',r:1,opts:[
      {v:'duldung',l:'Duldung'},
      {v:'gestattung',l:'Aufenthaltsgestattung (Asylverfahren läuft)'},
      {v:'visum',l:'Visum'},
      {v:'kein_dokument',l:'Kein Dokument'},
    ]},
  ]},

widerspruch_ablehnung:{n:'Widerspruch gegen Ablehnungsbescheid',ic:'⚖️',cat:'aufenthalt',sub:'Widerspruch',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Ahmet'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Yilmaz'},
    {k:'geburtsdatum',l:'Geburtsdatum',t:'date',r:1},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Ausstellende Behörde',t:'text',r:1,ph:'ABH Heidenheim'},
    {k:'az',l:'Aktenzeichen des Bescheids',t:'text',r:0},
    {k:'datum',l:'Datum des Bescheids',t:'date',r:1},
    {k:'zustellung_datum',l:'Zustellungsdatum (Wann erhalten?)',t:'date',r:1},
    {k:'ablehnungsgrund',l:'Was wurde abgelehnt?',t:'select',r:1,opts:[
      {v:'aufenthaltserlaubnis',l:'Aufenthaltserlaubnis'},
      {v:'duldung',l:'Duldungsverlängerung'},
      {v:'familiennachzug',l:'Familiennachzug'},
      {v:'arbeitserlaubnis',l:'Arbeitserlaubnis'},
      {v:'reiseausweis',l:'Reiseausweis für Ausländer'},
    ]},
    {k:'widerspruch_grund',l:'Grund des Widerspruchs',t:'textarea',r:1,ph:'z.B. Die Ablehnung berücksichtigt nicht meine familiäre Situation (§ 6 GG). Meine Kinder sind deutsche Staatsbürger...'},
  ]},

// ════════════════════════════════════════
// 5. BEHÖRDE & RECHT
// ════════════════════════════════════════
widerspruch:{n:'Widerspruch gegen Behördenbescheid',ic:'⚖️',cat:'behoerde',sub:'Widerspruch',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Name der Behörde',t:'text',r:1,ph:'z.B. Jobcenter Berlin-Mitte, ABH Heidenheim'},
    {k:'beh_adresse',l:'Adresse der Behörde',t:'text',r:0},
    {k:'datum',l:'Datum des Bescheids',t:'date',r:1},
    {k:'az',l:'Aktenzeichen / Bescheid-Nr.',t:'text',r:0},
    {k:'zustellung_datum',l:'Zustellungsdatum des Bescheids',t:'date',r:1},
    {k:'betreff',l:'Um was geht es? (Thema des Bescheids)',t:'select',r:1,opts:[
      {v:'Leistungskürzung',l:'Leistungskürzung / Sanktion'},
      {v:'Ablehnungsbescheid',l:'Ablehnungsbescheid'},
      {v:'Rückforderungsbescheid',l:'Rückforderung / Erstattung'},
      {v:'Aufhebungsbescheid',l:'Aufhebung eines Bewilligungsbescheids'},
      {v:'Sonstiger Bescheid',l:'Sonstiger Bescheid'},
    ]},
    {k:'widerspruch_begruendung',l:'Ihre Widerspruchsbegründung',t:'textarea',r:1,ph:'z.B. Der Bescheid ist rechtswidrig, weil... / Die genannten Voraussetzungen liegen nicht vor, weil...'},
  ]},

bussgeldbescheid:{n:'Einspruch gegen Bußgeldbescheid',ic:'🚦',cat:'behoerde',sub:'Bußgeld',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Ausstellende Behörde',t:'text',r:1,ph:'z.B. Stadt Stuttgart, Landratsamt'},
    {k:'datum',l:'Datum des Bußgeldbescheids',t:'date',r:1},
    {k:'az',l:'Aktenzeichen',t:'text',r:0},
    {k:'betrag',l:'Bußgeldbetrag (€)',t:'text',r:1,ph:'z.B. 100,00'},
    {k:'vorwurf',l:'Vorgeworfener Verstoß',t:'textarea',r:1,ph:'z.B. Überschreitung der zulässigen Höchstgeschwindigkeit um 21 km/h innerorts'},
    {k:'einspruch_grund',l:'Grund des Einspruchs',t:'select',r:1,opts:[
      {v:'falsche_messung',l:'Fehler bei der Messung / Messprotokoll fehlerhaft'},
      {v:'nicht_ich',l:'Ich war nicht der Fahrer/die Fahrerin'},
      {v:'beschilderung',l:'Beschilderung war nicht erkennbar / fehlend'},
      {v:'notstand',l:'Nothilfe / medizinischer Notfall'},
      {v:'formfehler',l:'Formfehler im Bescheid'},
    ]},
  ]},

mahnbescheid_einspruch:{n:'Einspruch gegen Mahnbescheid',ic:'📬',cat:'behoerde',sub:'Mahnung',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'glaeubiger',l:'Name des Gläubigers (Antragsteller)',t:'text',r:1,ph:'z.B. Telefónica GmbH'},
    {k:'datum',l:'Datum des Mahnbescheids',t:'date',r:1},
    {k:'az',l:'Geschäftsnummer des Mahnbescheids',t:'text',r:0},
    {k:'betrag',l:'Geforderter Betrag (€)',t:'text',r:1,ph:'z.B. 345,00'},
    {k:'einspruch_grund',l:'Warum legen Sie Einspruch ein?',t:'select',r:1,opts:[
      {v:'forderung_nicht_bestehend',l:'Die Forderung besteht nicht'},
      {v:'bereits_bezahlt',l:'Ich habe bereits bezahlt'},
      {v:'forderung_zu_hoch',l:'Die Forderung ist überhöht'},
      {v:'verjährt',l:'Die Forderung ist verjährt'},
    ]},
  ]},

// ════════════════════════════════════════
// 6. VERBRAUCHER & RECHT
// ════════════════════════════════════════
reklamation:{n:'Reklamation / Gewährleistung',ic:'🛒',cat:'verbraucher',sub:'Mängelrechte',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'haendler',l:'Name des Händlers / Unternehmens',t:'text',r:1,ph:'z.B. MediaMarkt GmbH, Online-Shop XY'},
    {k:'haendler_adresse',l:'Adresse des Händlers',t:'text',r:0},
    {k:'artikel',l:'Bezeichnung des mangelhaften Artikels',t:'text',r:1,ph:'z.B. Samsung TV Modell XY, Art.-Nr. 12345'},
    {k:'kaufdatum',l:'Kaufdatum',t:'date',r:1},
    {k:'kaufpreis',l:'Kaufpreis (€)',t:'text',r:1,ph:'z.B. 499,00'},
    {k:'mangel_beschreibung',l:'Beschreibung des Mangels',t:'textarea',r:1,ph:'z.B. Bildschirm zeigt nach 3 Monaten einen vertikalen Streifen, obwohl das Gerät korrekt verwendet wurde'},
    {k:'mangel_seit',l:'Mangel bemerkt am',t:'date',r:1},
    {k:'gewuenschte_loesung',l:'Was verlangen Sie?',t:'select',r:1,opts:[
      {v:'nacherfuellung_reparatur',l:'Nacherfüllung — Reparatur'},
      {v:'nacherfuellung_ersatz',l:'Nacherfüllung — Ersatzlieferung'},
      {v:'minderung',l:'Minderung des Kaufpreises'},
      {v:'ruecktritt',l:'Rücktritt vom Kaufvertrag'},
      {v:'schadensersatz',l:'Schadensersatz'},
    ]},
    {k:'frist',l:'Frist zur Bearbeitung (Tage)',t:'select',r:1,opts:[
      {v:'14',l:'14 Tage'},
      {v:'21',l:'21 Tage'},
    ]},
  ]},

widerruf:{n:'Widerruf Online-Kauf',ic:'↩️',cat:'verbraucher',sub:'Widerruf',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'haendler',l:'Name des Händlers',t:'text',r:1,ph:'z.B. Amazon, Zalando'},
    {k:'bestellnummer',l:'Bestellnummer',t:'text',r:0},
    {k:'artikel',l:'Bezeichnung der Ware',t:'text',r:1,ph:'z.B. Nike Air Max, Größe 42'},
    {k:'kaufdatum',l:'Kaufdatum',t:'date',r:1},
    {k:'erhalt_datum',l:'Erhalt der Ware',t:'date',r:1},
    {k:'widerruf_grund',l:'Widerrufsgrund',t:'select',r:0,opts:[
      {v:'kein_grund',l:'Kein Grund nötig (gesetzliches Widerrufsrecht)'},
      {v:'falsche_groesse',l:'Falsche Größe'},
      {v:'nicht_gefallen',l:'Gefällt mir nicht'},
      {v:'mangelhaft',l:'Artikel mangelhaft'},
    ]},
    {k:'rueckgabe_art',l:'Rücksendung',t:'select',r:1,opts:[
      {v:'paket',l:'Per Paket (Rücksendeetikett erbeten)'},
      {v:'abholung',l:'Bitte Abholung beauftragen'},
    ]},
  ]},

mahnung:{n:'Mahnung schreiben',ic:'💰',cat:'verbraucher',sub:'Forderungen',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'schuldner',l:'Name des Schuldners',t:'text',r:1,ph:'Hans Schulze'},
    {k:'schuldner_adresse',l:'Adresse des Schuldners',t:'text',r:1,ph:'Schulzestraße 1, 12345 Berlin'},
    {k:'betrag',l:'Offener Betrag (€)',t:'text',r:1,ph:'z.B. 500,00'},
    {k:'grund_forderung',l:'Grund der Forderung',t:'text',r:1,ph:'z.B. Rechnung Nr. 2024-15 vom 01.03.2024'},
    {k:'faelligkeitsdatum',l:'Fälligkeitsdatum der ursprünglichen Forderung',t:'date',r:1},
    {k:'mahnung_nr',l:'Mahnstufe',t:'select',r:1,opts:[
      {v:'1. Mahnung',l:'1. Mahnung (freundliche Erinnerung)'},
      {v:'2. Mahnung',l:'2. Mahnung (letzte Mahnung)'},
      {v:'3. Mahnung',l:'3. Mahnung (letzte Warnung vor Inkasso)'},
    ]},
    {k:'zahlungsfrist',l:'Zahlungsfrist (Tage)',t:'select',r:1,opts:[
      {v:'7',l:'7 Tage'},
      {v:'14',l:'14 Tage'},
    ]},
  ]},

// ════════════════════════════════════════
// 7. REISE & URLAUB
// ════════════════════════════════════════
fluggastrechte:{n:'Entschädigung Flugverspätung (EU 261)',ic:'✈️',cat:'reise',sub:'Flugrecht',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'airline',l:'Fluggesellschaft',t:'text',r:1,ph:'z.B. Lufthansa, Ryanair, Eurowings'},
    {k:'airline_adresse',l:'Adresse der Airline (Kundendienst)',t:'text',r:0},
    {k:'flugnummer',l:'Flugnummer',t:'text',r:1,ph:'z.B. LH 1234'},
    {k:'flugdatum',l:'Flugdatum (geplant)',t:'date',r:1},
    {k:'abflughafen',l:'Abflughafen',t:'text',r:1,ph:'z.B. Frankfurt (FRA)'},
    {k:'zielflughafen',l:'Zielflughafen',t:'text',r:1,ph:'z.B. Madrid (MAD)'},
    {k:'buchungsreferenz',l:'Buchungsreferenz',t:'text',r:0},
    {k:'problem_art',l:'Was ist passiert?',t:'select',r:1,opts:[
      {v:'verspaetung_3_4h',l:'Verspätung 3–4 Stunden'},
      {v:'verspaetung_4h_plus',l:'Verspätung über 4 Stunden'},
      {v:'annullierung',l:'Flug annulliert (weniger als 14 Tage vorher)'},
      {v:'befoerderung_verweigerung',l:'Beförderung verweigert (Überbuchung)'},
    ]},
    {k:'entschaedigung',l:'Entschädigungsbetrag',t:'select',r:1,opts:[
      {v:'250',l:'250 € (Flugstrecke bis 1.500 km)'},
      {v:'400',l:'400 € (Flugstrecke 1.500–3.500 km)'},
      {v:'600',l:'600 € (Flugstrecke über 3.500 km)'},
    ]},
  ]},

// ════════════════════════════════════════
// 8. STEUERN & FINANZEN  
// ════════════════════════════════════════
einspruch_steuer:{n:'Einspruch gegen Steuerbescheid',ic:'📊',cat:'steuern',sub:'Einspruch',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'steuer_nr',l:'Steuernummer',t:'text',r:0,ph:'12/345/67890'},
    {k:'finanzamt',l:'Zuständiges Finanzamt',t:'text',r:1,ph:'z.B. Finanzamt Stuttgart-Mitte'},
    {k:'datum',l:'Datum des Steuerbescheids',t:'date',r:1},
    {k:'az',l:'Aktenzeichen / Steuerbescheid-Nr.',t:'text',r:0},
    {k:'steuerart',l:'Art der Steuer',t:'select',r:1,opts:[
      {v:'Einkommensteuer',l:'Einkommensteuer'},
      {v:'Körperschaftsteuer',l:'Körperschaftsteuer'},
      {v:'Umsatzsteuer',l:'Umsatzsteuer'},
      {v:'Gewerbesteuer',l:'Gewerbesteuer'},
      {v:'Grundsteuer',l:'Grundsteuer'},
    ]},
    {k:'einspruch_grund',l:'Einspruchsgrund',t:'textarea',r:1,ph:'z.B. Die Werbungskosten in Höhe von 1.250 € wurden nicht berücksichtigt. Ich lege hiermit Einspruch ein und beantrage die Aussetzung der Vollziehung.'},
    {k:'aussetzung_vollziehung',l:'Aussetzung der Vollziehung beantragen?',t:'select',r:1,opts:[
      {v:'ja',l:'Ja — Zahlung bis Entscheidung aussetzen (§ 361 AO)'},
      {v:'nein',l:'Nein'},
    ]},
  ]},

ratenzahlung:{n:'Ratenzahlungsantrag',ic:'💳',cat:'steuern',sub:'Zahlungen',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'glaeubiger',l:'Gläubiger (Finanzamt / Firma)',t:'text',r:1,ph:'Finanzamt Stuttgart'},
    {k:'gesamtbetrag',l:'Gesamtbetrag der Schuld (€)',t:'text',r:1,ph:'z.B. 3.500,00'},
    {k:'monatsrate',l:'Vorgeschlagene monatliche Rate (€)',t:'text',r:1,ph:'z.B. 200,00'},
    {k:'erster_zahlungstermin',l:'Erster Zahlungstermin',t:'date',r:1},
    {k:'begruendung',l:'Begründung (wirtschaftliche Lage)',t:'textarea',r:1,ph:'z.B. Durch unvorhergesehene Ausgaben (Krankheit, Jobverlust) bin ich vorübergehend nicht in der Lage, den Betrag auf einmal zu begleichen.'},
  ]},

// ════════════════════════════════════════
// 9. GESUNDHEIT
// ════════════════════════════════════════
krankenkasse_widerspruch:{n:'Widerspruch gegen Krankenkasse',ic:'🏥',cat:'gesundheit',sub:'Krankenkasse',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'krankenkasse',l:'Name der Krankenkasse',t:'text',r:1,ph:'z.B. AOK, TK, Barmer'},
    {k:'versicherungsnr',l:'Versicherungsnummer',t:'text',r:0},
    {k:'datum',l:'Datum des Ablehnungsbescheids',t:'date',r:1},
    {k:'az',l:'Aktenzeichen / Bescheid-Nr.',t:'text',r:0},
    {k:'abgelehnte_leistung',l:'Abgelehnte Leistung / was wurde abgelehnt?',t:'text',r:1,ph:'z.B. Physiotherapie, Hilfsmittel (Rollstuhl), Krankenhausbehandlung'},
    {k:'widerspruch_grund',l:'Begründung des Widerspruchs',t:'textarea',r:1,ph:'z.B. Die medizinische Notwendigkeit ist durch ärztliches Attest belegt. Die Ablehnung widerspricht § 12 SGB V...'},
  ]},

// ════════════════════════════════════════
// 10. SOZIALES & FAMILIE
// ════════════════════════════════════════
buergergeld:{n:'Widerspruch gegen Bürgergeld-Bescheid',ic:'👥',cat:'soziales',sub:'Bürgergeld',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Jobcenter (Name & Ort)',t:'text',r:1,ph:'Jobcenter Berlin-Mitte'},
    {k:'beh_adresse',l:'Adresse des Jobcenters',t:'text',r:0},
    {k:'datum',l:'Datum des Bescheids',t:'date',r:1},
    {k:'az',l:'Aktenzeichen / Bedarfsgemeinschaft-Nr.',t:'text',r:0},
    {k:'bescheid_art',l:'Art des Bescheids',t:'select',r:1,opts:[
      {v:'Leistungskürzung',l:'Leistungskürzung / Sanktion'},
      {v:'Ablehnung',l:'Ablehnung des Antrags'},
      {v:'Aufhebung',l:'Aufhebung bisheriger Leistungen'},
      {v:'Rückforderung',l:'Rückforderung bereits gezahlter Leistungen'},
    ]},
    {k:'widerspruch_begruendung',l:'Begründung des Widerspruchs',t:'textarea',r:1,ph:'z.B. Die Sanktion ist unverhältnismäßig. Ich habe den Termin aus folgendem Grund nicht wahrgenommen können...'},
  ]},

familiennachzug:{n:'Antrag auf Familiennachzug',ic:'👨‍👩‍👧',cat:'soziales',sub:'Familie',
  qs:[
    {k:'vorname',l:'Ihr Vorname (Antragsteller in DE)',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse in Deutschland',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'beh',l:'Ausländerbehörde',t:'text',r:1,ph:'ABH Stuttgart'},
    {k:'nachziehende_person',l:'Name der nachziehenden Person',t:'text',r:1,ph:'Fatma Mustermann'},
    {k:'beziehung',l:'Beziehung zur nachziehenden Person',t:'select',r:1,opts:[
      {v:'Ehegatte',l:'Ehegatte/in'},
      {v:'minderjähriges Kind',l:'Minderjähriges Kind'},
      {v:'volljähriges Kind',l:'Volljähriges Kind'},
      {v:'Elternteil',l:'Elternteil'},
    ]},
    {k:'aufenthaltsstatus_antragsteller',l:'Ihr Aufenthaltsstatus',t:'select',r:1,opts:[
      {v:'deutsch',l:'Deutscher Staatsbürger'},
      {v:'niederlassungserlaubnis',l:'Niederlassungserlaubnis'},
      {v:'aufenthaltserlaubnis',l:'Aufenthaltserlaubnis'},
      {v:'subsidiärer Schutz',l:'Subsidiärer Schutz'},
      {v:'anerkannter Flüchtling',l:'Anerkannter Flüchtling (GFK)'},
    ]},
    {k:'aufenthaltsland_nachziehender',l:'Aktueller Aufenthalt der nachziehenden Person',t:'text',r:1,ph:'z.B. Türkei, Syrien'},
  ]},

_default:{n:'Allgemeines Schreiben',ic:'📝',cat:'sonstige',sub:'',
  qs:[
    {k:'vorname',l:'Ihr Vorname',t:'text',r:1,ph:'Max'},
    {k:'nachname',l:'Ihr Nachname',t:'text',r:1,ph:'Mustermann'},
    {k:'adresse',l:'Ihre Adresse',t:'text',r:1,ph:'Musterstraße 1, 12345 Berlin'},
    {k:'empfaenger',l:'Empfänger',t:'text',r:1,ph:'Behörde / Unternehmen'},
    {k:'empfaenger_adresse',l:'Adresse des Empfängers',t:'text',r:0},
    {k:'betreff',l:'Betreff',t:'text',r:1,ph:'Ihr Anliegen kurz'},
    {k:'inhalt',l:'Inhalt des Schreibens',t:'textarea',r:1,ph:'Beschreiben Sie Ihr Anliegen ausführlich'},
  ]},
};

const CATS={
  behoerde:{n:'Beh\u00f6rde & Amt',ic:'\u{1F3DB}\uFE0F',docs:['widerspruch','aufenthaltserlaubnis','einbuergerung','akteneinsicht']},
  steuern:{n:'Steuern',ic:'\u{1F9FE}',docs:['steuer_an','steuer_selbst','einspruch_steuer','steuer_anlage_v']},
  miete:{n:'Miete & Wohnen',ic:'\u{1F3E0}',docs:['mietvertrag','kuendigung_mieter','kuendigung_vermieter','kuendigung_widerspruch','maengelanzeige','mietminderung','kaution']},
  arbeit:{n:'Arbeit & Beruf',ic:'\u{1F4BC}',docs:['arbeitsvertrag','arbeit_kuendigung','arbeit_kuendigung_widerspruch','abmahnung_widerspruch','zeugnis','aufhebungsvertrag']},
  familie:{n:'Familie & Vorsorge',ic:'\u{1F46A}',docs:['testament','vorsorgevollmacht','vollmacht','unterhalt','kindergeld','elterngeld']},
  strafrecht:{n:'Strafrecht',ic:'\u{1F6A8}',docs:['strafanzeige','strafanzeige_beleidigung','strafanzeige_betrug','stalking','koerperverletzung']},
  auto:{n:'Auto & Verkehr',ic:'\u{1F697}',docs:['kfz_kaufvertrag','kfz_kuendigung','unfallbericht','bussgeldbescheid']},
  verbraucher:{n:'Verbraucher',ic:'\u{1F6D2}',docs:['widerruf','mahnschreiben','fluggastrechte','inkasso','abo_kuendigung']},
};
const OFF={kindergeld:'https://www.arbeitsagentur.de/datei/kg1-antrag_ba013559.pdf',elterngeld:'https://www.elterngeld.net/fileadmin/Downloads/Formulare/antrag_elterngeld.pdf',buergergeld:'https://www.arbeitsagentur.de/datei/arbeitslosengeld-ii-alg-ii_ba014020.pdf'};
const LP={tr:/\b(ben|bir|ve|i\u00e7in|bu|var|yok|istiyorum|laz\u0131m)\b/i,ar:/[\u0600-\u06FF]/,ru:/[\u0400-\u04FF]/,fr:/\b(je|vous|pour|les|que|est|bonjour)\b/i,en:/\b(i|you|the|and|for|have|want)\b/i};

let CC='DE',UL='de',P={},CD=null,busy=false,plan='free',dLang='de';
try{const pr=JSON.parse(localStorage.getItem(PK)||'{}');CC=pr.cc||'DE';UL=pr.lang||'de';plan=pr.plan||'free';P=JSON.parse(localStorage.getItem(FK)||'{}');}catch(e){}

function applyUI(){
  const u=UI_TEXTS[UL]||UI_TEXTS.de;
  ['wlc-h','wlc-p','wc1','wc2','wc3','wc4'].forEach((id,i)=>{
    const el=document.getElementById(id);
    if(el) el.textContent=[u.h,u.p,u.c1,u.c2,u.c3,u.c4][i];
  });
  const inp=document.getElementById('inp');
  if(inp) inp.placeholder=u.ph||'Schreiben Sie Ihr Anliegen\u2026';
  document.body.dir=UL==='ar'?'rtl':'ltr';
  document.querySelectorAll('.lang-opt').forEach(b=>b.classList.toggle('on',b.dataset.l===UL));
}
function applyCC(){
  const c=CC_DATA[CC]||CC_DATA.DE;
  const el=id=>document.getElementById(id);
  if(el('sb-cflag')) el('sb-cflag').textContent=c.f;
  if(el('sb-cname')) el('sb-cname').textContent=c.n;
  if(el('sb-clang')) el('sb-clang').textContent=c.l.toUpperCase()+' \u00b7 '+c.law;
  if(el('tb-flag')) el('tb-flag').textContent=c.f;
  if(el('k-cc')) el('k-cc').textContent=c.f+' '+c.n;
  document.querySelectorAll('.co').forEach(el=>el.classList.toggle('on',el.dataset.cc===CC));
}
function setCountry(cc){CC=cc;const c=CC_DATA[cc]||CC_DATA.DE;if(!localStorage.getItem('ch_lm')){UL=c.l||'de';}savePref();applyUI();applyCC();addMsg('ai','\u2705 Land: '+c.f+' '+c.n);}
function setLang(l){UL=l;localStorage.setItem('ch_lm','1');savePref();applyUI();addMsg('ai','✅ Sprache geändert: '+l.toUpperCase());setTimeout(()=>gP('chat'),300);}
function savePref(){localStorage.setItem(PK,JSON.stringify({cc:CC,lang:UL,plan}));}

async function detectLoc(){
  if(localStorage.getItem('ch_ldi')){applyUI();applyCC();loadRecentDocs();return;}
  try{
    const r=await fetch(API+'?action=detect_location');const d=await r.json();
    if(d.country){CC=d.country;if(!localStorage.getItem('ch_lm'))UL=d.lang||'de';localStorage.setItem('ch_ldi','1');savePref();}
  }catch(e){}
  applyUI();applyCC();loadRecentDocs();
}

function detL(t){if(!t||t.length<3)return UL;if(LP.ar.test(t))return'ar';if(LP.ru.test(t))return'ru';if(LP.tr.test(t))return'tr';if(LP.fr.test(t))return'fr';if(LP.en.test(t))return'en';return'de';}
function setFlag(l){dLang=l;['de','tr','ar','ru','en','fr'].forEach(x=>{document.getElementById('fl-'+x)?.classList.toggle('on',x===l);document.getElementById('lf-'+x)?.classList.toggle('on',x===l);});}
function oSB(){document.getElementById('sb').classList.add('open');document.getElementById('ov').classList.add('show');}
function cSB(){document.getElementById('sb').classList.remove('open');document.getElementById('ov').classList.remove('show');}
function oPM(){document.getElementById('pm').style.display='flex';}
function cPM(){document.getElementById('pm').style.display='none';}
function sPlan(p){plan=p;const t={single:'Einzeln',monthly:'Pro Monat',yearly:'Pro Jahr'};const n=t[p]||'Free';document.getElementById('plan-tier').textContent=n;document.getElementById('k-plan-name').textContent=n;savePref();cPM();addMsg('ai','\u2705 Tarif aktiviert!');}
function gP(n){
  document.querySelectorAll('.sn').forEach(x=>x.classList.remove('on'));
  document.querySelectorAll('.pnl').forEach(x=>x.classList.remove('on'));
  document.getElementById('nav-'+n)?.classList.add('on');
  document.getElementById('pnl-'+n)?.classList.add('on');
  const ia=document.getElementById('ia');if(ia)ia.style.display=n==='chat'?'block':'none';
  if(n==='ant')loadHistory();if(n==='konto')loadKonto();cSB();
}
function onKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();}}
function onInp(el){el.style.height='auto';el.style.height=Math.min(el.scrollHeight,120)+'px';if(el.value.length>3)setFlag(detL(el.value));}
function scrl(){const m=document.getElementById('msgs');setTimeout(()=>m?.scrollTo({top:m.scrollHeight,behavior:'smooth'}),80);}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}
function saveConv(){
  const mi=document.getElementById('mi');if(!mi)return;
  const msgs=[...mi.querySelectorAll('.msg')].slice(-16).map(m=>({r:m.classList.contains('user')?'u':'a',h:m.querySelector('.bbl')?.innerHTML||''}));
  try{localStorage.setItem(CK,JSON.stringify({msgs,ts:Date.now()}));}catch(e){}
}
function restoreConv(){
  try{
    const s=JSON.parse(localStorage.getItem(CK)||'{}');
    if(!s.msgs||!s.ts||Date.now()-s.ts>86400000)return;
    const mi=document.getElementById('mi');if(!mi||!s.msgs.length)return;
    document.getElementById('wlc')?.remove();
    s.msgs.forEach(m=>{
      const d=document.createElement('div');d.className='msg '+(m.r==='u'?'user':'ai');
      const av=m.r==='u'?(P.f1?P.f1[0].toUpperCase():'U'):'CH';
      d.innerHTML=`<div class="av">${av}</div><div class="mbody"><div class="mname">${m.r==='u'?((UI_TEXTS[UL]||UI_TEXTS.de).you||'Sie'):'Chat-Help'}</div><div class="bbl">${m.h}</div></div>`;
      mi.appendChild(d);
    });scrl();
  }catch(e){}
}
function addMsg(role,content){
  document.getElementById('wlc')?.remove();
  const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg '+role;
  const u=UI_TEXTS[UL]||UI_TEXTS.de;const av=role==='user'?(P.f1?P.f1[0].toUpperCase():'U'):'CH';
  d.innerHTML=`<div class="av">${av}</div><div class="mbody"><div class="mname">${role==='user'?(u.you||'Sie'):'Chat-Help'}</div><div class="bbl" id="bb${Date.now()}"></div></div>`;
  mi.appendChild(d);const b=d.querySelector('.bbl');
  if(typeof content==='string')b.innerHTML=content.replace(/\n/g,'<br>');else b.appendChild(content);
  scrl();saveConv();return d;
}
function showTyping(){document.getElementById('wlc')?.remove();const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg ai';d.id='typ';d.innerHTML=`<div class="av">CH</div><div class="mbody"><div class="mname">Chat-Help</div><div class="bbl"><div class="typing"><span></span><span></span><span></span></div></div></div>`;mi.appendChild(d);scrl();}
function remT(){document.getElementById('typ')?.remove();}
function showProc(steps){document.getElementById('wlc')?.remove();const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg ai';d.id='proc';const h=steps.map((s,i)=>`<div class="ps${i===0?' on':''}" id="ps${i}"><div class="ps-ic">${s.ic}</div>${s.txt}</div>`).join('');d.innerHTML=`<div class="av">CH</div><div class="mbody"><div class="mname">Chat-Help</div><div class="bbl"><div class="pbox"><div class="pbox-hdr">VERARBEITUNG</div>${h}</div></div></div>`;mi.appendChild(d);scrl();}
function updP(n){document.querySelectorAll('#proc .ps').forEach((el,i)=>{el.classList.remove('on','done');if(i<n)el.classList.add('done');else if(i===n)el.classList.add('on');});scrl();}
function remP(){document.getElementById('proc')?.remove();}

document.getElementById('pf').addEventListener('change',async(e)=>{
  const files=Array.from(e.target.files).slice(0,15);
  if(!files.length)return;
  e.target.value='';
  gP('chat');
  document.getElementById('wlc')?.remove();

  // Thumbnails
  const mi=document.getElementById('mi');
  const pvDiv=document.createElement('div');
  pvDiv.style.cssText='display:flex;flex-wrap:wrap;gap:6px;padding:8px 16px';
  files.forEach(f=>{
    const img=document.createElement('img');
    img.src=URL.createObjectURL(f);
    img.style.cssText='width:64px;height:64px;border-radius:8px;object-fit:cover;border:2px solid rgba(212,168,74,.3)';
    pvDiv.appendChild(img);
  });
  mi.appendChild(pvDiv);scrl();

  // Loading — use showProc style
  showProc([{ic:'\uD83D\uDD0D',txt:'Dokument wird analysiert\u2026'}]);

  // OCR
  let r={};
  try{
    const file=files[0];
    const b64=await new Promise(res=>{const fr=new FileReader();fr.onload=()=>res(fr.result.split(',')[1]);fr.readAsDataURL(file);});
    const resp=await fetch(API+'?action=analyze_photo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({b64,mime:file.type})});
    const d=await resp.json();
    r=d.result||d.ocr||{};
  }catch(err){console.error('OCR:',err);}

  remP();

  // Store for pre-fill
  window._lastOcrData={
    behoerde: r.behoerde||r.absender||r.firma||'',
    datum:    r.datum||'',
    az:       r.aktenzeichen||r.kundennummer||r.az||'',
    frist:    r.frist||'',
    betrag:   r.betrag||''
  };

  // Build result card using addMsg
  const docType=r.doc&&r.doc!=='other'?r.doc:null;
  const doc=docType&&DOCS[docType]?DOCS[docType]:null;

  const card=document.createElement('div');
  let html='';

  // Extracted data
  const rows=[
    [r.erkannt||r.zusammenfassung,'Dokument'],
    [window._lastOcrData.behoerde,'Absender'],
    [window._lastOcrData.datum,'Datum'],
    [window._lastOcrData.az,'Aktenzeichen'],
    [window._lastOcrData.frist,'Frist'],
    [window._lastOcrData.betrag,'Betrag']
  ].filter(([v])=>v);

  if(rows.length){
    html+='<div style="background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.2);border-radius:11px;padding:12px;margin-bottom:12px">';
    rows.forEach(([v,l])=>{
      html+=`<div style="display:flex;gap:10px;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:12.5px"><span style="color:#606080;min-width:90px;font-size:12px">${l}</span><span style="color:#fff;font-weight:600">${v}</span></div>`;
    });
    html+='</div>';
  }

  if(doc){
    html+=`<div style="font-size:12.5px;color:var(--t2);margin-bottom:10px">Ich erstelle eine <strong>${doc.n}</strong>. Die erkannten Daten werden \u00fcbernommen.</div>`;
  }

  // Action buttons
  const opts=[];
  if(docType&&DOCS[docType])opts.push({k:docType,t:DOCS[docType].n,p:true});
  const alts={'widerspruch':['mahnschreiben'],'bussgeldbescheid':['widerspruch'],'mahnschreiben':['widerspruch','ratenzahlung'],'einspruch_steuer':['stundungsantrag_fa'],'kuendigung_mieter':['mietminderung'],'fluggastrechte':['widerruf'],'mietminderung':['widerspruch']};
  (alts[docType]||['widerspruch','widerruf']).slice(0,2).forEach(k=>{if(DOCS[k]&&k!==docType)opts.push({k,t:DOCS[k].n,p:false});});
  if(!opts.length)opts.push({k:'widerspruch',t:'Widerspruch einlegen',p:true});

  html+='<div style="display:flex;flex-direction:column;gap:7px">';
  opts.forEach(o=>{
    html+=`<button onclick="qS('${o.k}')" style="text-align:left;padding:11px 14px;background:${o.p?'rgba(212,168,74,.12)':'rgba(255,255,255,.04)'};border:1.5px solid ${o.p?'rgba(212,168,74,.3)':'rgba(255,255,255,.08)'};border-radius:11px;color:${o.p?'var(--gold)':'var(--t2)'};font-size:13px;font-weight:${o.p?700:400};cursor:pointer;font-family:inherit">${o.p?'\uD83D\uDCC4 ':'\u2192 '}${o.t}</button>`;
  });
  html+='</div>';
  html+='<div style="font-size:10.5px;color:#404060;margin-top:8px">Automatische Erkennung \u2014 keine rechtliche Bewertung.</div>';

  addMsg('ai',html);
});




async function doSend(){
  const inp=document.getElementById('inp');const tx=inp.value.trim();
  if(!tx||busy)return;inp.value='';inp.style.height='auto';
  setFlag(detL(tx));addMsg('user',tx);cSB();
  busy=true;document.getElementById('sbtn').disabled=true;
  showTyping();await sleep(500);remT();
  if(!P.f1){addMsg('ai','Bitte zuerst Ihre pers\u00f6nlichen Daten:');showProfileForm(null);busy=false;document.getElementById('sbtn').disabled=false;return;}
  try{
    const r=await fetch(API+'?action=detect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text:tx,lang:dLang,country:CC})});
    const d=await r.json();
    if(d.doc&&d.doc!=='other'&&DOCS[d.doc]){if(d.reply)addMsg('ai',d.reply);CD=d.doc;setTimeout(()=>showQuestions(d.doc),300);}
    else{addMsg('ai',d.reply||'Welches Dokument ben\u00f6tigen Sie?');showAllCats();}
  }catch(e){addMsg('ai','Welches Dokument ben\u00f6tigen Sie?');showAllCats();}
  busy=false;document.getElementById('sbtn').disabled=false;
}
function showCat(k){
  const cat=CATS[k];if(!cat)return;cSB();addMsg('user',cat.n);
  const c=document.createElement('div');
  const p=document.createElement('p');p.style.cssText='font-size:13.5px;color:#d0d0f8;margin-bottom:9px';p.textContent='W\u00e4hlen Sie das Dokument:';c.appendChild(p);
  const list=document.createElement('div');list.className='dlist';
  cat.docs.forEach(dk=>{
    const doc=DOCS[dk];if(!doc)return;const row=document.createElement('div');row.className='drow';
    const pr=doc.price===0?`<span class="dprice dp-free">Kostenlos</span>`:plan==='monthly'||plan==='yearly'?`<span class="dprice dp-abo">Im Abo</span>`:`<span class="dprice dp-pay" style="display:none"></span>`;
    row.innerHTML=`<div class="drow-ic">${doc.ic}</div><div><div class="drow-n">${doc.n}</div><div class="drow-s">${doc.sub}</div></div>${pr}`;
    row.onclick=()=>{document.querySelectorAll('.drow').forEach(x=>x.style.pointerEvents='none');addMsg('user',doc.n);CD=dk;setTimeout(()=>showQuestions(dk),300);};
    list.appendChild(row);
  });c.appendChild(list);addMsg('ai',c);
}
function showAllCats(){
  const g=document.createElement('div');g.className='cgrid';
  Object.entries(CATS).forEach(([k,c])=>{
    const card=document.createElement('div');card.className='ccard';
    card.innerHTML=`<div class="ccard-ic">${c.ic}</div><div class="ccard-n">${c.n}</div>`;
    card.onclick=()=>{document.querySelectorAll('.ccard').forEach(x=>x.style.pointerEvents='none');addMsg('user',c.n);setTimeout(()=>showCat(k),200);};
    g.appendChild(card);
  });addMsg('ai',g);
}
function showQuestions(dk){
  const _ocr=window._lastOcrData||{};
  const doc=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  const box=document.createElement('div');box.className='fbox';
  box.innerHTML=`<div class="fhdr"><div class="fhdr-ic">${doc.ic}</div><div class="fhdr-t">${doc.n}</div><div class="fhdr-cc">${c.f} ${CC}</div></div>`;
  const body=document.createElement('div');body.className='fbody';
  const pb=document.createElement('div');pb.className='prog-bar';const pf=document.createElement('div');pf.className='prog-fill';pf.style.width='10%';pb.appendChild(pf);body.appendChild(pb);
  const ans={};
  (doc.qs||[]).forEach(q=>{
    const w=document.createElement('div');w.className='fi';w.style.marginBottom='11px';
    const _pf={
      vorname:P.f1||'',nachname:P.f2||'',name:(P.f1&&P.f2?P.f1+' '+P.f2:''),
      adresse:P.f3||'',plz_ort:P.f4||'',geburtsdatum:P.f5||'',
      
      datum:_ocr.datum,erhalten_am:_ocr.datum,flugdatum:_ocr.datum,
      beh:_ocr.behoerde,empfaenger:_ocr.behoerde,vermieter:_ocr.behoerde,
      anbieter:_ocr.behoerde,airline:_ocr.behoerde,firma:_ocr.behoerde,
      absender_name:_ocr.behoerde,
      az:_ocr.az,aktenzeichen:_ocr.az,flugnummer:_ocr.az,
      kundennummer:_ocr.az,vertragsnummer:_ocr.az,buchungsreferenz:_ocr.az,
      frist:_ocr.frist,frist_datum:_ocr.frist,zahlungsdatum:_ocr.frist,
      betrag:_ocr.betrag,schadensbetrag:_ocr.betrag,
      datum_bescheid:_ocr.datum,bescheid_datum:_ocr.datum,
      kuendigungsdatum:_ocr.datum,kuendigung_datum:_ocr.datum,
      unfalldatum:_ocr.datum,tatzeit:_ocr.datum
    }
    const _v=_pf[q.k]||'';
    let inp=q.t==='textarea'?`<textarea data-k="${q.k}" rows="${q.r?3:2}"${q.r?' required':''}>${_v}</textarea>`:q.t==='date'?`<input type="date" data-k="${q.k}" value="${_v}"${q.r?' required':''}>`:`<input type="text" data-k="${q.k}" value="${_v}"${q.r?' required':''}">`;
    w.innerHTML=`<label>${q.l}${q.r?' <span style="color:var(--rd)">*</span>':' <span style="color:var(--t4);font-size:9px">(optional)</span>'}</label>${inp}`;
    const ie=w.querySelector('[data-k]');
    if(ie)ie.addEventListener('input',()=>{const filled=[...box.querySelectorAll('[required]')].filter(el=>el.value.trim()).length;const tot=box.querySelectorAll('[required]').length;pf.style.width=Math.max(10,Math.round(filled/Math.max(1,tot)*100))+'%';});
    body.appendChild(w);
  });
  const btn=document.createElement('button');btn.className='btn-g';btn.textContent='\u{1F4C4} Dokument generieren \u2192';
  btn.onclick=async()=>{
    const reqs=[...box.querySelectorAll('[required]')];
    for(const el of reqs){if(!el.value.trim()){el.focus();el.style.borderColor='var(--rd)';setTimeout(()=>el.style.borderColor='',2000);return;}}
    box.querySelectorAll('[data-k]').forEach(el=>ans[el.dataset.k]=el.value);
    btn.disabled=true;btn.textContent='\u23F3 Wird erstellt...';
    const toneDocs=['widerspruch', 'einspruch_steuer', 'bussgeldbescheid', 'mietminderung', 'maengel_vermieter', 'widerspruch_mieterhoehung', 'reklamation', 'widerspruch_ablehnung', 'buergergeld', 'krankenkasse_widerspruch', 'mahnbescheid_einspruch', 'fluggastrechte'];
    const planLvl=P.plan||'free'; // 'basic','pro','elite'
    if((planLvl==='pro'||planLvl==='elite')&&toneDocs.includes(dk)){
      btn.disabled=false;btn.textContent='\uD83D\uDCC4 Dokument erstellen \u2192';
      showToneSelector(dk,ans,planLvl);
    } else {
      await genDoc(dk,ans);
    }
  };
  body.appendChild(btn);box.appendChild(body);
  addMsg('ai',`Ich erstelle Ihren ${doc.n} f\u00fcr ${c.f} ${c.n}:`);
  addMsg('ai',box);scrl();
}
async function genDoc(dk,ans){
  // Plan check
  const userPlan=P.plan||'free';
  const tier=getDocPrice(dk);
  const freeAllowed=['kuendigung_handy','kuendigung_internet','widerruf'];
  if(userPlan==='free'&&!freeAllowed.includes(dk)){
    showPaywall(dk,ans);return;
  }
  busy=true;document.getElementById('sbtn').disabled=true;
  const doc=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  showProc([{ic:'\u{1F510}',txt:`Daten anonymisieren (${c.f} ${c.n})...`},{ic:'🔬',txt:'DeepAnalyse läuft...'},{ic:'\u270D\uFE0F',txt:'ChatHelp AI schreibt...'},{ic:'\u{1F512}',txt:'Echte Daten einsetzen...'}]);
  try{
    updP(0);await sleep(200);updP(1);
    const r=await fetch(API+'?action=generate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docType:dk,docName:doc.n,answers:ans,tone:ans._tone||'standard',profile:P,lang:dLang,country:CC})});
    updP(2);const data=await r.json();updP(3);await sleep(500);remP();
    if(data.error){addMsg('ai','\u274C '+data.error);busy=false;document.getElementById('sbtn').disabled=false;return;}
    const rm={de:'\u2705 Ihr Dokument ist fertig!',tr:'\u2705 Belgeniz haz\u0131r!',ar:'\u2705 \u0645\u0633\u062a\u0646\u062f\u0643 \u062c\u0627\u0647\u0632!',ru:'\u2705 \u0413\u043e\u0442\u043e\u0432\u043e!',en:'\u2705 Your document is ready!',fr:'\u2705 Votre document est pr\u00eat!'};
    addMsg('ai',rm[UL]||'\u2705 Fertig!');showResult(data.document,dk,data.research,data.docId);
    if(plan!=='free'&&data.document.length>200){
      setTimeout(async()=>{try{const fu=await fetch(API+'?action=followup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docType:dk,docName:doc.n,answers:ans,draft:data.document,country:CC,lang:dLang})});const fd=await fu.json();if(fd.needs_more&&fd.questions?.length)showFollowup(dk,fd,ans);}catch(e){}},1500);
    }
  }catch(ex){remP();addMsg('ai','\u274C Fehler: '+ex.message);}
  busy=false;document.getElementById('sbtn').disabled=false;
}
function showFollowup(dk,fd,prevAns){
  const box=document.createElement('div');box.className='fubox';
  box.innerHTML=`<div class="fubox-t">\u{1F50D} Erg\u00e4nzende Fragen</div><div class="fubox-msg">${fd.message}</div>`;
  const ans={...prevAns};
  fd.questions.forEach(q=>{
    const w=document.createElement('div');w.className='fi';w.style.marginBottom='10px';
    let inp=q.type==='textarea'?`<textarea data-k="${q.key}" rows="3"></textarea>`:`<input type="${q.type||'text'}" data-k="${q.key}">`;
    w.innerHTML=`<label>${q.label}${q.required?' <span style="color:var(--rd)">*</span>':''}</label>${inp}`;box.appendChild(w);
  });
  const btn=document.createElement('button');btn.className='btn-g';btn.style.marginTop='12px';btn.textContent='Dokument verbessern \u2192';
  btn.onclick=async()=>{box.querySelectorAll('[data-k]').forEach(el=>ans[el.dataset.k]=el.value);btn.disabled=true;btn.textContent='\u23F3';await genDoc(dk,ans);};
  const skip=document.createElement('button');skip.className='btn-s';skip.style.cssText='margin-top:8px;width:100%';skip.textContent='\u00dcberspringen';skip.onclick=()=>box.remove();
  box.appendChild(btn);box.appendChild(skip);addMsg('ai',box);
}
function makePDF(doc,name,cc){
  const{jsPDF}=window.jspdf;const c=CC_DATA[cc||CC]||CC_DATA.DE;
  const pdf=new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
  pdf.setFillColor(7,7,14);pdf.rect(0,0,210,19,'F');
  pdf.setTextColor(212,168,74);pdf.setFontSize(12);pdf.setFont('helvetica','bold');pdf.text('ChatHelp',14,12);
  pdf.setTextColor(160,160,190);pdf.setFontSize(7.5);pdf.setFont('helvetica','normal');pdf.text('chat-help.de \u00b7 chat-help.com',14,17);
  pdf.setTextColor(120,120,150);pdf.text(name+' \u00b7 '+c.n,200,12,{align:'right'});pdf.text(new Date().toLocaleDateString('de-DE'),200,17,{align:'right'});
  pdf.setFillColor(246,246,252);pdf.rect(0,19,210,7,'F');pdf.setTextColor(110,110,140);pdf.setFontSize(6.5);
  pdf.text('KI-erstelltes Dokument \u2014 keine Rechtsberatung \u2014 bitte von einem Rechtsanwalt pr\u00fcfen | chat-help.de',105,23.5,{align:'center'});
  pdf.setTextColor(18,18,28);pdf.setFontSize(10);pdf.setFont('helvetica','normal');
  const lines=pdf.splitTextToSize(doc,183);let y=33;
  lines.forEach(l=>{if(y>277){pdf.addPage();y=16;}pdf.text(l,13,y);y+=5.5;});
  const pc=pdf.internal.getNumberOfPages();
  for(let pg=1;pg<=pc;pg++){pdf.setPage(pg);pdf.setDrawColor(200,200,220);pdf.line(13,284,197,284);pdf.setFontSize(7);pdf.setTextColor(130,130,160);pdf.text('Chat-Help \u2014 chat-help.de | Kein Rechtsrat | DSGVO | EU-Server | '+c.n,105,289,{align:'center'});pdf.text(`${pg}/${pc}`,197,289,{align:'right'});}
  pdf.save('ChatHelp-'+name.replace(/[\s\/\\:*?"<>|]/g,'-')+'-'+CC+'.pdf');
}
function showResult(doc,dk,research,docId){
  const d=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  const box=document.createElement('div');box.className='rbox';
  const hdr=document.createElement('div');hdr.className='rhdr';
  hdr.innerHTML=`<div><div class="rtitle">${d.n}</div><div class="rcc">${c.f} ${c.n}</div></div>`;
  const ac=document.createElement('div');ac.className='racts';
  const cp=document.createElement('button');cp.className='act';cp.innerHTML='<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Kopieren';cp.onclick=()=>navigator.clipboard.writeText(doc).then(()=>{cp.innerHTML='<svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>\u2705 Kopiert';setTimeout(()=>{cp.innerHTML='<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Kopieren';},2000);});
  const em=document.createElement('button');em.className='act';em.innerHTML='<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>E-Mail';em.onclick=()=>{const e=P.f7||prompt('E-Mail:');if(e)window.open('mailto:'+e+'?subject='+encodeURIComponent('Ihr Dokument')+'&body='+encodeURIComponent(doc));};
  const pd=document.createElement('button');pd.className='act gold';pd.innerHTML='<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9,15 12,18 15,15"/></svg>PDF';pd.onclick=()=>makePDF(doc,d.n,CC);
  ac.appendChild(cp);ac.appendChild(em);ac.appendChild(pd);hdr.appendChild(ac);
  const cnt=document.createElement('div');cnt.className='rcnt';
  const pre=document.createElement('pre');pre.className='rpre';const pid='rp'+Date.now();pre.id=pid;cnt.appendChild(pre);
  if(research){const rb=document.createElement('div');rb.className='research-box';rb.innerHTML='<div class="research-t">\u{1F4DA} Aktuelle Rechtslage</div>';research.split('\n').filter(l=>l.trim()).slice(0,5).forEach(line=>{const el=document.createElement('div');el.className='research-item';el.textContent=line.replace(/^[-\u2022*]\s*/,'');rb.appendChild(el);});cnt.appendChild(rb);}
  const qual=document.createElement('div');qual.className='rqual';qual.innerHTML='\u{1F512} Pers\u00f6nliche Daten nur auf Ihrem Server \00b7 ChatHelp AI \u00b7 '+c.f+' '+c.law+' \u00b7 DSGVO';
  const ns=document.createElement('div');ns.className='rns';ns.innerHTML='<div class="rns-t">N\u00e4chste Schritte</div>';
  (d.ns||[]).forEach(s=>{const el=document.createElement('div');el.className='rns-item';el.textContent=s;ns.appendChild(el);});
  box.appendChild(hdr);box.appendChild(cnt);box.appendChild(qual);box.appendChild(ns);
  if(OFF[dk]){const ob=document.createElement('div');ob.className='offbox';ob.innerHTML=`<div class="offbox-t">\u{1F4CB} Offizielles Formular verf\u00fcgbar</div><div class="offbox-d">Zus\u00e4tzlich das offizielle Formular herunterladen.</div><a href="${OFF[dk]}" target="_blank" class="offbox-a">\u2B07\uFE0F Offizielles Formular</a>`;box.appendChild(ob);}
  if(docId){const kr=document.createElement('div');kr.className='keep-row';kr.innerHTML='<span>\u23F0 Wird in 24h gel\u00f6scht \u2014 oder:</span>';const kb=document.createElement('button');kb.className='act';kb.innerHTML='<svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/></svg>Behalten';kb.onclick=async()=>{const r=await fetch(API+'?action=keepdoc',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId,keep:true})});const dt=await r.json();if(dt.success){kb.innerHTML='\u2705 Dauerhaft';kb.disabled=true;kb.className='act kept';}};kr.appendChild(kb);box.appendChild(kr);}
  // Payment prompt (show after free use)
  if(plan==='free'&&d.price>0){
    const payRow=document.createElement('div');
    payRow.style.cssText='padding:14px 16px;border-top:1px solid var(--bdr);background:linear-gradient(135deg,rgba(212,168,74,.06),rgba(212,168,74,.02))';
    payRow.innerHTML=`<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:2px">\u{1F4C4} Dokument entsperren</div><div style="font-size:11.5px;color:var(--t3)">PDF herunterladen &amp; dauerhaft speichern</div></div>
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:18px;font-weight:800;color:var(--gold)">${d.price.toFixed(2).replace('.',',')}\u20ac</span>
        <button class="btn-g" style="width:auto;padding:8px 18px;font-size:13px" onclick="oPM()">Jetzt kaufen</button>
      </div>
    </div>`;
    box.appendChild(payRow);
  }
  const chips=document.createElement('div');chips.className='chips';
  const c1=document.createElement('button');c1.className='chip';c1.textContent='\u270F\uFE0F Neues Dokument';c1.onclick=()=>NC();
  const c2=document.createElement('button');c2.className='chip';c2.textContent='\u{1F4C2} Meine Antr\u00e4ge';c2.onclick=()=>gP('ant');
  const c3=document.createElement('button');c3.className='chip';c3.textContent='\u{1F504} \u00dcberarbeiten';c3.onclick=()=>showQuestions(dk);
  chips.appendChild(c1);chips.appendChild(c2);chips.appendChild(c3);box.appendChild(chips);
  addMsg('ai',box);
  const el=document.getElementById(pid);let i=0;
  function type(){if(i<doc.length){el.innerHTML=doc.substring(0,i+1)+'<span class="blink"></span>';i++;setTimeout(type,i<500?3:7);}else el.textContent=doc;}type();scrl();
}
function showProfileForm(afterDk){
  const form=document.createElement('div');form.className='fbox';
  form.innerHTML='<div class="fhdr"><div class="fhdr-ic">\u{1F464}</div><div class="fhdr-t">Pers\u00f6nliche Daten</div></div>';
  const body=document.createElement('div');body.className='fbody';
  const g=document.createElement('div');g.className='fgrid';
  [{id:'f1',l:'Vorname',t:'text',ph:'Max',f:false,ac:'given-name'},{id:'f2',l:'Nachname',t:'text',ph:'Mustermann',f:false,ac:'family-name'},{id:'f3',l:'Stra\u00dfe & Nr.',t:'text',ph:'Musterstra\u00dfe 1',f:false,ac:'address-line1'},{id:'f4',l:'PLZ & Ort',t:'text',ph:'12345 Berlin',f:false,ac:'address-level2'},{id:'f5',l:'Geburtsdatum',t:'date',ph:'',f:false,ac:'bday'},{id:'f6',l:'Telefon',t:'tel',ph:'+49 123 456789',f:false,ac:'tel'},{id:'f7',l:'E-Mail',t:'email',ph:'ihre@email.de',f:true,ac:'email'}].forEach(f=>{
    const w=document.createElement('div');w.className='fi'+(f.f?' fful':'');
    w.innerHTML=`<label>${f.l}</label><input type="${f.t}" id="${f.id}" placeholder="${f.ph}" autocomplete="${f.ac}" value="${P[f.id]||''}">`;g.appendChild(w);
  });
  body.appendChild(g);
  const dn=document.createElement('div');dn.className='dsgvo-n';dn.innerHTML='\u{1F512} <b>Maximale Datensicherheit:</b> ChatHelp AI nutzt NUR anonyme Platzhalter [VORNAME] etc. Echte Daten bleiben auf Ihrem EU-Server. 24h Auto-L\u00f6schung.';body.appendChild(dn);
  const btn=document.createElement('button');btn.className='btn-g';btn.textContent='Weiter und Dokument erstellen \u2192';
  btn.onclick=()=>{
    const f1=document.getElementById('f1').value.trim(),f2=document.getElementById('f2').value.trim(),f3=document.getElementById('f3').value.trim(),f7=document.getElementById('f7').value.trim();
    if(!f1||!f2||!f3||!f7){alert('Bitte Vorname, Nachname, Adresse und E-Mail.');return;}
    P={f1,f2,f3,f4:document.getElementById('f4').value.trim(),f5:document.getElementById('f5').value,f6:document.getElementById('f6').value.trim(),f7};
    try{localStorage.setItem(FK,JSON.stringify(P));}catch(ex){}
    btn.disabled=true;addMsg('user',f1+' '+f2);
    setTimeout(()=>{addMsg('ai','\u2705 Profil gespeichert!');const dk=afterDk||CD;if(dk)setTimeout(()=>showQuestions(dk),300);else showAllCats();},350);
  };
  body.appendChild(btn);form.appendChild(body);addMsg('ai',form);
}
async function loadHistory(){
  const list=document.getElementById('ant-list');const stats=document.getElementById('ant-stats');
  list.innerHTML='<div style="text-align:center;padding:28px;color:var(--t3);font-size:13px">\u23F3 Laden...</div>';
  try{
    const r=await fetch(API+'?action=history');const data=await r.json();const docs=data.docs||[];
    if(!docs.length){stats.style.display='none';list.innerHTML='<div class="ant-empty"><div class="ant-empty-ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg></div><div class="ant-empty-t">Noch keine Antr\u00e4ge</div><div class="ant-empty-s">Erstellen Sie Ihr erstes Dokument \u2014 es erscheint hier.</div></div>';return;}
    const kept=docs.filter(d=>d.keep).length;const soon=docs.filter(d=>!d.keep&&parseInt(d.expiresIn)<3).length;
    document.getElementById('stat-tot').textContent=docs.length;document.getElementById('stat-kept').textContent=kept;document.getElementById('stat-exp').textContent=soon;stats.style.display='flex';list.innerHTML='';
    docs.forEach(d=>{
      const docDef=DOCS[d.docType]||DOCS._default;const cc=CC_DATA[d.country]||CC_DATA.DE;
      const expCls=d.keep?'ae-kept':parseInt(d.expiresIn)<3?'ae-soon':'ae-norm';
      const expTxt=d.keep?'\u2705 Dauerhaft gespeichert':'\u23F0 L\u00e4uft ab in '+d.expiresIn;
      const card=document.createElement('div');card.className='acard';
      const prev=(d.preview||'').replace(/`/g,'\\`').replace(/\n/g,'\\n');
      card.innerHTML=`<div class="acard-hdr"><div class="acard-ic">${docDef.ic}</div><div class="acard-info"><div class="acard-name">${d.docName}</div><div class="acard-meta">${d.created} \u00b7 ${cc.f} ${cc.n}</div></div><div class="acard-acts"><button class="ab" onclick="dlDoc('${d.docName}','${d.country}',\`${prev}\`)"><svg viewBox="0 0 24 24"><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9,15 12,18 15,15"/><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>PDF</button><button class="ab ${d.keep?'kept':''}" onclick="keepDoc('${d.id}',this)">${d.keep?'\u2705 Behalten':'<svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/></svg>Behalten'}</button><button class="ab del" onclick="delDoc('${d.id}',this)"><svg viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>L\u00f6schen</button></div></div><div class="acard-body"><div class="aprev">${d.preview||''}<div class="aprev-fade"></div></div><span class="aexp ${expCls}">${expTxt}</span></div>`;
      list.appendChild(card);
    });loadRecentDocs(docs);
  }catch(ex){list.innerHTML='<div style="text-align:center;padding:28px;color:var(--rd);font-size:13px">\u274C '+ex.message+'</div>';}
}
async function keepDoc(docId,btn){const r=await fetch(API+'?action=keepdoc',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId,keep:true})});const d=await r.json();if(d.success){btn.className='ab kept';btn.innerHTML='\u2705 Behalten';btn.disabled=true;setTimeout(loadHistory,500);}}
async function delDoc(docId,btn){if(!confirm('Dokument wirklich l\u00f6schen?'))return;const r=await fetch(API+'?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId})});const d=await r.json();if(d.success)loadHistory();}
function dlDoc(name,cc,preview){makePDF(preview.replace(/\\n/g,'\n'),name,cc);}
function loadRecentDocs(docs){
  if(!docs){fetch(API+'?action=history').then(r=>r.json()).then(d=>loadRecentDocs(d.docs||[])).catch(()=>{});return;}
  const sec=document.getElementById('recent-sec');const list=document.getElementById('recent-list');
  if(!sec||!list||!docs.length){if(sec)sec.style.display='none';return;}
  sec.style.display='block';list.innerHTML='';
  docs.slice(0,3).forEach(d=>{
    const docDef=DOCS[d.docType]||DOCS._default;const item=document.createElement('div');item.className='recent-item';
    item.innerHTML=`<div class="recent-item-ic">${docDef.ic}</div><div class="recent-item-n">${d.docName}</div><div class="recent-item-d">${d.created}</div>`;
    item.onclick=()=>gP('ant');list.appendChild(item);
  });
}
function loadKonto(){
  renderKontoPlanSelector();
  // Show user info
  const user = JSON.parse(localStorage.getItem('ch_user')||'null');
  if(user){
    const info = document.getElementById('konto-user-info');
    if(info) info.style.display='flex';
    const av = document.getElementById('konto-avatar');
    if(av) av.textContent=(user.name||user.email||'U')[0].toUpperCase();
    const nm = document.getElementById('konto-name');
    if(nm) nm.textContent=user.name||'-';
    const em = document.getElementById('konto-email');
    if(em) em.textContent=user.email||'-';
    const pb = document.getElementById('konto-plan-badge');
    if(pb){ pb.textContent=(user.plan||'FREE').toUpperCase(); }
  }
  document.getElementById('k-name').textContent=P.f1&&P.f2?P.f1+' '+P.f2:'\u2014';
  document.getElementById('k-email').textContent=P.f7||'\u2014';
  document.getElementById('k-adr').textContent=P.f3?(P.f3+(P.f4?' '+P.f4:'')):'—';
  const c=CC_DATA[CC]||CC_DATA.DE;document.getElementById('k-cc').textContent=c.f+' '+c.n;
}
function editProfile(){gP('chat');setTimeout(()=>{addMsg('ai','Profil bearbeiten:');showProfileForm(null);},200);}
function deleteProfile(){if(!confirm('Profil wirklich l\u00f6schen?'))return;P={};try{localStorage.removeItem(FK);}catch(e){}loadKonto();addMsg('ai','\u2705 Profil gel\u00f6scht.');gP('chat');}
function qS(dk){cSB();gP('chat');const doc=DOCS[dk]||DOCS._default;addMsg('user',doc.n);CD=dk;if(!P.f1){setTimeout(()=>{addMsg('ai','Bitte zuerst Ihre Daten:');showProfileForm(dk);},400);}else setTimeout(()=>showQuestions(dk),400);}
function NC(){
  CD=null;busy=false;
  try{localStorage.removeItem(CK);}catch(e){}
  location.reload();
}

applyUI();applyCC();restoreConv();detectLoc();
</script>

<!-- IMPRESSUM MODAL -->
<div class="lm" id="lm-imp" onclick="if(event.target===this)cLM('lm-imp')">
<div class="lm-box">
  <div class="lm-hdr">
    <div class="lm-title">&#x1F4CB; Impressum</div>
    <button class="lm-close" onclick="cLM('lm-imp')">&#x2715;</button>
  </div>
  <div class="lm-body">
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;5 TMG &mdash; Diensteanbieter</div>
      <div class="lm-grid">
        <div class="lm-card"><div class="lm-card-l">Unternehmen</div><div class="lm-card-v">Acerasoft LLC</div></div>
        <div class="lm-card"><div class="lm-card-l">Rechtsform</div><div class="lm-card-v">LLC &middot; Delaware, USA</div></div>
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Adresse</div><div class="lm-card-v">8 The Green, Suite B<br>Dover, Delaware 19901 &middot; USA</div></div>
        <div class="lm-card"><div class="lm-card-l">E-Mail</div><div class="lm-card-v"><a href="mailto:info@chat-help.de">info@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Steuer-ID (EIN)</div><div class="lm-card-v">61-2070643</div></div>
      </div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;55 Abs. 2 RStV</div>
      <h2>Inhaltlich Verantwortlicher</h2>
      <div class="lm-grid">
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Verantwortlich f&uuml;r den Inhalt</div><div class="lm-card-v">Serdar Bilgi&ccedil;</div></div>
        <div class="lm-card"><div class="lm-card-l">E-Mail</div><div class="lm-card-v"><a href="mailto:info@chat-help.de">info@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Datenschutz</div><div class="lm-card-v"><a href="mailto:datenschutz@chat-help.de">datenschutz@chat-help.de</a></div></div>
      </div>
    </div>
    <div class="lm-sec">
      <h2>Haftungsausschluss</h2>
      <p>Die durch ChatHelp erstellten Rechtsdokumente sind KI-generierte Vorlagen und stellen <strong>keine Rechtsberatung</strong> im Sinne des RDG dar. Sie ersetzen nicht die Beratung durch einen zugelassenen Rechtsanwalt.</p>
      <div class="lm-warn"><p>&#x26A0;&#xFE0F; KI-erstellte Dokumente sind Vorlagen &mdash; bitte von einem Rechtsanwalt pr&uuml;fen lassen.</p></div>
    </div>
    <div class="lm-sec">
      <h2>KI-Transparenz &mdash; EU AI Act</h2>
      <p>ChatHelp f&auml;llt in die Kategorie <strong>&bdquo;geringes Risiko&ldquo;</strong> gem&auml;&szlig; EU AI Act. Alle Dokumente sind KI-generiert und werden dem Nutzer klar als solche kenntlich gemacht.</p>
    </div>
    <div class="lm-sec">
      <h2>Streitschlichtung (ODR)</h2>
      <p>EU-Streitschlichtungsplattform: <a href="https://ec.europa.eu/consumers/odr" target="_blank" style="color:var(--gold)">ec.europa.eu/consumers/odr</a><br>Wir sind nicht verpflichtet, an Streitschlichtungsverfahren teilzunehmen.</p>
    </div>
  </div>
</div>
</div>

<!-- DATENSCHUTZ MODAL -->
<div class="lm" id="lm-dsc" onclick="if(event.target===this)cLM('lm-dsc')">
<div class="lm-box">
  <div class="lm-hdr">
    <div class="lm-title">&#x1F512; Datenschutzerkl&auml;rung</div>
    <button class="lm-close" onclick="cLM('lm-dsc')">&#x2715;</button>
  </div>
  <div class="lm-body">
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;1 &mdash; Verantwortlicher</div>
      <div class="lm-grid">
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Betreiber</div><div class="lm-card-v">Acerasoft LLC &middot; 8 The Green, Ste B &middot; Dover, Delaware 19901 &middot; USA</div></div>
        <div class="lm-card"><div class="lm-card-l">Datenschutz</div><div class="lm-card-v"><a href="mailto:datenschutz@chat-help.de">datenschutz@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Aufsichtsbeh&ouml;rde</div><div class="lm-card-v">LfDI Baden-W&uuml;rttemberg</div></div>
      </div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;2 &mdash; Unser Kernprinzip</div>
      <div class="lm-note"><p><strong>Privacy by Design:</strong> ChatHelp AI erh&auml;lt NUR anonyme Platzhalter [VORNAME], [ADRESSE] etc. Echte pers&ouml;nliche Daten verlassen niemals Ihren EU-Server. Automatische L&ouml;schung nach 24h.</p></div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;3 &mdash; Wie KI-Verarbeitung funktioniert</div>
      <ol style="list-style:none;padding:0;counter-reset:step">
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">1</span>Nutzer gibt Falldaten ein</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">2</span>Server ersetzt alle Daten durch Platzhalter</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">3</span>KI erh&auml;lt NUR Platzhalter &mdash; kein Name, keine Adresse</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">4</span>Server setzt echte Daten lokal ein &mdash; nie nach au&szlig;en</li>
      </ol>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;4 &mdash; Datenspeicherung</div>
      <table class="lm-table">
        <tr><th>Datenart</th><th>Speicher</th><th>L&ouml;schung</th></tr>
        <tr><td>Profildaten</td><td>Ihr Browser (lokal)</td><td class="lm-diff">Jederzeit</td></tr>
        <tr><td>Dokumente</td><td>EU-Server</td><td class="lm-yes">24h automatisch</td></tr>
        <tr><td>Dokumente (Behalten)</td><td>EU-Server</td><td class="lm-diff">Auf Anfrage</td></tr>
        <tr><td>Serverlog / IP</td><td>EU-Server</td><td class="lm-yes">7 Tage anonymisiert</td></tr>
        <tr><td>Zahlungsdaten</td><td>Stripe Europe</td><td class="lm-diff">Steuerrecht (10 J.)</td></tr>
      </table>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;5 &mdash; Drittanbieter</div>
      <table class="lm-table">
        <tr><th>Anbieter</th><th>Zweck</th><th>AVV</th></tr>
        <tr><td>Host-Europe GmbH</td><td>Hosting (DE)</td><td class="lm-yes">&#x2713; Abgeschlossen</td></tr>
        <tr><td>Stripe Europe Ltd.</td><td>Zahlungen</td><td class="lm-yes">&#x2713; Standard-DPA</td></tr>
        <tr><td>KI-Dienste</td><td>Dokumenterstellung</td><td class="lm-diff">Nicht n&ouml;tig (anonym)</td></tr>
      </table>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;6 &mdash; Ihre Rechte (DSGVO)</div>
      <ul>
        <li><strong>Auskunft</strong> (Art. 15): Einsicht in gespeicherte Daten</li>
        <li><strong>L&ouml;schung</strong> (Art. 17): In der App unter Konto &rarr; Profil l&ouml;schen</li>
        <li><strong>Berichtigung</strong> (Art. 16): Auf Anfrage sofort</li>
        <li><strong>Daten&uuml;bertragbarkeit</strong> (Art. 20): Auf Anfrage</li>
        <li><strong>Beschwerde</strong>: LfDI Baden-W&uuml;rttemberg</li>
      </ul>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;7 &mdash; Cookies &amp; Tracking</div>
      <p>ChatHelp verwendet <strong>kein Google Analytics, kein Facebook Pixel, kein Tracking</strong>. Nur technisch notwendige localStorage-Eintr&auml;ge (Sitzung, Einstellungen).</p>
    </div>
    <div class="lm-sec" style="border:1px solid rgba(102,136,255,.25);background:linear-gradient(135deg,rgba(102,136,255,.06),var(--s2));border-radius:var(--r2);padding:16px 18px">
      <div class="lm-sec-n" style="color:rgba(140,170,255,.9)">&#xa7;8 &mdash; B2B &mdash; Kanzleien &amp; Unternehmen</div>
      <h2>F&uuml;r Berufsgeheimnistr&auml;ger</h2>
      <div style="background:rgba(102,136,255,.07);border:1px solid rgba(102,136,255,.2);border-radius:var(--r2);padding:12px 14px;margin-bottom:12px"><p style="color:rgba(160,190,255,.9);font-size:12.5px;margin:0"><strong>&#xa7;203 StGB:</strong> Rechtsanw&auml;lte, Notare, Steuerberater k&ouml;nnen ChatHelp datenschutzkonform nutzen. Auf Anfrage stellen wir eine Geheimhaltungsvereinbarung nach &#xa7;203 Abs. 4 StGB aus.</p></div>
      <table class="lm-table">
        <tr><th>Feature</th><th>Standard</th><th>B2B</th></tr>
        <tr><td>Anonymisierung</td><td class="lm-yes">&#x2713;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>AVV (Art. 28 DSGVO)</td><td class="lm-yes">&#x2713; Host + Stripe</td><td class="lm-yes">&#x2713; Erweitert</td></tr>
        <tr><td>&#xa7;203 StGB Vereinbarung</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>Eigene Subdom&auml;ne</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>Audit-Log</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
      </table>
      <a href="mailto:business@chat-help.de?subject=B2B%20Anfrage" style="display:inline-flex;align-items:center;gap:6px;background:rgba(102,136,255,.15);border:1px solid rgba(102,136,255,.3);color:rgba(160,190,255,.95);padding:8px 16px;border-radius:var(--r);font-size:12.5px;font-weight:600;text-decoration:none;margin-top:10px">
        &#x2709; business@chat-help.de
      </a>
    </div>
    <div class="lm-sec" style="margin-top:20px">
      <p style="font-size:12px;color:var(--t4);text-align:center">Stand: Mai 2026 &middot; chat-help.de &middot; chat-help.com</p>
    </div>
  </div>
</div>
</div>


<!-- ═══ LOGIN / REGISTER MODAL ═══ -->
<div id="auth-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);align-items:center;justify-content:center;padding:20px">
  <div style="background:#0d0d1f;border:0.5px solid rgba(212,168,74,.25);border-radius:20px;width:100%;max-width:360px;overflow:hidden">
    <div style="padding:28px 24px 0;text-align:center">
      <div style="font-size:22px;font-weight:700;color:#fff;margin-bottom:6px">Chat<span style="color:#d4a84a">Help</span></div>
      <div id="auth-subtitle" style="font-size:13px;color:#60607a;margin-bottom:20px">Mit Ihrem Konto anmelden</div>
    </div>
    <div style="display:flex;margin:0 20px 18px;background:rgba(255,255,255,.06);border-radius:12px;padding:3px;gap:3px">
      <button id="tab-login" onclick="switchTab('login')" style="flex:1;padding:8px;background:rgba(212,168,74,.18);border:0.5px solid rgba(212,168,74,.35);border-radius:10px;color:#d4a84a;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit">Anmelden</button>
      <button id="tab-register" onclick="switchTab('register')" style="flex:1;padding:8px;background:none;border:0.5px solid transparent;border-radius:10px;color:#60607a;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit">Registrieren</button>
    </div>
    <div style="padding:0 20px 24px;display:flex;flex-direction:column;gap:10px">
      <div id="auth-name-field" style="display:none;position:relative">
        <i class="ti ti-user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#60607a;font-size:16px;pointer-events:none"></i>
        <input id="auth-name" type="text" placeholder="Ihr vollständiger Name" style="width:100%;padding:11px 12px 11px 36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
      <div style="position:relative">
        <i class="ti ti-mail" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#60607a;font-size:16px;pointer-events:none"></i>
        <input id="auth-email" type="email" placeholder="E-Mail-Adresse" style="width:100%;padding:11px 12px 11px 36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
      <div style="position:relative">
        <i class="ti ti-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#60607a;font-size:16px;pointer-events:none"></i>
        <input id="auth-pass" type="password" placeholder="Passwort (min. 8 Zeichen)" style="width:100%;padding:11px 12px 11px 36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
      <div id="auth-pass2-field" style="display:none;position:relative">
        <i class="ti ti-lock-check" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#60607a;font-size:16px;pointer-events:none"></i>
        <input id="auth-pass2" type="password" placeholder="Passwort bestätigen" style="width:100%;padding:11px 12px 11px 36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
      <div id="auth-forgot" style="text-align:right;margin-top:-4px">
        <span onclick="showForgot()" style="font-size:12px;color:#60607a;cursor:pointer;text-decoration:underline;text-underline-offset:2px">Passwort vergessen?</span>
      </div>
      <div id="auth-error" style="display:none;border-radius:10px;padding:10px 14px;font-size:12.5px;margin-top:-2px"></div>
      <button id="auth-submit-btn" onclick="submitAuth()" style="width:100%;padding:13px;background:linear-gradient(135deg,#c49038,#e8b840);border:none;border-radius:13px;color:#1a0e00;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">Anmelden</button>
      <div style="display:flex;align-items:center;gap:10px;margin:2px 0">
        <div style="flex:1;height:0.5px;background:rgba(255,255,255,.1)"></div>
        <span style="font-size:11px;color:#404055">oder fortfahren mit</span>
        <div style="flex:1;height:0.5px;background:rgba(255,255,255,.1)"></div>
      </div>
      <div id="google-signin-btn" style="display:flex;justify-content:center"></div>
      <button onclick="closeAuthModal()" style="width:100%;padding:10px;background:none;border:0.5px solid rgba(255,255,255,.1);border-radius:12px;color:#60607a;font-size:13px;cursor:pointer;font-family:inherit">Abbrechen</button>
    </div>
  </div>
</div>

<div class="pnl" id="pnl-ant">
  <div class="pnl-topbar" style="display:flex;align-items:center;gap:12px;padding:0 16px;height:54px;border-bottom:0.5px solid rgba(255,255,255,.07);flex-shrink:0">
    <button onclick="gP('chat')" style="width:36px;height:36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;-webkit-tap-highlight-color:transparent">
      <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="2.2" stroke-linecap="round" width="16" height="16"><polyline points="15,18 9,12 15,6"/></svg>
    </button>
    <div style="font-size:15px;font-weight:700;color:#fff;flex:1">Meine Anträge</div>
    <div id="ant-count" style="font-size:11px;color:#606080;background:rgba(255,255,255,.06);padding:3px 9px;border-radius:100px"></div>
  </div></div>
<div class="pnl" id="pnl-konto">
  <div class="pnl-topbar">
    <div class="pnl-back-btn" onclick="gP('chat')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="15,18 9,12 15,6"/></svg>
    </div>
    <div class="pnl-topbar-title">Konto</div>
  </div>
  <div class="pnl-scroll">
    <div class="sec-hdr"><div><div class="sec-title">Konto &amp; Einstellungen</div><div class="sec-sub">Profil, Land, Sprache &amp; Datenschutz</div></div></div>
    <div id="konto-user-info" style="display:none;background:rgba(212,168,74,.06);border:0.5px solid rgba(212,168,74,.15);border-radius:14px;padding:14px 16px;margin:0 0 14px;display:flex;align-items:center;gap:12px">
      <div id="konto-avatar" style="width:40px;height:40px;background:linear-gradient(135deg,#c49038,#e8b840);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#1a0e00;flex-shrink:0">?</div>
      <div style="flex:1;min-width:0">
        <div id="konto-name" style="font-size:14px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">-</div>
        <div id="konto-email" style="font-size:11.5px;color:#606080;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">-</div>
      </div>
      <div id="konto-plan-badge" style="font-size:10px;font-weight:700;color:#d4a84a;background:rgba(212,168,74,.12);border:0.5px solid rgba(212,168,74,.25);padding:4px 8px;border-radius:100px;flex-shrink:0">FREE</div>
    </div>
    <div class="konto-wrap">
      <div class="konto-plan">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);opacity:.7;margin-bottom:4px">Aktueller Plan</div>
            <div class="kp-name" id="k-plan-name" style="font-size:22px;font-weight:800;letter-spacing:-.5px">Free</div>
          </div>
          <div class="kp-badge" style="font-size:10px;padding:5px 14px;border-radius:100px">AKTIV</div>
        </div>
        <div class="kp-feats">
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle EU-L&#228;nder &amp; Rechtssysteme</div>
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>8 Sprachen automatisch erkannt</div>
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>24h Dokumentenspeicher</div>
        </div>
        <div style="background:rgba(212,168,74,.06);border:1px solid rgba(212,168,74,.15);border-radius:var(--r);padding:12px 14px;margin-bottom:14px">
          <div style="font-size:11px;color:var(--gold);font-weight:600;margin-bottom:4px">&#x26A1; Pro freischalten</div>
          <div style="font-size:12px;color:#d0d0f0;line-height:1.5">300+ Dokumente / Monat &#183; Foto-Analyse &#183; Follow-up &#183; Dauerhaft speichern &#183; Anwalt-Check</div>
        </div>
        <div id="k-plan-selector" style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
        </div>
        <div id="k-current-plan-info" style="margin-top:10px;font-size:11.5px;color:var(--t4);text-align:center;line-height:1.6"></div>
      </div>
      <!-- LOGOUT -->
      <div style="margin-bottom:14px">
        <button onclick="doLogout()" style="width:100%;padding:13px;background:rgba(224,72,88,.08);border:0.5px solid rgba(224,72,88,.2);border-radius:14px;color:#e04858;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:10px;-webkit-tap-highlight-color:transparent">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="17" height="17"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Abmelden
        </button>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><div class="ksec-hdr-t">Land &amp; Rechtssystem</div></div>
        <div class="ksec-body"><div class="country-grid" id="cg">
          <div class="co on" data-cc="DE" onclick="setCountry('DE')"><div class="co-flag">&#x1F1E9;&#x1F1EA;</div><div class="co-n">Deutschland</div></div>
          <div class="co" data-cc="AT" onclick="setCountry('AT')"><div class="co-flag">&#x1F1E6;&#x1F1F9;</div><div class="co-n">&#214;sterreich</div></div>
          <div class="co" data-cc="CH" onclick="setCountry('CH')"><div class="co-flag">&#x1F1E8;&#x1F1ED;</div><div class="co-n">Schweiz</div></div>
          <div class="co" data-cc="FR" onclick="setCountry('FR')"><div class="co-flag">&#x1F1EB;&#x1F1F7;</div><div class="co-n">France</div></div>
          <div class="co" data-cc="IT" onclick="setCountry('IT')"><div class="co-flag">&#x1F1EE;&#x1F1F9;</div><div class="co-n">Italia</div></div>
          <div class="co" data-cc="ES" onclick="setCountry('ES')"><div class="co-flag">&#x1F1EA;&#x1F1F8;</div><div class="co-n">Espa&#241;a</div></div>
          <div class="co" data-cc="NL" onclick="setCountry('NL')"><div class="co-flag">&#x1F1F3;&#x1F1F1;</div><div class="co-n">Nederland</div></div>
          <div class="co" data-cc="BE" onclick="setCountry('BE')"><div class="co-flag">&#x1F1E7;&#x1F1EA;</div><div class="co-n">Belgique</div></div>
          <div class="co" data-cc="PL" onclick="setCountry('PL')"><div class="co-flag">&#x1F1F5;&#x1F1F1;</div><div class="co-n">Polska</div></div>
          <div class="co" data-cc="SE" onclick="setCountry('SE')"><div class="co-flag">&#x1F1F8;&#x1F1EA;</div><div class="co-n">Sverige</div></div>
          <div class="co" data-cc="PT" onclick="setCountry('PT')"><div class="co-flag">&#x1F1F5;&#x1F1F9;</div><div class="co-n">Portugal</div></div>
          <div class="co" data-cc="EU" onclick="setCountry('EU')"><div class="co-flag">&#x1F1EA;&#x1F1FA;</div><div class="co-n">Andere EU</div></div>
        </div></div>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><div class="ksec-hdr-t">Interface-Sprache</div></div>
        <div class="ksec-body"><div class="lang-grid" id="lg">
          <button class="lang-opt on" data-l="de" onclick="setLang('de')"><span class="lang-flag">&#x1F1E9;&#x1F1EA;</span>Deutsch</button>
          <button class="lang-opt" data-l="en" onclick="setLang('en')"><span class="lang-flag">&#x1F1EC;&#x1F1E7;</span>English</button>
          <button class="lang-opt" data-l="tr" onclick="setLang('tr')"><span class="lang-flag">&#x1F1F9;&#x1F1F7;</span>T&#252;rk&#231;e</button>
          <button class="lang-opt" data-l="ar" onclick="setLang('ar')"><span class="lang-flag">&#x1F1F8;&#x1F1E6;</span>&#x627;&#x644;&#x639;&#x631;&#x628;&#x64A;&#x629;</button>
          <button class="lang-opt" data-l="fr" onclick="setLang('fr')"><span class="lang-flag">&#x1F1EB;&#x1F1F7;</span>Fran&#231;ais</button>
          <button class="lang-opt" data-l="ru" onclick="setLang('ru')"><span class="lang-flag">&#x1F1F7;&#x1F1FA;</span>&#x420;&#x443;&#x441;&#x441;&#x43A;&#x438;&#x439;</button>
          <button class="lang-opt" data-l="it" onclick="setLang('it')"><span class="lang-flag">&#x1F1EE;&#x1F1F9;</span>Italiano</button>
          <button class="lang-opt" data-l="es" onclick="setLang('es')"><span class="lang-flag">&#x1F1EA;&#x1F1F8;</span>Espa&#241;ol</button>
        </div></div>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><div class="ksec-hdr-t">Profil</div></div>
        <div class="ksec-body">
          <div class="krow"><span class="krow-l">Name</span><span class="krow-r" id="k-name">&#8212;</span></div>
          <div class="krow"><span class="krow-l">E-Mail</span><span class="krow-r" id="k-email">&#8212;</span></div>
          <div class="krow"><span class="krow-l">Adresse</span><span class="krow-r" id="k-adr">&#8212;</span></div>
          <div class="krow"><span class="krow-l">Land</span><span class="krow-r gold" id="k-cc">&#x1F1E9;&#x1F1EA; Deutschland</span></div>
          <div style="margin-top:12px;display:flex;gap:7px;flex-wrap:wrap">
            <button class="btn-s" style="width:auto;font-size:12px;padding:7px 13px" onclick="editProfile()">&#x270F;&#xFE0F; Bearbeiten</button>
            <button class="btn-s" style="width:auto;font-size:12px;padding:7px 13px;color:var(--rd)" onclick="deleteProfile()">&#x1F5D1;&#xFE0F; Profil l&#246;schen</button>
          </div>
        </div>
      </div>
      <div class="kdsgvo">
        <h4><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Datenschutz &amp; DSGVO</h4>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>ChatHelp AI verarbeitet NUR anonyme Platzhalter &#8212; kein Name, keine Adresse</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Echte Daten werden ausschlie&#223;lich auf Ihrem Server eingesetzt</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle Dokumente nach 24h automatisch gel&#246;scht (au&#223;er "Behalten")</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Server: Deutschland (EU) &#183; Keine Weitergabe an Dritte</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>API-Keys nur auf dem Server &#183; Jederzeit l&#246;schbar</div>
      </div>
    </div>
  </div></div>
</body>
</html>
