<?php
/**
 * ChatHelp — apply-law-hide (CH_LAW_HIDE) — hukuk-ulke degisimi ana sayfadan KOMPLE kalkar
 *  apply-law-konto secicileri Konto'ya tasimis, ust bar bayragini Konto'ya
 *  YONLENDIRMISTI. Kullanici istegi: gosterge de kalmasin — ana sayfada/ust
 *  barda hukuk ulkesiyle ilgili HICBIR sey gorunmesin, yalniz DIL degisimi kalsin.
 *  [1] Ust bar bayragi (#tb-flag-emoji) + ulke adi (#tb-country-name) ve
 *      sidebar hukuk gostergesi (#sb-clang) tamamen gizlenir — tiklanabilir
 *      sarmalayici dugmeleri de JS ile bulunup gizlenir (bos kutu kalmaz).
 *  [2] Hukuk degisimi yalniz Konto'daki secicide (CH_LAW_KONTO) kalir.
 *  DIL secicilere (setLang / fl-* / lf-*) DOKUNULMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-law-hide.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-law-hide BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_LAW_HIDE')!==false) exit("Zaten ekli (CH_LAW_HIDE).\n");
if (strpos($src,'CH_LAW_KONTO')===false) exit("HATA: once apply-law-konto gerekli.\n");

$bundle = <<<'HTML'
<style id="ch-law-hide-css">
/* CH_LAW_HIDE — hukuk-ulke gostergesi ana sayfada/ust barda tamamen gizli */
#tb-flag-emoji,#tb-country-name,#sb-clang{display:none !important}
</style>
<script id="ch-law-hide-js">
/* CH_LAW_HIDE — gostergelerin tiklanabilir sarmalayicilarini da gizle */
try{(function(){
  function hideWrap(id){
    try{
      var el=document.getElementById(id); if(!el) return;
      var w=el.closest("[onclick]");
      if(w && w.id!=="sb" && !w._chLawHide){
        /* sarmalayici SADECE bayrak/ulke gostergesini tasiyorsa gizle;
           icinde baska islev varsa yalniz gosterge gizli kalir (CSS halleder) */
        var txt=(w.textContent||"").trim();
        var own=(el.textContent||"").trim();
        var other=txt.replace(own,"").trim();
        if(other.length<=6){ w.style.display="none"; w._chLawHide=1; }
      }
    }catch(e){}
  }
  function tick(){ hideWrap("tb-flag-emoji"); hideWrap("tb-country-name"); hideWrap("sb-clang"); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick, 1500);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ ust bar bayragi + ulke adi + sidebar hukuk gostergesi tamamen gizlendi\n";
echo "  ✓ hukuk degisimi yalniz Konto'da; dil seciciler aynen duruyor\n";

$tmp = tempnam(sys_get_temp_dir(),'lh').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-lawhide-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_LAW_HIDE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_LAW_HIDE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_LAW_HIDE uygulandi. Ana sayfada hukuk-ulke degisimiyle ilgili hicbir\n";
echo "   sey kalmadi; yalniz dil degisimi gorunur. Hukuk: sadece Konto.\n";
