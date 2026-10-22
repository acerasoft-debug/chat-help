<?php
/**
 * ChatHelp — DİL ↔ HUKUK TAM AYRIMI (sarmalama yöntemi — anchor kırılganlığı YOK)
 * ---------------------------------------------------------------------------------
 *  Kullanıcı raporu:
 *   1) Ülke (hukuk) seçilince sayfa güncellenmiyor gibi görünüyor, kullanıcı ayarlarda kalıyor
 *   2) Dil bayrağı seçilince "resmî belge Almanca oluşturulur" uyarısı çıkıyor — ülke
 *      Almanya değilken bu YANLIŞ (belge seçilen ülkenin dilinde/hukukunda üretilir)
 *   3) Önceki setCountry yaması sunucuda anchor uyuşmazlığıyla kısmen uygulanamadı (✗ 1 -> 0)
 *
 *  Çözüm (CH_LANG_LAW_SEP) — fonksiyon GÖVDESİNE dokunmadan sarmalar (modüllerin
 *  kanıtlanmış hook deseni; orijinal kod ne olursa olsun çalışır):
 *   A) setCountry sarmalanır: elle seçim kilidi (ch_cc_manual) + dLang'ı ülkenin
 *      diline eşitler (kullanıcı yazım dilini elle kilitlemediyse) + seçilen ülkenin
 *      HUKUKUNU açıkça bildiren yerelleştirilmiş mesaj + 400ms sonra sohbete dönüş
 *      (gP('chat')) -> katalog/karşılama YENİ ülkeye göre anında görünür.
 *   B) addMsg sarmalanır: "belge Almanca oluşturulur" iddiası taşıyan ESKİ sabit
 *      mesajlar, seçili ülkenin belge dili Almanca DEĞİLSE, doğru ve ülkeye duyarlı
 *      metinle değiştirilir (DE/AT/CH'de mesaj zaten doğru; dokunulmaz).
 *
 * KULLANIM: pull-updates.php?files=apply-lang-law-separation.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-lang-law-separation BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_LANG_LAW_SEP")!==false) exit("Zaten ekli (CH_LANG_LAW_SEP).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-langlaw-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_LANG_LAW_SEP — dil ve hukuk tam ayrımı (setCountry + addMsg sarmalayıcıları) */
