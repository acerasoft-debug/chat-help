<?php
/**
 * ChatHelp — apply-chclean-fix (CH_CLEANFIX) — ACIL: "acilinca kartlar gizli".
 *  Kok neden: NC() (yeni sohbet) cagrilinca body.ch-clean ekleniyor ve
 *  .wcat-grid/.wlc-quick/.sec-hdr gizleniyor; ch-clean yalniz gP(k!=="chat")
 *  ile kalkiyor -> ana sayfada TAKILI kaliyor, kartlar gorunmuyor.
 *  Cozum: ch-clean'i notralize et (aninda + DOMContentLoaded + kisa interval
 *  ile kaldir). Kartlar hep gorunur. "Temiz sohbet" ayri Zen dugmesiyle (ch-zen)
 *  zaten var; o bozulmaz. Additive, gozlemci YOK (freeze-safe). node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-chclean-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chclean-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CLEANFIX')!==false) exit("Zaten ekli (CH_CLEANFIX).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-cleanfix-css">
/* CH_CLEANFIX — ch-clean takilsa bile ana sayfa kartlari/karsilama GORUNUR kalsin */
html body.ch-clean .wcat-grid{display:grid !important}
html body.ch-clean .wlc-quick,html body.ch-clean .sec-hdr,html body.ch-clean .wlc-h1 ~ .sec-hdr{display:block !important}
</style>
<script id="ch-cleanfix-js">
/* CH_CLEANFIX — ch-clean sinifinin TAKILMASINI notralize et (kartlar hep gorunur) */
try{(function(){
  function unstick(){
    try{
      var b=document.body||document.getElementsByTagName('body')[0];
      if(b && b.classList && b.classList.contains('ch-clean')) b.classList.remove('ch-clean');
      var h=document.documentElement;
      if(h && h.classList && h.classList.contains('ch-clean')) h.classList.remove('ch-clean');
    }catch(e){}
  }
  unstick();
  try{ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',unstick); }catch(e){}
  /* NC() ileride cagrildikca ch-clean yeniden eklenebilir; kisa aralikla temizle */
  try{ setInterval(unstick, 600); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'cf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cleanfix-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CLEANFIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CLEANFIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ ch-clean takilmasi notralize edildi — ana sayfa kartlari artik hep gorunur.\n";
echo "   'Temiz sohbet' gorunumu ayri Zen dugmesiyle calismaya devam eder.\n";
