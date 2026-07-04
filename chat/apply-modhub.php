<?php
/**
 * ChatHelp — apply-modhub (CH_MODHUB)
 *  🧩 Modul Merkezi — ana kart/galeri:
 *   • Ana sayfada premium "Module entdecken" karti -> hub paneli
 *   • Hub: TUM modul kartlari (ikon, ad, aciklama, durum rozeti)
 *   • "＋ Zur Oberfläche" / "− Entfernen" ile arayuze tasi/kaldir (ch_mods — Konto kartiyla senkron)
 *   • Aktif modul karttan dogrudan acilir ("Öffnen →")
 * KULLANIM: pull-updates.php?files=apply-modhub.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-modhub BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_MODHUB') !== false) exit("Zaten ekli (CH_MODHUB).\n");
file_put_contents($file . '.bak-modhub-' . date('Ymd-His'), $src);

$bundle = <<<'HUBHTML'
<script id="ch-modhub">
/* CH_MODHUB — Modul Merkezi: tum kartlar tek yerde, kullanici arayuzune tasir */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function mods(){ try{ return JSON.parse(localStorage.getItem("ch_mods")||"{}"); }catch(e){ return {}; } }
  function modOn(k){ var m=mods(); return m[k]===undefined?true:!!m[k]; }
  function setMod(k,v){ var m=mods(); m[k]=v?1:0; try{ localStorage.setItem("ch_mods",JSON.stringify(m)); }catch(e){} applyNow(); }
  function applyNow(){
    try{
      ["jobs","cv","vst","fris","rech","tresor","briefe","fav"].forEach(function(k){
        var nav=document.getElementById("nav-"+k);
        if(nav) nav.style.display=modOn(k)?"":"none";
      });
      ["cv","vst"].forEach(function(k){ var pl=document.getElementById("ch-pill-"+k); if(pl) pl.style.display=modOn(k)?"":"none"; });
      var fr=document.getElementById("ch-favrow"); if(fr&&!modOn("fav")) fr.style.display="none";
    }catch(e){}
  }

  var HT={
   de:{ttl:"Modul-Zentrale",sub:"Alle Funktionen auf einen Blick — Sie entscheiden, was in Ihrer Oberfläche erscheint.",on:"AKTIV",off:"INAKTIV",add:"＋ Zur Oberfläche",rem:"− Entfernen",open:"Öffnen →",disc:"🧩 Module entdecken",discs:"Oberfläche selbst gestalten"},
   tr:{ttl:"Modül Merkezi",sub:"Tüm özellikler tek bakışta — arayüzünde ne görüneceğine sen karar verirsin.",on:"AKTİF",off:"KAPALI",add:"＋ Arayüze taşı",rem:"− Kaldır",open:"Aç →",disc:"🧩 Modülleri keşfet",discs:"Arayüzünü kendin tasarla"},
   en:{ttl:"Module Hub",sub:"All features at a glance — you decide what appears in your interface.",on:"ACTIVE",off:"OFF",add:"＋ Add to interface",rem:"− Remove",open:"Open →",disc:"🧩 Discover modules",discs:"Design your own interface"}
  };
  function HL(){ return HT[UIL()]||HT.en; }

  var MODS=[
   {k:"jobs",ic:"💼",g:"jobs",n:{de:"Jobsuche & Bewerbung",tr:"İş Arama & Başvuru",en:"Job search & apply"},
    d:{de:"Echte Arbeitgeber finden, Anschreiben erstellen, per E-Mail bewerben.",tr:"Gerçek işverenleri bul, Anschreiben oluştur, e-postayla başvur.",en:"Find real employers, generate cover letters, apply by email."}},
   {k:"cv",ic:"📇",g:"cv",n:{de:"Lebenslauf & CV",tr:"Özgeçmiş & CV",en:"CV & Résumé"},
    d:{de:"4 Premium-Vorlagen mit Foto — ausfüllen, Live-Vorschau, PDF.",tr:"Fotoğraflı 4 premium şablon — doldur, canlı önizleme, PDF.",en:"4 premium templates with photo — fill in, live preview, PDF."}},
   {k:"vst",ic:"📑",g:"vst",n:{de:"Verträge",tr:"Sözleşmeler",en:"Contracts"},
    d:{de:"Miet-, Arbeits- & Kfz-Kaufvertrag — Seite für Seite, anwaltlich sauber.",tr:"Kira, iş ve araç satış sözleşmesi — sayfa sayfa, hukuken temiz.",en:"Rental, employment & car sale contracts — page by page."}},
   {k:"fris",ic:"⏰",g:"fris",n:{de:"Fristen-Tracker",tr:"Süre Takibi",en:"Deadline tracker"},
    d:{de:"Widerspruchs- & Kündigungsfristen mit Countdown und Warnung.",tr:"İtiraz ve fesih süreleri — geri sayım ve uyarıyla.",en:"Objection & notice deadlines with countdown and alerts."}},
   {k:"rech",ic:"🧮",g:"rech",n:{de:"Rechner",tr:"Hesaplayıcılar",en:"Calculators"},
    d:{de:"Brutto-Netto, Kündigungsfrist (§622 BGB), Kaution-Zins.",tr:"Brüt-net, fesih süresi (§622 BGB), depozito faizi.",en:"Gross-net, notice period (§622 BGB), deposit interest."}},
   {k:"tresor",ic:"📁",g:"tresor",n:{de:"Dokumententresor",tr:"Belge Kasası",en:"Document vault"},
    d:{de:"Alle erstellten Dokumente — suchen, behalten, löschen.",tr:"Tüm belgelerin — ara, kalıcı yap, sil.",en:"All your documents — search, keep, delete."}},
   {k:"briefe",ic:"✉️",g:"briefe",n:{de:"Schnellbriefe",tr:"Hazır Mektuplar",en:"Quick letters"},
    d:{de:"10 fertige Briefe (SEPA, DSGVO, Kündigung …) — 1 Klick zum PDF.",tr:"10 hazır mektup (SEPA, DSGVO, fesih …) — tek tık PDF.",en:"10 ready letters (SEPA, GDPR, cancellation …) — 1-click PDF."}},
   {k:"fav",ic:"⭐",g:null,n:{de:"Favoriten",tr:"Favoriler",en:"Favorites"},
    d:{de:"Ihre 3 meistgenutzten Dokumente als Schnellzugriff auf der Startseite.",tr:"En çok kullandığın 3 belge ana sayfada kısayol.",en:"Your top-3 documents as home shortcuts."}}
  ];

  function mkPanel(){
    var p=document.getElementById("pnl-hub");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-hub"; document.body.appendChild(p); }
    return p;
  }
  function renderHub(){
    var l=HL(), lang=UIL(), p=mkPanel();
    p.innerHTML='<div class="vst-topbar"><button class="vst-back" id="hub-back">‹</button><div class="vst-ttl">🧩 '+esc(l.ttl)+'</div></div>'
     +'<div class="vst-scroll"><div class="vst-wrap">'
     +'<div class="vst-sub">'+esc(l.sub)+'</div>'
     +'<div class="vst-grid" style="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))">'
     +MODS.map(function(m){
        var on=modOn(m.k);
        var name=m.n[lang]||m.n.en, desc=m.d[lang]||m.d.en;
        return '<div class="vst-card" style="cursor:default;display:flex;flex-direction:column">'
          +'<div style="display:flex;align-items:center;gap:11px;margin-bottom:10px"><div class="vst-ic" style="margin:0;width:46px;height:46px;font-size:23px">'+m.ic+'</div>'
          +'<div style="flex:1"><div class="vst-n" style="font-size:14px">'+esc(name)+'</div></div>'
          +'<span style="font-size:9px;font-weight:800;letter-spacing:.6px;padding:3px 9px;border-radius:100px;'+(on?"color:#42df94;border:1px solid rgba(66,223,148,.45)":"color:var(--t4,#6d6d92);border:1px solid var(--bdr,rgba(255,255,255,.15))")+'">'+(on?esc(l.on):esc(l.off))+'</span></div>'
          +'<div class="vst-sn" style="flex:1;line-height:1.55;margin-bottom:12px">'+esc(desc)+'</div>'
          +'<div style="display:flex;gap:8px">'
          +'<button class="jb-mbtn'+(on?"":" p")+'" data-tg="'+m.k+'" style="flex:1">'+(on?esc(l.rem):esc(l.add))+'</button>'
          +(on&&m.g?'<button class="jb-mbtn p" data-go="'+m.g+'">'+esc(l.open)+'</button>':"")
          +'</div></div>';
      }).join("")+'</div></div></div>';
    var bb=document.getElementById("hub-back");
    if(bb) bb.addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    p.querySelectorAll("[data-tg]").forEach(function(b){
      b.addEventListener("click",function(){ var k=b.getAttribute("data-tg"); setMod(k,!modOn(k)); renderHub(); });
    });
    p.querySelectorAll("[data-go]").forEach(function(b){
      b.addEventListener("click",function(){ try{ gP(b.getAttribute("data-go")); }catch(e){} });
    });
  }

  /* ana sayfada kesfet karti */
  function addDiscover(){
    try{
      var q=document.querySelector(".wlc-quick");
      if(!q||document.getElementById("ch-hub-card")) return;
      var l=HL();
      var c=document.createElement("div"); c.id="ch-hub-card";
      c.style.cssText="display:flex;align-items:center;gap:12px;margin:12px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.35);border-radius:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.03));transition:transform .14s";
      c.innerHTML='<div style="font-size:24px">🧩</div><div style="flex:1"><div style="font-size:13.5px;font-weight:800;color:#fff">'+esc(l.disc)+'</div>'
        +'<div style="font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:2px">'+esc(l.discs)+'</div></div>'
        +'<div style="font-size:16px;color:var(--gold,#d4a84a)">›</div>';
      c.addEventListener("click",function(){ try{ gP("hub"); }catch(e){} });
      c.addEventListener("mouseenter",function(){ c.style.transform="translateY(-1px)"; });
      c.addEventListener("mouseleave",function(){ c.style.transform=""; });
      q.parentNode.insertBefore(c, q);
    }catch(e){}
  }
  /* hub acildiginda tazele */
  function wrapGP3(){
    try{
      if(typeof gP!=="function"||gP.__hub) return;
      var _g=gP;
      var w=function(k){ var r=_g.apply(this,arguments); try{ if(k==="hub") renderHub(); }catch(e){} return r; };
      w.__hub=1; w.__clean=_g.__clean; w.__modpack=_g.__modpack;
      gP=w; if(typeof window!=="undefined") window.gP=w;
    }catch(e){}
  }

  var built=false;
  function tick(){
    try{
      if(!built){ mkPanel(); renderHub(); built=true; }
      addDiscover(); wrapGP3();
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1600); });
  else { tick(); setInterval(tick,1600); }
})();}catch(e){}
</script>
HUBHTML;

$nn = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $nn);
if (!$nn) exit("HATA: </body> bulunamadi — yazilmadi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_MODHUB uygulandi.\n";
echo "Ana sayfada '🧩 Module entdecken' karti -> Modul-Zentrale: tum kartlar, arayuze tasi/kaldir + dogrudan ac.\n";