try{(function(){
  var DOCLANG={DE:"de",AT:"de",CH:"de",FR:"fr",BE:"fr",LU:"fr",IT:"it",ES:"es",NL:"nl",TR:"tr",GB:"en",US:"en",PL:"pl",SE:"sv",PT:"pt"};
  var LNAME={de:"Deutsch",fr:"Français",it:"Italiano",es:"Español",nl:"Nederlands",tr:"Türkçe",en:"English",pl:"Polski",sv:"Svenska",pt:"Português"};

  function curCC(){ try{ return (typeof CC!=="undefined"&&CC)?CC:"DE"; }catch(e){ return "DE"; } }
  function curUL(){ try{ return (typeof UL!=="undefined"&&UL)?UL:"de"; }catch(e){ return "de"; } }
  function ccInfo(cc){ try{ return (typeof CC_DATA!=="undefined"&&CC_DATA[cc])?CC_DATA[cc]:null; }catch(e){ return null; } }

  /* Yerelleştirilmiş "hukuk değişti" bildirimi */
  function lawSwitchedMsg(cc){
    var c=ccInfo(cc)||{n:cc,law:""}; var dl=DOCLANG[cc]||"de"; var dln=LNAME[dl]||dl;
    var M={
      tr:"⚖️ Hukuk sistemi değişti: "+c.n+" — "+c.law+". Belgeler artık "+dln+" dilinde ve bu ülkenin hukukuna göre oluşturulur. Katalog güncellendi.",
      de:"⚖️ Rechtssystem gewechselt: "+c.n+" — "+c.law+". Dokumente werden ab jetzt auf "+dln+" und nach dem Recht dieses Landes erstellt. Katalog aktualisiert.",
      en:"⚖️ Legal system switched: "+c.n+" — "+c.law+". Documents will now be created in "+dln+" under this country's law. Catalog updated.",
      fr:"⚖️ Système juridique changé : "+c.n+" — "+c.law+". Les documents seront créés en "+dln+" selon le droit de ce pays.",
      es:"⚖️ Sistema jurídico cambiado: "+c.n+" — "+c.law+". Los documentos se crearán en "+dln+" según el derecho de este país."
    };
    return M[curUL()]||M.en;
  }

  /* Yerelleştirilmiş DOĞRU dil-seçimi açıklaması (eski "Almanca kalır" iddiasının yerine) */
  function langPickCorrectMsg(){
    var cc=curCC(); var c=ccInfo(cc)||{n:cc}; var dl=DOCLANG[cc]||"de"; var dln=LNAME[dl]||dl;
    var M={
      tr:"🌐 Kendi dilinizde yazabilirsiniz. Resmî belge, seçtiğiniz ülkeye göre "+dln+" dilinde ve "+c.n+" hukukuna göre oluşturulur"+(dl!==curUL()?" — dilinizde kısa bir açıklama eklenir.":"."),
      de:"🌐 Sie können in Ihrer Sprache schreiben. Das offizielle Dokument wird auf "+dln+" und nach dem Recht von "+c.n+" erstellt"+(dl!==curUL()?" — mit einer kurzen Erklärung in Ihrer Sprache.":"."),
      en:"🌐 You can write in your own language. The official document is created in "+dln+" under the law of "+c.n+(dl!==curUL()?" — with a short explanation in your language.":"."),
      fr:"🌐 Vous pouvez écrire dans votre langue. Le document officiel est créé en "+dln+" selon le droit de "+c.n+".",
      es:"🌐 Puede escribir en su idioma. El documento oficial se crea en "+dln+" según el derecho de "+c.n+"."
    };
    return M[curUL()]||M.en;
  }

  /* A) setCountry sarmalayıcı — ARAYÜZ KOMPLE DEĞİŞSİN diye tek seferlik yenileme yapar:
     i18n tam-çeviri katmanı 'ch_uilang' anahtarını sayfa yüklenirken okur; burada
     anahtarları set edip yeniliyoruz -> katalog + tüm menü/buton metinleri + bayraklar
     aynı anda yeni ülkeye döner. Yenileme sonrası hukuk bildirimi gösterilir. */
  function wrapSetCountry(){
    try{
      if(typeof setCountry!=="function" || setCountry.__langlaw) return;
      var _sc=setCountry;
      var w=function(cc){
        var r; try{ r=_sc.apply(this,arguments); }catch(e){}
        try{ localStorage.setItem("ch_cc_manual","1"); }catch(e){}
        try{
          var ci=ccInfo(cc);
          if(!localStorage.getItem("ch_lm") && ci && ci.l){
            if(typeof dLang!=="undefined") dLang=ci.l;
            localStorage.setItem("ch_uilang", ci.l);   /* i18n katmanı tam çeviri için */
          }
          localStorage.setItem("ch_law_notice", cc);   /* yenileme sonrası bildirim */
        }catch(e){}
        try{ setTimeout(function(){ try{ location.reload(); }catch(e){} }, 350); }catch(e){}
        return r;
      };
      w.__langlaw=1; setCountry=w; if(typeof window!=="undefined") window.setCountry=w;
    }catch(e){}
  }

  /* Yenileme sonrası hukuk-değişti bildirimi */
  function pendingNotice(){
    try{
      var cc=localStorage.getItem("ch_law_notice");
      if(!cc) return;
      if(typeof addMsg!=="function") return;   /* sonraki tick'te tekrar dener */
      localStorage.removeItem("ch_law_notice");
      addMsg("ai",lawSwitchedMsg(cc));
    }catch(e){}
  }

  /* B) addMsg sarmalayıcı: eski "belge Almanca" iddialarını ülkeye duyarlı düzelt */
  var BAD=[/official document is created in German/i,
           /offizielle Dokument (wird|bleibt)[^.]{0,60}(DEUTSCH|Deutsch)/,
           /Dokument bleibt vollständig auf DEUTSCH/i,
           /resm[iî] belge[^.]{0,50}Almanca/i,
           /belge Almanca (olarak )?(kalacak|oluşturul)/i];
  function wrapAddMsg(){
    try{
      if(typeof addMsg!=="function" || addMsg.__langlaw) return;
      var _am=addMsg;
      var w=function(role,content){
        try{
          if(typeof content==="string"){
            var dl=DOCLANG[curCC()]||"de";
            if(dl!=="de"){
              for(var i=0;i<BAD.length;i++){
                if(BAD[i].test(content)){ content=langPickCorrectMsg(); break; }
              }
            }
          }
        }catch(e){}
        return _am.call(this,role,content);
      };
      w.__langlaw=1; addMsg=w; if(typeof window!=="undefined") window.addMsg=w;
    }catch(e){}
  }

  var ticks=0;
  function run(){ wrapSetCountry(); wrapAddMsg(); pendingNotice(); if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Dil-hukuk ayrimi kuruldu (CH_LANG_LAW_SEP):\n"; }
else exit("degisiklik yok!\n");
echo "  A) Ulke secimi: kilit + ch_uilang/dLang senkronu + SAYFA YENILEME (arayuz komple degisir) + hukuk bildirimi\n";
echo "  B) Eski 'belge Almanca olusturulur' mesajlari, ulke Almanca-disi ise dogru ulke-duyarli metinle degistirilir\n";
echo "SIL: rm apply-lang-law-separation.php\n";
echo "Test: 1) Ayarlar -> Espana sec -> sayfa kendini yeniler -> TUM arayuz + katalog Ispanyolca + hukuk mesaji gelir\n";
echo "      2) Dil bayragi sec (TR ulkesindeyken) -> 'belge Almanca' DEGIL 'belge Turkce/Turk hukuku' demeli\n";
