<?php
/**
 * ChatHelp — apply-vertrag-home (CH_VERTRAG_HOME)
 *  Vertrag Studio sozlesmelerini ana sayfada da (buğulu ornek kartlar
 *  halinde, 4'lu izgara) gosterir; bir karta tiklaninca DOGRUDAN o
 *  sozlesme Vertrag Studio modulunde acilir. En fazla 16 sozlesme ana
 *  sayfada; gerisi yalnizca modulde.
 *   • Ucret odenmemisse kartlar buğulu + kilitli (icerik okunmaz).
 *   • Almanya disi hukuk secildiğinde ana sayfa vitrini de gizlenir.
 *  Once Vertrag Studio kapatmasindan CT/buildPages/renderStep'i window'a
 *  acar (cerrahi str_replace), sonra ana-sayfa vitrin script'ini ekler.
 *  GEREKLI: apply-contract-studio.php (CH_VST).
 * KULLANIM: pull-updates.php?files=apply-vertrag-home.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vertrag-home BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VERTRAG_HOME') !== false) exit("Zaten ekli (CH_VERTRAG_HOME).\n");
if (strpos($src, 'CH_VST') === false) exit("HATA: Vertrag Studio (CH_VST) yok — once apply-contract-studio.php.\n");
file_put_contents($file . '.bak-vtrhome2-' . date('Ymd-His'), $src);

/* ── 1) Vertrag Studio icinden CT/buildPages/renderStep'i disari ac ── */
$oldBoot = 'function boot(){ addCss(); addNav(); if(!document.getElementById("pnl-vst")){ panel(); renderCat(); } }';
$newBoot = 'function boot(){ addCss(); addNav();'
  . ' try{window.__vstOpen=function(k){try{if(typeof gP==="function")gP("vst");CUR=k;STEP=0;renderStep();}catch(e){}};'
  . 'window.__vstList=function(){return CT;};'
  . 'window.__vstThumb=function(k){try{return (buildPages(k,{})||[])[0]||"";}catch(e){return"";}};}catch(e){}'
  . ' if(!document.getElementById("pnl-vst")){ panel(); renderCat(); } }';
$c1=0; $src=str_replace($oldBoot,$newBoot,$src,$c1);
if($c1!==1) exit("HATA: Vertrag Studio boot() anchor tam 1 kez eslesmedi (c=$c1) — degistirilmedi.\n");
echo "  ✓ [1] Vertrag Studio API window'a acildi (__vstOpen/__vstList/__vstThumb)\n";

/* ── 2) ana sayfa vitrin script'i + CSS ── */
$bundle = <<<'VHHTML'
<style id="ch-vertrag-home-css">
#ch-vst-home{margin:16px 0}
#ch-vst-home .vh-head{font-size:13px;font-weight:800;color:var(--gold,#d4a84a);letter-spacing:.4px;margin-bottom:10px;display:flex;align-items:center;gap:7px}
#ch-vst-home .vh-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
@media(max-width:760px){#ch-vst-home .vh-grid{grid-template-columns:repeat(2,1fr)}}
#ch-vst-home .vh-card{border:1px solid rgba(212,168,74,.2);border-radius:12px;overflow:hidden;background:var(--s2,#25252e);cursor:pointer;transition:transform .15s,border-color .2s,box-shadow .2s;position:relative}
#ch-vst-home .vh-card:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.7);box-shadow:0 10px 26px rgba(0,0,0,.32)}
#ch-vst-home .vh-thumb{height:98px;overflow:hidden;position:relative;background:#fff}
#ch-vst-home .vh-thumb .vsA4{transform:scale(0.2);transform-origin:top left;position:absolute;top:0;left:50%;margin-left:-79px;box-shadow:none}
#ch-vst-home .vh-card.locked .vh-thumb .vsA4{filter:blur(4px)}
#ch-vst-home .vh-card .vh-lock{position:absolute;top:0;left:0;right:0;height:98px;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,rgba(20,20,26,.26),rgba(20,20,26,.5));color:#fff;font-size:19px;filter:drop-shadow(0 2px 5px rgba(0,0,0,.5))}
#ch-vst-home .vh-n{font-size:11.5px;font-weight:700;color:#fff;padding:8px 9px 9px;line-height:1.25}
body.ch-cc-nonde #ch-vst-home{display:none !important}
</style>
<script id="ch-vertrag-home">
/* CH_VERTRAG_HOME — ana sayfa sozlesme vitrini (max 16 -> modul) */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var TTL={de:"Verträge",tr:"Sözleşmeler",en:"Contracts",fr:"Contrats",es:"Contratos",it:"Contratti",nl:"Contracten",pl:"Umowy",sv:"Avtal",pt:"Contratos"};
  function ccIsDE(){ try{ return ((typeof CC!=="undefined"&&CC)||"DE")==="DE"; }catch(e){ return true; } }
  function locked(){ var pl=""; try{ pl=(window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||""; }catch(e){} return ["basic","pro","elite"].indexOf(pl)<0; }
  var lastLock=null;
  function build(){
    try{
      if(!ccIsDE()) return;
      if(typeof window.__vstList!=="function"||typeof window.__vstThumb!=="function") return;
      var CTd=window.__vstList()||{}; var keys=Object.keys(CTd).slice(0,16);
      if(!keys.length) return;
      var t0=window.__vstThumb(keys[0]); if(!t0) return; /* modul henuz hazir degil */
      var lk=locked();
      var ex=document.getElementById("ch-vst-home");
      if(ex){ if(lk===lastLock) return; ex.parentNode.removeChild(ex); } /* plan degistiyse yeniden kur */
      lastLock=lk;
      var host=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label"); if(!host||!host.parentNode) return;
      var sec=document.createElement("div"); sec.id="ch-vst-home";
      sec.innerHTML='<div class="vh-head">📑 '+esc(TTL[UIL()]||TTL.en)+'</div><div class="vh-grid"></div>';
      var grid=sec.querySelector(".vh-grid");
      keys.forEach(function(k){
        var c=CTd[k]||{}; var thumb=window.__vstThumb(k)||"";
        var card=document.createElement("div"); card.className="vh-card"+(lk?" locked":"");
        card.innerHTML='<div class="vh-thumb">'+thumb+(lk?'<div class="vh-lock">🔒</div>':'')+'</div><div class="vh-n">'+esc(c.n||k)+'</div>';
        card.addEventListener("click",function(){ try{ if(window.__vstOpen) window.__vstOpen(k); }catch(e){} });
        grid.appendChild(card);
      });
      host.parentNode.insertBefore(sec, host);
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ build(); setInterval(build,1200); });
  else { build(); setInterval(build,1200); }
})();}catch(e){}
</script>
VHHTML;

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
echo "\n✓ CH_VERTRAG_HOME uygulandi. Ana sayfada 4'lu buğulu sozlesme vitrini; tiklayinca modulde acilir (max 16).\n";
