<?php
/**
 * ChatHelp — apply-billing-btn (CH_BILLING_PORTAL_UI)
 *  Konto paneline "Zahlungsmethode / Abo verwalten" butonu ekler (EKLEMELI:
 *  </body> oncesi tek <script>, mevcut kod degismez). Buton yalniz GIRIS YAPMIS
 *  + UCRETLI plan (basic/pro/elite) kullanicilarina gorunur; tiklaninca
 *  auth.php?action=billing_portal'i JWT ile cagirip Stripe Customer Portal'a
 *  yonlendirir. Abo yoksa nazik uyari. 7 dil. lint+yedek+dogrulama var.
 * KULLANIM: pull2.php?key=...&files=apply-billing-btn.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-billing-btn BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_BILLING_PORTAL_UI')!==false) exit("Zaten ekli (CH_BILLING_PORTAL_UI).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-billing-portal-ui">
/* CH_BILLING_PORTAL_UI — Konto: Karte/Abo selbst verwalten (Stripe Customer Portal) */
try{(function(){
  var LBL={de:'Zahlungsmethode / Abo verwalten',tr:'Ödeme yöntemi / Abonelik yönet',en:'Manage payment / subscription',fr:'Gérer paiement / abonnement',es:'Gestionar pago / suscripción',it:'Gestisci pagamento / abbonamento',nl:'Betaling / abonnement beheren'};
  var WAIT={de:'Wird geöffnet…',tr:'Açılıyor…',en:'Opening…',fr:'Ouverture…',es:'Abriendo…',it:'Apertura…',nl:'Openen…'};
  var NOCUS={de:'Kein aktives Abonnement gefunden. Bitte zuerst ein Paket buchen.',tr:'Aktif abonelik bulunamadı. Lütfen önce bir paket alın.',en:'No active subscription found. Please purchase a plan first.',fr:"Aucun abonnement actif. Veuillez d'abord acheter un forfait.",es:'No hay suscripción activa. Compre un plan primero.',it:'Nessun abbonamento attivo. Acquista prima un piano.',nl:'Geen actief abonnement gevonden. Koop eerst een pakket.'};
  var ERRM={de:'Fehler beim Öffnen des Portals.',tr:'Portal açılırken hata oluştu.',en:'Error opening portal.',fr:"Erreur lors de l'ouverture du portail.",es:'Error al abrir el portal.',it:"Errore nell'apertura del portale.",nl:'Fout bij openen van portaal.'};
  function UIL(){ try{ var s=localStorage.getItem('ch_uilang'); if(s) return s.slice(0,2); }catch(e){} return 'de'; }
  function L(m){ return m[UIL()]||m.de; }
  function tok(){ try{ return localStorage.getItem('ch_token'); }catch(e){ return null; } }
  function planOf(){ try{ return (window.P&&P.plan)||JSON.parse(localStorage.getItem('ch_user')||'{}').plan||''; }catch(e){ return ''; } }
  function openPortal(btn){
    var t=tok(); if(!t) return;
    var old=btn.textContent; btn.textContent=L(WAIT); btn.disabled=true;
    fetch('auth.php?action=billing_portal',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+t}})
      .then(function(r){ return r.json().then(function(j){ return {s:r.status,j:j}; }); })
      .then(function(o){
        btn.textContent=old; btn.disabled=false;
        if(o.j&&o.j.url){ window.location.href=o.j.url; return; }
        if(o.j&&o.j.error==='no_customer'){ alert(L(NOCUS)); return; }
        alert((o.j&&o.j.message)||L(ERRM));
      })
      .catch(function(){ btn.textContent=old; btn.disabled=false; alert(L(ERRM)); });
  }
  function ensure(){
    try{
      var pnl=document.getElementById('pnl-konto'); if(!pnl) return;
      var ex=document.getElementById('ch-billing-btn');
      if(!tok()){ if(ex) ex.remove(); return; }
      var pl=planOf(); var paid=(pl==='basic'||pl==='pro'||pl==='elite');
      if(!paid){ if(ex) ex.remove(); return; }
      if(ex){ ex.textContent=L(LBL); return; }
      var logout=null, bs=pnl.querySelectorAll('button');
      for(var i=0;i<bs.length;i++){ if(/Abmelden|Logout|Çıkış|Einloggen/i.test(bs[i].textContent||'')){ logout=bs[i]; break; } }
      var b=document.createElement('button');
      b.id='ch-billing-btn'; b.type='button'; b.textContent=L(LBL);
      b.style.cssText='display:block;width:calc(100% - 32px);margin:10px 16px;padding:12px 14px;border:1px solid rgba(212,168,74,.4);border-radius:10px;background:rgba(212,168,74,.1);color:#e8c874;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;-webkit-tap-highlight-color:transparent';
      b.onmouseover=function(){ b.style.background='rgba(212,168,74,.18)'; };
      b.onmouseout=function(){ b.style.background='rgba(212,168,74,.1)'; };
      b.onclick=function(){ openPortal(b); };
      if(logout&&logout.parentNode){ logout.parentNode.insertBefore(b, logout); }
      else { var sc=pnl.querySelector('.pnl-scroll')||pnl; sc.appendChild(b); }
    }catch(e){}
  }
  var n=0; (function loop(){ ensure(); if(n++<150) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

/* lint on temp */
$tmp = tempnam(sys_get_temp_dir(),'bb').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$bk=@file_put_contents($file.'.bak-billingbtn-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_BILLING_PORTAL_UI')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_BILLING_PORTAL_UI diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Konto'da 'Zahlungsmethode / Abo verwalten' butonu aktif (ucretli plan sahipleri).\n";
echo "   Tiklayinca Stripe Customer Portal acilir. Stripe > Settings > Billing >\n";
echo "   Customer portal AKTIF olmali (bir kez).\n";
