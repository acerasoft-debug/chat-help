<?php
/**
 * ChatHelp — apply-hide-prices (CH_HIDE_PRICES)
 *  Modul kataloglarindaki fiyat etiketlerini gizler — fiyat zaten satin
 *  alma (paywall) aninda gosterilecek, kartlarda gerek yok:
 *   • Vertrag Studio kart fiyatlari (.vst-pr) gizlenir
 *   • CV Studio ust-bar fiyati (.cvs-price) gizlenir
 *   • Vertrag Studio ust-bar rozetindeki € araligi metni JS ile temizlenir
 *     (kota bilgisi "Basic 3 · Pro 10 · Elite 50" kalir)
 *  Paywall/gate modallerindeki fiyat DOKUNULMAZ — orasi "sonunda alinacak" ani.
 * KULLANIM: pull-updates.php?files=apply-hide-prices.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hide-prices BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_HIDE_PRICES') !== false) exit("Zaten ekli (CH_HIDE_PRICES).\n");
file_put_contents($file . '.bak-hideprices-' . date('Ymd-His'), $src);

$bundle = <<<'HPHTML'
<style id="ch-hide-prices-css">
/* CH_HIDE_PRICES — katalog fiyat etiketlerini gizle (paywall fiyati kalir) */
.vst-pr{display:none !important}
.cvs-price{display:none !important}
</style>
<script id="ch-hide-prices">
try{(function(){
  function clean(){
    try{
      document.querySelectorAll(".vst-badge").forEach(function(b){
        var t=b.textContent||"";
        if(t.indexOf("€")>-1){
          var nt=t.replace(/^\s*[\d.,]+\s*[–-]\s*[\d.,]+\s*€\s*[·|]?\s*/,"").replace(/\s*[\d.,]+\s*€\s*[·|]?\s*/g," ").trim();
          if(nt!==t) b.textContent=nt;
        }
      });
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ clean(); setInterval(clean,1200); });
  else { clean(); setInterval(clean,1200); }
})();}catch(e){}
</script>
HPHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_HIDE_PRICES uygulandi. Kart/ust-bar fiyatlari gizlendi; satin alma anindaki fiyat korunuyor.\n";
