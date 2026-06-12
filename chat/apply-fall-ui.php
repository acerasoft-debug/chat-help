<?php
/**
 * ChatHelp — "Fall eröffnen" arayüzü (index.php'ye enjekte eder)
 * -------------------------------------------------------------
 *   • Kenar menüdeki foto butonu -> "Fall eröffnen" (openFall)
 *   • Bağımsız #pnl-fall paneli + JS + CSS
 *   • Pro/Elite kilidi, başlıklı Fall'lar, sohbet -> "Fall lösen"
 *   • Backend: fall-api.php (3x DeepSeek + Claude)
 *
 * KULLANIM: chat-help.com/chat/apply-fall-ui.php  -> sonra opcache-reset.php
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-fall-' . date('Ymd-His'), $src);

$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* 1) Kenar menü foto butonu -> Fall eröffnen */
rep($src, 'Foto butonu -> Fall (onclick)',
    'class="sb-photo-btn" onclick="document.getElementById(\'pf\').click()"',
    'class="sb-photo-btn" onclick="openFall()"');
rep($src, 'Foto butonu -> Fall (etiket)',
    '<div class="sb-photo-t">Brief / Bescheid scannen</div><div class="sb-photo-s">Bis zu 15 Fotos · KI-Analyse</div>',
    '<div class="sb-photo-t">⚖️ Fall eröffnen</div><div class="sb-photo-s">KI-Tiefenanalyse · Pro/Elite</div>');

