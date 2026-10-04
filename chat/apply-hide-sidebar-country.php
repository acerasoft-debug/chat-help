<?php
/**
 * ChatHelp — SOL MENÜDEKİ TEKRARLI ÜLKE GÖSTERİMİNİ GİZLE
 * -----------------------------------------------------------------
 *  Teşhis: sidebar'da (sol menü) "🇩🇪 Deutschland ›" gösterimi (#sb-country)
 *  ile üst bardaki (sağ üst) ülke düğmesi (#tb-flag / "Land wechseln") AYNI
 *  işlevi (ülke değiştirme) tekrar ediyor. Kullanıcı: sol menüdeki fazlalık
 *  olsun istemiyor.
 *  Güvenli yöntem: HTML/onclick'e dokunmadan sadece CSS ile gizler (geri
 *  alınabilir, hiçbir fonksiyon bozulmaz — üst bar üzerinden ülke değiştirme
 *  tıklanabilir kalır).
 * KULLANIM: pull-updates.php?files=apply-hide-sidebar-country.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-hide-sidebar-country BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_HIDE_SB_COUNTRY")!==false) exit("Zaten ekli (CH_HIDE_SB_COUNTRY).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-hidesbc-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_HIDE_SB_COUNTRY — sol menudeki tekrarli ulke gosterimini gizle (ust bar yeterli) */
try{(function(){
  function addCss(){
    if(document.getElementById("ch-hide-sbc")) return;
    var st=document.createElement("style"); st.id="ch-hide-sbc";
    st.textContent=".sb-country{display:none!important}";
    document.head.appendChild(st);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",addCss); else addCss();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Sol menu ulke gosterimi gizlendi (CH_HIDE_SB_COUNTRY).\n"; }
else exit("degisiklik yok!\n");
echo "SIL: rm apply-hide-sidebar-country.php\n";
echo "Test: sol menude artik bayrak/ulke-adi satiri gorunmemeli. Ust bardaki (sag ust) bayrak duzenden kaldi,\n";
echo "      oradan tiklayip ulke degistirebilirsin.\n";
