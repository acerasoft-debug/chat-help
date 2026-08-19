<?php
/**
 * ChatHelp — apply-navy-premium (CH_NAVY_THEME)
 *  • Ana tema: parlament lacivert (Play gorselleriyle ayni ton) — VARSAYILAN
 *  • Antrasit ikinci tema olarak Konto'da secilebilir (localStorage ch_theme)
 *  • Yeni CH balon logosu + ChatHelp yazi markasi (sidebar)
 *  • Premium polish: kartlar, scrollbar, focus, golgeler
 * KULLANIM: pull-updates.php?files=apply-navy-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-navy-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_NAVY_THEME') !== false) exit("Zaten ekli (CH_NAVY_THEME).\n");
file_put_contents($file . '.bak-navy-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<style id="ch-navy-theme">
/* CH_NAVY_THEME — parlament lacivert (varsayilan). Antrasit: html.ch-anth ile eski tema. */
html.ch-navy{
  --bg:#0a1633; --s1:#0d1c3f; --s2:#12244c; --s3:#182e5c; --s4:#20386b;
  --bdr:rgba(160,190,255,.15);
  --t3:#9fb0d8; --t4:#66799f;
  --ga:rgba(212,168,74,.12); --gb:rgba(212,168,74,.32);
}
html.ch-navy body{ background:linear-gradient(165deg,#0a1a3f 0%,#0d2452 38%,#0a1533 100%) fixed !important; }
html.ch-navy #sb{ background:linear-gradient(180deg,#0d1c3f,#0a1735) !important; border-right:1px solid rgba(160,190,255,.12) !important; }
/* ── premium polish (her iki temada) ── */
.wcat{ border-radius:16px !important; transition:transform .15s ease, box-shadow .2s ease, border-color .2s ease !important; }
.wcat:hover{ transform:translateY(-2px); box-shadow:0 14px 34px rgba(0,0,0,.35); border-color:var(--gb) !important; }
html.ch-navy .wcat{ background:linear-gradient(135deg,rgba(255,255,255,.05),rgba(212,168,74,.05)) !important; border:1px solid rgba(212,168,74,.16) !important; }
html.ch-navy .sec-title{ letter-spacing:-.2px; }
::-webkit-scrollbar{ width:9px; height:9px; }
::-webkit-scrollbar-thumb{ background:rgba(212,168,74,.25); border-radius:100px; }
::-webkit-scrollbar-thumb:hover{ background:rgba(212,168,74,.45); }
::-webkit-scrollbar-track{ background:transparent; }
button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible{ outline:2px solid rgba(212,168,74,.55); outline-offset:1px; }
html.ch-navy .jb-card{ background:linear-gradient(135deg,rgba(255,255,255,.05),rgba(212,168,74,.05)) !important; border:1px solid rgba(212,168,74,.16) !important; }
/* logo */
.sb-brand #ch-nlogo{ flex-shrink:0 }
.sb-brand .ch-nname{ font-size:21px; font-weight:800; color:#fff; letter-spacing:-.4px }
.sb-brand .ch-nname .x{ color:var(--gold) }
/* tema secici */
#ch-theme-sw{ margin:14px 0; padding:14px; border:1px solid var(--bdr); border-radius:14px; background:rgba(255,255,255,.03) }
#ch-theme-sw .t{ font-size:12px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--gold); margin-bottom:10px }
#ch-theme-sw .row{ display:flex; gap:10px }
#ch-theme-sw button{ flex:1; display:flex; align-items:center; gap:9px; padding:11px 12px; border-radius:11px; cursor:pointer;
  border:1.5px solid var(--bdr); background:rgba(255,255,255,.04); color:var(--t2); font-size:13px; font-weight:700; font-family:inherit }
#ch-theme-sw button.on{ border-color:var(--gold); color:#fff; background:rgba(212,168,74,.10) }
#ch-theme-sw .sw{ width:18px; height:18px; border-radius:6px; flex-shrink:0; border:1px solid rgba(255,255,255,.25) }
</style>
<script id="ch-navy-js">
/* CH_NAVY_THEME JS — tema yonetimi + yeni logo + Konto tema secici */
try{(function(){
  function cur(){ try{ return localStorage.getItem("ch_theme")||"navy"; }catch(e){ return "navy"; } }
  function apply(t){
    try{ document.documentElement.classList.toggle("ch-navy", t!=="anth"); }catch(e){}
    try{ document.querySelectorAll("#ch-theme-sw button").forEach(function(b){ b.classList.toggle("on", b.getAttribute("data-t")===t); }); }catch(e){}
  }
  window.chSetTheme=function(t){ try{ localStorage.setItem("ch_theme",t); }catch(e){} apply(t); };
  apply(cur());

  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var TT={ de:{t:"Design",n:"Marine (Standard)",a:"Anthrazit"}, tr:{t:"Tema",n:"Lacivert (Varsayılan)",a:"Antrasit"},
           en:{t:"Theme",n:"Navy (default)",a:"Anthracite"}, fr:{t:"Thème",n:"Marine (défaut)",a:"Anthracite"},
           es:{t:"Tema",n:"Azul marino (estándar)",a:"Antracita"}, it:{t:"Tema",n:"Blu navy (standard)",a:"Antracite"} };

  function injectSwitcher(){
    try{
      if(document.getElementById("ch-theme-sw")) return;
      var host=document.querySelector("#pnl-konto .pnl-scroll")||document.getElementById("pnl-konto");
      if(!host) return;
      var L=TT[UIL()]||TT.de;
      var d=document.createElement("div"); d.id="ch-theme-sw";
      d.innerHTML='<div class="t">🎨 '+L.t+'</div><div class="row">'+
        '<button type="button" data-t="navy"><span class="sw" style="background:linear-gradient(135deg,#0d2452,#0a1633)"></span>'+L.n+'</button>'+
        '<button type="button" data-t="anth"><span class="sw" style="background:linear-gradient(135deg,#25252e,#17171d)"></span>'+L.a+'</button></div>';
      d.querySelectorAll("button").forEach(function(b){ b.addEventListener("click",function(){ window.chSetTheme(b.getAttribute("data-t")); }); });
      host.insertBefore(d, host.firstChild);
      apply(cur());
    }catch(e){}
  }

  function newLogo(){
    try{
      var br=document.querySelector(".sb-brand");
      if(!br || br.querySelector("#ch-nlogo")) return;
      br.innerHTML='<svg id="ch-nlogo" width="34" height="34" viewBox="0 0 512 512"><path fill="#fff" d="M156 96 h200 a56 56 0 0 1 56 56 v150 a56 56 0 0 1 -56 56 h-92 l-70 62 v-62 h-38 a56 56 0 0 1 -56 -56 v-150 a56 56 0 0 1 56 -56 z"/><text x="256" y="196" text-anchor="middle" dominant-baseline="central" font-family="Inter,Arial" font-weight="800" font-size="150" letter-spacing="-8"><tspan fill="#0e2758">C</tspan><tspan fill="#cf9f42">H</tspan></text></svg>'+
        '<div class="ch-nname">Chat<span class="x">Help</span></div>';
    }catch(e){}
  }

  function tick(){ injectSwitcher(); newLogo(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1500); });
  else { tick(); setInterval(tick,1500); }
})();}catch(e){}
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "✓ CH_NAVY_THEME eklendi: lacivert varsayilan tema + antrasit secenegi + yeni logo + premium polish\n";
echo "Test: gizli pencerede ac -> arka plan lacivert olmali; Konto'da 🎨 tema secici gorunmeli.\n";
