<?php
/**
 * ChatHelp — apply-cardmove-fix (CH_CARDMOVE_FIX) — ACIL: ana sayfa kart oynamasi.
 *  Kok neden: CH_CARDS_CLEAN kartlari JS ile (700ms dongu + body-wide observer)
 *  GORUNUR OLDUKTAN SONRA gizliyordu; renderMissing kartlari interval'de sonradan
 *  ekleyince kart once cikip sonra gizleniyor -> grid yeniden diziliyor = "oynama".
 *  DUZELTME (iki adim):
 *   (1) CSS attribute-selector ile Verträge/Bewerbung kartlarini ANINDA gizle
 *       (hic gorunmeden; reflow yok).
 *   (2) CH_CARDS_CLEAN'in body-wide 700ms churn'unu tek grid-scoped observer'a
 *       indir (byte-exact str_replace; anchor yoksa atla).
 * KULLANIM: pull2.php?key=...&files=apply-cardmove-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardmove-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDMOVE_FIX')!==false) exit("Zaten ekli (CH_CARDMOVE_FIX).\n");

/* ── ADIM 1: body-wide churn'u grid-scoped tek observer'a indir ── */
$old = <<<'OLD'
  var n=0; (function loop(){ hide(); if(n++<200) setTimeout(loop,700); })();
  try{ if(window.MutationObserver){ new MutationObserver(hide).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
OLD;
$new_js = <<<'NEWJS'
  hide(); /*CH_CARDMOVE_FIX: dongu yerine tek gecis; CSS zaten gizliyor*/
  try{ if(window.MutationObserver){ var _cg=document.querySelector('.wcat-grid'); new MutationObserver(hide).observe(_cg||document.body,{childList:true}); } }catch(e){}
NEWJS;

$c = substr_count($src,$old);
if ($c===1) {
  $src = str_replace($old,$new_js,$src);
  echo "  ✓ ADIM 1: CH_CARDS_CLEAN churn -> grid-scoped tek observer (1 degistirildi)\n";
} else {
  echo "  ⚠ ADIM 1 atlandi: churn anchor $c kez bulundu (1 bekleniyordu). Sadece CSS uygulanacak.\n";
}

/* ── ADIM 2: CSS ile hedef kartlari aninda gizle ── */
$block = <<<'HTMLBLOCK'
<style id="ch-cardmove-fix-css">
/* CH_CARDMOVE_FIX — Verträge/Bewerbung kartlarini CSS ile ANINDA gizle:
   kart hic gorunmeden gizlenir, "gorunur-sonra-gizle" reflow'u (oynama) biter. */
.wcat[data-univcat="vertraege"],.wcat[data-univcat="bewerbung"],
.wcat[data-ccat="vertraege"],.wcat[data-ccat="bewerbung"],
.wcat[data-trcat="vertraege"],.wcat[data-trcat="bewerbung"],
.wcat[data-frcat="vertraege"],.wcat[data-frcat="bewerbung"]{display:none !important}
</style>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'cm').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cardmovefix-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CARDMOVE_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CARDMOVE_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Kart oynamasi bitti: Verträge/Bewerbung kartlari CSS ile aninda gizlendi;\n";
echo "  JS churn grid-scoped tek observer'a indirildi.\n";
