<?php
/**
 * ChatHelp — ÜLKE SEÇİCİ DÜZELTMESİ (apply-country-picker)
 * ---------------------------------------------------------------------------
 * SORUN: Ülke seçici menüsü SABİT bir HTML .co listesi (DE/AT/CH/FR/IT/ES/NL/BE/
 *        PL/SE/PT/EU). CH_TR_MODULE çalışma anında CC_DATA.TR ekliyor ama bu
 *        sabit listeye DOKUNMUYOR — bu yüzden Türkiye hukuk modülü çalışsa da
 *        seçicide Türkiye HİÇ görünmüyor (aynı kök neden: welcome-grid "0 belge"
 *        hatasındaki gibi, HTML listesi JS veri nesnesini yansıtmıyor).
 *
 * ÇÖZÜM: Deferred DOM enjeksiyonu (welcome-grid ile AYNI teknik) — çalışma
 *        anında mevcut bir .co düğümünü KLONLAYIP hedef ülkeye uyarlar ve
 *        listeye ekler. Böylece seçicinin gerçek yapısını/tıklama kablolamasını
 *        (inline onclick veya data-cc delegasyonu) OLDUĞU GİBİ miras alır.
 *        GENELLEŞTİRİLMİŞ: CC_DATA'da olup seçicide olmayan HER ülkeyi ekler
 *        (pratikte bugün = Türkiye). Ekleme yalnızca EKLER, mevcut hiçbir şeyi
 *        bozmaz; tekrar çalıştırılabilir (idempotent) ve tamamen fail-safe.
 *
 * KULLANIM: chat-help.com/chat/apply-country-picker.php  ->  opcache-reset.php
 * SONRA SİL: rm apply-country-picker.php
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-country-picker BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;

if(strpos($src,"CH_CC_PICKER")!==false) exit("Zaten ekli (CH_CC_PICKER). Degisiklik yok.\n");

$js=<<<'CHJS'

/* CH_CC_PICKER — ülke seçiciye CC_DATA'daki eksik ülkeleri (ör. Türkiye) ekler (deferred, fail-safe, idempotent) */
try{(function(){
  function esc(s){ return String(s).replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
  function CCD(){ return (typeof CC_DATA!=="undefined"&&CC_DATA)?CC_DATA:null; }

  /* .co elemanlarını barındıran kabı bul (önce data-cc'li .co, sonra .country-grid, sonra herhangi .co) */
  function findGrid(){
    var a=document.querySelector(".country-grid .co[data-cc]")
        ||document.querySelector(".co[data-cc]")
        ||document.querySelector(".country-grid .co")
        ||document.querySelector(".co");
    return a?a.parentNode:null;
  }
  /* şablon düğüm: DE tercih, yoksa data-cc'si olan ilk .co, yoksa ilk .co */
  function findTmpl(g){
    return g.querySelector('.co[data-cc="DE"]')
        || g.querySelector('.co[data-cc="de"]')
        || g.querySelector(".co[data-cc]")
        || g.querySelector(".co");
  }
  function existingCodes(g){
    var set={}; g.querySelectorAll(".co[data-cc]").forEach(function(el){
      var v=el.getAttribute("data-cc"); if(v) set[v.toUpperCase()]=1;
    }); return set;
  }

  function buildEntry(t,tcc,cc){
    var d=CCD()||{}, sd=d[tcc]||{}, dd=d[cc]||{};
    var el=t.cloneNode(true);

    /* 1) data-cc (şablonun büyük/küçük harf düzenini koru) */
    if(t.getAttribute("data-cc")!=null){
      var raw=t.getAttribute("data-cc");
      var val=(raw===raw.toUpperCase())?cc.toUpperCase():(raw===raw.toLowerCase()?cc.toLowerCase():cc);
      el.setAttribute("data-cc",val);
    }
    /* 2) inline onclick içindeki ülke-kodu token'ını değiştir ('DE'->'TR', "DE"->"TR") */
    var oc=el.getAttribute("onclick");
    if(oc && tcc){
      [tcc, tcc.toUpperCase(), tcc.toLowerCase()].forEach(function(tok){
        var re=new RegExp("(['\"])"+esc(tok)+"(['\"])","g");
        oc=oc.replace(re,function(m,q1,q2){ return q1+cc+q2; });
      });
      el.setAttribute("onclick",oc);
    }
    /* 3) bayrak + ülke adını güncelle (şablonun CC_DATA değerlerini yenisiyle değiştir) */
    var html=el.innerHTML;
    if(sd.f && dd.f) html=html.split(sd.f).join(dd.f);
    if(sd.n && dd.n) html=html.split(sd.n).join(dd.n);
    el.innerHTML=html;
    /* 4) kök düğümdeki title/aria-label gibi metinler */
    ["title","aria-label"].forEach(function(at){
      var v=el.getAttribute(at);
      if(v && sd.n && dd.n) el.setAttribute(at, v.split(sd.n).join(dd.n));
    });
    return el;
  }

  function run(){
    try{
      var d=CCD(); if(!d) return;
      var g=findGrid(); if(!g) return;
      var t=findTmpl(g); if(!t) return;
      var tcc=(t.getAttribute("data-cc")||"DE");
      var have=existingCodes(g);
      have[tcc.toUpperCase()]=1;
      Object.keys(d).forEach(function(cc){
        if(!cc) return;
        if(have[cc.toUpperCase()]) return;      /* zaten var */
        try{ g.appendChild(buildEntry(t,tcc,cc)); have[cc.toUpperCase()]=1; }catch(e){}
      });
    }catch(e){}
  }

  /* Deferred: sayfa/veri hazır olana kadar dene (welcome-grid ile aynı desen) */
  var n=0;
  function loop(){ run(); if(n++<40) setTimeout(loop,500); }
  function boot(){ loop(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();

  /* Seçici her açılışta yeniden çiziliyorsa: açma tıklamalarını yakala ve yeniden ekle */
  try{
    document.addEventListener("click",function(ev){
      var el=ev.target; if(!el||!el.closest) return;
      if(el.closest('#sb-country,.sb-country,#k-cc,.k-cc,[data-cc-open],[onclick*="Country"],[onclick*="country"]')){
        setTimeout(run,50); setTimeout(run,250); setTimeout(run,600);
      }
    },true);
  }catch(e){}
})();}catch(e){}
CHJS;

/* Yerleştirme: CH_TR_MODULE ile aynı güvenli anchor'dan sonra ekle; yoksa TR modülünden hemen önce; o da yoksa net uyarı ver. */
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)!==false){
  $src=str_replace($anchor,$anchor.$js,$src);
} elseif(strpos($src,"/* CH_TR_MODULE")!==false){
  $src=str_replace("/* CH_TR_MODULE",$js."\n/* CH_TR_MODULE",$src);
} else {
  exit("HATA: guvenli anchor bulunamadi (getDocPrice / CH_TR_MODULE yok).\n".
       "Lutfen once dump-country-picker.php ciktisini paylasin; markup'a gore hedefli yerlestiririm.\n");
}

if($src!==$start){
  file_put_contents($file.".bak-ccpick-".date("Ymd-His"),$start);
  file_put_contents($file,$src);
  echo "OK: CH_CC_PICKER eklendi. Ulke secici artik CC_DATA'daki eksik ulkeleri (Turkiye dahil) calisma aninda ekliyor.\n";
  echo "Yedek: index.php.bak-ccpick-".date("Ymd-His")."\n";
} else {
  echo "Degisiklik yok (anchor eslesmesi bos donmus olabilir).\n";
}
echo "\nSONRA: opcache-reset.php calistir.  SIL: rm apply-country-picker.php\n";
echo "TEST: Ulke secici menusunu ac -> 🇹🇷 Turkiye gorunmeli -> sec -> kategoriler Turkce gelmeli.\n";
echo "NOT: Bu betik yalnizca EKLER; hicbir mevcut .co veya secici mantigini degistirmez (fail-safe).\n";
