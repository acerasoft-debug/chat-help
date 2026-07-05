<?php
/**
 * ChatHelp — apply-modhub-nav (CH_MODHUB_NAV)
 *  Modul-Zentrale'ye GARANTILI erisim: sol menuye premium "🧩 Module" girisi.
 *  (Ana sayfa kartinin bagli oldugu .wlc-quick JS ile uretiliyor -> zamanlama iskaliyordu.
 *   Nav mekanizmasi kanitli calisiyor: jobs/cv oradan aciliyor.)
 *  + Ana sayfa kartini da coklu-ankraj ile daha saglam hale getirir.
 * KULLANIM: pull-updates.php?files=apply-modhub-nav.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-modhub-nav BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_MODHUB_NAV') !== false) exit("Zaten ekli (CH_MODHUB_NAV).\n");
file_put_contents($file . '.bak-modhubnav-' . date('Ymd-His'), $src);

$bundle = <<<'HNHTML'
<style id="ch-modhub-nav-css">
#nav-hub{background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.04)) !important;border:1px solid rgba(212,168,74,.35) !important;border-radius:12px !important;margin:6px 8px !important}
#nav-hub .sb-photo-t{color:var(--gold,#d4a84a) !important;font-weight:800 !important}
#ch-hub-fab{position:fixed;right:16px;bottom:92px;z-index:430;display:flex;align-items:center;gap:9px;
  padding:12px 18px;border-radius:100px;cursor:pointer;
  background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-weight:800;font-size:14px;
  box-shadow:0 10px 28px rgba(212,168,74,.4);border:none;font-family:inherit;-webkit-tap-highlight-color:transparent}
#ch-hub-fab:active{transform:scale(.96)}
#ch-hub-fab .fabx{margin-left:2px;font-size:16px;opacity:.7;cursor:pointer}
@media(max-width:700px){#ch-hub-fab{bottom:84px;right:12px;padding:11px 16px;font-size:13px}}
/* yeni panellerin goster/gizle kurali (jobs/cv/vst gibi) — bunlar eksikti */
#pnl-hub,#pnl-fris,#pnl-rech,#pnl-tresor,#pnl-briefe{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}
#pnl-hub.on,#pnl-fris.on,#pnl-rech.on,#pnl-tresor.on,#pnl-briefe.on{display:flex}
</style>
<script id="ch-modhub-nav">
/* CH_MODHUB_NAV — sol menude garantili 🧩 Module girisi + saglam ana-sayfa karti */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  var TT={de:"Module",tr:"Modüller",en:"Modules",fr:"Modules",es:"Módulos",it:"Moduli",nl:"Modules",pl:"Moduły",sv:"Moduler",pt:"Módulos"};
  function name(){ return TT[UIL()]||TT.en; }

  function addHubNav(){
    try{
      var ex=document.getElementById("nav-hub");
      if(ex){ var t=ex.querySelector(".sb-photo-t"); if(t&&t.textContent!==name()) t.textContent=name(); return; }
      var sb=document.getElementById("sb"); if(!sb) return;
      var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-hub";
      nav.setAttribute("onclick","gP('hub')"); nav.style.cssText="cursor:pointer";
      nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>'
        +'<div><div class="sb-photo-t">🧩 '+esc(name())+'</div></div>';
      /* konum: ilk .sb-photo-btn'den ONCE (modul girislerinin en ustu); yoksa sb sonuna */
      var first=sb.querySelector(".sb-photo-btn");
      if(first&&first.parentNode){ first.parentNode.insertBefore(nav, first); }
      else { sb.appendChild(nav); }
    }catch(e){}
  }

  /* ana sayfa kartini coklu ankraj ile ekle (wlc-quick JS-uretimli -> gec gelebilir) */
  function addCardRobust(){
    try{
      if(document.getElementById("ch-hub-card")) return;
      var anchor=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label")||document.querySelector(".wlc-diff")||null;
      if(!anchor) return; /* welcome henuz yok, sonra denenir */
      var l=name();
      var disc={de:"Module entdecken",tr:"Modülleri keşfet",en:"Discover modules"}[UIL()]||"Discover modules";
      var subt={de:"Oberfläche selbst gestalten",tr:"Arayüzünü kendin tasarla",en:"Design your own interface"}[UIL()]||"";
      var c=document.createElement("div"); c.id="ch-hub-card";
      c.style.cssText="display:flex;align-items:center;gap:12px;margin:12px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.35);border-radius:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.03))";
      c.innerHTML='<div style="font-size:24px">🧩</div><div style="flex:1"><div style="font-size:13.5px;font-weight:800;color:#fff">'+esc(disc)+'</div>'
        +'<div style="font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:2px">'+esc(subt)+'</div></div><div style="font-size:16px;color:var(--gold,#d4a84a)">›</div>';
      c.addEventListener("click",function(){ try{ gP("hub"); }catch(e){} });
      anchor.parentNode.insertBefore(c, anchor);
    }catch(e){}
  }

  /* GARANTILI: her zaman gorunen sabit 🧩 buton (position:fixed, hicbir DOM zamanlamasina bagli degil) */
  function addFab(){
    try{
      if(document.getElementById("ch-hub-fab")) return;
      if(!document.body) return;
      var disc={de:"Module",tr:"Modüller",en:"Modules",fr:"Modules",es:"Módulos",it:"Moduli"}[UIL()]||"Modules";
      var b=document.createElement("button"); b.id="ch-hub-fab"; b.type="button";
      b.innerHTML='🧩 <span>'+esc(disc)+'</span>';
      b.addEventListener("click",function(){ try{ gP("hub"); }catch(e){} });
      document.body.appendChild(b);
    }catch(e){}
  }
  function tick(){ addHubNav(); addFab(); addCardRobust(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1200); });
  else { tick(); setInterval(tick,1200); }
})();}catch(e){}
</script>
HNHTML;

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
echo "✓ CH_MODHUB_NAV uygulandi.\n";
echo "Sol menude en ustte altin '🧩 Module' girisi -> Modul-Zentrale. Ana sayfa karti da coklu-ankrajli.\n";
