<?php
/**
 * ChatHelp — apply-lang-unify (CH_LANG_UNIFY)
 *  Dil/hukuk seciminin KALICI ve TUTARLI olmasi:
 *   [1] FR katmaninin kosulsuz dil zorlamasini kaldirir (elle secime saygi: ch_lm)
 *   [2] FR setCountry kancasindaki kosulsuz ch_uilang=fr yazimini ch_lm ile korur
 *   [3] Boot-birlestirici: elle secilen dil (ch_lm+ch_uilang) HER katmani yener;
 *       UL <-> ch_uilang tek kaynaga baglanir; forcer'lara karsi periyodik bekci.
 *  Kurallar: dil secimi -> Interface Sprache o dil olur ve KALIR.
 *            hukuk ulkesi secimi -> KALIR (geo/katman ezemez).
 * KULLANIM: pull-updates.php?files=apply-lang-unify.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lang-unify BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_LANG_UNIFY') !== false) exit("Zaten ekli (CH_LANG_UNIFY).\n");
file_put_contents($file . '.bak-langunify-' . date('Ymd-His'), $src);

/* ── [1] FR periyodik zorlamasi: ch_lm'e saygi ── */
$a1 = 'if(typeof CC!=="undefined" && CC==="FR"){ if(localStorage.getItem("ch_uilang")!=="fr"){';
$n1 = 'if(typeof CC!=="undefined" && CC==="FR" && !localStorage.getItem("ch_lm")){ if(localStorage.getItem("ch_uilang")!=="fr"){';
$src2 = str_replace($a1, $n1, $src, $c1);
if ($c1) { $src = $src2; echo "  ✓ [1] FR periyodik zorlama ch_lm korumasi aldi ($c1)\n"; }
else echo "  - [1] anchor yok (baska bicimde olabilir — bekci yine koruyacak)\n";

/* ── [2] FR setCountry kancasi: kosulsuz ch_uilang=fr -> ch_lm korumali ── */
$a2 = 'try{ if(cc==="FR"){ localStorage.setItem("ch_uilang","fr"); } }catch(e){}';
$n2 = 'try{ if(cc==="FR" && !localStorage.getItem("ch_lm")){ localStorage.setItem("ch_uilang","fr"); } }catch(e){}';
$src2 = str_replace($a2, $n2, $src, $c2);
if ($c2) { $src = $src2; echo "  ✓ [2] FR setCountry kancasi ch_lm korumasi aldi ($c2)\n"; }
else echo "  - [2] anchor yok (bekci koruyacak)\n";

/* ── [3] boot-birlestirici + bekci ── */
$bundle = <<<'HTML'
<script id="ch-lang-unify">
/* CH_LANG_UNIFY — tek hakikat kaynagi: elle secim (ch_lm) her seyi yener ve kalicidir */
try{(function(){
  function fix(){
    try{
      var lm=null,uil=null,lu=null;
      try{ lm=localStorage.getItem("ch_lm"); uil=localStorage.getItem("ch_uilang"); lu=localStorage.getItem("ch_lang_user"); }catch(e){}
      if(lm && lu){
        /* elle secilmis dil: forcer ch_uilang'i degistirdiyse geri al */
        if(uil!==lu){ try{ localStorage.setItem("ch_uilang", lu); }catch(e){} uil=lu; }
        /* cekirdek UL da ayni dile cekilir (UI_TEXTS metinleri dahil) */
        if(typeof UL!=="undefined" && UL!==lu){ UL=lu; try{ savePref(); }catch(e){} try{ applyUI(); }catch(e){} }
      } else if(!uil && typeof UL!=="undefined" && UL){
        try{ localStorage.setItem("ch_uilang", UL); }catch(e){}
      }
    }catch(e){}
  }
  function wrapSetLang(){
    try{
      if(typeof setLang!=="function" || setLang.__unify) return;
      var _s=setLang;
      var w=function(l){
        try{ localStorage.setItem("ch_lang_user", l); }catch(e){}
        return _s.apply(this,arguments);
      };
      w.__unify=1; setLang=w; if(typeof window!=="undefined") window.setLang=w;
    }catch(e){}
  }
  /* mevcut durumdan tohumla: daha once elle dil secilmisse kaydi olustur */
  try{ if(localStorage.getItem("ch_lm") && !localStorage.getItem("ch_lang_user") && localStorage.getItem("ch_uilang")){ localStorage.setItem("ch_lang_user", localStorage.getItem("ch_uilang")); } }catch(e){}
  function boot(){ wrapSetLang(); fix(); setInterval(function(){ wrapSetLang(); fix(); }, 900); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "  ✓ [3] boot-birlestirici + bekci eklendi\n";
echo "\n✓ CH_LANG_UNIFY uygulandi.\n";
echo "Test senaryolari:\n";
echo "  1) Dil menusunden Türkçe sec -> yenile/kapat-ac -> HALA Türkçe olmali.\n";
echo "  2) Hukuk ulkesi Fransa sec + dil Türkçe sec -> arayuz Türkçe KALMALI (fr'ye sicramamali).\n";
echo "  3) Ulke degistir -> kapat-ac -> ulke ayni kalmali.\n";
