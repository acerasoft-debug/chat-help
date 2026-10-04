<?php
/**
 * ChatHelp — apply-doc-studio (CH_DOC_DEV) — F2-7: "Dokument erstellen & verbessern"
 *  Yeni premium modul (panel #pnl-dev):
 *   • OLUSTUR: konuyu anlat -> kisa, resmi belge (Claude uretir — CH_AIROUTE)
 *   • IYILESTIR: mevcut belge metnini yapistir -> duzeltilmis/guclendirilmis hali
 *   • FOTO: mevcut sohbet atac akisina yonlendirir (foto->analiz zaten calisiyor)
 *   • Sonuc kutusu: PDF (kurumsal makePDF) + Kopyala
 *   • PREMIUM kapisi: plan 'free' ise panelde plan-sayfasi CTA'si gosterilir
 *  Ana sayfada giris karti + sol menu ogesi. 6 dil. Tamamen EK.
 * KULLANIM: pull2.php?key=...&files=apply-doc-studio.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-doc-studio BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DOC_DEV')!==false) exit("Zaten ekli (CH_DOC_DEV).\n");

$bundle = <<<'HTML'
<style id="ch-docdev-css">
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
#pnl-dev .dv-res{margin-top:16px;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:#fff;color:#16161d;padding:16px;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word;display:none;font-family:'Noto Serif',Georgia,serif}
#pnl-dev .dv-acts{display:none;gap:10px;margin-top:10px}
#pnl-dev .dv-act{flex:1;padding:10px;border-radius:11px;border:1px solid rgba(255,255,255,.14);background:var(--s2,#171a24);color:var(--t2,#e8e8f0);font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit}
#pnl-dev .dv-lock{border:1.5px solid rgba(212,168,74,.5);border-radius:16px;padding:22px;text-align:center;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.03))}
#pnl-dev .dv-lock .lt{font-size:15px;font-weight:800;color:var(--gold,#e9c46a)}
#pnl-dev .dv-lock .ls{font-size:12.5px;color:var(--t4,#9a9aae);margin:6px 0 14px}
#pnl-dev .dv-lock button{padding:11px 22px;border-radius:12px;border:0;font-weight:800;font-size:13px;cursor:pointer;font-family:inherit;background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410}
#ch-dev-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:10px 0 4px;padding:14px 16px;border:1px solid rgba(96,148,255,.4);border-radius:16px;background:linear-gradient(135deg,rgba(96,148,255,.1),rgba(96,148,255,.03));cursor:pointer}
#ch-dev-cta .t{font-size:13.5px;font-weight:800;color:#8ab0ff}
#ch-dev-cta .s{font-size:11.5px;color:var(--t4,#9a9aae);margin-top:2px}
#ch-dev-cta .go{color:#8ab0ff;font-size:18px}
</style>
<script id="ch-docdev-js">
/* CH_DOC_DEV — Dokument erstellen & verbessern (F2-7) */
try{(function(){
  function L(){ try{ return (window.chUIL?window.chUIL():null)||localStorage.getItem("ch_uilang")||(window.UL||"de"); }catch(e){ return "de"; } }
  function myPlan(){ try{ var p=JSON.parse(localStorage.getItem("ch_profile")||"{}"); return p.plan||""; }catch(e){ return ""; } }
  var T={
   de:{name:"Dokument erstellen & verbessern",sub:"Beschreiben Sie Ihr Anliegen oder fügen Sie ein Dokument ein",t1:"✍️ Erstellen",t2:"🛠 Verbessern",
       ph1:"Beschreiben Sie kurz, worum es geht (Empfänger, Fakten, Ziel) — Sie erhalten ein kurzes, formelles Dokument…",
       ph2:"Fügen Sie hier Ihr vorhandenes Dokument ein — es wird sprachlich und rechtlich verbessert…",
       go1:"Dokument erstellen",go2:"Dokument verbessern",busy:"Wird erstellt…",foto:"📷 Brief/Bescheid fotografieren? Nutzen Sie",fotoA:"den Chat mit Anhang",
       pdf:"Als PDF",copy:"Kopieren",copied:"Kopiert ✓",err:"Fehler — bitte erneut versuchen.",
       lockT:"Premium-Funktion",lockS:"Dokument erstellen & verbessern ist ab dem Basic-Plan verfügbar.",lockB:"Pläne ansehen",
       i1:"Erstelle aus der folgenden Beschreibung ein kurzes, formelles, versandfertiges Dokument (Brief/Schreiben) in der Sprache der Beschreibung. Nur das Dokument ausgeben, ohne Erklärungen:\n\n",
       i2:"Verbessere das folgende Dokument sprachlich, strukturell und rechtlich (Form, Ton, Klarheit). Gib NUR die verbesserte Fassung aus, in derselben Sprache:\n\n"},
   en:{name:"Create & improve document",sub:"Describe your matter or paste a document",t1:"✍️ Create",t2:"🛠 Improve",
       ph1:"Briefly describe the matter (recipient, facts, goal) — you'll get a short formal document…",
       ph2:"Paste your existing document here — it will be improved in language and legal form…",
       go1:"Create document",go2:"Improve document",busy:"Working…",foto:"📷 Photograph a letter? Use",fotoA:"the chat with attachment",
       pdf:"As PDF",copy:"Copy",copied:"Copied ✓",err:"Error — please try again.",
       lockT:"Premium feature",lockS:"Create & improve is available from the Basic plan.",lockB:"View plans",
       i1:"From the following description, create a short, formal, ready-to-send document in the language of the description. Output only the document, no explanations:\n\n",
       i2:"Improve the following document linguistically, structurally and legally (form, tone, clarity). Output ONLY the improved version, in the same language:\n\n"},
   tr:{name:"Belge oluştur & iyileştir",sub:"Konunuzu anlatın veya mevcut belgenizi yapıştırın",t1:"✍️ Oluştur",t2:"🛠 İyileştir",
       ph1:"Konuyu kısaca anlatın (muhatap, olaylar, amaç) — kısa ve resmi bir belge alırsınız…",
       ph2:"Mevcut belgenizi buraya yapıştırın — dil ve hukuki biçim yönünden iyileştirilir…",
       go1:"Belge oluştur",go2:"Belgeyi iyileştir",busy:"Hazırlanıyor…",foto:"📷 Mektup/karar fotoğrafı mı? Kullanın:",fotoA:"ekli sohbet",
       pdf:"PDF olarak",copy:"Kopyala",copied:"Kopyalandı ✓",err:"Hata — lütfen tekrar deneyin.",
       lockT:"Premium özellik",lockS:"Belge oluştur & iyileştir, Basic plandan itibaren kullanılabilir.",lockB:"Planları gör",
       i1:"Aşağıdaki anlatımdan, anlatımın dilinde kısa, resmi, gönderime hazır bir belge (dilekçe/mektup) oluştur. Sadece belgeyi yaz, açıklama ekleme:\n\n",
       i2:"Aşağıdaki belgeyi dil, yapı ve hukuki biçim yönünden iyileştir (üslup, netlik). SADECE iyileştirilmiş halini, aynı dilde yaz:\n\n"},
   es:{name:"Crear y mejorar documento",sub:"Describa su asunto o pegue un documento",t1:"✍️ Crear",t2:"🛠 Mejorar",
       ph1:"Describa brevemente el asunto (destinatario, hechos, objetivo): recibirá un documento formal breve…",
       ph2:"Pegue aquí su documento: se mejorará en lenguaje y forma jurídica…",
       go1:"Crear documento",go2:"Mejorar documento",busy:"Creando…",foto:"📷 ¿Fotografiar una carta? Use",fotoA:"el chat con adjunto",
       pdf:"Como PDF",copy:"Copiar",copied:"Copiado ✓",err:"Error — inténtelo de nuevo.",
       lockT:"Función premium",lockS:"Crear y mejorar está disponible desde el plan Basic.",lockB:"Ver planes",
       i1:"A partir de la siguiente descripción, crea un documento breve, formal y listo para enviar, en el idioma de la descripción. Escribe solo el documento, sin explicaciones:\n\n",
       i2:"Mejora el siguiente documento en lenguaje, estructura y forma jurídica (tono, claridad). Escribe SOLO la versión mejorada, en el mismo idioma:\n\n"},
   fr:{name:"Créer & améliorer un document",sub:"Décrivez votre affaire ou collez un document",t1:"✍️ Créer",t2:"🛠 Améliorer",
       ph1:"Décrivez brièvement l'affaire (destinataire, faits, objectif) — vous recevrez un document formel court…",
       ph2:"Collez ici votre document — il sera amélioré sur le plan linguistique et juridique…",
       go1:"Créer le document",go2:"Améliorer le document",busy:"En cours…",foto:"📷 Photographier un courrier ? Utilisez",fotoA:"le chat avec pièce jointe",
       pdf:"En PDF",copy:"Copier",copied:"Copié ✓",err:"Erreur — veuillez réessayer.",
       lockT:"Fonction premium",lockS:"Créer & améliorer est disponible à partir du plan Basic.",lockB:"Voir les offres",
       i1:"À partir de la description suivante, crée un document court, formel et prêt à envoyer, dans la langue de la description. N'écris que le document, sans explications :\n\n",
       i2:"Améliore le document suivant sur le plan linguistique, structurel et juridique (ton, clarté). N'écris QUE la version améliorée, dans la même langue :\n\n"},
   it:{name:"Creare e migliorare documento",sub:"Descrivi la questione o incolla un documento",t1:"✍️ Creare",t2:"🛠 Migliorare",
       ph1:"Descrivi brevemente la questione (destinatario, fatti, obiettivo): riceverai un documento formale breve…",
       ph2:"Incolla qui il tuo documento: sarà migliorato nella lingua e nella forma giuridica…",
       go1:"Crea documento",go2:"Migliora documento",busy:"In corso…",foto:"📷 Fotografare una lettera? Usa",fotoA:"la chat con allegato",
       pdf:"Come PDF",copy:"Copia",copied:"Copiato ✓",err:"Errore — riprova.",
       lockT:"Funzione premium",lockS:"Creare e migliorare è disponibile dal piano Basic.",lockB:"Vedi i piani",
       i1:"Dalla seguente descrizione crea un documento breve, formale e pronto per l'invio, nella lingua della descrizione. Scrivi solo il documento, senza spiegazioni:\n\n",
       i2:"Migliora il seguente documento dal punto di vista linguistico, strutturale e giuridico (tono, chiarezza). Scrivi SOLO la versione migliorata, nella stessa lingua:\n\n"}
  };
  function t(){ return T[L()]||T.en; }
  var mode="create", lastDoc="";
  function panel(){
    var p=document.getElementById("pnl-dev");
    if(!p){ p=document.createElement("div"); p.id="pnl-dev"; p.className="pnl"; p.setAttribute("data-noi18n","1"); document.body.appendChild(p); }
    return p;
  }
  function strip(s){ return String(s||"").replace(/\[\[(DOC|SUGGEST:[a-z]+)\]\]/gi,"").trim(); }
  function render(){
    var Lx=t(), p=panel();
    if(!myPlan()){
      p.innerHTML='<div class="dv-wrap"><div class="dv-top"><button class="dv-back" onclick="try{gP(\'chat\')}catch(e){}">‹</button><div class="dv-h">🛠 '+Lx.name+'</div></div>'
        +'<div class="dv-lock"><div class="lt">💎 '+Lx.lockT+'</div><div class="ls">'+Lx.lockS+'</div>'
        +'<button onclick="try{window.chOpenPlans()}catch(e){}">'+Lx.lockB+'</button></div></div>';
      return;
    }
    p.innerHTML='<div class="dv-wrap">'
      +'<div class="dv-top"><button class="dv-back" onclick="try{gP(\'chat\')}catch(e){}">‹</button><div class="dv-h">🛠 '+Lx.name+'</div></div>'
      +'<div class="dv-sub">'+Lx.sub+'</div>'
      +'<div class="dv-tabs"><button class="dv-tab'+(mode==="create"?" on":"")+'" data-m="create">'+Lx.t1+'</button>'
      +'<button class="dv-tab'+(mode==="improve"?" on":"")+'" data-m="improve">'+Lx.t2+'</button></div>'
      +'<textarea id="dv-in" placeholder="'+(mode==="create"?Lx.ph1:Lx.ph2)+'"></textarea>'
      +'<button class="dv-go" id="dv-go">'+(mode==="create"?Lx.go1:Lx.go2)+'</button>'
      +'<div class="dv-foto">'+Lx.foto+' <a id="dv-foto">'+Lx.fotoA+'</a></div>'
      +'<div class="dv-res" id="dv-res"></div>'
      +'<div class="dv-acts" id="dv-acts"><button class="dv-act" id="dv-pdf">📄 '+Lx.pdf+'</button><button class="dv-act" id="dv-copy">📋 '+Lx.copy+'</button></div>'
      +'</div>';
    p.querySelectorAll(".dv-tab").forEach(function(b){ b.addEventListener("click",function(){ mode=b.getAttribute("data-m"); render(); }); });
    var foto=p.querySelector("#dv-foto"); if(foto) foto.addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    var go=p.querySelector("#dv-go");
    go.addEventListener("click",async function(){
      var v=(p.querySelector("#dv-in").value||"").trim(); if(!v) return;
      var Lx2=t();
      go.disabled=true; go.textContent=Lx2.busy;
      try{
        var r=await fetch("api.php?action=aichat",{method:"POST",headers:{"Content-Type":"application/json"},
          body:JSON.stringify({message:(mode==="create"?Lx2.i1:Lx2.i2)+v,history:[],provider:"claude",profile:{},lang:L(),country:(typeof CC!=="undefined"?CC:"DE")})});
        var d=await r.json();
        lastDoc=strip(d&&d.reply);
        var res=p.querySelector("#dv-res"), acts=p.querySelector("#dv-acts");
        if(lastDoc){ res.textContent=lastDoc; res.style.display="block"; acts.style.display="flex";
          var pdf=p.querySelector("#dv-pdf"); pdf.onclick=function(){ try{ makePDF(lastDoc, Lx2.name, (typeof CC!=="undefined"?CC:"DE")); }catch(e){} };
          var cp=p.querySelector("#dv-copy"); cp.onclick=function(){ try{ navigator.clipboard.writeText(lastDoc); cp.textContent="📋 "+Lx2.copied; }catch(e){} };
        } else { res.textContent=Lx2.err; res.style.display="block"; }
      }catch(e){ var res2=p.querySelector("#dv-res"); res2.textContent=t().err; res2.style.display="block"; }
      go.disabled=false; go.textContent=(mode==="create"?t().go1:t().go2);
    });
  }
  window.chOpenDev=function(){ render(); try{ gP("dev"); }catch(e){} var el=document.getElementById("pnl-dev"); if(el) el.classList.add("on"); };
  function addNav(){
    try{
      var sb=document.getElementById("sb"); if(!sb) return;
      if(document.getElementById("nav-dev")) return;
      var a=document.createElement("div"); a.id="nav-dev"; a.className="sn"; a.setAttribute("data-noi18n","1");
      a.style.cssText="cursor:pointer";
      a.innerHTML='<span style="font-size:16px">🛠</span><span class="sn-t" style="margin-left:8px">'+t().name+'</span>';
      a.addEventListener("click",function(){ window.chOpenDev(); });
      sb.appendChild(a);
    }catch(e){}
  }
  function addCTA(){
    try{
      var host=document.getElementById("wlc"); if(!host) return;
      if(document.getElementById("ch-dev-cta")){ return; }
      var Lx=t();
      var c=document.createElement("div"); c.id="ch-dev-cta"; c.setAttribute("data-noi18n","1");
      c.innerHTML='<div><div class="t">🛠 '+Lx.name+'</div><div class="s">'+Lx.sub+'</div></div><div class="go">›</div>';
      c.addEventListener("click",function(){ window.chOpenDev(); });
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
echo "  ✓ Dokument erstellen & verbessern paneli + ana sayfa girisi + sol menu eklendi\n";
echo "  ✓ Olustur/Iyilestir sekmeleri; uretim Claude'la; PDF+Kopyala; premium kapisi\n";

$tmp = tempnam(sys_get_temp_dir(),'dd').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-docdev-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_DOC_DEV uygulandi. Ana sayfadaki 🛠 kart veya sol menuden acilir;\n";
echo "   ucretsiz kullanicilar plan sayfasina yonlendirilir (Basic'ten itibaren acik).\n";
