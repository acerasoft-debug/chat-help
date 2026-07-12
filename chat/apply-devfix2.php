<?php
/**
 * ChatHelp — apply-devfix2 (CH_DEVFIX2) — kullanici geri bildirimleri (5 madde)
 *  [1] "💎 Premium entdecken" ana sayfa karti KALDIRILDI (#ch-plans-cta gizli +
 *      kendini yeniden-ekleyen dongusu durduruldu). Plan sayfasina Konto'daki
 *      "💎 Pläne & Preise" girisi ve chOpenPlans() yonlendirmeleri ile gidilir.
 *  [2] "Dokument erstellen & verbessern" karti ana sayfada EN USTE alinir
 *      (hero'nun hemen altina).
 *  [3] Sol ust 🧘 dugmesi yine TAM TEMIZLIK yapar: sidebar + tum karsilama
 *      bolumleri gizlenir, SADECE sohbet kalir (kalicilik yok — oturumluk).
 *  [4] Doc-Dev plan tespiti DUZELTILDI: canli uygulamada plan, ust-duzey
 *      `let plan='free'` DEGISKENIdir (fonksiyon degil!) + ch_user.plan.
 *      v2'deki myPlan bu yuzden Elite'i goremiyordu ve para istiyordu.
 *      Paketi olan: belge acik + PDF UCRETSIZ. Paketsiz: belge BULANIK
 *      kilitli onizleme (okunamaz, kopyalanamaz) + "🔓 Freischalten · X,XX €"
 *      butonu mevcut tek-belge Stripe akisina gider.
 *  [5] Belgede kullanicinin profil verileri kullanilir: ad (+varsa telefon,
 *      e-posta, adres) gonderen olarak AI'ye iletilir (P.f1..f5,f7).
 * KULLANIM: pull2.php?key=...&files=apply-devfix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-devfix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DEVFIX2')!==false) exit("Zaten ekli (CH_DEVFIX2).\n");
if (strpos($src,'ch-docdev-js')===false) exit("HATA: docdev katmani yok.\n");
if (strpos($src,'CH_ZEN_CARDSONLY')===false) exit("HATA: zen katmani beklenen durumda degil.\n");

$fail=[];

/* ── [3] ZEN: tam temizlik — sadece sohbet ── */
$z1 = 'body.ch-zen #wlc .wcat-grid,body.ch-zen #wlc .ch-cat-tg,body.ch-zen #wlc .ch-vtr-sec,body.ch-zen #wlc #ch-vst-home{display:none !important} /*CH_ZEN_CARDSONLY — yalniz kartlar/vitrin; sidebar+hero+ornekler+chat kalir*/';
$n1 = 'body.ch-zen #sb,body.ch-zen #wlc .wlc-hero,body.ch-zen #wlc .wcat-grid,body.ch-zen #wlc .ch-cat-tg,body.ch-zen #wlc .ch-vtr-sec,body.ch-zen #wlc .ch-ex-sec,body.ch-zen #wlc #ch-vst-home,body.ch-zen #wlc .co-grid,body.ch-zen #wlc [class*="staat"],body.ch-zen #ch-dev-cta,body.ch-zen #ch-plans-cta,body.ch-zen #ch-hub-card,body.ch-zen #ch-favrow{display:none !important} /*CH_ZEN_FULL (CH_ZEN_CARDSONLY yerine) — tertemiz: sadece sohbet kalir*/';
$c=0; $src=str_replace($z1,$n1,$src,$c);
echo ($c===1?"  ✓ [3] zen: tam temizlik — sidebar + tum bolumler gizlenir, sadece sohbet\n":"  ✗ [3] zen css anchor ($c)\n"); if($c!==1)$fail[]=3;

$c=0; $src=str_replace('"Kartlari gizle/goster"','"Sadece sohbet"',$src,$c);
echo ($c>=1?"  ✓ [3b] dugme baligi 'Sadece sohbet' ($c gecis)\n":"  ⚠ [3b] balik metni bulunamadi (onemsiz)\n");

/* ── [1] Premium-entdecken kartinin kendini yeniden-ekleme dongusunu durdur ── */
$p1 = 'if(document.getElementById("ch-plans-cta")) { host.appendChild(document.getElementById("ch-plans-cta")); return; }';
$c=0; $src=str_replace($p1,'if(document.getElementById("ch-plans-cta")) { return; } /*CH_DEVFIX2 — kart gizli, tasima yok*/',$src,$c);
echo ($c===1?"  ✓ [1] plans-cta yeniden-ekleme dongusu durduruldu\n":"  ⚠ [1] plans-cta dongu anchor ($c) — CSS gizleme yeterli, devam\n");