/* 2) Panel + CSS + JS enjekte et (</body> öncesi) */
if (strpos($src, 'id="pnl-fall"') === false) {
$block = <<<'HTML'
<style id="chp-fall">
#pnl-fall{position:fixed!important;inset:0!important;z-index:600!important;background:#07070e!important;display:none!important;flex-direction:column!important;overflow:hidden}
#pnl-fall.on{display:flex!important}
#fall-body{flex:1;overflow-y:auto;padding:18px}
#fall-body::-webkit-scrollbar{width:4px}#fall-body::-webkit-scrollbar-thumb{background:#21213a;border-radius:2px}
</style>
<div class="pnl" id="pnl-fall">
  <div class="pnl-topbar">
    <div class="pnl-back-btn" onclick="gP('chat')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="15,18 9,12 15,6"/></svg></div>
    <div class="pnl-topbar-title">⚖️ Fall-Analyse</div>
  </div>
  <div id="fall-body"></div>
</div>
<script>
(function(){
  const FK='ch_falls_v1';
  function falls(){ try{return JSON.parse(localStorage.getItem(FK)||'[]');}catch(e){return [];} }
  function saveFalls(f){ try{localStorage.setItem(FK, JSON.stringify(f));}catch(e){} }
  function plan(){ return (window.P&&P.plan)||(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||'free'; }
  function token(){ return localStorage.getItem('ch_token')||''; }
  function esc(s){ return (s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
  let cur=null;

  window.openFall=function(){ if(typeof gP==='function')gP('fall'); if(typeof cSB==='function')cSB(); render(); };
  function getCur(){ return falls().find(f=>f.id===cur); }
  function updateCur(fn){ const all=falls(); const f=all.find(x=>x.id===cur); if(f){fn(f); saveFalls(all);} }

  function render(){
    const p=document.getElementById('fall-body'); if(!p)return;
    if(!token()){ p.innerHTML=upsell('Bitte zuerst anmelden, um Fälle zu eröffnen.', true); return; }
    if(plan()!=='pro'&&plan()!=='elite'){ p.innerHTML=upsell('Die KI-Fallanalyse ist nur für Pro & Elite verfügbar.', false); return; }
    if(cur===null) renderList(p); else renderDetail(p);
  }
  function upsell(msg, login){
    return '<div style="max-width:420px;margin:44px auto;text-align:center">'
      +'<div style="font-size:42px;margin-bottom:14px">⚖️</div>'
      +'<div style="font-size:18px;font-weight:800;color:#fff;margin-bottom:8px">Fall-Analyse</div>'
      +'<div style="font-size:13px;color:#9090b8;line-height:1.6;margin-bottom:22px">'+esc(msg)+'<br>3× DeepSeek + Claude · maßgeschneidertes Dokument</div>'
      +'<button onclick="'+(login?'handleUserBtn()':"gP('konto')")+'" style="padding:13px 24px;background:linear-gradient(135deg,#d4a84a,#e8b840);border:none;border-radius:12px;color:#1a0e00;font-weight:800;cursor:pointer;font-family:inherit">'+(login?'Anmelden':'Zu den Tarifen →')+'</button></div>';
  }
  function renderList(p){
    const list=falls();
    let h='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:10px">'
      +'<div><div style="font-size:18px;font-weight:800;color:#fff">Meine Fälle</div>'
      +'<div style="font-size:11.5px;color:#808090">KI-Tiefenanalyse · 3× DeepSeek + Claude</div></div>'
      +'<button onclick="fallNew()" style="padding:10px 16px;background:linear-gradient(135deg,#d4a84a,#e8b840);border:none;border-radius:11px;color:#1a0e00;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">+ Neuer Fall</button></div>';
    if(!list.length) h+='<div style="text-align:center;padding:46px 20px;color:#606080;font-size:13px">Noch keine Fälle.<br>Eröffnen Sie Ihren ersten Fall.</div>';
    list.slice().reverse().forEach(f=>{
      h+='<div onclick="fallOpen(\''+f.id+'\')" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px 16px;margin-bottom:10px;cursor:pointer;display:flex;align-items:center;gap:12px">'
        +'<div style="font-size:20px">⚖️</div><div style="flex:1;min-width:0">'
        +'<div style="font-size:14px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(f.title)+'</div>'
        +'<div style="font-size:11px;color:#606080;margin-top:2px">'+((f.messages||[]).length)+' Nachrichten · '+(f.document?'✅ Dokument fertig':'in Bearbeitung')+'</div></div>'
        +'<button onclick="event.stopPropagation();fallDelete(\''+f.id+'\')" style="background:none;border:none;color:#e05050;cursor:pointer;font-size:15px;padding:6px">🗑️</button></div>';
    });
    p.innerHTML=h;
  }
  function renderDetail(p){
    const f=getCur(); if(!f){cur=null;render();return;}
    let h='<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">'
      +'<button onclick="fallBack()" style="width:34px;height:34px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;cursor:pointer;font-size:16px">←</button>'
      +'<div style="font-size:16px;font-weight:800;color:#fff;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(f.title)+'</div></div>';
    h+='<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">';
    (f.messages||[]).forEach(m=>{
      const u=m.role==='user';
      h+='<div style="align-self:'+(u?'flex-end':'flex-start')+';max-width:88%;background:'+(u?'rgba(212,168,74,.14)':'rgba(255,255,255,.05)')+';border:1px solid '+(u?'rgba(212,168,74,.3)':'rgba(255,255,255,.08)')+';border-radius:14px;padding:10px 14px;font-size:13.5px;color:#f0f0ff;line-height:1.6;white-space:pre-wrap">'+esc(m.content)+'</div>';
    });
    h+='</div>';
    if(f.document){
      h+='<div style="background:rgba(66,223,148,.06);border:1px solid rgba(66,223,148,.2);border-radius:14px;padding:14px;margin-bottom:14px">'
        +'<div style="font-size:12px;font-weight:700;color:#42df94;margin-bottom:8px">📄 Maßgeschneidertes Dokument</div>'
        +'<pre style="white-space:pre-wrap;font-family:inherit;font-size:13px;color:#e0e0ff;line-height:1.7;margin:0">'+esc(f.document)+'</pre>'
        +'<div style="display:flex;gap:8px;margin-top:12px">'
        +'<button onclick="fallCopy()" style="flex:1;padding:9px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:#fff;cursor:pointer;font-family:inherit">📋 Kopieren</button>'
        +'<button onclick="fallPDF()" style="flex:1;padding:9px;background:linear-gradient(135deg,#d4a84a,#e8b840);border:none;border-radius:10px;color:#1a0e00;font-weight:700;cursor:pointer;font-family:inherit">📄 PDF</button></div></div>';
    }
    h+='<div style="display:flex;gap:8px;margin-bottom:10px">'
      +'<input id="fall-input" placeholder="Situation beschreiben…" onkeydown="if(event.key===\'Enter\')fallSend()" style="flex:1;background:#15152a;border:1.5px solid rgba(212,168,74,.3);border-radius:12px;padding:12px 14px;color:#fff;font-size:15px;outline:none;font-family:inherit">'
      +'<button onclick="fallSend()" style="padding:0 18px;background:rgba(212,168,74,.15);border:1px solid rgba(212,168,74,.3);border-radius:12px;color:#d4a84a;font-weight:700;cursor:pointer;font-family:inherit">Senden</button></div>';
    h+='<button id="fall-solve-btn" onclick="fallSolve()" style="width:100%;padding:13px;background:linear-gradient(135deg,#9060d4,#c090ff);border:none;border-radius:12px;color:#fff;font-weight:800;cursor:pointer;font-family:inherit">🧠 Fall lösen — Dokument erstellen</button>';
    p.innerHTML=h;
    const ip=document.getElementById('fall-input'); if(ip)ip.focus();
  }

  window.fallNew=function(){
    const t=prompt('Titel des Falls (z.B. „Mieterhöhung Widerspruch"):');
    if(!t||!t.trim())return;
    const all=falls(); const f={id:'f'+Date.now(),title:t.trim(),messages:[],document:'',createdAt:Date.now()};
    all.push(f); saveFalls(all); cur=f.id; render();
  };
  window.fallOpen=function(id){ cur=id; render(); };
  window.fallBack=function(){ cur=null; render(); };
  window.fallDelete=function(id){ if(!confirm('Diesen Fall löschen?'))return; saveFalls(falls().filter(f=>f.id!==id)); if(cur===id)cur=null; render(); };
  window.fallCopy=function(){ const f=getCur(); if(f&&f.document)navigator.clipboard.writeText(f.document).then(()=>alert('✅ Kopiert')); };
  window.fallPDF=function(){ const f=getCur(); if(f&&f.document&&typeof makePDF==='function')makePDF(f.document,f.title,(window.CC||'DE')); else alert('PDF nicht verfügbar.'); };

  window.fallSend=async function(){
    const f=getCur(); if(!f)return;
    const inp=document.getElementById('fall-input'); const text=inp?inp.value.trim():''; if(!text)return; inp.value='';
    updateCur(x=>x.messages.push({role:'user',content:text}));
    updateCur(x=>x.messages.push({role:'assistant',content:'…'})); render();
    try{
      const r=await fetch('fall-api.php',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token()},
        body:JSON.stringify({action:'fall_chat',title:f.title,messages:getCur().messages.filter(m=>m.content!=='…')})});
      const d=await r.json();
      updateCur(x=>{x.messages=x.messages.filter(m=>m.content!=='…');x.messages.push({role:'assistant',content:d.reply||d.error||'Fehler'});});
    }catch(e){ updateCur(x=>{x.messages=x.messages.filter(m=>m.content!=='…');x.messages.push({role:'assistant',content:'Verbindungsfehler.'});}); }
    render();
  };
  window.fallSolve=async function(){
    const f=getCur(); if(!f)return;
    if(!(f.messages||[]).length){ alert('Bitte beschreiben Sie zuerst kurz Ihre Situation.'); return; }
    const b=document.getElementById('fall-solve-btn'); if(b){b.disabled=true;b.textContent='🧠 Analysiere… (1-2 Min)';}
    try{
      const r=await fetch('fall-api.php',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token()},
        body:JSON.stringify({action:'fall_solve',title:f.title,messages:f.messages})});
      const d=await r.json();
      if(d.document){ updateCur(x=>{x.document=d.document;x.analysis=d.analysis;x.messages.push({role:'assistant',content:'✅ Analyse abgeschlossen — Ihr Dokument ist unten.'});}); }
      else alert(d.error||'Fehler bei der Analyse.');
    }catch(e){ alert('Verbindungsfehler.'); }
    render();
  };
})();
</script>
HTML;
    $src = str_replace('</body>', $block . "\n</body>", $src);
    $report[] = ['Fall paneli + JS enjekte edildi', substr_count($block,'id="pnl-fall"')];
} else {
    $report[] = ['Fall paneli zaten ekli', 0];
}

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Fall eröffnen UI raporu\n=================================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: index.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php aç. Sonra: kenar menüde 'Fall eröffnen'.\n";
echo "Gerekli: fall-api.php sunucuda olmalı.\nSil: rm apply-fall-ui.php\n";
