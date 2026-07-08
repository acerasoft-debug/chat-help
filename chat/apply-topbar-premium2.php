<?php
/**
 * ChatHelp — apply-topbar-premium2 (CH_TOPBAR_PREM2)
 *  1) AKTIF PROFIL ADI: ust barda "👤 <profil adi>" cipi — secili gonderen
 *     profilini (ch_profiles/ch_prof_active) canli gosterir; tiklayinca Konto.
 *  2) PREMIUM UST BAR: menu ikonu, ulke, Anmelden, Anträge, Online — tutarli
 *     pill butonlar, altin vurgu, hover, nabiz atan Online noktasi. Bilinen
 *     siniflar (.tb-r/.tb-country-btn/#tb-auth-btn/.tb-btn/.tb-online) +
 *     menu ikonu calisma-aninda bulunur (kesin selector'a bagli degil).
 * KULLANIM: pull-updates.php?files=apply-topbar-premium2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-topbar-premium2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TOPBAR_PREM2')!==false) exit("Zaten ekli (CH_TOPBAR_PREM2).\n");

$bundle = <<<'HTML'
<style id="ch-tbp2-css">
/* CH_TOPBAR_PREM2 — premium ust bar */
.tb-r{gap:8px !important;align-items:center !important}
.tb-country-btn,#tb-auth-btn,.tb-btn{
  height:36px !important;border-radius:12px !important;
  border:1px solid rgba(212,168,74,.30) !important;
  background:linear-gradient(180deg,rgba(212,168,74,.10),rgba(212,168,74,.04)) !important;
  transition:transform .12s ease,border-color .15s ease,background .15s ease,box-shadow .15s ease !important;
  -webkit-tap-highlight-color:transparent}
.tb-country-btn:hover,#tb-auth-btn:hover,.tb-btn:hover{
  border-color:rgba(212,168,74,.60) !important;
  box-shadow:0 3px 14px rgba(212,168,74,.14) !important;transform:translateY(-1px)}
.tb-country-btn:active,#tb-auth-btn:active,.tb-btn:active{transform:scale(.97)}
.tb-btn{width:36px;display:flex !important;align-items:center;justify-content:center}
.tb-btn>svg{width:18px !important;height:18px !important;stroke:var(--gold,#d4a84a) !important}
#tb-auth-icon{stroke:var(--gold,#d4a84a) !important}
/* nabiz atan online noktasi */
.tb-dot{box-shadow:0 0 0 0 rgba(70,190,120,.55);animation:chDot 1.8s infinite}
@keyframes chDot{0%{box-shadow:0 0 0 0 rgba(70,190,120,.5)}70%{box-shadow:0 0 0 7px rgba(70,190,120,0)}100%{box-shadow:0 0 0 0 rgba(70,190,120,0)}}
/* mobil menu ikonu — premium cerceve */
.ch-tb-menu{border-radius:12px !important;border:1px solid rgba(212,168,74,.28) !important;
  background:linear-gradient(180deg,rgba(212,168,74,.10),rgba(212,168,74,.03)) !important;
  transition:border-color .15s ease,transform .12s ease;padding:6px !important}
.ch-tb-menu:hover{border-color:rgba(212,168,74,.55) !important;transform:translateY(-1px)}
.ch-tb-menu:active{transform:scale(.95)}
/* aktif profil cipi */
#ch-actprof{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 12px;
  border-radius:12px;cursor:pointer;font-size:12px;font-weight:700;color:#fff;
  border:1px solid rgba(212,168,74,.45);
  background:linear-gradient(135deg,rgba(212,168,74,.20),rgba(212,168,74,.06));
  max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  transition:transform .12s ease,box-shadow .15s ease}
#ch-actprof:hover{box-shadow:0 3px 14px rgba(212,168,74,.18);transform:translateY(-1px)}
#ch-actprof .ap-ic{font-size:13px;flex-shrink:0}
#ch-actprof .ap-n{overflow:hidden;text-overflow:ellipsis}
@media (max-width:520px){ #ch-actprof{max-width:104px;padding:0 9px} #ch-actprof .ap-n{max-width:64px} }
</style>
<script id="ch-tbp2-js">
/* CH_TOPBAR_PREM2 — aktif profil cipi + menu ikonu estetigi */
try{(function(){
  function actProfileName(){
    try{
      var a=JSON.parse(localStorage.getItem("ch_profiles")||"null");
      if(!a||!a.length) return null;
      var i=parseInt(localStorage.getItem("ch_prof_active")||"0",10); if(isNaN(i)||i<0||i>=a.length) i=0;
      return (a[i]&&a[i].name)?String(a[i].name):null;
    }catch(e){ return null; }
  }
  function loggedIn(){ try{ return !!localStorage.getItem("ch_token"); }catch(e){ return false; } }
  function tbR(){ return document.querySelector(".tb-r"); }

  function syncChip(){
    try{
      var r=tbR(); if(!r) return;
      var nm=actProfileName();
      var chip=document.getElementById("ch-actprof");
      if(!nm || !loggedIn()){ if(chip) chip.style.display="none"; return; }
      if(!chip){
        chip=document.createElement("div"); chip.id="ch-actprof"; chip.title="Aktif profil — değiştir";
        chip.innerHTML='<span class="ap-ic">👤</span><span class="ap-n"></span>';
        chip.addEventListener("click",function(){ try{ gP("konto"); }catch(e){} });
        r.insertBefore(chip, r.firstChild);
      }
      chip.style.display="inline-flex";
      var n=chip.querySelector(".ap-n"); if(n && n.textContent!==nm) n.textContent=nm;
    }catch(e){}
  }

  function decorateMenu(){
    try{
      /* mobil menu ikonu: sidebar acan eleman */
      var m=document.querySelector('[onclick*="oSB"],[onclick*="openSidebar"],[onclick*="toggleSidebar"],[onclick*="toggleSb"],.tb-menu,.tb-burger');
      if(m && !m.classList.contains("ch-tb-menu")) m.classList.add("ch-tb-menu");
    }catch(e){}
  }
  function tick(){ syncChip(); decorateMenu(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick, 1000);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ aktif profil cipi + premium ust bar eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'tp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-tbp2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_TOPBAR_PREM2 uygulandi. Ust barda aktif profil adi + premium ikonlar.\n";
