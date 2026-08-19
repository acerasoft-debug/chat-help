<?php
/**
 * ChatHelp — DİL/ÜLKE UYUŞMAZLIĞI AÇIK BİLDİRİMİ + setCountry SAĞLAMLAŞTIRMA
 * ---------------------------------------------------------------------------------
 *  Kullanıcı raporu: "arayüzü Türkçeye çevirmeme rağmen belgeler Almanca".
 *  Analiz: CH_LANG_LAW_SEP sadece setCountry()'yi sarmalıyordu. Kullanıcı SADECE
 *  "Interface-Sprache" (setLang) düğmesine basıp ÜLKEYİ (Land & Rechtssystem,
 *  ayrı bölüm) DEĞİŞTİRMEDİYSE, hukuk hâlâ Almanya'da kalır ve belge (tasarım
 *  gereği doğru şekilde) Almanca/Alman hukukuna göre üretilir — ama kullanıcıya
 *  bu hiç açıklanmıyordu, "neden hâlâ Almanca" sorusu kafa karıştırıyordu.
 *
 *  Düzeltme (CH_LANG_MISMATCH):
 *   1) setLang sarmalanır: seçilen dil, mevcut ÜLKENİN belge diliyle uyuşmuyorsa,
 *      AÇIK bir bildirim gösterilir: "arayüz X oldu, ama hukuk hâlâ Y ülkesi —
 *      belgeler Y dilinde/hukukunda kalır; değiştirmek için ülke seçin."
 *   2) setCountry BİR KEZ DAHA (ikinci, bağımsız) sarmalanır — CH_LANG_LAW_SEP'in
 *      sarmalayıcısı herhangi bir sebeple devrede değilse (ör. sayfa yenilenmeden
 *      önce çağrı yakalanamadıysa) dahi ch_cc_manual kilidinin kesin kurulmasını
 *      garanti eder. İki sarmalayıcı zincirlenir, çakışmaz (her biri kendi
 *      işaretini kontrol eder).
 *
 * KULLANIM: pull-updates.php?files=apply-lang-mismatch-notice.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-lang-mismatch-notice BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_LANG_MISMATCH")!==false) exit("Zaten ekli (CH_LANG_MISMATCH).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-langmismatch-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_LANG_MISMATCH — dil/ülke uyuşmazlığında açık bildirim + setCountry çift-güvence */
try{(function(){
  var DOCLANG={DE:"de",AT:"de",CH:"de",FR:"fr",BE:"fr",LU:"fr",IT:"it",ES:"es",NL:"nl",TR:"tr",GB:"en",US:"en",PL:"pl",SE:"sv",PT:"pt"};
  var LNAME={de:"Almanca/Deutsch",fr:"Fransızca/Français",it:"İtalyanca/Italiano",es:"İspanyolca/Español",nl:"Felemenkçe/Nederlands",tr:"Türkçe/Türkçe",en:"İngilizce/English",pl:"Lehçe/Polski",sv:"İsveççe/Svenska",pt:"Portekizce/Português"};

  function curCC(){ try{ return (typeof CC!=="undefined"&&CC)?CC:"DE"; }catch(e){ return "DE"; } }
  function ccInfo(cc){ try{ return (typeof CC_DATA!=="undefined"&&CC_DATA[cc])?CC_DATA[cc]:null; }catch(e){ return null; } }

  function mismatchMsg(newLang,cc){
    var ci=ccInfo(cc)||{n:cc,law:""}; var dl=DOCLANG[cc]||"de"; var dln=LNAME[dl]||dl;
    var M={
      tr:"ℹ️ Arayüz dili değişti. Ancak hukuk sistemi hâlâ "+ci.n+" ("+ci.law+") — resmî belgeler "+dln+" dilinde ve bu ülkenin hukukuna göre üretilecek. Farklı bir ülkenin hukukunu kullanmak için Ayarlar → \"Land & Rechtssystem\" bölümünden ülke seçin.",
      de:"ℹ️ Die Oberflächensprache wurde geändert. Das Rechtssystem bleibt jedoch "+ci.n+" ("+ci.law+") — offizielle Dokumente werden weiterhin auf "+dln+" und nach diesem Recht erstellt. Um ein anderes Länderrecht zu nutzen, wählen Sie unter \"Land & Rechtssystem\" ein Land aus.",
      en:"ℹ️ The interface language changed. However, the legal system is still "+ci.n+" ("+ci.law+") — official documents will still be created in "+dln+" under this country's law. To use a different country's law, pick a country under \"Land & Rechtssystem\" in Settings.",
      fr:"ℹ️ La langue de l'interface a changé. Le système juridique reste toutefois "+ci.n+" ("+ci.law+") — les documents officiels seront créés en "+dln+" selon le droit de ce pays. Pour utiliser un autre droit national, choisissez un pays dans « Land & Rechtssystem ».",
      es:"ℹ️ Se cambió el idioma de la interfaz. El sistema jurídico sigue siendo "+ci.n+" ("+ci.law+") — los documentos oficiales se seguirán creando en "+dln+" según el derecho de ese país. Para usar otro derecho nacional, elija un país en «Land & Rechtssystem»."
    };
    return M[newLang]||M.en;
  }

  /* 1) setLang: dil/ülke uyuşmazlığında açık bildirim */
  function wrapSetLang(){
    try{
      if(typeof setLang!=="function" || setLang.__langmismatch) return;
      var _sl=setLang;
      var w=function(l){
        var r; try{ r=_sl.apply(this,arguments); }catch(e){}
        try{
          var cc=curCC(); var dl=DOCLANG[cc]||"de";
          if(l && l!==dl){
            setTimeout(function(){
              try{ if(typeof addMsg==="function") addMsg("ai", mismatchMsg(l,cc)); }catch(e){}
            }, 450);
          }
        }catch(e){}
        return r;
      };
      w.__langmismatch=1; setLang=w; if(typeof window!=="undefined") window.setLang=w;
    }catch(e){}
  }

  /* 2) setCountry: bagimsiz ikinci sarmalayici -> ch_cc_manual kesin kurulur */
  function wrapSetCountry2(){
    try{
      if(typeof setCountry!=="function" || setCountry.__langmismatch) return;
      var _sc=setCountry;
      var w=function(cc){
        var r; try{ r=_sc.apply(this,arguments); }catch(e){}
        try{ localStorage.setItem("ch_cc_manual","1"); }catch(e){}
        return r;
      };
      w.__langmismatch=1; setCountry=w; if(typeof window!=="undefined") window.setCountry=w;
    }catch(e){}
  }

  var ticks=0;
  function run(){ wrapSetLang(); wrapSetCountry2(); if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Dil/ulke uyusmazlik bildirimi eklendi (CH_LANG_MISMATCH).\n"; }
else exit("degisiklik yok!\n");
echo "SIL: rm apply-lang-mismatch-notice.php\n";
echo "Test: ULKEYI degistirmeden SADECE Interface-Sprache'den Turkce sec -> net aciklama mesaji gelmeli\n";
echo "      ('hukuk hala Almanya, belgeler Almanca kalir, ulke secmen gerekir').\n";
echo "Test2: Land & Rechtssystem'den Turkiye SEC -> belge Turkce/Turk hukuku olmali (onceki CH_LANG_LAW_SEP yamasi).\n";
