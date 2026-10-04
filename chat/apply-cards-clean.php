<?php
/**
 * ChatHelp — apply-cards-clean (CH_CARDS_CLEAN) — Verträge + Bewerbung'u ana sayfa
 *  KARTLARINDAN/kategorilerinden kaldir. KALIR: Vertrag Studio modulu (nav-vst),
 *  modul hub. Kaldirilan: .wcat kategori karti (vertraege/bewerbung) + #ch-vst-home
 *  (sozlesme vitrini) + #ch-jobs-card (Bewerbung karti).
 * KULLANIM: pull2.php?key=...&files=apply-cards-clean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cards-clean BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDS_CLEAN')!==false) exit("Zaten ekli (CH_CARDS_CLEAN).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-cards-clean-css">
/* CH_CARDS_CLEAN — sozlesme vitrini + Bewerbung/Job karti ana sayfada gizli */
#ch-vst-home{display:none !important}
#ch-jobs-card{display:none !important}
</style>
<script id="ch-cards-clean-js">
try{(function(){
  function hide(){
    try{
      document.querySelectorAll('.wcat').forEach(function(el){
        var uc=(el.getAttribute('data-univcat')||el.getAttribute('data-ccat')||el.getAttribute('data-cat')||'').toLowerCase();
        var oc=(el.getAttribute('onclick')||'').toLowerCase();
        if(uc==='vertraege'||uc==='bewerbung' || /['"(](vertraege|bewerbung)['")]/.test(oc)){
          el.style.display='none';
        }
      });
    }catch(e){}
  }
  var n=0; (function loop(){ hide(); if(n++<200) setTimeout(loop,700); })();
  try{ if(window.MutationObserver){ new MutationObserver(hide).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp = tempnam(sys_get_temp_dir(),'cc').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cardsclean-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CARDS_CLEAN')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CARDS_CLEAN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Verträge (kategori + vitrin) + Bewerbung ana sayfa kartlarindan kaldirildi.\n";
echo "  Vertrag Studio modulu (nav-vst) + modul hub KALDI.\n";
