<?php
/**
 * ChatHelp — apply-modulepack (CH_MODPACK)
 *  5 yeni premium modul + kullanici-secmeli arayuz:
 *   ⏰ Fristen-Tracker  — sure takibi, renkli geri sayim, nav uyari noktasi
 *   🧮 Rechner          — Brutto-Netto (Näherung), Kündigungsfrist (§622 BGB), Kaution-Zins
 *   📁 Dokumententresor — belgeler (arama, behalten, silme) tek panelde
 *   ✉️ Schnellbriefe    — 10 hazir kisa mektup -> doldur -> premium A4 PDF
 *   ⭐ Favoriten        — en cok kullanilan 3 belge ana sayfada kisayol
 *  Konto'daki 🧩 Module karti 8 modulu de yonetir (ac/kapa) — arayuzu kullanici belirler.
 * KULLANIM: pull-updates.php?files=apply-modulepack.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-modulepack BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_MODPACK') !== false) exit("Zaten ekli (CH_MODPACK).\n");
file_put_contents($file . '.bak-modpack-' . date('Ymd-His'), $src);

$bundle = <<<'MODHTML'
<script id="ch-modpack">
/* CH_MODPACK — Fristen / Rechner / Tresor / Briefe / Favoriten + kullanici-secmeli moduller */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function LS(k,d){ try{ var v=JSON.parse(localStorage.getItem(k)); return v==null?d:v; }catch(e){ return d; } }
  function SV(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  var API="api.php";

  var T={
   de:{fris:"Fristen",rech:"Rechner",tresor:"Dokumente",briefe:"Schnellbriefe",fav:"Favoriten",
     fadd:"+ Frist hinzufügen",fttl:"Bezeichnung (z. B. Widerspruchsfrist Bescheid)",fdate:"Stichtag",fnote:"Notiz (optional)",fsave:"Speichern",fempty:"Keine Fristen — fügen Sie die erste hinzu.",fleft:"Tage",fover:"überfällig",ftoday:"HEUTE",
     bn:"Brutto → Netto",kf:"Kündigungsfrist",kz:"Kaution-Zins",brutto:"Brutto / Monat (€)",stkl:"Steuerklasse",calc:"Berechnen",netto:"Netto ca.",abz:"Abzüge ca.",disc:"Näherung ohne Kirchensteuer/Soli — keine Steuerberatung.",
     start:"Beschäftigt seit",res:"Ergebnis",an:"Arbeitnehmer: 4 Wochen zum 15. oder Monatsende",agf:"Arbeitgeber-Frist",early:"Frühestes Vertragsende (heute gekündigt)",
     kaut:"Kaution (€)",jahre:"Jahre",zins:"Zinssatz % p. a.",zres:"Zinsen gesamt",total:"Auszahlung ca.",
     search:"Suchen…",kept:"Behalten",all:"Alle",open:"Ansehen",del:"Löschen",keep:"Behalten ✓",tempty:"Noch keine Dokumente.",
     bfill:"Ausfüllen",bmake:"📄 PDF erstellen",bto:"Empfänger (Name, Adresse)",bdet:"Details",bsub:"Betreff",
     favt:"⭐ Ihre Favoriten",modt:"Module",modn:"Sie bestimmen Ihre Oberfläche — Module ein-/ausblenden:"},
   tr:{fris:"Süre Takibi",rech:"Hesaplayıcı",tresor:"Belgelerim",briefe:"Hazır Mektuplar",fav:"Favoriler",
     fadd:"+ Süre ekle",fttl:"Başlık (örn. itiraz süresi)",fdate:"Son gün",fnote:"Not (opsiyonel)",fsave:"Kaydet",fempty:"Kayıtlı süre yok — ilkini ekle.",fleft:"gün",fover:"geçti",ftoday:"BUGÜN",
     bn:"Brüt → Net",kf:"Fesih süresi",kz:"Depozito faizi",brutto:"Brüt / ay (€)",stkl:"Vergi sınıfı",calc:"Hesapla",netto:"Net (yaklaşık)",abz:"Kesintiler",disc:"Yaklaşık değer — vergi danışmanlığı değildir.",
     start:"İşe giriş tarihi",res:"Sonuç",an:"Çalışan: ayın 15'i veya sonuna 4 hafta",agf:"İşveren süresi",early:"En erken sözleşme sonu (bugün fesihte)",
     kaut:"Depozito (€)",jahre:"Yıl",zins:"Faiz % yıllık",zres:"Toplam faiz",total:"Ödeme (yaklaşık)",
     search:"Ara…",kept:"Kalıcı",all:"Tümü",open:"Görüntüle",del:"Sil",keep:"Kalıcı yap ✓",tempty:"Henüz belge yok.",
     bfill:"Doldur",bmake:"📄 PDF oluştur",bto:"Alıcı (ad, adres)",bdet:"Detaylar",bsub:"Konu",
     favt:"⭐ Favorilerin",modt:"Modüller",modn:"Arayüzü sen belirlersin — modülleri aç/kapat:"},
   en:{fris:"Deadlines",rech:"Calculators",tresor:"My documents",briefe:"Quick letters",fav:"Favorites",
     fadd:"+ Add deadline",fttl:"Title (e.g. objection deadline)",fdate:"Due date",fnote:"Note (optional)",fsave:"Save",fempty:"No deadlines yet.",fleft:"days",fover:"overdue",ftoday:"TODAY",
     bn:"Gross → Net",kf:"Notice period",kz:"Deposit interest",brutto:"Gross / month (€)",stkl:"Tax class",calc:"Calculate",netto:"Net approx.",abz:"Deductions",disc:"Approximation — not tax advice.",
     start:"Employed since",res:"Result",an:"Employee: 4 weeks to the 15th or month end",agf:"Employer period",early:"Earliest contract end (if terminated today)",
     kaut:"Deposit (€)",jahre:"Years",zins:"Interest % p.a.",zres:"Total interest",total:"Payout approx.",
     search:"Search…",kept:"Kept",all:"All",open:"View",del:"Delete",keep:"Keep ✓",tempty:"No documents yet.",
     bfill:"Fill in",bmake:"📄 Create PDF",bto:"Recipient (name, address)",bdet:"Details",bsub:"Subject",
     favt:"⭐ Your favorites",modt:"Modules",modn:"You decide your interface — toggle modules:"}
  };
  function L(){ return T[UIL()]||T.en; }

  /* ── ortak panel/nav fabrikasi ── */
  function mkPanel(key){
    var p=document.getElementById("pnl-"+key);
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-"+key; document.body.appendChild(p); }
    return p;
  }
  function top(t,extra){
    return '<div class="vst-topbar"><button class="vst-back" data-back="1">‹</button><div class="vst-ttl">'+esc(t)+'</div>'+(extra||"")+'</div>';
  }
  function bindBack(p,fn){ var b=p.querySelector('[data-back="1"]'); if(b) b.addEventListener("click",fn||function(){ try{ gP("chat"); }catch(e){} }); }
  var NAVDEF=[
    ["fris","⏰",'<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>'],
    ["rech","🧮",'<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h2M8 15h2M14 11h2M14 15h2"/>'],
    ["tresor","📁",'<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'],
    ["briefe","✉️",'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>']
  ];
  function addNavs(){
    var l=L();
    var names={fris:l.fris,rech:l.rech,tresor:l.tresor,briefe:l.briefe};
    var ref=document.getElementById("nav-vst")||document.getElementById("nav-cv")||document.getElementById("nav-jobs")||document.querySelector(".sb-photo-btn");
    NAVDEF.forEach(function(nd){
      var id="nav-"+nd[0];
      var ex=document.getElementById(id);
      if(ex){ var tt=ex.querySelector(".sb-photo-t"); if(tt&&tt.textContent!==names[nd[0]]) tt.textContent=names[nd[0]]; return; }
      var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id=id;
      nav.setAttribute("onclick","gP('"+nd[0]+"')"); nav.style.cssText="cursor:pointer;position:relative";
      nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0">'+nd[2]+'</svg>'
        +'<div><div class="sb-photo-t">'+esc(names[nd[0]])+'</div></div><span id="'+id+'-dot" style="display:none;position:absolute;top:8px;right:10px;width:8px;height:8px;border-radius:50%;background:#e05050"></span>';
      if(ref&&ref.parentNode){ ref.parentNode.insertBefore(nav, ref.nextSibling); ref=nav; }
      else { var sb=document.getElementById("sb"); if(sb) sb.appendChild(nav); }
    });
  }

  /* ══ ⏰ FRISTEN ══ */
  function frList(){ return LS("ch_fristen",[]); }
  function frSave(v){ SV("ch_fristen",v); frDot(); }
  function frDays(d){ var t=new Date(); t.setHours(0,0,0,0); return Math.round((new Date(d+"T00:00:00")-t)/86400000); }
  function frDot(){
    try{
      var urgent=frList().some(function(f){ var d=frDays(f.d); return d<=3; });
      var dot=document.getElementById("nav-fris-dot"); if(dot) dot.style.display=urgent?"block":"none";
    }catch(e){}
  }
  function renderFris(){
    var l=L(), p=mkPanel("fris");
    var items=frList().slice().sort(function(a,b){ return a.d<b.d?-1:1; });
    p.innerHTML=top("⏰ "+l.fris)
     +'<div class="vst-scroll"><div class="vst-wrap" style="max-width:640px">'
     +'<div class="vst-fl">'+esc(l.fttl)+'</div><input class="vst-in" id="fr-t">'
     +'<div class="cvs-row"><div style="flex:1"><div class="vst-fl">'+esc(l.fdate)+'</div><input class="vst-in" id="fr-d" type="date"></div>'
     +'<div style="flex:1"><div class="vst-fl">'+esc(l.fnote)+'</div><input class="vst-in" id="fr-n"></div></div>'
     +'<button class="vst-nextb" id="fr-add" style="width:100%">'+esc(l.fadd)+'</button>'
     +'<div style="height:16px"></div>'
     +(items.length?items.map(function(f,i){
        var d=frDays(f.d);
        var col=d<0?"#e05050":(d===0?"#e05050":(d<=7?"#d4a84a":"#42df94"));
        var badge=d<0?(Math.abs(d)+" "+l.fleft+" "+l.fover):(d===0?l.ftoday:(d+" "+l.fleft));
        return '<div class="vst-card" style="cursor:default;margin-bottom:10px;display:flex;align-items:center;gap:13px">'
          +'<div style="min-width:74px;text-align:center;padding:8px 6px;border-radius:11px;font-size:12px;font-weight:800;color:#0e0e14;background:'+col+'">'+esc(badge)+'</div>'
          +'<div style="flex:1"><div class="vst-n" style="font-size:14px">'+esc(f.t)+'</div>'
          +'<div class="vst-sn">'+esc(f.d)+(f.n?" · "+esc(f.n):"")+'</div></div>'
          +'<button class="cvs-x" style="position:static" data-i="'+i+'">✕</button></div>';
      }).join(""):'<div class="vst-sub">'+esc(l.fempty)+'</div>')
     +'</div></div>';
    bindBack(p);
    document.getElementById("fr-add").addEventListener("click",function(){
      var t=document.getElementById("fr-t").value.trim(), d=document.getElementById("fr-d").value;
      if(!t||!d) return;
      var v=frList(); v.push({t:t,d:d,n:document.getElementById("fr-n").value.trim()}); frSave(v); renderFris();
    });
    p.querySelectorAll(".cvs-x").forEach(function(b){
      b.addEventListener("click",function(){ var v=frList(); v.sort(function(a,c){return a.d<c.d?-1:1;}); v.splice(+b.getAttribute("data-i"),1); frSave(v); renderFris(); });
    });
  }

  /* ══ 🧮 RECHNER ══ */
  function eSt(z){ /* §32a yaklasik (2024) */
    if(z<=11784) return 0;
    if(z<=17005){ var y=(z-11784)/10000; return (922.98*y+1400)*y; }
    if(z<=66760){ var q=(z-17005)/10000; return (181.19*q+2397)*q+1025.38; }
    if(z<=277825) return 0.42*z-10602.13;
    return 0.45*z-18936.88;
  }
  function renderRech(tab){
    var l=L(), p=mkPanel("rech"); tab=tab||"bn";
    function tb(k,lbl){ return '<button class="cvs-tplb'+(tab===k?" on":"")+'" data-rt="'+k+'" style="font-size:11.5px">'+esc(lbl)+'</button>'; }
    var body="";
    if(tab==="bn"){
      body='<div class="vst-fl">'+esc(l.brutto)+'</div><input class="vst-in" id="rc-b" type="number" placeholder="3500">'
        +'<div class="vst-fl">'+esc(l.stkl)+'</div><select class="vst-in" id="rc-k"><option value="1">I / IV</option><option value="3">III</option><option value="5">V</option></select>'
        +'<button class="vst-nextb" id="rc-go" style="width:100%">'+esc(l.calc)+'</button><div id="rc-out" style="margin-top:14px"></div>'
        +'<div class="vst-note">'+esc(l.disc)+'</div>';
    } else if(tab==="kf"){
      body='<div class="vst-fl">'+esc(l.start)+'</div><input class="vst-in" id="rc-s" type="date">'
        +'<button class="vst-nextb" id="rc-go2" style="width:100%">'+esc(l.calc)+'</button><div id="rc-out2" style="margin-top:14px"></div>'
        +'<div class="vst-note">§ 622 BGB.</div>';
    } else {
      body='<div class="cvs-row"><div style="flex:1"><div class="vst-fl">'+esc(l.kaut)+'</div><input class="vst-in" id="rc-ka" type="number" placeholder="1500"></div>'
        +'<div style="flex:1"><div class="vst-fl">'+esc(l.jahre)+'</div><input class="vst-in" id="rc-j" type="number" placeholder="5"></div>'
        +'<div style="flex:1"><div class="vst-fl">'+esc(l.zins)+'</div><input class="vst-in" id="rc-z" type="number" step="0.05" value="1.5"></div></div>'
        +'<button class="vst-nextb" id="rc-go3" style="width:100%">'+esc(l.calc)+'</button><div id="rc-out3" style="margin-top:14px"></div>';
    }
    p.innerHTML=top("🧮 "+l.rech)
     +'<div class="vst-scroll"><div class="vst-wrap" style="max-width:560px">'
     +'<div class="cvs-tpls">'+tb("bn",l.bn)+tb("kf",l.kf)+tb("kz",l.kz)+'</div><div style="height:10px"></div>'+body
     +'</div></div>';
    bindBack(p);
    p.querySelectorAll("[data-rt]").forEach(function(b){ b.addEventListener("click",function(){ renderRech(b.getAttribute("data-rt")); }); });
    function box(rows){ return '<div class="vst-card" style="cursor:default">'+rows.map(function(r){
      return '<div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--t2,#e0e0ff);padding:5px 0"><span>'+r[0]+'</span><b style="color:'+(r[2]||"var(--gold,#d4a84a)")+'">'+r[1]+'</b></div>'; }).join("")+'</div>'; }
    var g=document.getElementById("rc-go");
    if(g) g.addEventListener("click",function(){
      var b=+document.getElementById("rc-b").value||0, k=document.getElementById("rc-k").value;
      if(!b) return;
      var sv=b*0.2035;
      var z=Math.max(0,(b-sv)*12-1230);
      var st=(k==="3")?2*eSt(z/2):(k==="5"?eSt(z)*1.22:eSt(z));
      var lst=st/12, net=b-sv-lst;
      document.getElementById("rc-out").innerHTML=box([[esc(l.abz)+" (SV+LSt)",(sv+lst).toFixed(0)+" €","#e0a0a0"],[esc(l.netto),net.toFixed(0)+" €","#42df94"]]);
    });
    var g2=document.getElementById("rc-go2");
    if(g2) g2.addEventListener("click",function(){
      var s=document.getElementById("rc-s").value; if(!s) return;
      var yrs=(Date.now()-new Date(s+"T00:00:00"))/31557600000;
      var tbl=[[2,1],[5,2],[8,3],[10,4],[12,5],[15,6],[20,7]], m=0;
      for(var i=0;i<tbl.length;i++) if(yrs>=tbl[i][0]) m=tbl[i][1];
      var now=new Date(), end;
      if(m===0){ end=new Date(now); end.setDate(end.getDate()+28); var d15=new Date(end.getFullYear(),end.getMonth(),15), dE=new Date(end.getFullYear(),end.getMonth()+1,0); end=end<=d15?d15:dE; }
      else { end=new Date(now.getFullYear(),now.getMonth()+m+1,0); }
      var ag=m===0?"4 Wochen → 15./Monatsende":m+" Monat(e) → Monatsende";
      document.getElementById("rc-out2").innerHTML=box([[esc(l.an),"✓","#42df94"],[esc(l.agf),esc(ag)],[esc(l.early),end.toLocaleDateString("de-DE")]]);
    });
    var g3=document.getElementById("rc-go3");
    if(g3) g3.addEventListener("click",function(){
      var ka=+document.getElementById("rc-ka").value||0, j=+document.getElementById("rc-j").value||0, z=+document.getElementById("rc-z").value||0;
      if(!ka||!j) return;
      var v=ka; for(var i=0;i<j;i++) v*=(1+z/100);
      document.getElementById("rc-out3").innerHTML=box([[esc(l.zres),(v-ka).toFixed(2)+" €"],[esc(l.total),v.toFixed(2)+" €","#42df94"]]);
    });
  }

  /* ══ 📁 TRESOR ══ */
  function renderTresor(filter,q){
    var l=L(), p=mkPanel("tresor"); filter=filter||"all"; q=q||"";
    p.innerHTML=top("📁 "+l.tresor)
     +'<div class="vst-scroll"><div class="vst-wrap" style="max-width:680px">'
     +'<div class="cvs-row"><input class="vst-in" id="ts-q" placeholder="'+esc(l.search)+'" value="'+esc(q)+'">'
     +'<button class="cvs-tplb'+(filter==="all"?" on":"")+'" data-tf="all" style="max-width:90px">'+esc(l.all)+'</button>'
     +'<button class="cvs-tplb'+(filter==="kept"?" on":"")+'" data-tf="kept" style="max-width:110px">'+esc(l.kept)+'</button></div>'
     +'<div id="ts-list" class="vst-sub">…</div></div></div>';
    bindBack(p);
    p.querySelectorAll("[data-tf]").forEach(function(b){ b.addEventListener("click",function(){ renderTresor(b.getAttribute("data-tf"),document.getElementById("ts-q").value); }); });
    var qi=document.getElementById("ts-q"), qt=null;
    qi.addEventListener("input",function(){ clearTimeout(qt); qt=setTimeout(function(){ renderTresor(filter,qi.value); },350); });
    fetch(API+"?action=history").then(function(r){ return r.json(); }).then(function(d){
      var docs=(d&&d.docs)||[];
      if(filter==="kept") docs=docs.filter(function(x){ return x.keep; });
      if(q) docs=docs.filter(function(x){ return ((x.docName||"")+" "+(x.preview||"")).toLowerCase().indexOf(q.toLowerCase())>-1; });
      var el=document.getElementById("ts-list"); if(!el) return;
      if(!docs.length){ el.textContent=l.tempty; return; }
      el.classList.remove("vst-sub");
      el.innerHTML=docs.map(function(x){
        return '<div class="vst-card" style="cursor:default;margin-bottom:10px">'
          +'<div style="display:flex;align-items:center;gap:9px"><div class="vst-n" style="font-size:14px;flex:1">'+esc(x.docName||x.docType||"Dokument")+'</div>'
          +(x.keep?'<span style="font-size:10px;font-weight:800;color:#42df94;border:1px solid rgba(66,223,148,.4);border-radius:100px;padding:2px 9px">'+esc(l.kept)+'</span>':"")+'</div>'
          +'<div class="vst-sn" style="margin:6px 0">'+esc((x.preview||"").slice(0,140))+'…</div>'
          +'<div style="display:flex;gap:8px">'
          +'<button class="jb-mbtn" data-op="open">'+esc(l.open)+'</button>'
          +(!x.keep?'<button class="jb-mbtn" data-op="keep" data-id="'+esc(x.id||x.docId||"")+'">'+esc(l.keep)+'</button>':"")
          +'<button class="jb-mbtn" style="color:#ff9d9d" data-op="del" data-id="'+esc(x.id||x.docId||"")+'">'+esc(l.del)+'</button>'
          +'</div></div>';
      }).join("");
      el.querySelectorAll("[data-op]").forEach(function(b){
        b.addEventListener("click",function(){
          var op=b.getAttribute("data-op"), id=b.getAttribute("data-id");
          if(op==="open"){ try{ gP("ant"); }catch(e){} return; }
          var action=op==="del"?"delete":"keepdoc";
          fetch(API+"?action="+action,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({docId:id,keep:true})})
            .then(function(){ renderTresor(filter,q); });
        });
      });
    }).catch(function(){ var el=document.getElementById("ts-list"); if(el) el.textContent=l.tempty; });
  }

  /* ══ ✉️ SCHNELLBRIEFE ══ */
  var BRIEFE=[
    {k:"adr",ic:"🏠",n:"Adressänderung mitteilen",s:"Mitteilung meiner neuen Anschrift",b:function(x){return "hiermit teile ich Ihnen mit, dass sich meine Anschrift geändert hat.\n\nNeue Anschrift:\n"+x+"\n\nBitte aktualisieren Sie meine Daten in Ihren Unterlagen und senden Sie künftige Korrespondenz an die neue Adresse.";}},
    {k:"sepa",ic:"🏦",n:"SEPA-Lastschrift widerrufen",s:"Widerruf der SEPA-Lastschriftmandats",b:function(x){return "hiermit widerrufe ich das Ihnen erteilte SEPA-Lastschriftmandat mit sofortiger Wirkung.\n\nReferenz / Vertragsnummer: "+x+"\n\nBitte bestätigen Sie mir den Widerruf schriftlich. Künftige Abbuchungen von meinem Konto sind nicht mehr autorisiert."}},
    {k:"fit",ic:"🏋️",n:"Mitgliedschaft kündigen",s:"Kündigung meiner Mitgliedschaft",b:function(x){return "hiermit kündige ich meine Mitgliedschaft ("+x+") fristgerecht zum nächstmöglichen Termin.\n\nBitte bestätigen Sie mir die Kündigung und das Vertragsende schriftlich."}},
    {k:"wid",ic:"↩️",n:"Online-Kauf widerrufen (14 Tage)",s:"Widerruf meines Kaufvertrags",b:function(x){return "hiermit widerrufe ich den von mir abgeschlossenen Kaufvertrag fristgerecht innerhalb der 14-tägigen Widerrufsfrist (§§ 355, 312g BGB).\n\nBestellung / Rechnungsnummer: "+x+"\n\nBitte erstatten Sie den Kaufpreis auf das ursprüngliche Zahlungsmittel."}},
    {k:"mahn",ic:"⚠️",n:"Mahnung — Widerspruch (kurz)",s:"Widerspruch gegen Ihre Mahnung",b:function(x){return "gegen Ihre Mahnung lege ich hiermit Widerspruch ein.\n\nGrund / Referenz: "+x+"\n\nDie geltend gemachte Forderung ist nicht berechtigt. Bitte legen Sie mir einen Nachweis der Forderung vor oder stellen Sie die Mahnläufe ein."}},
    {k:"term",ic:"📅",n:"Termin absagen / verschieben",s:"Terminabsage",b:function(x){return "leider muss ich den vereinbarten Termin absagen.\n\nTermin: "+x+"\n\nBitte teilen Sie mir einen Ersatztermin mit. Vielen Dank für Ihr Verständnis."}},
    {k:"rate",ic:"💶",n:"Ratenzahlung anbieten",s:"Bitte um Ratenzahlung",b:function(x){return "die offene Forderung kann ich derzeit nicht in einer Summe begleichen. Ich bitte daher um Ratenzahlung.\n\nMein Vorschlag: "+x+"\n\nBitte bestätigen Sie mir die Vereinbarung schriftlich; die Raten werde ich pünktlich leisten."}},
    {k:"dsgvo",ic:"🔒",n:"DSGVO-Auskunft (Art. 15)",s:"Auskunftsersuchen nach Art. 15 DSGVO",b:function(x){return "hiermit fordere ich Sie auf, mir gemäß Art. 15 DSGVO Auskunft über alle zu meiner Person gespeicherten Daten zu erteilen.\n\nKundenreferenz: "+x+"\n\nBitte antworten Sie innerhalb eines Monats (Art. 12 Abs. 3 DSGVO)."}},
    {k:"laerm",ic:"🔇",n:"Lärmbelästigung (freundlich)",s:"Bitte um Rücksichtnahme — Lärm",b:function(x){return "ich wende mich mit einer freundlichen Bitte an Sie: In letzter Zeit kommt es wiederholt zu erheblichem Lärm ("+x+").\n\nIch bitte Sie höflich um Rücksichtnahme, insbesondere während der Ruhezeiten (22–6 Uhr). Vielen Dank für Ihr Verständnis."}},
    {k:"krank",ic:"🤒",n:"Krankmeldung (kurz)",s:"Arbeitsunfähigkeit — Mitteilung",b:function(x){return "hiermit teile ich Ihnen mit, dass ich arbeitsunfähig erkrankt bin.\n\nVoraussichtliche Dauer: "+x+"\n\nDie ärztliche Bescheinigung reiche ich unverzüglich nach."}}
  ];
  var BRCSS=".brA4{width:794px;min-height:1123px;background:#fff;color:#22222a;font-family:'Inter',Arial,sans-serif;padding:70px 76px;position:relative}"
    +".brA4 .snd{font-size:11px;color:#55555d;border-bottom:1px solid #d8b25a;padding-bottom:8px;margin-bottom:26px}"
    +".brA4 .to{font-size:12.5px;line-height:1.6;margin-bottom:30px;white-space:pre-line}"
    +".brA4 .dt{text-align:right;font-size:11.5px;color:#55555d;margin-bottom:26px}"
    +".brA4 .sj{font-size:13.5px;font-weight:800;margin-bottom:18px;color:#0a1a3f}"
    +".brA4 .bd{font-size:12.5px;line-height:1.75;white-space:pre-line}"
    +".brA4 .gr{margin-top:30px;font-size:12.5px}"
    +".brA4 .nm{margin-top:44px;font-size:12.5px;border-top:1px solid #22222a;display:inline-block;padding-top:5px}";
  function renderBriefe(sel){
    var l=L(), p=mkPanel("briefe");
    if(sel==null){
      p.innerHTML=top("✉️ "+l.briefe)
       +'<div class="vst-scroll"><div class="vst-wrap"><div class="vst-grid">'
       +BRIEFE.map(function(b,i){
          return '<div class="vst-card" data-b="'+i+'"><div class="vst-ic">'+b.ic+'</div><div class="vst-n" style="font-size:13.5px">'+esc(b.n)+'</div>'
            +'<div class="vst-sn" style="margin-top:8px;color:var(--gold,#d4a84a);font-weight:700">'+esc(l.bfill)+' →</div></div>';
        }).join("")+'</div></div></div>';
      bindBack(p);
      p.querySelectorAll("[data-b]").forEach(function(c){ c.addEventListener("click",function(){ renderBriefe(+c.getAttribute("data-b")); }); });
      return;
    }
    var B=BRIEFE[sel];
    p.innerHTML=top(B.ic+" "+B.n)
     +'<div class="vst-scroll"><div class="vst-wrap" style="max-width:560px">'
     +'<div class="vst-fl">'+esc(l.bto)+'</div><textarea class="vst-in" id="br-to" rows="2"></textarea>'
     +'<div class="vst-fl">'+esc(l.bdet)+'</div><textarea class="vst-in" id="br-x" rows="2"></textarea>'
     +'<button class="vst-nextb" id="br-go" style="width:100%">'+esc(l.bmake)+'</button>'
     +'<div class="vst-note">'+esc(l.disc||"")+'</div>'
     +'</div></div>';
    bindBack(p,function(){ renderBriefe(null); });
    document.getElementById("br-go").addEventListener("click",function(){
      var P2=window.P||{};
      var name=((P2.f1||"")+" "+(P2.f2||"")).trim()||"—";
      var adr=[P2.f3,P2.f4].filter(Boolean).join(" · ");
      var to=document.getElementById("br-to").value.trim()||"____________";
      var x=document.getElementById("br-x").value.trim()||"____________";
      var today=new Date().toLocaleDateString("de-DE");
      var html='<!doctype html><html><head><meta charset="utf-8"><title>'+esc(B.n)+'</title>'
       +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+BRCSS+'</style></head><body>'
       +'<div class="brA4"><div class="snd">'+esc(name)+(adr?" · "+esc(adr):"")+'</div>'
       +'<div class="to">'+esc(to)+'</div><div class="dt">'+esc(today)+'</div>'
       +'<div class="sj">'+esc(B.s)+'</div>'
       +'<div class="bd">Sehr geehrte Damen und Herren,\n\n'+esc(B.b(x))+'</div>'
       +'<div class="gr">Mit freundlichen Grüßen</div><div class="nm">'+esc(name)+'</div></div></body></html>';
      if(window.chPrintDoc) window.chPrintDoc(html);
    });
  }

  /* ══ ⭐ FAVORITEN ══ */
  function favBump(name){
    if(!name) return;
    var u=LS("ch_usage",{}); u[name]=(u[name]||0)+1; SV("ch_usage",u);
  }
  function wrapGen(){
    try{
      if(typeof genDoc!=="function"||genDoc.__fav) return;
      var _g=genDoc;
      var w=function(){ try{ favBump(arguments[1]||arguments[0]); }catch(e){} return _g.apply(this,arguments); };
      w.__fav=1; w.__wait=_g.__wait; genDoc=w; if(typeof window!=="undefined") window.genDoc=w;
    }catch(e){}
  }
  function addFavRow(){
    try{
      if(!modOn("fav")) { var ex=document.getElementById("ch-favrow"); if(ex) ex.style.display="none"; return; }
      var u=LS("ch_usage",{});
      var topDocs=Object.keys(u).sort(function(a,b){ return u[b]-u[a]; }).slice(0,3);
      var q=document.querySelector(".wlc-quick");
      if(!q) return;
      var row=document.getElementById("ch-favrow");
      if(!topDocs.length){ if(row) row.style.display="none"; return; }
      var l=L();
      var html='<div style="font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--gold,#d4a84a);margin:12px 0 7px">'+esc(l.favt)+'</div>'
        +'<div style="display:flex;gap:7px;flex-wrap:wrap">'+topDocs.map(function(n){
          return '<div class="wlc-pill" data-fav="'+esc(n)+'" style="border-color:rgba(212,168,74,.45)">⭐ '+esc(String(n).slice(0,34))+'</div>';
        }).join("")+'</div>';
      if(!row){ row=document.createElement("div"); row.id="ch-favrow"; q.parentNode.insertBefore(row,q.nextSibling); }
      row.style.display="";
      if(row.dataset.sig!==topDocs.join("|")){
        row.dataset.sig=topDocs.join("|"); row.innerHTML=html;
        row.querySelectorAll("[data-fav]").forEach(function(pl){
          pl.addEventListener("click",function(){
            try{ var inp=document.getElementById("inp"); if(inp){ inp.value=pl.getAttribute("data-fav"); inp.focus(); } }catch(e){}
          });
        });
      }
    }catch(e){}
  }

  /* ══ 🧩 MODULE karti v2 (8 modul) ══ */
  function mods(){ return LS("ch_mods",{}); }
  function modOn(k){ var m=mods(); return m[k]===undefined?true:!!m[k]; }
  function setMod(k,v){ var m=mods(); m[k]=v?1:0; SV("ch_mods",m); }
  var MODLIST=[["jobs","💼"],["cv","📇"],["vst","📑"],["fris","⏰"],["rech","🧮"],["tresor","📁"],["briefe","✉️"],["fav","⭐"]];
  function modName(k){
    var l=L();
    return {jobs:(T[UIL()]||T.en).fav&&({de:"Jobsuche",tr:"İş arama",en:"Job search"}[UIL()]||"Job search"),
      cv:{de:"Lebenslauf & CV",tr:"Özgeçmiş & CV",en:"CV"}[UIL()]||"CV",
      vst:{de:"Verträge",tr:"Sözleşmeler",en:"Contracts"}[UIL()]||"Contracts",
      fris:l.fris,rech:l.rech,tresor:l.tresor,briefe:l.briefe,fav:l.fav}[k]||k;
  }
  function applyMods(){
    try{
      MODLIST.forEach(function(x){
        var nav=document.getElementById("nav-"+(x[0]==="jobs"?"jobs":x[0]));
        if(nav) nav.style.display=modOn(x[0])?"":"none";
      });
      ["cv","vst"].forEach(function(k){ var pl=document.getElementById("ch-pill-"+k); if(pl) pl.style.display=modOn(k)?"":"none"; });
    }catch(e){}
  }
  function injectMods2(){
    try{
      var host=document.querySelector("#pnl-konto .pnl-scroll")||document.getElementById("pnl-konto");
      if(!host) return;
      var d=document.getElementById("ch-mods-sw");
      if(d && d.dataset.v2) return;
      if(d) d.remove();
      var l=L();
      d=document.createElement("div"); d.id="ch-mods-sw"; d.dataset.v2="1";
      d.style.cssText="margin:14px 0;padding:14px;border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:14px;background:rgba(255,255,255,.03)";
      d.innerHTML='<div style="font-size:12px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--gold,#d4a84a);margin-bottom:6px">🧩 '+esc(l.modt)+'</div>'
        +'<div style="font-size:11px;color:var(--t3,#a8a8d0);margin-bottom:10px">'+esc(l.modn)+'</div>'
        +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:7px">'
        +MODLIST.map(function(x){
          return '<label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--t2,#e0e0ff);cursor:pointer;padding:8px 10px;border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:10px;background:rgba(255,255,255,.02)">'
            +'<input type="checkbox" data-mod="'+x[0]+'" '+(modOn(x[0])?"checked":"")+' style="width:15px;height:15px;accent-color:#d4a84a">'
            +x[1]+' '+esc(modName(x[0]))+'</label>';
        }).join("")+'</div>';
      var ref=document.getElementById("ch-theme-sw");
      if(ref&&ref.parentNode===host) host.insertBefore(d, ref.nextSibling); else host.insertBefore(d, host.firstChild);
      d.querySelectorAll("input[data-mod]").forEach(function(cb){
        cb.addEventListener("change",function(){ setMod(cb.getAttribute("data-mod"), cb.checked); applyMods(); addFavRow(); });
      });
    }catch(e){}
  }

  /* ══ panel bootstrap ══ */
  var BUILT={};
  function ensurePanels(){
    [["fris",renderFris],["rech",function(){renderRech("bn");}],["tresor",function(){renderTresor("all","");}],["briefe",function(){renderBriefe(null);}]].forEach(function(x){
      try{
        var p=document.getElementById("pnl-"+x[0]);
        if(!p){ p=mkPanel(x[0]); }
        if(!BUILT[x[0]]){ BUILT[x[0]]=1; x[1](); }
      }catch(e){}
    });
  }
  /* panel acilinca tazele: gP sarmalayici */
  function wrapGP2(){
    try{
      if(typeof gP!=="function"||gP.__modpack) return;
      var _g=gP;
      var w=function(k){
        var r=_g.apply(this,arguments);
        try{
          if(k==="fris") renderFris();
          else if(k==="rech") renderRech("bn");
          else if(k==="tresor") renderTresor("all","");
          else if(k==="briefe") renderBriefe(null);
        }catch(e){}
        return r;
      };
      w.__modpack=1; w.__clean=_g.__clean; gP=w; if(typeof window!=="undefined") window.gP=w;
    }catch(e){}
  }

  function tick(){ addNavs(); ensurePanels(); applyMods(); injectMods2(); wrapGen(); wrapGP2(); addFavRow(); frDot(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1500); });
  else { tick(); setInterval(tick,1500); }
})();}catch(e){}
</script>
MODHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_MODPACK uygulandi.\n";
echo "Yeni moduller: Fristen / Rechner / Dokumente / Schnellbriefe / Favoriten + Konto'da 8'li Module karti.\n";
