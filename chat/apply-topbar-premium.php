<?php
/**
 * ChatHelp — apply-topbar-premium (CH_TOPBAR_PREMIUM)
 *  1) "Online" yazisi + yesil nokta kaldirilir (.tb-online gizlenir)
 *  2) Sol menudeki "Chat" ogesi "⚖️ Fall eröffnen"in ALTINA tasinir (DOM reorder)
 *  3) Ust bar (#tb) premium polish: golden hover/active, tutarli spacing
 * KULLANIM: pull-updates.php?files=apply-topbar-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-topbar-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_TOPBAR_PREMIUM') !== false) exit("Zaten ekli (CH_TOPBAR_PREMIUM).\n");
file_put_contents($file . '.bak-topbarprem-' . date('Ymd-His'), $src);

$bundle = <<<'TBHTML'
<style id="ch-topbar-premium-css">
/* CH_TOPBAR_PREMIUM */
.tb-online{display:none !important}
#tb{border-bottom:1px solid rgba(212,168,74,.14) !important}
.tb-country-btn{transition:border-color .15s,background .15s,transform .12s !important}
.tb-country-btn:hover{border-color:rgba(212,168,74,.5) !important;background:rgba(212,168,74,.06) !important}
.tb-name{letter-spacing:-.3px}
#tb-auth-btn{transition:transform .12s,box-shadow .2s !important}
#tb-auth-btn:hover{transform:translateY(-1px)}
.tb-ham span{background:var(--t2,#e0e0ff) !important}
.tb-ham:hover span{background:var(--gold,#d4a84a) !important}
</style>
<script id="ch-topbar-premium">
/* CH_TOPBAR_PREMIUM JS — Chat navini "Fall eröffnen"in altina tasi (idempotent) */
try{(function(){
  function moveChatNav(){
    try{
      var navs=document.querySelectorAll(".sb-nav-item");
      var chatNav=null;
      navs.forEach(function(n){ if(!chatNav && n.getAttribute("onclick")==="NC()" && /Chat/.test(n.textContent||"")) chatNav=n; });
      if(!chatNav) return;
      var photoBtns=document.querySelectorAll(".sb-photo-btn");
      var fallBtn=null;
      photoBtns.forEach(function(b){ if(!fallBtn && /Fall\s*er[öo]ffnen/i.test(b.textContent||"")) fallBtn=b; });
      if(!fallBtn) return;
      if(chatNav.previousElementSibling===fallBtn) return; /* zaten dogru yerde */
      fallBtn.parentNode.insertBefore(chatNav, fallBtn.nextSibling);
    }catch(e){}
  }
  function tick(){ moveChatNav(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1500); });
  else { tick(); setInterval(tick,1500); }
})();}catch(e){}
</script>
TBHTML;

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
echo "✓ CH_TOPBAR_PREMIUM uygulandi: Online yazisi gizlendi, Chat navi Fall eröffnen altina tasindi, ust bar polish.\n";
