<?php
/**
 * ChatHelp — IS BULMA & BASVURU PANELI (frontend)
 * ------------------------------------------------------------------------
 *  Menu girisi + panel (#pnl-jobs, gP uyumlu): pozisyon+bolge+mektup dili gir
 *  -> jobsearch (anahtarsiz: AI firma onerisi / anahtarli: Jooble) -> ilan
 *  kartlari -> her ilan icin 'Basvuru mektubu hazirla' (coverletter, giris+paket
 *  gerekli) -> mektup modali (kopyala) + ilan linki. AI onerileri 'dogrula' etiketli.
 *
 * KULLANIM: pull-updates.php?files=apply-jobsearch-ui.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-jobsearch-ui BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_JOBS_UI")!==false) exit("Zaten ekli (CH_JOBS_UI).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-jobsui-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_JOBS_UI — Is Bulma & Basvuru paneli */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var T={
    en:{ttl:"Find a job & apply",note:"The system suggests real employers in your area. Suggestions — verify before applying.",kw:"Role / field (e.g. software engineer)",loc:"City / region (e.g. Berlin)",cv:"Your CV / background (optional) — improves the letter",lang:"Letter language",search:"Find jobs",searching:"Searching…",none:"No results — try other keywords.",apply:"Prepare application letter",open:"Open listing",need:"Please sign in and choose a plan to generate letters.",gen:"Writing your letter…",fail:"Could not generate. Try again.",copy:"Copy",close:"Close",lettl:"Application letter"},
    tr:{ttl:"İş Bul & Başvur",note:"Sistem bölgenizdeki gerçek firmaları önerir. Öneriler — başvurmadan doğrulayın.",kw:"Pozisyon / alan (örn. yazılım mühendisi)",loc:"Şehir / bölge (örn. Berlin)",cv:"CV / özgeçmiş metniniz (opsiyonel) — mektubu güçlendirir",lang:"Mektup dili",search:"İş bul",searching:"Aranıyor…",none:"Sonuç yok — başka kelime deneyin.",apply:"Başvuru mektubu hazırla",open:"İlanı aç",need:"Mektup üretmek için giriş yapıp bir paket seçin.",gen:"Mektubunuz yazılıyor…",fail:"Üretilemedi. Tekrar deneyin.",copy:"Kopyala",close:"Kapat",lettl:"Başvuru mektubu"},
    de:{ttl:"Job finden & bewerben",note:"Das System schlägt echte Arbeitgeber in Ihrer Region vor. Vorschläge — vor der Bewerbung prüfen.",kw:"Position / Bereich (z. B. Softwareentwickler)",loc:"Stadt / Region (z. B. Berlin)",cv:"Ihr Lebenslauf (optional) — verbessert das Anschreiben",lang:"Sprache des Anschreibens",search:"Jobs finden",searching:"Suche läuft…",none:"Keine Treffer — andere Stichwörter versuchen.",apply:"Anschreiben erstellen",open:"Anzeige öffnen",need:"Bitte anmelden und einen Tarif wählen, um Anschreiben zu erstellen.",gen:"Ihr Anschreiben wird erstellt…",fail:"Fehlgeschlagen. Bitte erneut versuchen.",copy:"Kopieren",close:"Schließen",lettl:"Anschreiben"},
    fr:{ttl:"Trouver un emploi",note:"Le système propose de vrais employeurs de votre région. Suggestions — à vérifier avant de postuler.",kw:"Poste / domaine (ex. développeur)",loc:"Ville / région (ex. Berlin)",cv:"Votre CV (facultatif) — améliore la lettre",lang:"Langue de la lettre",search:"Rechercher",searching:"Recherche…",none:"Aucun résultat.",apply:"Rédiger la lettre de motivation",open:"Voir l'annonce",need:"Connectez-vous et choisissez un forfait pour générer des lettres.",gen:"Rédaction de votre lettre…",fail:"Échec. Réessayez.",copy:"Copier",close:"Fermer",lettl:"Lettre de motivation"},
    es:{ttl:"Buscar empleo",note:"El sistema sugiere empresas reales de su zona. Sugerencias — verifique antes de postular.",kw:"Puesto / área (p. ej. desarrollador)",loc:"Ciudad / región (p. ej. Berlín)",cv:"Su CV (opcional) — mejora la carta",lang:"Idioma de la carta",search:"Buscar",searching:"Buscando…",none:"Sin resultados.",apply:"Redactar carta de presentación",open:"Ver oferta",need:"Inicie sesión y elija un plan para generar cartas.",gen:"Redactando su carta…",fail:"No se pudo generar. Reintente.",copy:"Copiar",close:"Cerrar",lettl:"Carta de presentación"},
    it:{ttl:"Trova lavoro",note:"Il sistema suggerisce datori di lavoro reali nella tua zona. Suggerimenti — verifica prima di candidarti.",kw:"Ruolo / settore (es. sviluppatore)",loc:"Città / regione (es. Berlino)",cv:"Il tuo CV (facoltativo) — migliora la lettera",lang:"Lingua della lettera",search:"Cerca",searching:"Ricerca…",none:"Nessun risultato.",apply:"Scrivi lettera di presentazione",open:"Apri annuncio",need:"Accedi e scegli un piano per generare lettere.",gen:"Scrittura della lettera…",fail:"Non riuscito. Riprova.",copy:"Copia",close:"Chiudi",lettl:"Lettera di presentazione"}
  };
  function L(){ return T[UIL()]||T.en; }
  var LANGS=[["de","Deutsch"],["en","English"],["tr","Türkçe"],["fr","Français"],["es","Español"],["it","Italiano"],["nl","Nederlands"],["pl","Polski"],["sv","Svenska"],["pt","Português"]];

  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }

  function addCss(){
    if(document.getElementById("ch-jobs-css")) return;
    var st=document.createElement("style"); st.id="ch-jobs-css";
    st.textContent=[
      "#pnl-jobs{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}",
      "#pnl-jobs.on{display:flex}",
      ".jb-topbar{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1));background:var(--s1,#1d1d24);flex-shrink:0}",
      ".jb-back{width:34px;height:34px;border-radius:10px;border:1px solid var(--bdr,rgba(255,255,255,.12));background:rgba(255,255,255,.05);color:var(--t2,#e0e0ff);font-size:20px;cursor:pointer}",
      ".jb-ttl{font-size:16px;font-weight:800;color:var(--t1,#fff)}",
      ".jb-scroll{flex:1;overflow-y:auto;padding:16px;max-width:720px;width:100%;margin:0 auto}",
      ".jb-note{font-size:12px;color:var(--t3,#a8a8d0);background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.2);border-radius:10px;padding:9px 11px;margin-bottom:12px;line-height:1.5}",
      ".jb-in{width:100%;padding:12px 13px;margin-bottom:9px;background:var(--s2,#25252e);border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:11px;color:var(--t1,#fff);font-size:14px;font-family:inherit}",
      ".jb-in:focus{outline:none;border-color:rgba(212,168,74,.5)}",
      ".jb-btn{width:100%;padding:13px;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;margin-bottom:14px}",
      ".jb-btn:disabled{opacity:.6}",
      ".jb-card{background:linear-gradient(135deg,var(--s2,#25252e),var(--s1,#1d1d24));border:1px solid rgba(212,168,74,.16);border-radius:14px;padding:13px 14px;margin-bottom:10px}",
      ".jb-title{font-size:15px;font-weight:700;color:var(--t1,#fff)}",
      ".jb-co{font-size:12.5px;color:var(--gold,#d4a84a);margin-top:1px}",
      ".jb-loc{font-size:12px;color:var(--t3,#a8a8d0);margin:5px 0}",
      ".jb-snip{font-size:12.5px;color:var(--t2,#e0e0ff);line-height:1.5;margin-bottom:9px}",
      ".jb-badge{display:inline-block;font-size:9px;font-weight:700;color:var(--gold,#d4a84a);border:1px solid rgba(212,168,74,.4);border-radius:100px;padding:1px 7px;margin-left:6px;vertical-align:middle}",
      ".jb-acts{display:flex;gap:8px;flex-wrap:wrap}",
      ".jb-apply{flex:1;min-width:150px;padding:9px 12px;background:rgba(212,168,74,.12);border:1px solid rgba(212,168,74,.35);color:var(--gold,#d4a84a);border-radius:10px;font-size:13px;font-weight:700;cursor:pointer}",
      ".jb-link{padding:9px 12px;background:rgba(255,255,255,.05);border:1px solid var(--bdr,rgba(255,255,255,.12));color:var(--t2,#e0e0ff);border-radius:10px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center}",
      ".jb-modal{position:fixed;inset:0;z-index:800;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px}",
      ".jb-sheet{background:var(--s1,#1d1d24);border:1px solid rgba(212,168,74,.2);border-radius:16px;max-width:640px;width:100%;max-height:85vh;display:flex;flex-direction:column;overflow:hidden}",
      ".jb-sheet-h{display:flex;align-items:center;justify-content:space-between;padding:13px 15px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1))}",
      ".jb-sheet-b{padding:15px;overflow-y:auto;white-space:pre-wrap;font-size:13.5px;line-height:1.6;color:var(--t1,#fff);font-family:inherit}",
      ".jb-sheet-f{padding:11px 15px;border-top:1px solid var(--bdr,rgba(255,255,255,.1));display:flex;gap:9px;justify-content:flex-end}",
      ".jb-mbtn{padding:9px 15px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid var(--bdr,rgba(255,255,255,.14));background:rgba(255,255,255,.05);color:var(--t1,#fff)}",
      ".jb-mbtn.p{background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;border:none}"
    ].join("\n");
    document.head.appendChild(st);
  }

  function buildPanel(){
    if(document.getElementById("pnl-jobs")) return;
    var l=L();
    var p=document.createElement("div"); p.className="pnl"; p.id="pnl-jobs";
    var opts=LANGS.map(function(x){return '<option value="'+x[0]+'">'+x[1]+'</option>';}).join("");
    p.innerHTML=
      '<div class="jb-topbar"><button class="jb-back" onclick="gP(\'chat\')">‹</button><div class="jb-ttl" id="jb-ttl"></div></div>'+
      '<div class="jb-scroll">'+
        '<div class="jb-note" id="jb-note"></div>'+
        '<input id="jb-kw" class="jb-in"><input id="jb-loc" class="jb-in">'+
        '<select id="jb-lang" class="jb-in">'+opts+'</select>'+
        '<textarea id="jb-cv" class="jb-in" rows="3"></textarea>'+
        '<button id="jb-search" class="jb-btn"></button>'+
        '<div id="jb-results"></div>'+
      '</div>';
    document.body.appendChild(p);
    refreshLabels();
    document.getElementById("jb-search").addEventListener("click", doSearch);
    // varsayilan mektup dili: arayuz dili
    try{ document.getElementById("jb-lang").value=UIL(); }catch(e){}
  }
  function refreshLabels(){
    var l=L();
    var q=function(id){return document.getElementById(id);};
    if(q("jb-ttl"))q("jb-ttl").textContent=l.ttl;
    if(q("jb-note"))q("jb-note").textContent=l.note;
    if(q("jb-kw"))q("jb-kw").placeholder=l.kw;
    if(q("jb-loc"))q("jb-loc").placeholder=l.loc;
    if(q("jb-cv"))q("jb-cv").placeholder=l.cv;
    if(q("jb-search"))q("jb-search").textContent=l.search;
  }

  var _lastJobs=[];
  async function doSearch(){
    var l=L(); var btn=document.getElementById("jb-search"); var box=document.getElementById("jb-results");
    var kw=(document.getElementById("jb-kw").value||"").trim();
    var loc=(document.getElementById("jb-loc").value||"").trim();
    if(!kw){ document.getElementById("jb-kw").focus(); return; }
    btn.disabled=true; var _t=btn.textContent; btn.textContent=l.searching; box.innerHTML="";
    try{
      var r=await fetch(API+"?action=jobsearch",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({keywords:kw,location:loc,lang:UIL()})});
      var d=await r.json(); var jobs=(d&&d.jobs)||[]; _lastJobs=jobs;
      if(!jobs.length){ box.innerHTML='<div class="jb-note">'+esc(l.none)+'</div>'; }
      else{
        box.innerHTML=jobs.map(function(j,i){
          var badge=j.source==="ai"?'<span class="jb-badge">AI</span>':'';
          var co=(j.company&&j.company!==j.title)?'<div class="jb-co">'+esc(j.company)+'</div>':'';
          var loc2=[j.location,j.salary].filter(Boolean).map(esc).join(" · ");
          var link=j.link?'<a class="jb-link" href="'+esc(j.link)+'" target="_blank" rel="noopener">🔗 '+esc(l.open)+'</a>':'';
          return '<div class="jb-card"><div class="jb-title">'+esc(j.title||j.company)+badge+'</div>'+co+
            (loc2?'<div class="jb-loc">📍 '+loc2+'</div>':'')+
            (j.snippet?'<div class="jb-snip">'+esc(j.snippet)+'</div>':'')+
            '<div class="jb-acts"><button class="jb-apply" data-i="'+i+'">✍️ '+esc(l.apply)+'</button>'+link+'</div></div>';
        }).join("");
        box.querySelectorAll(".jb-apply").forEach(function(b){ b.addEventListener("click",function(){ genLetter(_lastJobs[+b.getAttribute("data-i")]); }); });
      }
    }catch(e){ box.innerHTML='<div class="jb-note">'+esc(l.fail)+'</div>'; }
    btn.disabled=false; btn.textContent=_t;
  }

  function modal(bodyHtml, footHtml){
    var m=document.getElementById("jb-modal"); if(m) m.remove();
    m=document.createElement("div"); m.className="jb-modal"; m.id="jb-modal";
    m.innerHTML='<div class="jb-sheet"><div class="jb-sheet-h"><b>'+esc(L().lettl)+'</b><button class="jb-mbtn" id="jb-x">'+esc(L().close)+'</button></div>'+
      '<div class="jb-sheet-b" id="jb-letter">'+bodyHtml+'</div><div class="jb-sheet-f">'+(footHtml||"")+'</div></div>';
    m.addEventListener("click",function(e){ if(e.target===m) m.remove(); });
    document.body.appendChild(m);
    var x=document.getElementById("jb-x"); if(x) x.addEventListener("click",function(){ m.remove(); });
    return m;
  }

  async function genLetter(job){
    if(!job) return; var l=L();
    var tk=null,pl="free";
    try{ tk=localStorage.getItem("ch_token"); }catch(e){}
    try{ pl=(window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||"free"; }catch(e){}
    if(!tk||pl==="free"){ alert(l.need); try{ gP("konto"); }catch(e){} return; }
    modal('<div style="color:var(--t3)">'+esc(l.gen)+'</div>');
    try{
      var lang=(document.getElementById("jb-lang")&&document.getElementById("jb-lang").value)||UIL();
      var cv=(document.getElementById("jb-cv")&&document.getElementById("jb-cv").value)||"";
      var r=await fetch(API+"?action=coverletter",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({job:job,profile:(window.P||{}),lang:lang,cv:cv})});
      var d=await r.json();
      if(d&&d.letter){
        var foot='<button class="jb-mbtn" id="jb-copy">📋 '+esc(l.copy)+'</button>';
        var m=modal(esc(d.letter),foot);
        var cp=document.getElementById("jb-copy");
        if(cp) cp.addEventListener("click",function(){ try{ navigator.clipboard.writeText(d.letter); cp.textContent="✓"; }catch(e){} });
      } else { modal('<div style="color:var(--t3)">'+esc(l.fail)+'</div>'); }
    }catch(e){ modal('<div style="color:var(--t3)">'+esc(l.fail)+'</div>'); }
  }

  function addNav(){
    if(document.getElementById("nav-jobs")) return;
    var l=L();
    var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-jobs";
    nav.setAttribute("onclick","gP('jobs')");
    nav.style.cssText="cursor:pointer";
    nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>'+
      '<div><div class="sb-photo-t">'+esc(l.ttl)+'</div></div>';
    var ref=document.querySelector(".sb-photo-btn");
    if(ref&&ref.parentNode){ ref.parentNode.insertBefore(nav, ref.nextSibling); }
    else { var sb=document.getElementById("sb"); if(sb) sb.appendChild(nav); }
  }

  function boot(){ addCss(); buildPanel(); addNav(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
  // dil degisince etiketleri tazele
  setInterval(function(){ try{ refreshLabels(); }catch(e){} }, 1500);
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-jbui"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Is Bulma paneli eklendi (CH_JOBS_UI).\n";
echo "  Sol menude 'İş Bul & Başvur' girisi -> panel -> arama -> ilan kartlari -> basvuru mektubu.\n";
echo "SIL: rm apply-jobsearch-ui.php\n";
echo "Test: menuden 'İş Bul & Başvur' -> pozisyon+bolge gir -> 'İş bul' -> firma kartlari (AI etiketli);\n";
echo "      bir karta 'Başvuru mektubu hazırla' -> (giris+paket) -> mektup modali + kopyala.\n";
