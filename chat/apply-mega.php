<?php
/**
 * ChatHelp — MEGA katman (additive, mevcut koda dokunmaz)
 * --------------------------------------------------------
 * </body>'den önce kendi kendine yeten bir JS+CSS paketi enjekte eder.
 * Mevcut kodu DEĞİŞTİRMEZ — üstüne ekler, bu yüzden çok güvenilir.
 *
 *  #1 AI sağlayıcı adlarını gizle (DeepSeek/Claude/Anthropic -> "ChatHelp KI")
 *  #2 Global "Abbrechen" (durdur) butonu — fetch sarmalayıcı + AbortController
 *  #3 Ton seçici (Formell/Sachlich/Bestimmt) — Pro/Elite, fall_solve'a otomatik ekler
 *  #4 Konto'da isim/e-posta/avatar doldurma (defansif, çoklu strateji)
 *  #5 Plan rozetini localStorage'dan göster
 *
 * KULLANIM: chat-help.com/chat/apply-mega.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-mega-' . date('Ymd-His'), $src);

// Zaten ekliyse tekrar ekleme
if (strpos($src, 'ch-mega-layer') !== false) {
    exit("Zaten ekli (ch-mega-layer). Önce kaldırmak istersen yedekten dön.\n");
}

$bundle = <<<'HTML'
<style id="ch-mega-style">
#ch-stop-btn{display:none;position:fixed;left:50%;bottom:96px;transform:translateX(-50%);z-index:99999;background:#b3261e;color:#fff;border:none;border-radius:24px;padding:11px 22px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.45);align-items:center;gap:8px;font-family:inherit}
#ch-stop-btn:hover{background:#9b1f18}
#ch-tone-bar{display:none;gap:7px;flex-wrap:wrap;align-items:center;margin:10px 0;padding:10px 12px;background:var(--s2,#29292e);border:1px solid var(--bdr,rgba(255,255,255,.09));border-radius:12px}
#ch-tone-bar .ch-tone-lbl{font-size:12px;color:var(--t4,#8e8e9c);font-weight:700;margin-right:2px}
#ch-tone-bar button{flex:0 0 auto;padding:7px 13px;border-radius:9px;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:700;border:1.5px solid var(--bdr,rgba(255,255,255,.12));background:var(--s3,#313137);color:#cfcfdd;transition:.15s}
#ch-tone-bar button.on{border-color:var(--gold,#d4a84a);color:var(--gold,#d4a84a);background:rgba(212,168,74,.12)}
</style>
<script id="ch-mega-layer">
(function(){
  'use strict';

  /* ─── kullanıcı/plan yardımcıları ─── */
  function chUser(){ try{return JSON.parse(localStorage.getItem('ch_user')||'null');}catch(e){return null;} }
  function chPlan(){
    var u=chUser(); var p=(u&&u.plan)|| (window.P&&window.P.plan) ||'';
    p=(''+p).toLowerCase();
    if(p.indexOf('elite')>-1) return 'elite';
    if(p.indexOf('pro')>-1)   return 'pro';
    if(p.indexOf('basic')>-1||p.indexOf('basis')>-1) return 'basic';
    return p||'free';
  }

  /* ═══ #1 AI sağlayıcı adlarını gizle ═══ */
  var MAP=[
    [/Deep\s?Seek/g,'ChatHelp KI'],
    [/\bClaude(\s?(AI|Sonnet|Opus|Haiku|\d(\.\d)?))?/g,'ChatHelp KI'],
    [/\bAnthropic\b/g,'ChatHelp'],
    [/\bGPT[- ]?\d?(\.\d)?\b/g,'ChatHelp KI'],
    [/\bOpenAI\b/g,'ChatHelp']
  ];
  function scrub(node){
    if(!node) return;
    if(node.nodeType===3){
      var t=node.nodeValue,nt=t;
      for(var i=0;i<MAP.length;i++) nt=nt.replace(MAP[i][0],MAP[i][1]);
      if(nt!==t) node.nodeValue=nt;
    } else if(node.nodeType===1 && node.childNodes && !/^(SCRIPT|STYLE|TEXTAREA|INPUT|SELECT)$/.test(node.tagName)){
      for(var j=0;j<node.childNodes.length;j++) scrub(node.childNodes[j]);
    }
  }
  var sQ=false;
  function scrubAll(){ sQ=false; try{scrub(document.body);}catch(e){} }
  function startScrub(){
    scrubAll();
    try{
      new MutationObserver(function(){ if(!sQ){sQ=true;setTimeout(scrubAll,60);} })
        .observe(document.body,{childList:true,subtree:true,characterData:true});
    }catch(e){}
  }

  /* ═══ #2 Global Durdur (Abbrechen) + #3 ton enjeksiyonu ═══ */
  var inflight=new Set();
  var origFetch=window.fetch.bind(window);
  window.fetch=function(input,init){
    init=init||{};
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    var isAI=/api\.php|fall-api\.php/.test(url);
    if(isAI){
      // ton enjeksiyonu
      if(init.body&&typeof init.body==='string'&&window._chTone){
        try{
          var b=JSON.parse(init.body);
          if(b&&['fall_solve','generate','document','generate_doc','revise'].indexOf(b.action)>-1){
            b.tone=window._chTone; init.body=JSON.stringify(b);
          }
        }catch(e){}
      }
      // iptal sinyali
      if(!init.signal && typeof AbortController!=='undefined'){
        var ac=new AbortController(); init.signal=ac.signal;
        inflight.add(ac); upd();
        var p=origFetch(input,init), done=function(){inflight.delete(ac);upd();};
        p.then(done,done); return p;
      }
    }
    return origFetch(input,init);
  };
  function upd(){ var b=document.getElementById('ch-stop-btn'); if(b) b.style.display=inflight.size>0?'flex':'none'; }
  window.chStopAll=function(){ inflight.forEach(function(ac){try{ac.abort();}catch(e){}}); inflight.clear(); upd(); };
  function injectStop(){
    if(document.getElementById('ch-stop-btn')) return;
    var b=document.createElement('button');
    b.id='ch-stop-btn'; b.type='button'; b.textContent='⏹ Abbrechen';
    b.onclick=window.chStopAll;
    document.body.appendChild(b);
  }

  /* ═══ #3 Ton seçici UI (Pro/Elite) ═══ */
  window._chTone='formell';
  function injectTone(){
    var plan=chPlan();
    if(plan!=='pro'&&plan!=='elite') return;            // sadece Pro/Elite
    if(document.getElementById('ch-tone-bar')) return;
    var host=document.getElementById('pnl-fall')||document.querySelector('#pnl-fall .konto-wrap')||null;
    if(!host) return;                                    // Fall paneli yoksa atla
    var bar=document.createElement('div');
    bar.id='ch-tone-bar';
    bar.innerHTML='<span class="ch-tone-lbl">✍️ Schreibstil:</span>'
      +'<button data-t="formell" class="on">Formell</button>'
      +'<button data-t="sachlich">Sachlich</button>'
      +'<button data-t="bestimmt">Bestimmt</button>';
    bar.style.display='flex';
    bar.addEventListener('click',function(e){
      var bt=e.target.closest('button'); if(!bt) return;
      window._chTone=bt.dataset.t;
      bar.querySelectorAll('button').forEach(function(x){x.classList.toggle('on',x===bt);});
    });
    host.insertBefore(bar, host.firstChild);
  }

  /* ═══ #4/#5 Konto isim/plan doldurma ═══ */
  function fillKonto(){
    var u=chUser(); if(!u||!u.email) return;
    var name=u.name||u.email, first=(name||'').split(' ')[0];
    function set(id,val){var e=document.getElementById(id); if(e&&val) e.textContent=val;}
    set('konto-name',name); set('konto-email',u.email);
    set('tb-auth-label',first);
    var av=document.getElementById('konto-avatar'); if(av) av.textContent=((name||'U')[0]||'U').toUpperCase();
    var pb=document.getElementById('konto-plan-badge'); if(pb) pb.textContent=chPlan().toUpperCase();
    var info=document.getElementById('konto-user-info'); if(info) info.style.display='flex';
  }

  function boot(){ startScrub(); injectStop(); fillKonto(); injectTone();
    setInterval(function(){ fillKonto(); injectTone(); },1500); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — MEGA katman raporu\n=============================\n\n";
echo ($n > 0 ? "  ✓ Mega katman </body> öncesine eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nEklenen özellikler:\n";
echo "  • AI adları gizlendi (DeepSeek/Claude -> 'ChatHelp KI')\n";
echo "  • Global 'Abbrechen' butonu (üretim sırasında görünür, iptal eder)\n";
echo "  • Ton seçici (Pro/Elite, Fall panelinde)\n";
echo "  • Konto isim/e-posta/avatar/plan doldurma\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-mega.php\n";
echo "Test: dilekçe/Fall oluştururken altta kırmızı 'Abbrechen' çıkar; metinlerde DeepSeek/Claude görünmez.\n";
