<?php
/**
 * ChatHelp — SOL MENÜ NAVİGASYON İKONLARI PREMİUM DOKUNUŞ
 * -----------------------------------------------------------------
 *  dump-fall-ui çıktısındaki BİREBİR .sb-nav-item / .sb-photo-t / .sb-photo-s
 *  sınıf tanımlarına altın uyumlu aktif/hover durumu ekler (JS ile <style>
 *  enjekte, mevcut sınıf tanımlarına dokunmaz — apply-premium-skin ile aynı desen).
 * KULLANIM: pull-updates.php?files=apply-nav-skin.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-nav-skin BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_NAV_SKIN")!==false) exit("Zaten ekli (CH_NAV_SKIN).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-navskin-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_NAV_SKIN — sol menü navigasyon ikonları: altın uyumlu premium dokunuş */
try{(function(){
  function addNavSkin(){
    if(document.getElementById("ch-nav-skin")) return;
    var st=document.createElement("style"); st.id="ch-nav-skin";
    st.textContent=[
      ".sb-nav-item{border:1px solid transparent;transition:all .16s}",
      ".sb-nav-item:hover{border-color:rgba(212,168,74,.16)}",
      ".sb-nav-item.active{background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.03))!important;border-color:rgba(212,168,74,.28)}",
      ".sb-photo-t{color:#fff}",
      ".sb-mark{box-shadow:0 2px 10px rgba(212,168,74,.2),inset 0 1px 0 rgba(255,255,255,.06)}"
    ].join("\n");
    document.head.appendChild(st);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",addNavSkin); else addNavSkin();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Nav ikon premium dokunusu eklendi (CH_NAV_SKIN).\n"; }
else echo "degisiklik yok\n";
echo "SIL: rm apply-nav-skin.php\n";
echo "Test: sol menude aktif ogenin (orn. 'Neues Gespraech') altin cercevesi olmali, hover'da hafif altin kenar.\n";
