<?php
/**
 * ChatHelp — apply-plans-fix (CH_PLANS_FIX) — plan butonlari + tek-sayfa + Stripe.
 *  Sorun: plan butonlari calismiyor; planlari gosteren temiz tek sayfa yok.
 *  Cozum (EKLEMELI, mevcut stripe-checkout.php calisiyor):
 *   (1) chGoPlan(key): robust abonelik checkout (PLANS scope'una bagimsiz, direkt POST -> d.url)
 *   (2) window.stripeCheckout override: abonelik cagrilari chGoPlan'a (doc odemeleri orijinale)
 *   (3) window.chOpenPlans(): premium TEK SAYFA plan ekrani (Basic/Pro/Elite, calisir butonlar)
 *   (4) mevcut #pm "Tarife" acilisi -> temiz chOpenPlans'a yonlendir
 *  Gomulu JS node --check ile dogrulandi. Fiyatlar canli PLANS ile birebir.
 * KULLANIM: pull2.php?key=...&files=apply-plans-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plans-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PLANS_FIX')!==false) exit("Zaten ekli (CH_PLANS_FIX).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-plans-fix-css">
/* CH_PLANS_FIX — tek-sayfa plan ekrani */
#ch-plans{position:fixed;inset:0;z-index:9500;background:rgba(6,6,16,.92);backdrop-filter:blur(4px);display:none;overflow-y:auto;padding:32px 16px}
#ch-plans.on{display:block}
.chpf-wrap{max-width:1040px;margin:0 auto}
.chpf-head{text-align:center;margin-bottom:26px;position:relative}
.chpf-title{font-size:26px;font-weight:800;color:#f4f4fb;margin-bottom:6px}
.chpf-sub{font-size:14px;color:#9a9ac0}
.chpf-x{position:absolute;top:-8px;right:0;width:40px;height:40px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#c8c8e0;font-size:20px;cursor:pointer}
.chpf-x:hover{background:rgba(255,255,255,.1)}
.chpf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
@media(max-width:820px){.chpf-grid{grid-template-columns:1fr}}
.chpf-card{background:linear-gradient(180deg,#15152a,#0e0e1e);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:24px 20px;display:flex;flex-direction:column;position:relative}
.chpf-card.pop{border-color:rgba(212,168,74,.5);box-shadow:0 12px 40px rgba(212,168,74,.14)}
.chpf-badge{position:absolute;top:-11px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;font-size:11px;font-weight:800;padding:4px 12px;border-radius:20px;white-space:nowrap}
.chpf-ic{font-size:30px}
.chpf-nm{font-size:19px;font-weight:800;color:#f4f4fb;margin:8px 0 2px}
.chpf-pr{font-size:30px;font-weight:800;color:#fff;line-height:1}
.chpf-pr span{font-size:14px;font-weight:500;color:#9a9ac0}
.chpf-feats{list-style:none;margin:18px 0;padding:0;flex:1}
.chpf-feats li{display:flex;gap:9px;align-items:flex-start;font-size:13.5px;color:#d6d6ea;padding:6px 0}
.chpf-feats li .ck{color:#4caf7d;flex:0 0 auto;font-weight:800}
.chpf-feats li.off{color:#66668a;text-decoration:line-through}
.chpf-feats li.off .ck{color:#66668a}
.chpf-btn{width:100%;padding:13px;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;transition:transform .15s,box-shadow .15s}
.chpf-btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(212,168,74,.32)}
.chpf-btn.cur{background:#1d3a29;color:#7fe0a8;cursor:default}
.chpf-note{text-align:center;font-size:12px;color:#7a7a9a;margin-top:20px}
</style>
<script id="ch-plans-fix-js">
/* CH_PLANS_FIX */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{ttl:"Wählen Sie Ihren Plan",sub:"Jederzeit kündbar · sichere Zahlung über Stripe",btn:"Wählen",cur:"Aktiv",pop:"Beliebteste Wahl",per:"/Monat",note:"Zahlung erfolgt sicher über Stripe. Sie können jederzeit im Konto kündigen."},
    tr:{ttl:"Planınızı seçin",sub:"İstediğiniz zaman iptal · Stripe ile güvenli ödeme",btn:"Seç",cur:"Aktif",pop:"En popüler",per:"/ay",note:"Ödeme Stripe ile güvenli şekilde alınır. Hesabınızdan istediğiniz zaman iptal edebilirsiniz."},
    en:{ttl:"Choose your plan",sub:"Cancel anytime · secure payment via Stripe",btn:"Choose",cur:"Active",pop:"Most popular",per:"/month",note:"Payment is processed securely via Stripe. Cancel anytime in your account."},
    fr:{ttl:"Choisissez votre offre",sub:"Résiliable à tout moment · paiement sécurisé Stripe",btn:"Choisir",cur:"Actif",pop:"Le plus choisi",per:"/mois",note:"Paiement sécurisé via Stripe. Résiliez à tout moment."},
    es:{ttl:"Elija su plan",sub:"Cancele cuando quiera · pago seguro con Stripe",btn:"Elegir",cur:"Activo",pop:"Más popular",per:"/mes",note:"Pago seguro con Stripe. Cancele cuando quiera."},
    it:{ttl:"Scegli il tuo piano",sub:"Disdici quando vuoi · pagamento sicuro con Stripe",btn:"Scegli",cur:"Attivo",pop:"Più scelto",per:"/mese",note:"Pagamento sicuro tramite Stripe. Disdici quando vuoi."}
  };
  function L(){ return T[lang()]||T.de; }
  var PLAN_DEF=[
    {key:'basic',name:'Basic',price:'13,99',ic:'🥉',
     feats:['40 Dokumente / Monat','20 Staatliche Formulare / Monat','PDF-Download','KI-Recherche','5 Fallanalysen / Monat'],
     miss:['Ton-Anpassung','Strategie-Analyse'],pop:false},
    {key:'pro',name:'Pro',price:'29,99',ic:'🥈',
     feats:['200 Dokumente / Monat','Unbegrenzte Staatliche Formulare','PDF-Download','KI-Recherche','Ton-Anpassung','Strategie-Analyse','20 Fallanalysen / Monat'],
     miss:[],pop:true},
    {key:'elite',name:'Elite',price:'99,99',ic:'👑',
     feats:['1000 Dokumente / Monat','Unbegrenzte Staatliche Formulare','PDF-Download','KI-Recherche','Ton-Anpassung','Strategie-Analyse','100 Fallanalysen / Monat','Anwalt-DeepSearch'],
     miss:[],pop:false}
  ];
  function curPlan(){
    try{
      if(window.P && P.plan) return String(P.plan);
      var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); return String(u.plan||'');
    }catch(e){ return ''; }
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

  /* robust abonelik checkout — direkt POST, PLANS scope'una bagimsiz */
  function chGoPlan(key){
    try{
      if(!({basic:1,pro:1,elite:1}[key])) return;
      var sessId=((document.cookie.match(/ch_sid=([^;]+)/)||[])[1])||Math.random().toString(36).slice(2);
      var email=''; try{ email=(window.P&&P.f7)||JSON.parse(localStorage.getItem('ch_user')||'{}').email||''; }catch(e){}
      fetch('stripe-checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({type:'subscription',plan:key,doc_type:'',session_id:sessId,email:email})})
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d&&d.url){ window.location.href=d.url; } else { alert('Fehler: '+((d&&d.error)||'Unbekannt')); } })
      .catch(function(){ alert('Verbindungsfehler. Bitte erneut versuchen.'); });
    }catch(e){ alert('Verbindungsfehler.'); }
  }
  window.chGoPlan=chGoPlan;

  function render(){
    var l=L(), cur=curPlan();
    var cards=PLAN_DEF.map(function(p){
      var feats=p.feats.map(function(f){ return '<li><span class="ck">✓</span>'+esc(f)+'</li>'; }).join('');
      var miss=p.miss.map(function(m){ return '<li class="off"><span class="ck">✕</span>'+esc(m)+'</li>'; }).join('');
      var isCur=(cur===p.key);
      var btn=isCur
        ? '<button class="chpf-btn cur" disabled>✓ '+esc(l.cur)+'</button>'
        : '<button class="chpf-btn" data-k="'+p.key+'">'+esc(l.btn)+' · '+esc(p.name)+'</button>';
      return '<div class="chpf-card'+(p.pop?' pop':'')+'">'
        +(p.pop?'<div class="chpf-badge">'+esc(l.pop)+'</div>':'')
        +'<div class="chpf-ic">'+p.ic+'</div>'
        +'<div class="chpf-nm">'+esc(p.name)+'</div>'
        +'<div class="chpf-pr">'+esc(p.price)+'€ <span>'+esc(l.per)+'</span></div>'
        +'<ul class="chpf-feats">'+feats+miss+'</ul>'
        +btn+'</div>';
    }).join('');
    return '<div class="chpf-wrap"><div class="chpf-head">'
      +'<button class="chpf-x" data-close="1">✕</button>'
      +'<div class="chpf-title">'+esc(l.ttl)+'</div><div class="chpf-sub">'+esc(l.sub)+'</div></div>'
      +'<div class="chpf-grid">'+cards+'</div>'
      +'<div class="chpf-note">'+esc(l.note)+'</div></div>';
  }
  function chOpenPlans(){
    try{
      var host=document.getElementById('ch-plans');
      if(!host){ host=document.createElement('div'); host.id='ch-plans'; document.body.appendChild(host); }
      host.innerHTML=render();
      host.classList.add('on');
      host.onclick=function(ev){
        var t=ev.target;
        if(t.getAttribute && t.getAttribute('data-close')){ host.classList.remove('on'); return; }
        if(t===host){ host.classList.remove('on'); return; }
        var b=t.closest?t.closest('.chpf-btn[data-k]'):null;
        if(b){ chGoPlan(b.getAttribute('data-k')); }
      };
    }catch(e){}
  }
  window.chOpenPlans=chOpenPlans;

  /* mevcut stripeCheckout'u robust yap: abonelik -> chGoPlan; doc odemesi -> orijinal */
  function patchSC(){
    try{
      if(typeof window.stripeCheckout==='function' && !window.stripeCheckout.__chfix){
        var _sc=window.stripeCheckout;
        var w=function(planKey,docType,docKey){
          try{ if(docKey){ return _sc.apply(this,arguments); } return chGoPlan(planKey); }
          catch(e){ try{ return chGoPlan(planKey); }catch(_){} }
        };
        w.__chfix=1; window.stripeCheckout=w;
      }
    }catch(e){}
  }
  /* mevcut #pm "Tarife" modali acilinca temiz plan sayfasina yonlendir */
  function watchPM(){
    try{
      var pm=document.getElementById('pm'); if(!pm||pm.__chwatch) return;
      pm.__chwatch=1;
      if(window.MutationObserver){
        new MutationObserver(function(){
          try{ var d=pm.style.display; if(d && d!=='none'){ pm.style.display='none'; chOpenPlans(); } }catch(e){}
        }).observe(pm,{attributes:true,attributeFilter:['style']});
      }
    }catch(e){}
  }
  var n=0;(function loop(){ patchSC(); watchPM(); if(n++<120) setTimeout(loop,600); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'pf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-plansfix-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PLANS_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PLANS_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Tek-sayfa plan ekrani + robust Stripe checkout. window.chOpenPlans() ile acilir;\n";
echo "  mevcut plan butonlari ve #pm 'Tarife' artik calisir Stripe akisina gider.\n";
