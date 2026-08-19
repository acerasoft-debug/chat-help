<?php
/**
 * ChatHelp — apply-plan-page (CH_PLAN_PAGE) — YENI premium plan secim sayfasi
 *  Tamamen EK, mevcut fiyat kartlarina/PLANS'a/stripeCheckout'a DOKUNMAZ.
 *   • Yeni panel #pnl-plans (gP('plans') ile acilir; pnl-cv/vst gibi dinamik)
 *   • 3 premium kart: Basic 13,99 / Pro 29,99 (beliebt) / Elite 99,99
 *   • Yeni kotalar + kullanici sayisi + Vize dahil/haric + modul bilgileri
 *   • "sec" dugmeleri window.stripeCheckout(plan) cagirir (mevcut Stripe akisi)
 *   • Sol menuye giris + karsilamaya CTA; cok dilli (de/en/tr/es/fr/it, fb en)
 * KULLANIM: pull2.php?key=...&files=apply-plan-page.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plan-page BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PLAN_PAGE')!==false) exit("Zaten ekli (CH_PLAN_PAGE).\n");

$bundle = <<<'HTML'
<style id="ch-planpage-css">
#pnl-plans{position:fixed;inset:0;z-index:540;background:var(--bg,#0e1016);display:none;overflow-y:auto;-webkit-overflow-scrolling:touch}
#pnl-plans.on{display:block}
#pnl-plans .pp-wrap{max-width:1080px;margin:0 auto;padding:18px 16px 48px}
#pnl-plans .pp-top{display:flex;align-items:center;gap:12px;margin:2px 0 18px}
#pnl-plans .pp-back{width:40px;height:40px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:var(--s2,#1b1d27);color:var(--t2,#e8e8f0);font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
#pnl-plans .pp-h{font-size:22px;font-weight:800;color:var(--t1,#fff);letter-spacing:.2px}
#pnl-plans .pp-sub{font-size:13px;color:var(--t4,#9a9aae);margin:-10px 0 20px 52px}
#pnl-plans .pp-grid{display:grid;grid-template-columns:1fr;gap:16px}
@media(min-width:820px){#pnl-plans .pp-grid{grid-template-columns:repeat(3,1fr)}}
#pnl-plans .pp-card{position:relative;background:linear-gradient(180deg,var(--s1,#171a24),var(--s2,#12141c));border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:24px 20px;display:flex;flex-direction:column;box-shadow:0 8px 30px rgba(0,0,0,.28)}
#pnl-plans .pp-card.pop{border-color:rgba(212,168,74,.55);box-shadow:0 12px 40px rgba(212,168,74,.16)}
#pnl-plans .pp-badge{position:absolute;top:-11px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410;font-size:11px;font-weight:800;letter-spacing:.5px;padding:4px 14px;border-radius:999px;white-space:nowrap}
#pnl-plans .pp-ic{font-size:30px;margin-bottom:6px}
#pnl-plans .pp-name{font-size:17px;font-weight:800;color:var(--t1,#fff)}
#pnl-plans .pp-price{font-size:30px;font-weight:800;color:var(--t1,#fff);margin-top:8px;line-height:1}
#pnl-plans .pp-price small{font-size:13px;font-weight:600;color:var(--t4,#9a9aae)}
#pnl-plans .pp-per{font-size:11.5px;color:var(--t4,#9a9aae);margin-top:4px}
#pnl-plans .pp-feats{list-style:none;padding:0;margin:16px 0 0;flex:1}
#pnl-plans .pp-feats li{display:flex;gap:9px;align-items:flex-start;font-size:13px;color:var(--t2,#dcdce6);padding:6px 0;line-height:1.4}
#pnl-plans .pp-feats li .ck{color:#5ecb7e;flex-shrink:0;font-weight:800}
#pnl-plans .pp-feats li.no{color:var(--t4,#8a8a9a)}
#pnl-plans .pp-feats li.no .ck{color:#c25b5b}
#pnl-plans .pp-btn{margin-top:18px;padding:13px 16px;border-radius:13px;border:0;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410;transition:transform .12s,filter .12s}
#pnl-plans .pp-btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
#pnl-plans .pp-btn.sec{background:var(--s3,#232633);color:var(--t2,#e8e8f0);border:1px solid rgba(255,255,255,.12)}
#pnl-plans .pp-btn.cur{background:transparent;color:var(--t4,#9a9aae);border:1px dashed rgba(255,255,255,.2);cursor:default}
#pnl-plans .pp-foot{text-align:center;font-size:11.5px;color:var(--t4,#9a9aae);margin-top:22px}
#ch-plans-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0 4px;padding:14px 16px;border:1px solid rgba(212,168,74,.4);border-radius:16px;background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.03));cursor:pointer}
#ch-plans-cta .t{font-size:13.5px;font-weight:800;color:var(--gold,#e9c46a)}
#ch-plans-cta .s{font-size:11.5px;color:var(--t4,#9a9aae);margin-top:2px}
#ch-plans-cta .go{color:var(--gold,#e9c46a);font-size:18px}
</style>
<script id="ch-planpage-js">
try{(function(){
  function L(){ try{ var l=(window.chUIL?window.chUIL():null)||localStorage.getItem("ch_uilang")||(window.UL||"de"); return l; }catch(e){ return "de"; } }
  var T={
   de:{h:"Wählen Sie Ihren Plan",sub:"Monatlich · jederzeit kündbar",pop:"BELIEBT",pick:"Auswählen",cur:"Aktueller Plan",per:"/ Monat · jederzeit kündbar",
       foot:"Sichere Zahlung über Stripe · Daten auf EU-Servern · DSGVO",cta:"Premium entdecken",ctas:"Mehr Vorlagen, Module & Fallanalysen",
       b:["1 Nutzer","40 Schreiben / Monat","Alle Module (ohne Visum)","PDF-Export · KI-Recherche","5 Fallanalysen / Monat"],
       p:["5 Nutzer (Familie)","200 Schreiben / Monat","Alle Module inkl. Visum","Ton-Anpassung · Strategie-Analyse","20 Fallanalysen / Monat"],
       e:["Unbegrenzte Nutzer","1000 Schreiben / Monat","Alles aus Pro","Anwalts-Deepsearch: echte Urteile","100 Fallanalysen / Monat"]},
   en:{h:"Choose your plan",sub:"Monthly · cancel anytime",pop:"POPULAR",pick:"Choose",cur:"Current plan",per:"/ month · cancel anytime",
       foot:"Secure payment via Stripe · EU servers · GDPR",cta:"Discover Premium",ctas:"More templates, modules & case analyses",
       b:["1 user","40 documents / month","All modules (no Visa)","PDF export · AI research","5 case analyses / month"],
       p:["5 users (family)","200 documents / month","All modules incl. Visa","Tone control · Strategy analysis","20 case analyses / month"],
       e:["Unlimited users","1000 documents / month","Everything in Pro","Lawyer deepsearch: real rulings","100 case analyses / month"]},
   tr:{h:"Planınızı seçin",sub:"Aylık · istediğiniz zaman iptal",pop:"POPÜLER",pick:"Seç",cur:"Mevcut plan",per:"/ ay · istediğiniz zaman iptal",
       foot:"Stripe ile güvenli ödeme · AB sunucuları · KVKK/GDPR",cta:"Premium'u keşfet",ctas:"Daha fazla şablon, modül ve dava analizi",
       b:["1 kullanıcı","40 dilekçe / ay","Tüm modüller (Vize hariç)","PDF · yapay zekâ araştırması","5 dava analizi / ay"],
       p:["5 kullanıcı (aile)","200 dilekçe / ay","Vize dahil tüm modüller","Üslup ayarı · Strateji analizi","20 dava analizi / ay"],
       e:["Sınırsız kullanıcı","1000 dilekçe / ay","Pro'daki her şey","Avukat deepsearch: gerçek kararlar","100 dava analizi / ay"]},
   es:{h:"Elija su plan",sub:"Mensual · cancele cuando quiera",pop:"POPULAR",pick:"Elegir",cur:"Plan actual",per:"/ mes · cancele cuando quiera",
       foot:"Pago seguro con Stripe · Servidores UE · RGPD",cta:"Descubrir Premium",ctas:"Más plantillas, módulos y análisis",
       b:["1 usuario","40 escritos / mes","Todos los módulos (sin Visado)","PDF · investigación IA","5 análisis de caso / mes"],
       p:["5 usuarios (familia)","200 escritos / mes","Todos los módulos incl. Visado","Ajuste de tono · Análisis de estrategia","20 análisis de caso / mes"],
       e:["Usuarios ilimitados","1000 escritos / mes","Todo lo de Pro","Deepsearch de abogados: sentencias reales","100 análisis de caso / mes"]},
   fr:{h:"Choisissez votre offre",sub:"Mensuel · résiliable à tout moment",pop:"POPULAIRE",pick:"Choisir",cur:"Offre actuelle",per:"/ mois · résiliable à tout moment",
       foot:"Paiement sécurisé via Stripe · Serveurs UE · RGPD",cta:"Découvrir Premium",ctas:"Plus de modèles, modules et analyses",
       b:["1 utilisateur","40 courriers / mois","Tous les modules (sans Visa)","PDF · recherche IA","5 analyses de cas / mois"],
       p:["5 utilisateurs (famille)","200 courriers / mois","Tous les modules dont Visa","Réglage du ton · Analyse stratégique","20 analyses de cas / mois"],
       e:["Utilisateurs illimités","1000 courriers / mois","Tout de Pro","Deepsearch avocat : vrais jugements","100 analyses de cas / mois"]},
   it:{h:"Scegli il tuo piano",sub:"Mensile · disdici quando vuoi",pop:"POPOLARE",pick:"Scegli",cur:"Piano attuale",per:"/ mese · disdici quando vuoi",
       foot:"Pagamento sicuro con Stripe · Server UE · GDPR",cta:"Scopri Premium",ctas:"Più modelli, moduli e analisi",
       b:["1 utente","40 documenti / mese","Tutti i moduli (senza Visto)","PDF · ricerca IA","5 analisi caso / mese"],
       p:["5 utenti (famiglia)","200 documenti / mese","Tutti i moduli incl. Visto","Regolazione tono · Analisi strategica","20 analisi caso / mese"],
       e:["Utenti illimitati","1000 documenti / mese","Tutto di Pro","Deepsearch avvocati: sentenze reali","100 analisi caso / mese"]}
  };
  function t(){ return T[L()]||T.en; }
  function curPlan(){ try{ var p=JSON.parse(localStorage.getItem("ch_profile")||"{}"); return p.plan||""; }catch(e){ return ""; } }
  var PRICES={basic:"13,99",pro:"29,99",elite:"99,99"};
  function panel(){
    var p=document.getElementById("pnl-plans");
    if(!p){ p=document.createElement("div"); p.id="pnl-plans"; p.className="pnl"; p.setAttribute("data-noi18n","1"); document.body.appendChild(p); }
    return p;
  }
  function card(key,ic,pop){
    var L=t(), feats=L[key==="basic"?"b":key==="pro"?"p":"e"];
    var cur=curPlan()===key;
    var name=key.charAt(0).toUpperCase()+key.slice(1);
    var li=feats.map(function(f){ return '<li><span class="ck">✓</span><span>'+f+'</span></li>'; }).join("");
    var btn = cur
      ? '<button class="pp-btn cur" disabled>'+L.cur+'</button>'
      : '<button class="pp-btn'+(pop?"":" sec")+'" onclick="try{window.stripeCheckout(\''+key+'\')}catch(e){}">'+L.pick+'</button>';
    return '<div class="pp-card'+(pop?" pop":"")+'">'
      +(pop?'<div class="pp-badge">'+L.pop+'</div>':'')
      +'<div class="pp-ic">'+ic+'</div><div class="pp-name">'+name+'</div>'
      +'<div class="pp-price">'+PRICES[key]+'<small>€</small></div>'
      +'<div class="pp-per">'+L.per+'</div>'
      +'<ul class="pp-feats">'+li+'</ul>'+btn+'</div>';
  }
  function render(){
    var L=t(), p=panel();
    p.innerHTML='<div class="pp-wrap">'
      +'<div class="pp-top"><button class="pp-back" onclick="try{gP(\'chat\')}catch(e){}">‹</button><div class="pp-h">'+L.h+'</div></div>'
      +'<div class="pp-sub">'+L.sub+'</div>'
      +'<div class="pp-grid">'+card("basic","🥉",false)+card("pro","🥈",true)+card("elite","👑",false)+'</div>'
      +'<div class="pp-foot">'+L.foot+'</div></div>';
  }
  window.chOpenPlans=function(){ render(); try{ gP("plans"); }catch(e){} var el=document.getElementById("pnl-plans"); if(el) el.classList.add("on"); };
  /* sol menu girisi */
  function addNav(){
    try{
      var sb=document.getElementById("sb"); if(!sb) return;
      if(document.getElementById("nav-plans")) return;
      var a=document.createElement("div"); a.id="nav-plans"; a.className="sn"; a.setAttribute("data-noi18n","1");
      a.style.cssText="cursor:pointer";
      a.innerHTML='<span style="font-size:16px">💎</span><span class="sn-t" style="margin-left:8px">'+t().cta+'</span>';
      a.addEventListener("click",function(){ window.chOpenPlans(); });
      sb.appendChild(a);
    }catch(e){}
  }
  /* karsilama CTA */
  function addCTA(){
    try{
      var host=document.getElementById("wlc"); if(!host) return;
      if(document.getElementById("ch-plans-cta")) { host.appendChild(document.getElementById("ch-plans-cta")); return; }
      var L=t();
      var c=document.createElement("div"); c.id="ch-plans-cta"; c.setAttribute("data-noi18n","1");
      c.innerHTML='<div><div class="t">💎 '+L.cta+'</div><div class="s">'+L.ctas+'</div></div><div class="go">›</div>';
      c.addEventListener("click",function(){ window.chOpenPlans(); });
      host.appendChild(c);
    }catch(e){}
  }
  function tick(){ addNav(); addCTA(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick, 1500);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ #pnl-plans premium plan sayfasi + sol menu girisi + karsilama CTA eklendi\n";
echo "  ✓ 6 dil (de/en/tr/es/fr/it, fb en); sec dugmeleri mevcut stripeCheckout akisini cagirir\n";

$tmp = tempnam(sys_get_temp_dir(),'pp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-planpage-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_PLAN_PAGE uygulandi. Sol menuden 💎 veya karsilamadaki CTA ile acilir.\n";
