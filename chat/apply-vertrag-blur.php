<?php
/**
 * ChatHelp — apply-vertrag-blur (CH_VERTRAG_BLUR)
 *  • Ana sayfadaki eski tek-tek sozlesme kartlarini (.ch-vtr-sec) gizler —
 *    sozlesmeler artik yalnizca Vertrag Studio modulunde.
 *  • Vertrag Studio kategori kartlarina, sozlesmenin ilk A4 sayfasindan
 *    kucuk bir ONIZLEME ekler:
 *      – ucret odenmemis (plan yok) ise BUGULU (blur) + kilit -> icerik
 *        okunmaz, sadece premium oldugu hissedilir;
 *      – planli kullanicida net gorunur.
 *  • CV/Lebenslauf modulu zaten net ve tam ornek gosteriyor — dokunulmaz.
 *  GEREKLI: apply-contract-studio.php (CH_VST) kurulu olmali.
 * KULLANIM: pull-updates.php?files=apply-vertrag-blur.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vertrag-blur BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VERTRAG_BLUR') !== false) exit("Zaten ekli (CH_VERTRAG_BLUR).\n");
if (strpos($src, 'CH_VST') === false) exit("HATA: Vertrag Studio (CH_VST) yok — once apply-contract-studio.php.\n");
file_put_contents($file . '.bak-vtrblur-' . date('Ymd-His'), $src);

$changes = 0;

/* ── 1) renderCat map: her kart icin _locked + _thumb hesapla ── */
$oldMap = 'function(k){var c=CT[k];';
$newMap = 'function(k){var c=CT[k];var _locked=false;try{_locked=!QUOTA[plan()];}catch(e){_locked=true;}var _thumb="";try{_thumb=(buildPages(k,{})||[])[0]||"";}catch(e){}';
$c=0; $src=str_replace($oldMap,$newMap,$src,$c); if($c){ $changes++; echo "  ✓ [1] kart onizleme/kilit hesabi eklendi ($c)\n"; } else echo "  ✗ [1] renderCat map anchor yok\n";

/* ── 2) kart sablonuna bugulu onizleme + kilit yerlestir ── */
$oldCard = '+k+\'"><div class="vst-ic">\'';
$newCard = '+k+\'">\'+(_thumb?(\'<div class="vst-thumb\'+(_locked?\' locked\':\'\')+\'">\'+_thumb+(_locked?\'<div class="vst-lock"><span class="lk">🔒</span></div>\':\'\')+\'</div>\'):\'\')+\'<div class="vst-ic">\'';
$c=0; $src=str_replace($oldCard,$newCard,$src,$c); if($c){ $changes++; echo "  ✓ [2] karta bugulu onizleme yerlesti ($c)\n"; } else echo "  ✗ [2] kart sablonu anchor yok\n";

if ($changes < 2) { echo "\nHATA: 2 degisiklikten sadece $changes uygulandi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── 3) CSS + eski ana-sayfa sozlesme bolumunu gizle ── */
$bundle = <<<'VBHTML'
<style id="ch-vertrag-blur-css">
/* CH_VERTRAG_BLUR — eski ana sayfa sozlesme kartlarini kaldir */
.ch-vtr-sec{display:none !important}
/* Vertrag Studio kart onizlemesi */
.vst-card .vst-thumb{height:148px;overflow:hidden;position:relative;background:#fff;border-radius:14px 14px 0 0;margin:-18px -18px 14px}
.vst-card .vst-thumb .vsA4{transform:scale(0.29);transform-origin:top left;position:absolute;top:0;left:50%;margin-left:-115px;box-shadow:none}
.vst-card .vst-thumb.locked .vsA4{filter:blur(5px)}
.vst-card .vst-thumb .vst-lock{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:linear-gradient(180deg,rgba(20,20,26,.28),rgba(20,20,26,.5));color:#fff}
.vst-card .vst-thumb .vst-lock .lk{font-size:26px;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))}
</style>
VBHTML;

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
echo "\n✓ CH_VERTRAG_BLUR uygulandi ($changes/2 + CSS).\n";
echo "Test: ana sayfada eski tek-tek sozlesmeler yok; Vertrag Studio'da plansiz kullanici -> bugulu+kilitli onizleme, planli -> net.\n";
