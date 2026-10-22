<?php
/**
 * ChatHelp — apply-premium-ui2 (CH_PREMIUM_UI2)
 *  GERCEK markup hedefleriyle premium sidebar:
 *   • .sb-logo -> CH balon logosu + ChatHelp yazi markasi + "PREMIUM LEGAL AI" alt yazi
 *   • .sb-new  -> altin gradyan premium buton
 *   • .sb-nav-item -> rafine spacing, aktif ogede altin ic cizgi, hover
 *   • .sb-country / .sb-docs-label / .sb-photo-btn -> premium dokunuslar
 * KULLANIM: pull-updates.php?files=apply-premium-ui2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-premium-ui2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_PREMIUM_UI2') !== false) exit("Zaten ekli (CH_PREMIUM_UI2).\n");
file_put_contents($file . '.bak-premui2-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<style id="ch-premium-ui2">
/* CH_PREMIUM_UI2 — premium sidebar (gercek siniflar: .sb-logo/.sb-new/.sb-nav-item) */
.sb-head{ padding:16px 12px 10px !important }
.sb-logo{ display:flex !important; align-items:center; gap:10px; padding:6px 8px; border-radius:12px; cursor:pointer }
.sb-logo:hover{ background:rgba(212,168,74,.07) }
#ch-nlogo2{ flex-shrink:0; filter:drop-shadow(0 3px 8px rgba(0,0,0,.35)) }
.ch-lg-name{ font-size:19px; font-weight:800; letter-spacing:-.4px; color:#fff; line-height:1 }
.ch-lg-name .x{ background:linear-gradient(135deg,#d4a84a,#f0c860); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent }
.ch-lg-sub{ font-size:8.5px; letter-spacing:1.8px; color:var(--t4); text-transform:uppercase; margin-top:4px; font-weight:700 }
.sb-country{ border:1px solid var(--bdr) !important; background:rgba(255,255,255,.045) !important; border-radius:12px !important; transition:border-color .15s }
.sb-country:hover{ border-color:var(--gb) !important }
.sb-new{ background:linear-gradient(135deg,#d4a84a,#ecc060) !important; color:#1a1400 !important; font-weight:800 !important;
  border:none !important; border-radius:13px !important; box-shadow:0 8px 22px rgba(212,168,74,.28) !important;
  transition:transform .12s ease, box-shadow .2s ease !important }
.sb-new:hover{ transform:translateY(-1px); box-shadow:0 12px 30px rgba(212,168,74,.38) !important }
.sb-new svg{ stroke:#1a1400 !important }
.sb-nav-item{ display:flex; align-items:center; border-radius:12px !important; font-weight:600 !important;
  transition:background .15s, box-shadow .15s !important }
.sb-nav-item svg{ width:19px; height:19px; opacity:.85 }
.sb-nav-item:hover{ background:rgba(212,168,74,.07) !important }
.sb-nav-item.active{ color:#fff !important; background:linear-gradient(135deg,rgba(212,168,74,.13),rgba(212,168,74,.04)) !important;
  box-shadow:inset 3px 0 0 var(--gold) !important }
.sb-nav-item.active svg{ opacity:1; stroke:var(--gold2) }
.sb-docs-label{ font-size:10px !important; letter-spacing:1.6px !important; text-transform:uppercase !important;
  color:var(--gold) !important; opacity:.9; font-weight:800 !important }
.sb-photo-btn{ border:1px solid rgba(212,168,74,.28) !important; border-radius:13px !important;
  background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02)) !important; transition:border-color .15s }
.sb-photo-btn:hover{ border-color:var(--gold) !important }
</style>
<script id="ch-premium-ui2-js">
/* CH_PREMIUM_UI2 JS — .sb-logo icine yeni CH balon logosu (dogru hedef) */
try{(function(){
  function newLogo2(){
    try{
      var lg=document.querySelector(".sb-logo");
      if(!lg || lg.querySelector("#ch-nlogo2")) return;
      lg.innerHTML='<svg id="ch-nlogo2" width="34" height="34" viewBox="0 0 512 512"><path fill="#fff" d="M156 96 h200 a56 56 0 0 1 56 56 v150 a56 56 0 0 1 -56 56 h-92 l-70 62 v-62 h-38 a56 56 0 0 1 -56 -56 v-150 a56 56 0 0 1 56 -56 z"/><text x="256" y="196" text-anchor="middle" dominant-baseline="central" font-family="Inter,Arial" font-weight="800" font-size="150" letter-spacing="-8"><tspan fill="#0e2758">C</tspan><tspan fill="#cf9f42">H</tspan></text></svg>'+
        '<div><div class="ch-lg-name">Chat<span class="x">Help</span></div><div class="ch-lg-sub">Premium · Legal AI</div></div>';
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ newLogo2(); setInterval(newLogo2,1200); });
  else { newLogo2(); setInterval(newLogo2,1200); }
})();}catch(e){}
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "✓ CH_PREMIUM_UI2 uygulandi: yeni logo (.sb-logo) + altin 'Neu' butonu + premium nav.\n";
echo "Test: sidebar'da balonlu CH logosu + 'PREMIUM · LEGAL AI' alt yazisi gorunmeli; aktif menude altin cizgi.\n";
