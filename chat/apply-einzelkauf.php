<?php
/**
 * ChatHelp — Einzelkauf TESLİM: tek ödeme yapan belgeyi alsın (additive)
 *  • payment=success + mode=payment doğrulanınca 1 "Guthaben" verir
 *  • Bekleyen belge ödeme öncesi saklanır, dönüşte OTOMATİK üretilir/teslim edilir
 *  • genDoc: girişsiz/free kullanıcı Guthaben varsa belgeyi üretir (kredi tüketir)
 * Not: mevcut sistem zaten client-side kapılı; bu da onunla tutarlı.
 *      Tam güvenlik için webhook server-side kredi vermeli (sonraki adım).
 * KULLANIM: chat-help.com/chat/apply-einzelkauf.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-einzel-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-einzelkauf') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-einzel-css">
#ch-ez-toast{position:fixed;left:50%;bottom:90px;transform:translateX(-50%) translateY(20px);z-index:100002;max-width:90%;background:var(--s1,#16162a);border:1px solid var(--gold,#d4a84a);color:#f0f0f6;padding:13px 18px;border-radius:14px;box-shadow:0 12px 34px rgba(0,0,0,.5);font-size:13.5px;opacity:0;transition:.3s;text-align:center}
#ch-ez-toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
<script id="ch-einzelkauf">
(function(){
  'use strict';
  function credits(){ try{return parseInt(localStorage.getItem('ch_credits')||'0',10)||0;}catch(e){return 0;} }
  function addCredit(n){ try{localStorage.setItem('ch_credits',String(Math.max(0,credits()+n)));}catch(e){} }
  function useCredit(){ if(credits()>0){ addCredit(-1); return true; } return false; }
  window.chCredits=credits;

  function plan(){ var p=(window.P&&P.plan)||''; try{ if(!p)p=(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||''; }catch(e){} return (''+p).toLowerCase()||'free'; }
  function loggedPlan(){ return !!localStorage.getItem('ch_token') && plan()!=='free'; }

  function toast(msg){
    var t=document.getElementById('ch-ez-toast');
    if(!t){ t=document.createElement('div'); t.id='ch-ez-toast'; document.body.appendChild(t); }
    t.textContent=msg; t.classList.add('on');
    clearTimeout(t._t); t._t=setTimeout(function(){ t.classList.remove('on'); }, 8000);
  }

  /* stripeCheckout sarmala: doc ödemesinde bekleyen belgeyi sakla */
  function wrapCheckout(){
    if(typeof window.stripeCheckout==='function' && !window.stripeCheckout._chc){
      var _s=window.stripeCheckout;
      window.stripeCheckout=function(planKey, docType, docKey){
        try{
          if(docKey && window._pendingDoc){ localStorage.setItem('ch_pending', JSON.stringify(window._pendingDoc)); }
          else if(docKey && window._lastGenAns){ localStorage.setItem('ch_pending', JSON.stringify(window._lastGenAns)); }
        }catch(e){}
        return _s.apply(this, arguments);
      };
      window.stripeCheckout._chc=true;
    }
  }

  /* genDoc sarmala: Guthaben varsa girişsiz/free üret */
  function wrapGen(){
    if(typeof window.genDoc==='function' && !window.genDoc._chc){
      var _g=window.genDoc;
      window.genDoc=function(dk,ans){
        if(!loggedPlan() && credits()>0){
          useCredit();
          var hadTk=localStorage.getItem('ch_token'); var oPlan=window.P?P.plan:undefined;
          if(!hadTk){ try{localStorage.setItem('ch_token','ch_credit_guest');}catch(e){} }
          try{ if(window.P) P.plan='basic'; }catch(e){}
          var r;
          try{ r=_g.call(this,dk,ans); }
          finally{
            if(!hadTk){ try{localStorage.removeItem('ch_token');}catch(e){} }
            try{ if(window.P) P.plan=oPlan; }catch(e){}
          }
          return r;
        }
        return _g.apply(this,arguments);
      };
      window.genDoc._chc=true;
    }
  }

  /* Ödeme dönüşü: tek ödeme doğrulandıysa kredi + otomatik teslim */
  (function(){
    try{
      var u=new URLSearchParams(location.search);
      if(u.get('payment')==='success' && u.get('session_id')){
        var sid=u.get('session_id');
        if(!sessionStorage.getItem('ch_paid_'+sid)){
          fetch('stripe-checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'verify',session_id:sid})})
          .then(function(r){return r.json();}).then(function(d){
            if(d && d.paid && d.mode==='payment'){
              sessionStorage.setItem('ch_paid_'+sid,'1'); addCredit(1);
              var pj=null; try{ pj=localStorage.getItem('ch_pending'); }catch(e){}
              if(pj){
                try{ localStorage.removeItem('ch_pending'); var pd=JSON.parse(pj);
                  toast('✅ Zahlung erfolgreich — Ihr Dokument wird jetzt erstellt …');
                  setTimeout(function(){ if(typeof window.genDoc==='function' && pd && pd.dk){ window.genDoc(pd.dk, pd.ans||{}); } }, 1000);
                }catch(e){ toast('✅ Zahlung erfolgreich — Sie haben 1 Guthaben.'); }
              } else {
                toast('✅ Zahlung erfolgreich — Sie haben 1 Guthaben. Erstellen Sie jetzt Ihr Dokument oder Formular.');
              }
            }
          }).catch(function(){});
        }
      }
    }catch(e){}
  })();

  function tick(){ wrapCheckout(); wrapGen(); }
  tick(); setInterval(tick, 500);
})();
</script>
HTML;

$n=0;
$src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Einzelkauf teslim raporu\n===================================\n\n";
echo ($n>0?"  ✓ Einzelkauf katmanı eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-einzelkauf.php\n";
echo "Test: free hesapta dilekçe/Verträge -> paywall -> 1,99 öde -> dönüşte belge otomatik üretilir.\n";
