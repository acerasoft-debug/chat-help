<?php
/**
 * ChatHelp — "Önce anla" intake overlay + Geri butonu (additive)
 * --------------------------------------------------------------
 * </body>'den önce kendi kendine yeten bir overlay + geri butonu enjekte eder.
 *  • "✍️ Anliegen frei beschreiben" -> sistem önce 1-2 (gerekirse daha fazla)
 *    soru sorarak ihtiyacı anlar, sonra güçlü belgeyi üretir (intake-api.php).
 *  • Her adımda "← Zurück" ile geri dönülebilir (artık geri dönüş VAR).
 * Mevcut kodu değiştirmez.
 *
 * KULLANIM: chat-help.com/chat/apply-intake.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-intake-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-intake-layer') !== false) {
    exit("Zaten ekli (ch-intake-layer).\n");
}

$bundle = <<<'HTML'
<style id="ch-intake-style">
#ch-intake-launch{display:flex;align-items:center;gap:12px;width:100%;text-align:left;margin:0 0 16px;padding:16px 18px;border-radius:16px;cursor:pointer;font-family:inherit;border:1.5px solid var(--gold,#d4a84a);background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.04));color:var(--gold,#d4a84a);font-size:15px;font-weight:700;transition:.18s}
#ch-intake-launch:hover{background:linear-gradient(135deg,rgba(212,168,74,.22),rgba(212,168,74,.08));transform:translateY(-1px)}
#ch-intake-launch .il-ico{font-size:22px;line-height:1}
#ch-intake-launch .il-sub{display:block;font-size:12px;font-weight:500;color:var(--t3,#b4b4c0);margin-top:2px}
#ch-intake-ov{display:none;position:fixed;inset:0;z-index:100000;background:rgba(10,10,14,.78);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:14px}
#ch-intake-box{width:100%;max-width:680px;height:min(86vh,760px);display:flex;flex-direction:column;background:var(--s1,#222226);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:20px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.55)}
.ch-iv-top{display:flex;align-items:center;gap:10px;padding:14px 16px;background:var(--s2,#29292e);border-bottom:1px solid var(--bdr,rgba(255,255,255,.08))}
.ch-iv-top .iv-back,.ch-iv-top .iv-x{background:var(--s3,#313137);border:1px solid var(--bdr,rgba(255,255,255,.1));color:var(--t3,#cfcfdd);border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;padding:8px 12px;font-family:inherit}
.ch-iv-top .iv-back:hover,.ch-iv-top .iv-x:hover{color:var(--gold,#d4a84a);border-color:var(--gold,#d4a84a)}
.ch-iv-top .iv-title{flex:1;font-weight:800;font-size:15px;color:#f3f3f8}
.ch-iv-msgs{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:12px}
.ch-iv-msg{max-width:86%;padding:12px 15px;border-radius:14px;font-size:14.5px;line-height:1.5;white-space:pre-wrap}
.ch-iv-msg.u{align-self:flex-end;background:var(--gold,#d4a84a);color:#1b1b1e;font-weight:600;border-bottom-right-radius:5px}
.ch-iv-msg.a{align-self:flex-start;background:var(--s3,#313137);color:#e7e7ef;border-bottom-left-radius:5px}
.ch-iv-msg.doc{align-self:stretch;max-width:100%;background:var(--bg,#1b1b1e);border:1px solid var(--bdr,rgba(255,255,255,.12));font-family:'Times New Roman',Georgia,serif;color:#eaeaf2}
.ch-iv-foot{padding:12px 14px;border-top:1px solid var(--bdr,rgba(255,255,255,.08));background:var(--s2,#29292e)}
.ch-iv-row{display:flex;gap:9px;align-items:flex-end}
.ch-iv-row textarea{flex:1;resize:none;background:var(--bg,#1b1b1e);border:1px solid var(--bdr,rgba(255,255,255,.13));border-radius:12px;color:#f0f0f6;padding:11px 13px;font-family:inherit;font-size:14.5px;max-height:120px}
.ch-iv-row .iv-send{background:var(--gold,#d4a84a);color:#1b1b1e;border:none;border-radius:12px;width:46px;height:46px;font-size:19px;cursor:pointer;font-weight:800;flex:0 0 auto}
.ch-iv-solve{display:none;width:100%;margin-top:9px;padding:13px;border:none;border-radius:12px;background:linear-gradient(135deg,#2e7d32,#1f6b24);color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit}
.ch-iv-solve.on{display:block}
.ch-iv-acts{display:none;gap:9px;margin-top:9px}
.ch-iv-acts.on{display:flex}
.ch-iv-acts button{flex:1;padding:12px;border-radius:12px;border:1px solid var(--bdr,rgba(255,255,255,.13));background:var(--s3,#313137);color:#e7e7ef;font-weight:700;cursor:pointer;font-family:inherit;font-size:14px}
.ch-iv-acts button.prim{background:var(--gold,#d4a84a);color:#1b1b1e;border-color:transparent}
.ch-iv-typing{align-self:flex-start;color:var(--t4,#8e8e9c);font-size:13px;font-style:italic;padding:4px 6px}
#ch-back-chip{display:none;position:fixed;left:14px;top:14px;z-index:9998;background:var(--s2,#29292e);border:1px solid var(--bdr,rgba(255,255,255,.12));color:var(--t3,#cfcfdd);border-radius:20px;padding:8px 15px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(0,0,0,.3)}
#ch-back-chip:hover{color:var(--gold,#d4a84a);border-color:var(--gold,#d4a84a)}
</style>
<script id="ch-intake-layer">
(function(){
  'use strict';
  function tok(){
    var keys=['ch_token','token','jwt','chat_token','ch_jwt','authToken'];
    for(var i=0;i<keys.length;i++){var v=localStorage.getItem(keys[i]); if(v&&(''+v).split('.').length===3) return v;}
    try{var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); for(var k in u){ if(typeof u[k]==='string'&&u[k].split('.').length===3) return u[k]; }}catch(e){}
    return '';
  }
  function isLogged(){ return !!tok(); }
  var msgs=[]; // {role,content}

  function el(id){return document.getElementById(id);}
  function ovOpen(){
    if(!isLogged()){ alert('Bitte melden Sie sich zuerst an, um ein Dokument zu erstellen.'); return; }
    buildOverlay();
    msgs=[];
    el('ch-iv-msgs').innerHTML='';
    addMsg('a','Beschreiben Sie bitte kurz Ihr Anliegen – worum geht es? Ich stelle Ihnen dann gezielte Rückfragen, damit Ihr Dokument genau passt.');
    el('ch-intake-ov').style.display='flex';
    setTimeout(function(){var t=el('ch-iv-input'); if(t)t.focus();},80);
  }
  function ovClose(){ var o=el('ch-intake-ov'); if(o)o.style.display='none'; }

  function addMsg(cls,txt){
    var d=document.createElement('div'); d.className='ch-iv-msg '+cls; d.textContent=txt;
    el('ch-iv-msgs').appendChild(d); el('ch-iv-msgs').scrollTop=1e9; return d;
  }
  function typing(on){
    var t=el('ch-iv-typing');
    if(on){ if(!t){t=document.createElement('div');t.id='ch-iv-typing';t.className='ch-iv-typing';t.textContent='ChatHelp denkt nach …';el('ch-iv-msgs').appendChild(t);el('ch-iv-msgs').scrollTop=1e9;} }
    else if(t){ t.remove(); }
  }
  async function apiPost(action,extra){
    var r=await fetch('intake-api.php',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+tok()},
      body:JSON.stringify(Object.assign({action:action,messages:msgs},extra||{}))});
    return r.json();
  }

  async function send(){
    var t=el('ch-iv-input'); var v=(t.value||'').trim(); if(!v) return;
    t.value=''; addMsg('u',v); msgs.push({role:'user',content:v});
    typing(true);
    try{
      var d=await apiPost('intake_chat');
      typing(false);
      addMsg('a',d.reply||'…'); msgs.push({role:'assistant',content:d.reply||''});
      if(d.ready){ el('ch-iv-solve').classList.add('on'); }
    }catch(e){ typing(false); addMsg('a','Verbindungsfehler. Bitte erneut versuchen.'); }
  }

  async function solve(){
    el('ch-iv-solve').classList.remove('on');
    typing(true);
    addMsg('a','Ihr Dokument wird erstellt – einen Moment …');
    try{
      var d=await apiPost('intake_solve',{
        profile:(window.P||{}),
        datum:new Date().toLocaleDateString('de-DE'),
        tone:(window._chTone||'formell')
      });
      typing(false);
      var doc=d.document||'Fehler bei der Erstellung.';
      var dd=addMsg('doc',doc); window._chLastDoc=doc;
      var acts=el('ch-iv-acts'); acts.classList.add('on');
    }catch(e){ typing(false); addMsg('a','Verbindungsfehler bei der Erstellung.'); }
  }

  function downloadPDF(){
    var doc=window._chLastDoc||''; if(!doc) return;
    if(typeof window.makePDF==='function'){ try{ window.makePDF(doc,'Dokument'); return; }catch(e){} }
    // yedek: yeni sekmede yazdırma
    var w=window.open('','_blank');
    if(w){ w.document.write('<pre style="font-family:Times,serif;white-space:pre-wrap;padding:30px;font-size:14px">'+doc.replace(/[<>&]/g,function(c){return{'<':'&lt;','>':'&gt;','&':'&amp;'}[c];})+'</pre>'); w.document.close(); w.print(); }
  }
  function copyDoc(){ var d=window._chLastDoc||''; if(navigator.clipboard) navigator.clipboard.writeText(d); }
  function editAgain(){ el('ch-iv-acts').classList.remove('on'); var t=el('ch-iv-input'); if(t){t.focus();} addMsg('a','Was möchten Sie ändern oder ergänzen? Beschreiben Sie es – ich passe das Dokument an.'); }

  function buildOverlay(){
    if(el('ch-intake-ov')) return;
    var ov=document.createElement('div'); ov.id='ch-intake-ov';
    ov.innerHTML=
      '<div id="ch-intake-box">'
      +'<div class="ch-iv-top"><button class="iv-back" id="ch-iv-back">← Zurück</button>'
      +'<div class="iv-title">✍️ Dokument – Anliegen beschreiben</div>'
      +'<button class="iv-x" id="ch-iv-x">✕</button></div>'
      +'<div class="ch-iv-msgs" id="ch-iv-msgs"></div>'
      +'<div class="ch-iv-foot">'
      +'<div class="ch-iv-row"><textarea id="ch-iv-input" rows="1" placeholder="Ihre Antwort …"></textarea>'
      +'<button class="iv-send" id="ch-iv-send">➤</button></div>'
      +'<button class="ch-iv-solve" id="ch-iv-solve">✅ Dokument jetzt erstellen</button>'
      +'<div class="ch-iv-acts" id="ch-iv-acts">'
      +'<button class="prim" id="ch-iv-pdf">⬇ Als PDF</button>'
      +'<button id="ch-iv-copy">⧉ Kopieren</button>'
      +'<button id="ch-iv-edit">✎ Anpassen</button></div>'
      +'</div></div>';
    document.body.appendChild(ov);
    el('ch-iv-back').onclick=ovClose;
    el('ch-iv-x').onclick=ovClose;
    el('ch-iv-send').onclick=send;
    el('ch-iv-solve').onclick=solve;
    el('ch-iv-pdf').onclick=downloadPDF;
    el('ch-iv-copy').onclick=copyDoc;
    el('ch-iv-edit').onclick=editAgain;
    el('ch-iv-input').addEventListener('keydown',function(e){ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send();} });
    ov.addEventListener('click',function(e){ if(e.target===ov) ovClose(); });
  }

  /* Launcher'ı Anträge paneline ekle */
  function injectLauncher(){
    if(el('ch-intake-launch')) return;
    var host=el('pnl-ant')||document.querySelector('#pnl-ant')||el('msgs')||null;
    if(!host) return;
    var b=document.createElement('button'); b.id='ch-intake-launch'; b.type='button';
    b.innerHTML='<span class="il-ico">✍️</span><span>Anliegen frei beschreiben'
      +'<span class="il-sub">Wir verstehen zuerst genau, was Sie brauchen – dann erstellen wir das passende Dokument.</span></span>';
    b.onclick=ovOpen;
    host.insertBefore(b, host.firstChild);
  }

  /* Genel "← Zurück" çipi (geri dönüş yok sorununa) */
  function injectBackChip(){
    if(el('ch-back-chip')) return;
    var c=document.createElement('button'); c.id='ch-back-chip'; c.textContent='← Zurück';
    c.onclick=function(){
      var ov=el('ch-intake-ov'); if(ov&&ov.style.display==='flex'){ ovClose(); return; }
      try{ if(typeof window.gP==='function'){ window.gP('ant'); return; } }catch(e){}
      if(history.length>1) history.back();
    };
    document.body.appendChild(c);
    // panel chat/ant dışındaysa göster
    setInterval(function(){
      var ant=el('pnl-ant'), shown=ant&&getComputedStyle(ant).display!=='none';
      c.style.display = shown ? 'block':'none';
    },1000);
  }

  window.chOpenIntake=ovOpen;
  function boot(){ injectLauncher(); injectBackChip(); setInterval(injectLauncher,1500); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Intake (önce anla) + Geri butonu raporu\n==================================================\n\n";
echo ($n > 0 ? "  ✓ Intake katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nGEREKLİ: intake-api.php de yüklenmiş olmalı (scp ile).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-intake.php\n";
echo "Test: Anträge bölümünde '✍️ Anliegen frei beschreiben' -> birkaç soru -> 'Dokument jetzt erstellen'.\n";
