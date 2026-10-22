<?php
/**
 * ChatHelp — PREMİUM CİLA 2 (derin görsel kalite: tipografi, modal, fiyat kartları,
 * kaydırma çubukları, odak halkaları, seçim rengi) — antrasit taban AYNEN korunur.
 * apply-premium-skin ile aynı güvenli desen: sınıf tanımlarına dokunmadan <style> katmanı.
 * KULLANIM: pull-updates.php?files=apply-premium-skin2.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-premium-skin2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_PREMIUM_SKIN2")!==false) exit("Zaten ekli (CH_PREMIUM_SKIN2).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-skin2-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PREMIUM_SKIN2 — derin premium cila (antrasit + altın, sınıf tanımlarına dokunmaz) */
try{(function(){
  function addSkin2(){
    if(document.getElementById("ch-premium-skin2")) return;
    var st=document.createElement("style"); st.id="ch-premium-skin2";
    st.textContent=[
      /* Seçim & odak — marka hissi */
      "::selection{background:rgba(212,168,74,.32);color:#fff}",
      "button:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible{outline:2px solid rgba(212,168,74,.55)!important;outline-offset:2px}",
      /* Kaydırma çubukları */
      "*::-webkit-scrollbar{width:9px;height:9px}",
      "*::-webkit-scrollbar-track{background:transparent}",
      "*::-webkit-scrollbar-thumb{background:linear-gradient(180deg,rgba(212,168,74,.28),rgba(212,168,74,.12));border-radius:8px;border:2px solid rgba(0,0,0,.35)}",
      "*::-webkit-scrollbar-thumb:hover{background:rgba(212,168,74,.45)}",
      /* Mesaj balonları — daha rafine */
      ".msg.user .bbl{background:linear-gradient(135deg,var(--s2,#131325),var(--s3,#191930))!important;border-color:rgba(212,168,74,.18)!important;box-shadow:0 2px 10px rgba(0,0,0,.25)}",
      ".bbl a{color:var(--gold,#d4a84a)}",
      /* Fiyat kartları — vitrin */
      ".pc{transition:transform .18s,border-color .18s,box-shadow .18s}",
      ".pc:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.4)!important;box-shadow:0 12px 32px rgba(0,0,0,.4)}",
      ".pc.feat{box-shadow:0 8px 28px rgba(212,168,74,.12)}",
      ".pc-btn.g{box-shadow:0 4px 14px rgba(212,168,74,.3)}",
      ".pc-btn.g:hover{box-shadow:0 6px 20px rgba(212,168,74,.45);filter:brightness(1.06)}",
      /* Modallar */
      ".pm-inner{border:1px solid rgba(212,168,74,.22)!important;box-shadow:0 24px 80px rgba(0,0,0,.6)}",
      ".lm-box{border:1px solid rgba(212,168,74,.15)}",
      /* Konto/ayar satırları */
      ".krow{transition:background .15s}",
      ".krow:hover{background:rgba(255,255,255,.025)}",
      ".ksec{border:1px solid rgba(212,168,74,.1);border-radius:14px;overflow:hidden}",
      ".co.on{box-shadow:inset 0 0 0 1px rgba(212,168,74,.4)}",
      ".lang-opt.on{box-shadow:inset 0 0 0 1px rgba(212,168,74,.4)}",
      /* Üst bar ülke düğmesi */
      ".tb-country-btn{border-color:rgba(212,168,74,.22)!important}",
      ".tb-country-btn:hover{border-color:rgba(212,168,74,.5)!important;box-shadow:0 0 0 3px rgba(212,168,74,.08)}",
      /* Giriş alanı & ek düğmeleri */
      "#pb:hover{border-color:rgba(212,168,74,.55)!important;box-shadow:0 0 0 3px rgba(212,168,74,.08)}",
      /* Plan kutusu & marka */
      ".sb-plan{border-color:rgba(212,168,74,.3)!important}",
      ".sb-brand .sb-mark{transition:box-shadow .2s}",
      ".sb-brand:hover .sb-mark{box-shadow:0 4px 16px rgba(212,168,74,.35)}",
      /* Belge sonucu kalite şeridi */
      ".rqual{border:1px solid rgba(212,168,74,.18);border-radius:10px;padding:8px 12px;background:linear-gradient(135deg,rgba(212,168,74,.06),transparent)}",
      /* Yumuşak görünüm */
      "img,svg{shape-rendering:auto}",
      ".wcat,.ccard,.pc,.ksec,.pm-inner{backdrop-filter:saturate(1.05)}"
    ].join("\n");
    document.head.appendChild(st);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",addSkin2); else addSkin2();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Premium cila 2 eklendi (CH_PREMIUM_SKIN2): tipografi/odak/scrollbar/modal/fiyat kartlari/ayar panelleri.\n"; }
else exit("degisiklik yok!\n");
echo "SIL: rm apply-premium-skin2.php\n";
echo "Test: gizli pencere -> fiyat karti hover'da yukselir + altin cerceve; scrollbar altin tonlu; secim rengi altin.\n";
