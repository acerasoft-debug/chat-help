<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ChatHelp — Anmelden</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/2.44.0/iconfont/tabler-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#07070e;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#0d0d1f;border:0.5px solid rgba(212,168,74,.25);border-radius:20px;width:100%;max-width:360px;overflow:hidden}
.logo{padding:28px 24px 0;text-align:center;font-size:22px;font-weight:700;color:#fff;margin-bottom:6px}
.logo span{color:#d4a84a}
.sub{text-align:center;font-size:13px;color:#60607a;margin-bottom:20px;padding:0 24px}
.tabs{display:flex;margin:0 20px 18px;background:rgba(255,255,255,.06);border-radius:12px;padding:3px;gap:3px}
.tab{flex:1;padding:8px;border-radius:10px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .15s;border:0.5px solid transparent}
.tab.on{background:rgba(212,168,74,.18);border-color:rgba(212,168,74,.35);color:#d4a84a}
.tab.off{background:none;color:#60607a}
.body{padding:0 20px 24px;display:flex;flex-direction:column;gap:10px}
.field{position:relative}
.field i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#60607a;font-size:16px;pointer-events:none}
.field input{width:100%;padding:11px 12px 11px 36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none}
.field input:focus{border-color:rgba(212,168,74,.4)}
.field input::placeholder{color:#40405a}
.forgot{text-align:right;margin-top:-4px}
.forgot a{font-size:12px;color:#60607a;text-decoration:underline;text-underline-offset:2px;cursor:pointer}
.btn-main{width:100%;padding:13px;background:linear-gradient(135deg,#c49038,#e8b840);border:none;border-radius:13px;color:#1a0e00;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-main:hover{opacity:.9}
.divider{display:flex;align-items:center;gap:10px}
.divider span{font-size:11px;color:#404055}
.divider div{flex:1;height:0.5px;background:rgba(255,255,255,.1)}
.btn-google{width:100%;padding:11px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:10px}
.btn-google:hover{background:rgba(255,255,255,.1)}
.error{display:none;border-radius:10px;padding:10px 14px;font-size:12.5px}
.error.show{display:block}
.error.err{background:rgba(224,72,88,.1);border:0.5px solid rgba(224,72,88,.3);color:#e04858}
.error.ok{background:rgba(66,223,148,.1);border:0.5px solid rgba(66,223,148,.3);color:#42df94}
</style>
</head>
<body>
<div class="card">
  <div class="logo">Chat<span>Help</span></div>
  <div class="sub" id="sub">Mit Ihrem Konto anmelden</div>

  <div class="tabs">
    <button class="tab on" id="tab-l" onclick="switchTab('login')">Anmelden</button>
    <button class="tab off" id="tab-r" onclick="switchTab('register')">Registrieren</button>
  </div>

  <div class="body">
    <div class="field" id="name-field" style="display:none">
      <i class="ti ti-user"></i>
      <input id="inp-name" type="text" placeholder="Ihr vollständiger Name">
    </div>
    <div class="field">
      <i class="ti ti-mail"></i>
      <input id="inp-email" type="email" placeholder="E-Mail-Adresse">
    </div>
    <div class="field">
      <i class="ti ti-lock"></i>
      <input id="inp-pass" type="password" placeholder="Passwort (min. 8 Zeichen)">
    </div>
    <div class="field" id="pass2-field" style="display:none">
      <i class="ti ti-lock-check"></i>
      <input id="inp-pass2" type="password" placeholder="Passwort bestätigen">
    </div>
    <div class="forgot" id="forgot"><a onclick="doForgot()">Passwort vergessen?</a></div>
    <div class="error" id="msg"></div>
    <button class="btn-main" id="btn-submit" onclick="doSubmit()">Anmelden</button>
    <div class="divider"><div></div><span>oder fortfahren mit</span><div></div></div>
    <div id="g-btn" style="display:flex;justify-content:center"></div>
  </div>
</div>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
const AUTH = 'auth.php';
let _tab = 'login';
const ret = new URLSearchParams(location.search).get('ret') || 'index.php';

function switchTab(t){
  _tab = t;
  const isL = t==='login';
  document.getElementById('name-field').style.display = isL?'none':'block';
  document.getElementById('pass2-field').style.display = isL?'none':'block';
  document.getElementById('forgot').style.display = isL?'block':'none';
  document.getElementById('sub').textContent = isL?'Mit Ihrem Konto anmelden':'Neues Konto erstellen';
  document.getElementById('btn-submit').textContent = isL?'Anmelden':'Registrieren';
  document.getElementById('tab-l').className = 'tab '+(isL?'on':'off');
  document.getElementById('tab-r').className = 'tab '+(isL?'off':'on');
  showMsg('');
}

function showMsg(txt, isOk=false){
  const el = document.getElementById('msg');
  if(!txt){el.className='error';el.textContent='';return;}
  el.className = 'error show '+(isOk?'ok':'err');
  el.textContent = txt;
}

async function doSubmit(){
  const btn = document.getElementById('btn-submit');
  const email = document.getElementById('inp-email').value.trim();
  const pass  = document.getElementById('inp-pass').value;
  const name  = document.getElementById('inp-name').value.trim();
  const pass2 = document.getElementById('inp-pass2').value;

  if(!email||!pass){showMsg('Bitte alle Felder ausfüllen');return;}
  if(_tab==='register'&&pass!==pass2){showMsg('Passwörter stimmen nicht überein');return;}

  btn.disabled=true; btn.textContent='⏳ Bitte warten…';
  try{
    const body = _tab==='login'?{email,password:pass}:{email,password:pass,name};
    const res = await fetch(AUTH+'?action='+_tab,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const d = await res.json();
    if(d.error){showMsg(d.error);return;}
    if(_tab==='register'){showMsg('✅ Registrierung erfolgreich! Bitte E-Mail bestätigen.',true);return;}
    localStorage.setItem('ch_token',d.token);
    localStorage.setItem('ch_user',JSON.stringify(d.user));
    window.location.href = ret;
  }catch(e){showMsg('Verbindungsfehler.');}
  finally{btn.disabled=false;btn.textContent=_tab==='login'?'Anmelden':'Registrieren';}
}

async function doForgot(){
  const email = document.getElementById('inp-email').value.trim();
  if(!email){showMsg('Bitte zuerst E-Mail eingeben');return;}
  const res = await fetch(AUTH+'?action=reset_request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email})});
  const d = await res.json();
  showMsg(d.message||'Reset-E-Mail gesendet',true);
}

window.onload = function(){
  if(window.google){
    google.accounts.id.initialize({
      client_id:'485883163366-uv4v94k6el45eipnvqh21k74vjsfn68g.apps.googleusercontent.com',
      callback: async(r)=>{
        const res = await fetch(AUTH+'?action=google',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id_token:r.credential})});
        const d = await res.json();
        if(d.error){showMsg(d.error);return;}
        localStorage.setItem('ch_token',d.token);
        localStorage.setItem('ch_user',JSON.stringify(d.user));
        window.location.href = ret;
      }
    });
    google.accounts.id.renderButton(document.getElementById('g-btn'),{theme:'filled_black',size:'large',width:320,shape:'rectangular'});
  }
};
</script>
</body>
</html>