/* ── [2]+[4]+[5] docdev v3: eski bloklari sok, yeniden enjekte et ── */
$before=strlen($src);
$src=preg_replace('#<style id="ch-docdev-css">.*?</style>\s*#s','',$src,1);
$src=preg_replace('#<script id="ch-docdev-js">.*?</script>\s*#s','',$src,1);
echo "  ✓ eski docdev bloklari sokuldu (".($before-strlen($src))." bayt)\n";

$bundle = <<<'HTML'
<style id="ch-docdev-css">
/* CH_DEVFIX2 v3 */
#pnl-dev{position:fixed;inset:0;z-index:540;background:var(--bg,#0e1016);display:none;overflow-y:auto;-webkit-overflow-scrolling:touch}
#pnl-dev.on{display:block}
#pnl-dev .dv-wrap{max-width:760px;margin:0 auto;padding:18px 16px 48px}
#pnl-dev .dv-top{display:flex;align-items:center;gap:12px;margin:2px 0 14px}
#pnl-dev .dv-back{width:40px;height:40px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:var(--s2,#1b1d27);color:var(--t2,#e8e8f0);font-size:20px;cursor:pointer}
#pnl-dev .dv-h{font-size:19px;font-weight:800;color:var(--t1,#fff)}
#pnl-dev .dv-sub{font-size:12.5px;color:var(--t4,#9a9aae);margin:0 0 16px 52px}
#pnl-dev .dv-tabs{display:flex;gap:8px;margin-bottom:12px}
#pnl-dev .dv-tab{flex:1;padding:11px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:var(--s2,#171a24);color:var(--t3,#bdbdcc);font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;text-align:center}
#pnl-dev .dv-tab.on{border-color:rgba(212,168,74,.6);background:rgba(212,168,74,.12);color:var(--gold,#e9c46a)}
#pnl-dev textarea{width:100%;min-height:150px;border-radius:14px;border:1px solid rgba(255,255,255,.14);background:var(--s1,#14161f);color:var(--t1,#f0f0f6);padding:13px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box}
#pnl-dev .dv-go{width:100%;margin-top:12px;padding:13px;border-radius:13px;border:0;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410}
#pnl-dev .dv-go:disabled{opacity:.55;cursor:wait}
#pnl-dev .dv-foto{margin-top:10px;text-align:center;font-size:12px;color:var(--t4,#9a9aae)}
#pnl-dev .dv-foto a{color:var(--gold,#e9c46a);cursor:pointer;text-decoration:underline}
#pnl-dev .dv-reswrap{position:relative;margin-top:16px;display:none}
#pnl-dev .dv-res{border:1px solid rgba(255,255,255,.12);border-radius:14px;background:#fff;color:#16161d;padding:16px;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word;font-family:'Noto Serif',Georgia,serif}
#pnl-dev .dv-res.dv-locked{filter:blur(7px);user-select:none;-webkit-user-select:none;pointer-events:none;max-height:340px;overflow:hidden}
#pnl-dev .dv-unlock{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(10,12,18,.25);border-radius:14px}
#pnl-dev .dv-unlock button{padding:14px 22px;border-radius:14px;border:0;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410;box-shadow:0 10px 30px rgba(0,0,0,.45)}
#pnl-dev .dv-acts{display:none;gap:10px;margin-top:10px}
#pnl-dev .dv-act{flex:1;padding:10px;border-radius:11px;border:1px solid rgba(255,255,255,.14);background:var(--s2,#171a24);color:var(--t2,#e8e8f0);font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit}
#ch-dev-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:10px 0 12px;padding:14px 16px;border:1px solid rgba(96,148,255,.4);border-radius:16px;background:linear-gradient(135deg,rgba(96,148,255,.1),rgba(96,148,255,.03));cursor:pointer}
#ch-dev-cta .t{font-size:13.5px;font-weight:800;color:#8ab0ff}
#ch-dev-cta .s{font-size:11.5px;color:var(--t4,#9a9aae);margin-top:2px}
#ch-dev-cta .go{color:#8ab0ff;font-size:18px}
/* [1] Premium-entdecken karti kalkti; plan/dev nav zaten gizli */
#ch-plans-cta{display:none !important}
#nav-plans{display:none !important}
#ch-konto-plans{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:12px 14px;padding:13px 15px;border:1px solid rgba(212,168,74,.4);border-radius:14px;background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.03));cursor:pointer}
#ch-konto-plans .t{font-size:13px;font-weight:800;color:var(--gold,#e9c46a)}
#ch-konto-plans .go{color:var(--gold,#e9c46a);font-size:17px}
</style>
<script id="ch-docdev-js">
/* CH_DEVFIX2 — docdev v3: dogru plan tespiti + profil verisi + bulanik kilit + ustte kart */
try{(function(){
  function L(){ try{ return (window.chUIL?window.chUIL():null)||localStorage.getItem("ch_uilang")||(window.UL||"de"); }catch(e){ return "de"; } }
  function myPlan(){
    /* canli kaynak #1: ust-duzey `let plan='free'` DEGISKENI (fonksiyon degil) */
    try{ if(typeof plan==="string" && plan && plan!=="free") return plan; }catch(e){}
    try{ if(typeof plan==="function"){ var pf=plan(); if(pf&&pf!=="free") return pf; } }catch(e){}
    try{ if(window.P&&window.P.plan&&window.P.plan!=="free") return window.P.plan; }catch(e){}
    try{ var u=JSON.parse(localStorage.getItem("ch_user")||"{}"); if(u.plan&&u.plan!=="free") return u.plan; }catch(e){}
    try{ if(typeof window.chPlan==="function"){ var p1=window.chPlan(); if(p1&&p1!=="free") return p1; } }catch(e){}
    try{ var pr=JSON.parse(localStorage.getItem("ch_profile")||"{}"); if(pr.plan&&pr.plan!=="free") return pr.plan; }catch(e){}
    return "";
  }
  function prof(){
    var o={}; try{ ["f1","f2","f3","f4","f5","f7"].forEach(function(k){ if(typeof P!=="undefined"&&P&&P[k]) o[k]=P[k]; }); }catch(e){}
    return o;
  }
  function profLine(){
    var o=prof(), vals=[]; for(var k in o){ vals.push(o[k]); }
    return vals.length? vals.join(", ") : "";
  }
  var T={
   de:{name:"Dokument erstellen & verbessern",sub:"Beschreiben Sie Ihr Anliegen oder fügen Sie ein Dokument ein",t1:"✍️ Erstellen",t2:"🛠 Verbessern",
       ph1:"Beschreiben Sie kurz, worum es geht (Empfänger, Fakten, Ziel)…",ph2:"Fügen Sie hier Ihr vorhandenes Dokument ein…",
       go1:"Dokument erstellen",go2:"Dokument verbessern",busy:"Wird erstellt…",foto:"📷 Brief fotografieren? Nutzen Sie",fotoA:"den Chat mit Anhang",
       pdf:"Als PDF (im Plan enthalten)",copy:"Kopieren",copied:"Kopiert ✓",err:"Fehler — bitte erneut versuchen.",plans:"💎 Pläne & Preise",
       unlock:"🔓 Freischalten & PDF",
       snd:"Verwende diese Absenderdaten im Dokument (Name; falls vorhanden Adresse, Telefon, E-Mail): ",
       i1:"Erstelle aus der folgenden Beschreibung ein kurzes, formelles, versandfertiges Dokument in der Sprache der Beschreibung. Nur das Dokument ausgeben, ohne Erklärungen:\n\n",
       i2:"Verbessere das folgende Dokument sprachlich, strukturell und rechtlich. Gib NUR die verbesserte Fassung aus, in derselben Sprache:\n\n"},
   en:{name:"Create & improve document",sub:"Describe your matter or paste a document",t1:"✍️ Create",t2:"🛠 Improve",
       ph1:"Briefly describe the matter (recipient, facts, goal)…",ph2:"Paste your existing document here…",
       go1:"Create document",go2:"Improve document",busy:"Working…",foto:"📷 Photograph a letter? Use",fotoA:"the chat with attachment",
       pdf:"As PDF (included in plan)",copy:"Copy",copied:"Copied ✓",err:"Error — please try again.",plans:"💎 Plans & pricing",
       unlock:"🔓 Unlock & PDF",
       snd:"Use this sender data in the document (name; if present address, phone, email): ",
       i1:"From the following description, create a short, formal, ready-to-send document in the language of the description. Output only the document:\n\n",
       i2:"Improve the following document linguistically, structurally and legally. Output ONLY the improved version, same language:\n\n"},
   tr:{name:"Belge oluştur & iyileştir",sub:"Konunuzu anlatın veya mevcut belgenizi yapıştırın",t1:"✍️ Oluştur",t2:"🛠 İyileştir",
       ph1:"Konuyu kısaca anlatın (muhatap, olaylar, amaç)…",ph2:"Mevcut belgenizi buraya yapıştırın…",
       go1:"Belge oluştur",go2:"Belgeyi iyileştir",busy:"Hazırlanıyor…",foto:"📷 Mektup fotoğrafı mı? Kullanın:",fotoA:"ekli sohbet",
       pdf:"PDF olarak (pakete dahil)",copy:"Kopyala",copied:"Kopyalandı ✓",err:"Hata — lütfen tekrar deneyin.",plans:"💎 Planlar & fiyatlar",
       unlock:"🔓 Kilidi aç & PDF",
       snd:"Belgede bu gönderen bilgilerini kullan (ad; varsa adres, telefon, e-posta): ",
       i1:"Aşağıdaki anlatımdan, anlatımın dilinde kısa, resmi, gönderime hazır bir belge oluştur. Sadece belgeyi yaz:\n\n",
       i2:"Aşağıdaki belgeyi dil, yapı ve hukuki biçim yönünden iyileştir. SADECE iyileştirilmiş halini, aynı dilde yaz:\n\n"},
   es:{name:"Crear y mejorar documento",sub:"Describa su asunto o pegue un documento",t1:"✍️ Crear",t2:"🛠 Mejorar",
       ph1:"Describa brevemente el asunto…",ph2:"Pegue aquí su documento…",
       go1:"Crear documento",go2:"Mejorar documento",busy:"Creando…",foto:"📷 ¿Fotografiar una carta? Use",fotoA:"el chat con adjunto",
       pdf:"Como PDF (incluido en el plan)",copy:"Copiar",copied:"Copiado ✓",err:"Error — inténtelo de nuevo.",plans:"💎 Planes y precios",
       unlock:"🔓 Desbloquear & PDF",
       snd:"Usa estos datos del remitente en el documento (nombre; si existen dirección, teléfono, correo): ",
       i1:"A partir de la siguiente descripción, crea un documento breve, formal y listo para enviar, en el idioma de la descripción. Solo el documento:\n\n",
       i2:"Mejora el siguiente documento en lenguaje, estructura y forma jurídica. SOLO la versión mejorada, mismo idioma:\n\n"},
   fr:{name:"Créer & améliorer un document",sub:"Décrivez votre affaire ou collez un document",t1:"✍️ Créer",t2:"🛠 Améliorer",
       ph1:"Décrivez brièvement l'affaire…",ph2:"Collez ici votre document…",
       go1:"Créer le document",go2:"Améliorer le document",busy:"En cours…",foto:"📷 Photographier un courrier ? Utilisez",fotoA:"le chat avec pièce jointe",
       pdf:"En PDF (inclus dans l'offre)",copy:"Copier",copied:"Copié ✓",err:"Erreur — veuillez réessayer.",plans:"💎 Offres & tarifs",
       unlock:"🔓 Débloquer & PDF",
       snd:"Utilise ces coordonnées d'expéditeur dans le document (nom ; le cas échéant adresse, téléphone, e-mail) : ",
       i1:"À partir de la description suivante, crée un document court, formel et prêt à envoyer, dans la langue de la description. Uniquement le document :\n\n",
       i2:"Améliore le document suivant sur le plan linguistique, structurel et juridique. UNIQUEMENT la version améliorée :\n\n"},
   it:{name:"Creare e migliorare documento",sub:"Descrivi la questione o incolla un documento",t1:"✍️ Creare",t2:"🛠 Migliorare",
       ph1:"Descrivi brevemente la questione…",ph2:"Incolla qui il tuo documento…",
       go1:"Crea documento",go2:"Migliora documento",busy:"In corso…",foto:"📷 Fotografare una lettera? Usa",fotoA:"la chat con allegato",
       pdf:"Come PDF (incluso nel piano)",copy:"Copia",copied:"Copiato ✓",err:"Errore — riprova.",plans:"💎 Piani e prezzi",
       unlock:"🔓 Sblocca & PDF",
       snd:"Usa questi dati del mittente nel documento (nome; se presenti indirizzo, telefono, e-mail): ",
       i1:"Dalla seguente descrizione crea un documento breve, formale e pronto per l'invio, nella lingua della descrizione. Solo il documento:\n\n",
       i2:"Migliora il seguente documento (lingua, struttura, forma giuridica). SOLO la versione migliorata:\n\n"}
  };
  function t(){ return T[L()]||T.en; }
  var mode="create", lastDoc="";
  function tier(len){ return len<=2500 ? ["einfach","1,99"] : (len<=6000 ? ["standard","3,99"] : ["komplex","4,99"]); }
  function panel(){
    var p=document.getElementById("pnl-dev");
    if(!p){ p=document.createElement("div"); p.id="pnl-dev"; p.className="pnl"; p.setAttribute("data-noi18n","1"); document.body.appendChild(p); }
    return p;
  }
  function strip(s){ return String(s||"").replace(/\[\[(DOC|SUGGEST:[a-z]+)\]\]/gi,"").trim(); }
  function showDoc(p){
    var Lx=t(), wrap=p.querySelector("#dv-reswrap"), res=p.querySelector("#dv-res"),
        un=p.querySelector("#dv-unlock"), acts=p.querySelector("#dv-acts");
    res.textContent=lastDoc; wrap.style.display="block";
    if(myPlan()){
      /* [4] paketi olan: acik metin + UCRETSIZ PDF + kopyala */
      res.classList.remove("dv-locked"); un.style.display="none"; acts.style.display="flex";
      var pdf=p.querySelector("#dv-pdf"); pdf.textContent="📄 "+Lx.pdf;
      pdf.onclick=function(){ try{ makePDF(lastDoc, t().name, (typeof CC!=="undefined"?CC:"DE")); }catch(e){} };
      var cp=p.querySelector("#dv-copy"); cp.textContent="📋 "+Lx.copy;
      cp.onclick=function(){ try{ navigator.clipboard.writeText(lastDoc); cp.textContent="📋 "+Lx.copied; }catch(e){} };
    } else {
      /* [4] paketsiz: BULANIK kilit — odeme olmadan okunamaz/kopyalanamaz/PDF yok */
      res.classList.add("dv-locked"); acts.style.display="none"; un.style.display="flex";
      var tk=tier(lastDoc.length);
      var ub=p.querySelector("#dv-unlock button");
      ub.textContent=Lx.unlock+" · "+tk[1]+" €";
      ub.onclick=function(){ try{ window.stripeCheckout(null,"devdoc",tk[0]); }catch(e){} };
    }
  }
  function render(){
    var Lx=t(), p=panel();
    p.innerHTML='<div class="dv-wrap">'
      +'<div class="dv-top"><button class="dv-back" onclick="try{gP(\'chat\')}catch(e){}">‹</button><div class="dv-h">🛠 '+Lx.name+'</div></div>'
      +'<div class="dv-sub">'+Lx.sub+'</div>'
      +'<div class="dv-tabs"><button class="dv-tab'+(mode==="create"?" on":"")+'" data-m="create">'+Lx.t1+'</button>'
      +'<button class="dv-tab'+(mode==="improve"?" on":"")+'" data-m="improve">'+Lx.t2+'</button></div>'
      +'<textarea id="dv-in" placeholder="'+(mode==="create"?Lx.ph1:Lx.ph2)+'"></textarea>'
      +'<button class="dv-go" id="dv-go">'+(mode==="create"?Lx.go1:Lx.go2)+'</button>'
      +'<div class="dv-foto">'+Lx.foto+' <a id="dv-foto">'+Lx.fotoA+'</a></div>'
      +'<div class="dv-reswrap" id="dv-reswrap"><div class="dv-res" id="dv-res"></div>'
      +'<div class="dv-unlock" id="dv-unlock" style="display:none"><button type="button"></button></div></div>'
      +'<div class="dv-acts" id="dv-acts"><button class="dv-act" id="dv-pdf"></button><button class="dv-act" id="dv-copy"></button></div>'
      +'</div>';
    p.querySelectorAll(".dv-tab").forEach(function(b){ b.addEventListener("click",function(){ mode=b.getAttribute("data-m"); render(); }); });
    var foto=p.querySelector("#dv-foto"); if(foto) foto.addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    var go=p.querySelector("#dv-go");
    go.addEventListener("click",async function(){
      var v=(p.querySelector("#dv-in").value||"").trim(); if(!v) return;
      var Lx2=t();
      go.disabled=true; go.textContent=Lx2.busy;
      try{
        /* [5] profil verisi: gonderen adi (+varsa telefon/e-posta) belgeye girer */
        var pl=profLine();
        var msg=(mode==="create"?Lx2.i1:Lx2.i2) + (pl? Lx2.snd+pl+"\n\n" : "") + v;
        var r=await fetch("api.php?action=aichat",{method:"POST",headers:{"Content-Type":"application/json"},
          body:JSON.stringify({message:msg,history:[],provider:"claude",profile:prof(),lang:L(),country:(typeof CC!=="undefined"?CC:"DE")})});
        var d=await r.json();
        lastDoc=strip(d&&d.reply);
        if(lastDoc){ showDoc(p); }
        else { var res=p.querySelector("#dv-res"); res.textContent=Lx2.err; p.querySelector("#dv-reswrap").style.display="block"; }
      }catch(e){ var res2=p.querySelector("#dv-res"); res2.textContent=t().err; p.querySelector("#dv-reswrap").style.display="block"; }
      go.disabled=false; go.textContent=(mode==="create"?t().go1:t().go2);
    });
  }
  window.chOpenDev=function(){ render(); try{ gP("dev"); }catch(e){} var el=document.getElementById("pnl-dev"); if(el) el.classList.add("on"); };
  /* [2] kart ana sayfada EN USTE (hero'nun hemen altina) */
  function addCTA(){
    try{
      var host=document.getElementById("wlc"); if(!host) return;
      if(document.getElementById("ch-dev-cta")) return;
      var Lx=t();
      var c=document.createElement("div"); c.id="ch-dev-cta"; c.setAttribute("data-noi18n","1");
      c.innerHTML='<div><div class="t">🛠 '+Lx.name+'</div><div class="s">'+Lx.sub+'</div></div><div class="go">›</div>';
      c.addEventListener("click",function(){ window.chOpenDev(); });
      var hero=host.querySelector(".wlc-hero");
      if(hero&&hero.parentNode===host&&hero.nextSibling) host.insertBefore(c,hero.nextSibling);
      else host.insertBefore(c,host.firstChild);
    }catch(e){}
  }
  /* sol menu temizligi + Konto plan girisi (v2'den korundu) */
  function tidy(){
    try{ var nd=document.getElementById("nav-dev"); if(nd) nd.remove(); }catch(e){}
    try{
      var k=document.getElementById("pnl-konto");
      if(k && !document.getElementById("ch-konto-plans")){
        var Lx=t();
        var b=document.createElement("div"); b.id="ch-konto-plans"; b.setAttribute("data-noi18n","1");
        b.innerHTML='<div class="t">'+Lx.plans+'</div><div class="go">›</div>';
        b.addEventListener("click",function(){ try{ window.chOpenPlans(); }catch(e){} });
        var tb=k.querySelector(".pnl-topbar");
        if(tb && tb.nextSibling) k.insertBefore(b, tb.nextSibling); else k.appendChild(b);
      }
    }catch(e){}
    try{ var pc=document.getElementById("ch-plans-cta"); if(pc) pc.remove(); }catch(e){}
  }
  function tick(){ addCTA(); tidy(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick, 1500);
})();}catch(e){}
</script>
HTML;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [2] Doc-Dev karti ana sayfada EN USTE (hero altinda)\n";
echo "  ✓ [4] plan tespiti duzeltildi (global `plan` degiskeni) — paketlilere PDF UCRETSIZ\n";
echo "  ✓ [4b] paketsizlere bulanik kilitli onizleme + 'Freischalten · X,XX €'\n";
echo "  ✓ [5] profil (ad/telefon/e-posta) belgeye gonderen olarak gecer\n";
echo "  ✓ [1] 'Premium entdecken' karti ana sayfadan kalkti\n";

$tmp = tempnam(sys_get_temp_dir(),'d2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-devfix2-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam ediliyor\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) {
    echo "\n✗ YAZMA HATASI: donen ".var_export($w,true)." / beklenen ".strlen($src)." bayt.\n";
    exit;
}
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DEVFIX2')===false) { echo "\n✗ DOGRULAMA BASARISIZ: yazim sonrasi CH_DEVFIX2 diskte YOK.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DEVFIX2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_DEVFIX2 uygulandi (5 madde).\n";
