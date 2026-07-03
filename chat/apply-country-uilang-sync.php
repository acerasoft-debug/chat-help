<?php
/**
 * ChatHelp — #2 TAMAMLAMA: ULKE SECILINCE TUM SITE O DILE DONER
 * ------------------------------------------------------------------------
 *  Kullanici kurali: "ulke/hukuk secimi TUM siteyi degistirmeli (katalog,
 *  belge dili, hukuk, bayrak) — sadece dil secimi menu metnini degistirir."
 *
 *  Mevcut durum (dump kaniti): CH_LANG_LAW_SEP, setCountry'de arayuz dilini
 *  SADECE ch_lm (manuel dil kilidi) yoksa ayarliyordu. Kullanici daha once
 *  elle bir dil sectiyse ch_lm='1' olur ve ULKE degistirince arayuz Almanca
 *  KALIYORDU -> Anträge butonlari (Behalten/Löschen), kenar cubugu vs. Almanca.
 *
 *  Duzeltme (CH_CC_UILANG): setCountry SARILIR (bagimsiz, kendi isareti). Ulke
 *  secilince ch_uilang = o ulkenin belge diline ZORLANIR ve ch_lm kilidi
 *  KALDIRILIR. CH_LANG_LAW_SEP zaten setCountry sonrasi sayfayi yeniliyor;
 *  yenileme sonrasi i18n katmani yeni ch_uilang'i okuyup TUM arayuzu (menu,
 *  Anträge, basliklar, butonlar) o dile cevirir. Belge dili/hukuk zaten
 *  ulkeye bagli. Boylece ulke = butun site; dil = sadece menu.
 *
 * KULLANIM: pull-updates.php?files=apply-country-uilang-sync.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-country-uilang-sync BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_CC_UILANG")!==false) exit("Zaten ekli (CH_CC_UILANG).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-ccuilang-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_CC_UILANG — ulke secilince arayuz dili de o ulkeye doner (tum site degisir) */
try{(function(){
  var DOCLANG={DE:"de",AT:"de",CH:"de",FR:"fr",BE:"fr",LU:"fr",IT:"it",ES:"es",NL:"nl",TR:"tr",GB:"en",US:"en",PL:"pl",SE:"sv",PT:"pt"};
  /* i18n katmaninin desteledigi arayuz dilleri (translate.php) */
  var UISUP={tr:1,ru:1,ar:1,en:1,fr:1,es:1,it:1,pl:1,nl:1,sv:1,pt:1,de:1};
  function wrapSC(){
    try{
      if(typeof setCountry!=="function" || setCountry.__ccuilang) return;
      var _sc=setCountry;
      var w=function(cc){
        try{
          var dl=DOCLANG[cc]||"de";
          if(UISUP[dl]){
            /* ulke = otorite: manuel dil kilidini kaldir, arayuz dilini ulkeye esitle */
            try{ localStorage.removeItem("ch_lm"); }catch(e){}
            try{ localStorage.setItem("ch_uilang", dl); }catch(e){}
            try{ if(typeof UL!=="undefined") UL=dl; }catch(e){}
          }
        }catch(e){}
        return _sc.apply(this,arguments); /* CH_LANG_LAW_SEP burada reload'i tetikler */
      };
      w.__ccuilang=1; setCountry=w; if(typeof window!=="undefined") window.setCountry=w;
    }catch(e){}
  }
  var t=0; function run(){ wrapSC(); if(t++<50) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-ccul"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Ulke->arayuz dili senkronu eklendi (CH_CC_UILANG).\n";
echo "  Artik ULKE secilince: belge dili + hukuk + bayrak + ARAYUZ DILI hepsi o ulkeye doner.\n";
echo "  Sadece DIL secilince: yalnizca menu metni degisir (onceki davranis korunur).\n";
echo "SIL: rm apply-country-uilang-sync.php\n";
echo "Test: once elle Interface-Sprache'den bir dil sec (ch_lm kurulur) -> sonra Turkiye SEC ->\n";
echo "      sayfa yenilenince kenar cubugu, Anträge (Sakla/Sil), basliklar TAMAMEN Turkce olmali.\n";
