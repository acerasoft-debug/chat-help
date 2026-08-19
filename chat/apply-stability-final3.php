<?php
/**
 * ChatHelp — apply-stability-final3 (CH_STAB_FINAL3) — MENU + KARSILAMA kesin sabitlik
 *  dump-welcome-struct KANITLARI (ES'te "menuler oynuyor"):
 *   • [#51/#52] CH_PREMIUM_POLISH icinde HALA AKTIF bir enforceOrder() dongusu
 *     var: her 900ms'de sol menu ogelerini insertBefore ile FIZIKSEL TASIYOR
 *     (menufix2/3'un kapatilan yurutecleriyle ayni aile — bu ucuncusu gozden
 *     kacmis). Gorsel sira zaten menufix5'in CSS order kurallarinda; DOM
 *     tasiyici salt risk -> KAPATILIR.
 *   • Karsilama sutununun TAMAMI tek kapta: <div id="wlc"> (kartlar, Vertrage
 *     vitrini, Beispiel-Fragen...). Vitrin/serit ekleyicileri (900-1200ms) bu
 *     kaba perde DISINDAN ekleme yapiyordu -> ızgara sabit dursa bile altindaki
 *     bolumler beliriyor/itiyordu. Perde artik #wlc'nin tamamini kapsar:
 *     icerideki HER yerlesme gorunmez olur, tek seferde acilir.
 *  DEGISIKLIKLER (mevcut canli index uzerinde 5 cerrahi adim):
 *   [1] grids() secicisine #wlc eklenir (perde + gecici dondurma + gozlemci
 *       otomatik #wlc icin de calisir)
 *   [2] gecis/perde CSS'i #wlc'yi kapsar
 *   [3] sol menu: her #sb cocuguna dusuk-oncelikli order:50 yedegi (bilinen
 *       ogelerin ozel order'lari daha yuksek ozgunlukle ustun kalir) -> order
 *       tanimsiz hicbir oge kalmaz, yeni gelen oge hep ayni sabit yuvaya iner
 *   [4] enforceOrder'in 900ms dongusu kapatilir (2 kez: DOMContentLoaded ve
 *       else dallari) — acilistaki tek seferlik cagrilar kalir
 * KULLANIM: pull-updates.php?files=apply-stability-final3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stability-final3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_STAB_FINAL3')!==false) exit("Zaten ekli (CH_STAB_FINAL3).\n");
if (strpos($src,'CH_STAB_FINAL2')===false) exit("HATA: CH_STAB_FINAL2 katmani yok (once apply-stability-final2 gerekli).\n");
if (strpos($src,'id="wlc"')===false) exit("HATA: #wlc bulunamadi (beklenmedik yapi).\n");

/* on-kontrol: enforceOrder dongusu beklendigi gibi tam 2 kez mi? */
$eoCnt = substr_count($src,'setInterval(enforceOrder,900)');
if ($eoCnt!==2) exit("HATA: setInterval(enforceOrder,900) sayisi $eoCnt (beklenen 2) — index DEGISTIRILMEDI.\n");

$fail=[];

/* [1] perde hedefine #wlc ekle */
$o1 = '  function grids(){ return [].slice.call(document.querySelectorAll(".wcat-grid")); }';
$n1 = '  function grids(){ return [].slice.call(document.querySelectorAll(".wcat-grid,#wlc")); } /*CH_STAB_FINAL3*/';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] perde artik #wlc (tum karsilama sutunu) icin de calisiyor\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2a] gecis CSS'i */
$o2 = '.wcat-grid{transition:opacity .35s ease}';
$n2 = '.wcat-grid,#wlc{transition:opacity .35s ease} /*CH_STAB_FINAL3*/';
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2a] gecis CSS'i #wlc'yi kapsiyor\n":"  ✗ [2a] anchor ($c)\n"); if($c!==1)$fail[]='2a';

/* [2b] perde CSS'i + [3] sol menu order yedegi */
$o3 = '.wcat-grid.ch-settling{opacity:0;pointer-events:none;transition:none} /*CH_STAB_FINAL2*/';
$n3 = '.wcat-grid.ch-settling,#wlc.ch-settling{opacity:0;pointer-events:none;transition:none} /*CH_STAB_FINAL3*/'."\n"
    . '#sb>*{order:50 !important} /*CH_STAB_FINAL3 — order tanimsiz oge kalmasin; ozel kurallar daha yuksek ozgunlukle ustun*/';
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [2b+3] perde CSS'i #wlc'de · #sb order:50 yedegi\n":"  ✗ [2b] anchor ($c)\n"); if($c!==1)$fail[]='2b';

/* [4] enforceOrder dongusunu kapat (tek seferlik acilis cagrilari kalir) */
$o4 = 'setInterval(enforceOrder,900)';
$n4 = 'void(0) /*CH_STAB_FINAL3: enforceOrder dongusu kapatildi — sira zaten CSS order kilidinde*/';
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===2?"  ✓ [4] 900ms DOM-tasiyici enforceOrder dongusu kapatildi (2/2)\n":"  ✗ [4] anchor ($c, beklenen 2)\n"); if($c!==2)$fail[]=4;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'s3').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-stabfinal3-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_STAB_FINAL3 uygulandi:\n";
echo "   • sol menudeki 900ms'lik DOM-tasiyici KAPATILDI — surekli oynamanin motoru buydu\n";
echo "   • her menu ogesinin artik tanimli bir CSS order'i var — yeni oge sabit yuvaya iner\n";
echo "   • karsilama sutununun TAMAMI (kartlar+vitrin+ornek sorular) tek perdede yerlesir\n";
