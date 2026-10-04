<?php
/**
 * ChatHelp — apply-cardlock (CH_CARDLOCK) — kart oynamasina GEOMETRIK KESIN KILIT
 *
 *  KANIT (cardwatch2, ES): childList=0 ama A(order)=429, B(metin)=171,
 *  C(gorsel kaydi)=148 — ve C olaylari kartlarin AYNI ANDA YATAY kaymasiydi
 *  (X:742->-727, Y sabit). Yani izgara sarmalanmayan genis bir satir gibi
 *  davraniyor; order churn (A) VE ceviri->metin degisimi (B) kartlari yatay
 *  itiyor. Tek tek surucu kesmek yetmedi.
 *
 *  KESIN COZUM — geometriyi FIZIKSEL kilitle (hangi surucu olursa olsun kart
 *  hucresi kimildayamaz):
 *   [1] .wcat-grid -> SABIT SUTUNLU CSS GRID (!important). Kartlar sabit
 *       hucrelere oturur; metin uzasa/kissa da, order degisse de hucre YERI
 *       DEGISMEZ. Yatay reflow FIZIKSEL OLARAK imkansiz.
 *   [2] Kart ic geometrisi sabit: min-width:0, taskan metin gizli, kart
 *       yuksekligi zaten 2-satir kilitli (CH_UI_STABILITY).
 *   [3] Ceviri katmani kart metnine DOKUNMASIN: her .wcat-grid'e data-noi18n
 *       isaretlenir (i18n-full skipEl ata elemanda bunu gorunce tum ic metin
 *       dugumlerini atlar) -> B(metin churn) kartlarda 0'a iner. Kartlar zaten
 *       secili hukukun kendi dilinde uretiliyor.
 *  Tamamen EK (CSS + kisa isaret dongusu) — kirilgan kaynak-satir capasi YOK.
 * KULLANIM: pull2.php?key=...&files=apply-cardlock.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardlock BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDLOCK')!==false) exit("Zaten ekli (CH_CARDLOCK).\n");

$bundle = <<<'HTML'
<style id="ch-cardlock-css">
/* CH_CARDLOCK — kart izgarasi SABIT sutunlu grid: yatay reflow imkansiz */
.wcat-grid{display:grid !important;grid-template-columns:repeat(2,minmax(0,1fr)) !important;
  gap:10px !important;align-content:start !important;justify-content:stretch !important;overflow:hidden}
@media(min-width:640px){.wcat-grid{grid-template-columns:repeat(3,minmax(0,1fr)) !important}}
@media(min-width:1000px){.wcat-grid{grid-template-columns:repeat(4,minmax(0,1fr)) !important}}
.wcat-grid>.wcat{min-width:0 !important;max-width:100% !important;width:auto !important;
  margin:0 !important;overflow:hidden}
.wcat-grid>.wcat .wcat-t,.wcat-grid>.wcat .wcat-s{overflow:hidden}
</style>
<script id="ch-cardlock-js">
/* CH_CARDLOCK — izgaralari ceviri-katmanindan muaf tut (metin churn->reflow biter) */
try{(function(){
  function mark(){
    try{
      var gs=document.querySelectorAll(".wcat-grid");
      for(var i=0;i<gs.length;i++){ if(!gs[i].hasAttribute("data-noi18n")) gs[i].setAttribute("data-noi18n","1"); }
    }catch(e){}
  }
  mark();
  var n=0, iv=setInterval(function(){ mark(); if(++n>20) clearInterval(iv); }, 500); /* ilk 10sn boyunca gec gelen izgaralari da isaretle, sonra dur */
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",mark);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [1] izgara sabit sutunlu grid'e kilitlendi (yatay reflow imkansiz)\n";
echo "  ✓ [2] kart ic geometrisi sabit\n";
echo "  ✓ [3] izgaralar ceviri-katmanindan muaf (metin churn kartlarda biter)\n";

$tmp = tempnam(sys_get_temp_dir(),'cl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardlock-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CARDLOCK uygulandi. Kartlar artik sabit hucrelere oturur — order,\n";
echo "   metin veya dil ne yaparsa yapsin ana sayfada FIZIKSEL olarak kimildayamaz.\n";
