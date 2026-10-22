<?php
/**
 * ChatHelp — apply-stabilize (CH_STABILIZE) — "tam stabil yap".
 *  Sitedeki TÜM sürekli/kalıcı hareketi tek katmanda durdurur:
 *   [1] CSS: her ögede keyframe animasyonlarini kapat (animation:none) — geride
 *       kalan gizli/dekoratif "shimmer/scan/pulse/breathe" gibi ne varsa hepsi
 *       aninda durur. Hover geçişleri (transition) kalir -> "sakin premium".
 *   [2] Layout kaydiran gecisleri kis: transform/top/left/margin transition'lari
 *       0'a indir (yer degistirerek titreyen ögeleri sabitler).
 *   [3] JS: Web Animations API ile calisan sonsuz animasyonlari iptal et; kisa
 *       bir pencerede (6 sn) tekrar baslarsa yine iptal, sonra kendini kapat
 *       (kendisi churn yaratmaz).
 *   [4] En sona, </body> hemen oncesine yerlesir -> cascade'i kesin kazanir.
 *  Additive + idempotent. Geri alinabilir (marker'i sil, .bak geri yükle).
 * KULLANIM: pull2.php?key=...&files=apply-stabilize.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stabilize BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_STABILIZE')!==false) exit("Zaten ekli (CH_STABILIZE).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-stabilize-css">
/* CH_STABILIZE — tüm kalıcı hareketi durdur (tam stabil) */
*,*::before,*::after{animation:none!important}
html{scroll-behavior:auto!important}
/* yer degistirerek titreten gecisleri kis — renk/opaklik gecisleri kalir */
*{transition-property:background-color,color,border-color,box-shadow,opacity,fill,stroke!important}
</style>
<script id="ch-stabilize-js">
/* CH_STABILIZE — sonsuz Web-Animations'lari iptal et (kisa pencere, sonra dur) */
try{(function(){
  function killWAAPI(){
    try{
      if(!document.getAnimations) return;
      var list=document.getAnimations();
      for(var i=0;i<list.length;i++){
        try{
          var a=list[i];
          var inf = a.effect && a.effect.getTiming && (a.effect.getTiming().iterations===Infinity);
          if(inf){ a.cancel(); }
        }catch(e){}
      }
    }catch(e){}
  }
  killWAAPI();
  var n=0; var iv=setInterval(function(){ killWAAPI(); if(++n>=12){ clearInterval(iv); } }, 500);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'st').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-stabilize-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_STABILIZE')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_STABILIZE eklendi (".strlen($chk)." B)\n";
echo "✓ Tüm keyframe animasyonlari kapatildi (animation:none).\n";
echo "✓ Yer degistiren gecisler kisildi -> titreme durur; hover renk gecisleri kalir.\n";
echo "✓ Sonsuz Web-Animations iptal (6 sn pencere, sonra kendini kapatir).\n";
