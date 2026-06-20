<?php
/**
 * ChatHelp — Foto-Analyse paketli-only + Beispiel-Fragen (additive)
 *  #1 analyze_photo fetch'i sadece paketli (basic/pro/elite) kullanıcıya izinli
 *     -> free/girişsiz: token HARCANMAZ, premium "Foto = Paket" paywall çıkar
 *  #2 Welcome en altına (Verträge'nin ALTINA) tıklanabilir örnek sorular
 * KULLANIM: chat-help.com/chat/apply-foto-gate.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-fotogate-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-foto-gate') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-foto-gate-css">
#ch-fg-modal{position:fixed;inset:0;z-index:100050;background:rgba(0,0,0,.8);display:none;align-items:flex-end;justify-content:center}
#ch-fg-modal.on{display:flex}
#ch-fg-sheet{background:#0d0d1f;border-radius:22px 22px 0 0;padding:22px 20px 30px;max-width:460px;width:100%;border:1px solid #2a2a4a}
#ch-fg-sheet .fgh{text-align:center;margin-bottom:16px}
#ch-fg-sheet .fgh .ic{font-size:30px;margin-bottom:6px}
#ch-fg-sheet .fgh .t{font-size:17px;font-weight:800;color:#fff}
#ch-fg-sheet .fgh .s{font-size:12.5px;color:#8080a0;margin-top:5px}
#ch-fg-sheet .fgb{display:flex;align-items:center;gap:11px;padding:13px;margin-bottom:8px;border-radius:13px;cursor:pointer;color:#fff}
#ch-fg-sheet .fgb .nm{flex:1;text-align:left}
#ch-fg-sheet .fgb .nm b{display:block;font-size:13.5px}
#ch-fg-sheet .fgb .nm span{font-size:10.5px;color:#9090a8}
#ch-fg-sheet .fgb .pr{font-size:13px;font-weight:800}
#ch-fg-sheet .fgc{width:100%;padding:11px;background:transparent;border:none;color:#707090;font-size:13px;cursor:pointer;margin-top:4px}
/* örnek sorular */
.ch-ex-sec{margin:22px 0 8px;text-align:left}
.ch-ex-h{font-size:15px;font-weight:800;color:#fff;margin-bottom:3px;display:flex;align-items:center;gap:7px}
.ch-ex-sub{font-size:11.5px;color:var(--t3,#b4b4c0);margin-bottom:12px}
.ch-ex-grid{display:flex;flex-direction:column;gap:8px}
.ch-ex-q{display:flex;align-items:center;gap:11px;padding:13px 15px;background:var(--s2,#26262c);border:1px solid var(--bdr,rgba(255,255,255,.09));border-radius:13px;cursor:pointer;transition:all .16s;text-align:left;color:#e7e7ef;font-size:13.5px;font-weight:600;width:100%;font-family:inherit}
.ch-ex-q:hover{border-color:rgba(212,168,74,.4);background:var(--s3,#313137);transform:translateX(2px)}
.ch-ex-q .qic{font-size:17px;flex:0 0 auto}
.ch-ex-q .qar{margin-left:auto;color:var(--gold,#d4a84a);font-weight:800;flex:0 0 auto}
</style>
<script id="ch-foto-gate">
(function(){
  'use strict';
  function plan(){ var p=(window.P&&P.plan)||''; try{ if(!p)p=(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||''; }catch(e){} return (''+p).toLowerCase(); }
  function isPaid(){ return !!localStorage.getItem('ch_token') && ['basic','pro','elite'].indexOf(plan())>-1; }

  /* ── #1a Foto paywall ── */
  function buildModal(){
    if(document.getElementById('ch-fg-modal')) return;
    var m=document.createElement('div'); m.id='ch-fg-modal';
    m.innerHTML='<div id="ch-fg-sheet">'
      +'<div class="fgh"><div class="ic">📸</div><div class="t">Foto-Analyse freischalten</div>'
      +'<div class="s">Dokumente fotografieren & automatisch ausfüllen — eine Premium-Funktion.</div></div>'
      +'<div class="fgb" data-p="basic" style="background:rgba(212,168,74,.1);border:1px solid rgba(212,168,74,.32)"><span style="font-size:18px">🥉</span><span class="nm"><b>Basic</b><span>20 Dokumente · Foto-Analyse</span></span><span class="pr" style="color:#d4a84a">13,99 €/Mo</span></div>'
      +'<div class="fgb" data-p="pro" style="background:rgba(96,148,255,.08);border:1px solid rgba(96,148,255,.32)"><span style="font-size:18px">🥈</span><span class="nm"><b>Pro</b><span>50 Dok. · 20 Fallanalysen</span></span><span class="pr" style="color:#6094ff">29,99 €/Mo</span></div>'
      +'<div class="fgb" data-p="elite" style="background:rgba(200,160,255,.08);border:1px solid rgba(200,160,255,.32)"><span style="font-size:18px">👑</span><span class="nm"><b>Elite</b><span>Unbegrenzt · alle Funktionen</span></span><span class="pr" style="color:#c89aff">99,99 €/Mo</span></div>'
      +'<button class="fgc">Später</button></div>';
    document.body.appendChild(m);
    m.addEventListener('click',function(e){ if(e.target===m) m.classList.remove('on'); });
    m.querySelector('.fgc').addEventListener('click',function(){ m.classList.remove('on'); });
    m.querySelectorAll('.fgb').forEach(function(b){ b.addEventListener('click',function(){
      var p=b.getAttribute('data-p'); m.classList.remove('on');
      try{ if(typeof window.stripeCheckout==='function') window.stripeCheckout(p); }catch(e){}
    });});
  }
  function showFotoPaywall(){ buildModal(); var m=document.getElementById('ch-fg-modal'); if(m) m.classList.add('on'); }

  /* ── #1b fetch gate: analyze_photo sadece paketli ── */
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    if(/action=analyze_photo/.test(url) && !isPaid()){
      showFotoPaywall();
      return Promise.resolve(new Response(JSON.stringify({error:'paid_only',result:{}}),{status:200,headers:{'Content-Type':'application/json'}}));
    }
    return of(input,init);
  };

  /* ── #2 Beispiel-Fragen (welcome en altı) ── */
  var EX=[
    {ic:'⚖️',t:'Ich möchte Widerspruch gegen einen Bescheid einlegen'},
    {ic:'🏠',t:'Wie kündige ich meinen Mietvertrag richtig?'},
    {ic:'🚗',t:'Ich habe einen Bußgeldbescheid erhalten – was kann ich tun?'},
    {ic:'💼',t:'Ich brauche einen Arbeitsvertrag'},
    {ic:'💶',t:'Meine Kaution wurde nicht zurückgezahlt'},
    {ic:'📨',t:'Ich möchte eine Mahnung / Zahlungsaufforderung schreiben'},
    {ic:'🛒',t:'Ich möchte einen Online-Kauf widerrufen'}
  ];
  window.chExAsk=function(t){ var i=document.getElementById('inp'); if(i){ i.value=t; try{ if(typeof window.onInp==='function') onInp(i); }catch(e){} if(typeof window.doSend==='function') window.doSend(); } };
  function buildEx(){
    var host=document.getElementById('wlc'); if(!host) return;
    var ex=host.querySelector('.ch-ex-sec');
    if(ex){ host.appendChild(ex); return; } // her zaman en altta (Verträge'nin altında) kalsın
    var sec=document.createElement('div'); sec.className='ch-ex-sec';
    sec.innerHTML='<div class="ch-ex-h">💡 Beispiel-Fragen</div>'
      +'<div class="ch-ex-sub">Tippen Sie auf eine Frage, um direkt zu starten:</div>'
      +'<div class="ch-ex-grid">'
      + EX.map(function(e){ var t=e.t.replace(/"/g,'&quot;'); return '<button type="button" class="ch-ex-q" onclick="chExAsk(this.getAttribute(\'data-t\'))" data-t="'+t+'"><span class="qic">'+e.ic+'</span>'+e.t+'<span class="qar">→</span></button>'; }).join('')
      +'</div>';
    host.appendChild(sec);
  }

  function tick(){ buildEx(); }
  function boot(){ tick(); setInterval(tick,1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n=0;
$src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Foto-gate + Beispiele raporu\n=======================================\n\n";
echo ($n>0?"  ✓ Katman eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-foto-gate.php\n";
echo "Test: free hesapta foto yükle -> token harcanmaz, '📸 Foto-Analyse' paywall çıkar.\n";
echo "      Ana sayfa en altta (Verträge altında) '💡 Beispiel-Fragen' görünür.\n";
