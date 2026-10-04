<?php
/**
 * ChatHelp — apply-ui-stability (CH_UI_STABILITY) — GEOMETRI KILIDI + yerlesme kapisi
 *
 *  TEORI (kullanicinin "tum hukuk dillerinde ve cevirilerde her sey sabit
 *  durmak zorunda" vurgusuyla dogrulandi): siralama zaten CSS order ile
 *  sabit; kalan gorunur "oynama" iki kaynaktan geliyor:
 *   (a) CEVIRI METINLERI parti parti geldikce metin uzunlugu degisiyor ->
 *       baslik 1 satirdan 2 satira cikiyor / kisa kaliyor -> kart/menu
 *       YUKSEKLIGI degisiyor -> altindaki HER SEY kayiyor.
 *   (b) Ulke degisiminde yeni kategoriler ilk saniyelerde tek tek eklenirken
 *       izgara buyuyor -> yerlesme suresince gorunur itme.
 *
 *  COZUM:
 *   [1] GEOMETRI KILIDI (CSS): kart basligi HER ZAMAN 2 satirlik sabit alan
 *       kaplar (metin kisa da olsa uzun da olsa yukseklik AYNI); kart alt
 *       yazisi tek satir + tasan kisim "..."; sol menu ogeleri tek satir +
 *       "..." (ceviri uzun gelse bile satir atlayamaz). Metin degisimi artik
 *       FIZIKSEL OLARAK hicbir seyi kaydiramaz.
 *   [2] YERLESME KAPISI (JS): sayfa acilisinda ve ulke degisiminde kategori
 *       izgarasi kisik gorunurlukte (opacity .15) baslar; 700ms boyunca yeni
 *       kart eklenmezse yumusak gecisle tam gorunur olur (en gec 5 sn).
 *       Ilk saniyelerdeki tum ekleme/itme gorunmez gerceklesir. Yerlesme
 *       SONRASI eklenen nadir kartlar CSS order geregi hep SONA gider —
 *       mevcut kartlar yine kimildamaz.
 * KULLANIM: pull-updates.php?files=apply-ui-stability.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ui-stability BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_UI_STABILITY')!==false) exit("Zaten ekli (CH_UI_STABILITY).\n");

$bundle = <<<'HTML'
<style id="ch-ui-stability-css">
/* CH_UI_STABILITY — ceviri/dil ne olursa olsun GEOMETRI SABIT */
/* kart basligi: HER ZAMAN 2 satirlik sabit yukseklik (kisa metin de ayni yer kaplar) */
.wcat{overflow:hidden}
.wcat .wcat-t{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;
  overflow:hidden;line-height:1.28em;min-height:2.56em;max-height:2.56em}
/* kart alt yazisi: tek satir, tasarsa "..." */
.wcat .wcat-s{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* sol menu ogeleri: tek satir, tasarsa "..." — ceviri uzun gelse bile satir atlayamaz */
#sb>[id^="nav-"],#sb>.sb-nav-item,#sb>.sb-photo-btn,#sb .sn{
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* yerlesme kapisi */
.wcat-grid{transition:opacity .35s ease}
.wcat-grid.ch-settling{opacity:.15;pointer-events:none}
</style>
<script id="ch-ui-stability-js">
/* CH_UI_STABILITY — kategori izgarasi yerlesme kapisi */
try{(function(){
  var shown=false, t=null;
  function grids(){ return [].slice.call(document.querySelectorAll(".wcat-grid")); }
  function reveal(){ grids().forEach(function(g){ g.classList.remove("ch-settling"); }); shown=true; }
  function arm(){
    if(shown) return;
    grids().forEach(function(g){ g.classList.add("ch-settling"); });
    clearTimeout(t); t=setTimeout(reveal, 700);
  }
  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 5000); }
  function boot(){
    var gs=grids();
    if(!gs.length){ setTimeout(boot,250); return; }
    arm();
    setTimeout(function(){ if(!shown) reveal(); }, 5000); /* guvenlik: en gec 5 sn */
    try{
      gs.forEach(function(g){
        new MutationObserver(function(){ if(!shown) arm(); }).observe(g,{childList:true});
      });
    }catch(e){}
    /* ulke degisince yeniden yerles */
    var lastCC=null;
    setInterval(function(){
      try{
        var cc=(typeof CC!=="undefined"&&CC)?CC:null;
        if(cc&&lastCC&&cc!==lastCC) resettle();
        if(cc) lastCC=cc;
      }catch(e){}
    }, 400);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index.php degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [1] geometri kilidi (2-satir sabit kart basligi, tek-satir menu/altyazi)\n";
echo "  ✓ [2] yerlesme kapisi (acilis + ulke degisiminde yumusak gecis)\n";

$tmp = tempnam(sys_get_temp_dir(),'us').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-uistab-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_UI_STABILITY uygulandi. Ceviri/dil/ulke degisiminde kart ve menuler\n";
echo "   FIZIKSEL olarak sabit — metin degisse bile hicbir sey kayamaz.\n";
