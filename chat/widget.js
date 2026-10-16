/* ChatHelp Website-Widget — Einbinden: <script src="https://chat-help.com/chat/widget.js" data-key="wk_…" async></script> */
(function(){
  var S=document.currentScript||(function(){var a=document.getElementsByTagName('script');return a[a.length-1];})();
  var KEY=S.getAttribute('data-key')||''; if(!KEY) return;
  var API=(S.src||'').replace(/widget\.js.*$/,'widget-api.php');
  var COLOR=S.getAttribute('data-color')||'#2a2a3a', POS=S.getAttribute('data-position')||'right';
  var hist=[], open=false, meta={title:'Assistent',greeting:'Hallo! Wie kann ich helfen?'};
  var css='.chw-b{position:fixed;bottom:22px;'+POS+':22px;width:58px;height:58px;border-radius:50%;background:'+COLOR+';color:#fff;border:0;box-shadow:0 10px 30px rgba(0,0,0,.25);cursor:pointer;z-index:2147483000;font-size:26px;display:flex;align-items:center;justify-content:center;transition:transform .15s}.chw-b:hover{transform:scale(1.06)}'
   +'.chw-p{position:fixed;bottom:92px;'+POS+':22px;width:360px;max-width:calc(100vw - 32px);height:520px;max-height:calc(100vh - 120px);background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden;z-index:2147483000;font:14px/1.45 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1a1a24}.chw-p.on{display:flex}'
   +'.chw-h{background:'+COLOR+';color:#fff;padding:14px 16px;font-weight:600;display:flex;align-items:center;justify-content:space-between}.chw-h small{display:block;font-weight:400;opacity:.8;font-size:12px}.chw-x{background:transparent;border:0;color:#fff;font-size:20px;cursor:pointer}'
   +'.chw-m{flex:1;overflow:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#f6f6f9}.chw-u,.chw-a{max-width:85%;padding:10px 13px;border-radius:14px;white-space:pre-wrap;word-wrap:break-word}.chw-u{align-self:flex-end;background:'+COLOR+';color:#fff;border-bottom-right-radius:4px}.chw-a{align-self:flex-start;background:#fff;border:1px solid #e6e6ee;border-bottom-left-radius:4px}.chw-t{align-self:flex-start;color:#888;font-size:12px}'
   +'.chw-f{display:flex;gap:8px;padding:10px;border-top:1px solid #e6e6ee;background:#fff}.chw-f input{flex:1;border:1px solid #d8d8e2;border-radius:10px;padding:10px 12px;font:inherit;outline:0}.chw-f input:focus{border-color:'+COLOR+'}.chw-f button{background:'+COLOR+';color:#fff;border:0;border-radius:10px;padding:0 14px;cursor:pointer;font:inherit}'
   +'.chw-c{text-align:center;font-size:11px;color:#999;padding:6px;background:#fff}.chw-c a{color:#999}';
  var st=document.createElement('style'); st.textContent=css; document.head.appendChild(st);
  var btn=document.createElement('button'); btn.className='chw-b'; btn.setAttribute('aria-label','Chat öffnen'); btn.innerHTML='💬';
  var p=document.createElement('div'); p.className='chw-p'; p.setAttribute('role','dialog');
  p.innerHTML='<div class="chw-h"><div><span class="chw-tt">Assistent</span><small class="chw-st">online</small></div><button class="chw-x" aria-label="Schließen">×</button></div><div class="chw-m"></div><form class="chw-f"><input placeholder="Nachricht schreiben…" autocomplete="off" maxlength="2000"><button type="submit">Senden</button></form><div class="chw-c">powered by <a href="https://chat-help.com" target="_blank" rel="noopener">ChatHelp</a></div>';
  document.body.appendChild(btn); document.body.appendChild(p);
  var M=p.querySelector('.chw-m'), F=p.querySelector('.chw-f'), I=F.querySelector('input');
  function add(cls,txt){var d=document.createElement('div');d.className=cls;d.textContent=txt;M.appendChild(d);M.scrollTop=M.scrollHeight;return d;}
  function toggle(o){open=o; p.classList.toggle('on',o); btn.innerHTML=o?'×':'💬'; if(o){ if(!hist.length){ add('chw-a',meta.greeting); } setTimeout(function(){I.focus();},50);} }
  btn.onclick=function(){toggle(!open);}; p.querySelector('.chw-x').onclick=function(){toggle(false);};
  fetch(API+'?key='+encodeURIComponent(KEY)).then(function(r){return r.json();}).then(function(j){ if(j&&j.ok){ meta=j; p.querySelector('.chw-tt').textContent=j.title||'Assistent'; p.querySelector('.chw-st').textContent=(j.agent||'')+' · online'; } }).catch(function(){});
  F.onsubmit=function(e){ e.preventDefault(); var t=I.value.trim(); if(!t) return; I.value=''; add('chw-u',t); hist.push({role:'user',content:t});
    var ty=add('chw-t','…schreibt'); I.disabled=true;
    fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,messages:hist.slice(-12),lang:meta.lang||'de'})})
      .then(function(r){return r.json();}).then(function(j){ ty.remove(); var a=(j&&j.reply)||((j&&j.message)||'Entschuldigung, gerade nicht erreichbar. Bitte später erneut versuchen.'); add('chw-a',a); if(j&&j.reply) hist.push({role:'assistant',content:a}); })
      .catch(function(){ ty.remove(); add('chw-a','Verbindungsfehler – bitte erneut versuchen.'); })
      .then(function(){ I.disabled=false; I.focus(); }); };
})();
