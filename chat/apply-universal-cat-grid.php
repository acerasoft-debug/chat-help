<?php
/**
 * ChatHelp — EKSİK KATEGORİ KARTLARI EVRENSEL DÜZELTMESİ (kök neden)
 * -----------------------------------------------------------------------------
 *  Teşhis: Ana karşılama ekranındaki kategori kartları (.wcat-grid) her ülke
 *  modülünün kendi SABİT listesinden render ediliyor: TR_ORDER (apply-turkiye.php),
 *  X_ORDER (apply-espana.php/apply-schweiz.php/vb, her modülün kendi scope'unda).
 *  Bu listeler modül YAZILDIĞI ANDAKİ kategorileri içeriyor — SONRADAN "expand"
 *  betikleriyle eklenen kategoriler (tr_schengen, tr_visa_usa/canada/uk/australia,
 *  tr_aile, tr_basvuru, es_familia, ch_familie, gb_family, us_family, fr_famille…)
 *  bu sabit listelerde YOK, bu yüzden DOCS/CATS içinde tam olarak var olsalar
 *  bile (dump kanıtı: "Zaten ekli" — kod zaten sunucuda) welcome ekranında HİÇ
 *  görünmüyorlar. Kullanıcının "vize bölümü görünmüyor" şikayetinin kök nedeni bu.
 *
 *  Düzeltme (CH_UNIV_CAT_GRID): mevcut render fonksiyonlarına DOKUNMADAN, CATS
 *  objesini periyodik tarayan EK bir katman ekler — geçerli ülkeye ait ama
 *  henüz DOM'da kartı olmayan HER kategori için otomatik kart oluşturur.
 *  Statik listelere bağımlı değil; yeni bir ülke/kategori eklendiğinde bile
 *  otomatik yakalar. Diğer scriptlerin işaretleriyle (data-trcat/data-frcat/
 *  data-ccat) çakışmaz — onlar zaten kart oluşturmuşsa tekrar eklemez.
 *
 * KULLANIM: pull-updates.php?files=apply-universal-cat-grid.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-universal-cat-grid BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_UNIV_CAT_GRID")!==false) exit("Zaten ekli (CH_UNIV_CAT_GRID).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-univgrid-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_UNIV_CAT_GRID — statik kategori listelerinin unuttugu kategorileri welcome ekranina otomatik ekler */
try{(function(){
  function countryOf(k,c){
    if(c && c.country) return c.country;
    if(k.indexOf("fr_")===0) return "FR";
    if(k.indexOf("tr_")===0) return "TR";
    return "DE";
  }
  function hasCard(grid,k){
    return !!(grid.querySelector('[data-univcat="'+k+'"]')
      || grid.querySelector('[data-trcat="'+k+'"]')
      || grid.querySelector('[data-frcat="'+k+'"]')
      || grid.querySelector('[data-ccat="'+k+'"]'));
  }
  function renderMissing(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      if(typeof CATS==="undefined"||!CATS) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      for(var k in CATS){
        var c=CATS[k];
        if(!c || countryOf(k,c)!==cur) continue;
        if(hasCard(grid,k)) continue;
        var el=document.createElement("div");
        el.className="wcat"; el.setAttribute("data-univcat",k); el.setAttribute("data-cccountry",cur);
        el.onclick=(function(kk){ return function(){ try{ if(typeof showCat==="function") showCat(kk); }catch(e){} }; })(k);
        var n=(c.docs&&c.docs.length)?c.docs.length:0;
        el.innerHTML='<div class="wcat-ic">'+(c.ic||"📄")+'</div><div class="wcat-t">'+(c.n||k)+'</div><div class="wcat-s">'+n+' belge</div>';
        grid.appendChild(el);
      }
      grid.querySelectorAll(".wcat[data-univcat]").forEach(function(el){
        el.style.display=(el.getAttribute("data-cccountry")===cur)?"":"none";
      });
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",renderMissing); else renderMissing();
  setInterval(renderMissing, 900);
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Evrensel kategori tamamlama katmani eklendi (CH_UNIV_CAT_GRID).\n"; }
else exit("degisiklik yok!\n");
echo "SIL: rm apply-universal-cat-grid.php\n";
echo "Test: TR sec -> ana ekranda artik 'Schengen Vizesi Belgeleri', 'ABD/Kanada/Ingiltere/Avustralya Vizesi',\n";
echo "      'Aile Hukuku', 'Is Basvurusu ve CV' kartlari da gorunmeli (once sadece 6 kart vardi).\n";
echo "      ES/CH/GB/US sec -> 'Familia/Familie/Family' kartlari da gorunmeli.\n";
