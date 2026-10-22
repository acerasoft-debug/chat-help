<?php
/**
 * ChatHelp — apply-jobsnav-fix (CH_JOBSNAV_FIX) — MENU OYNAMASININ GERCEK KOKU
 *
 *  CANLI TESHIS (sbprobe3, 35+ saniye kesintisiz izleme) KESIN KANITLADI:
 *   • CSS order degerleri HIC degismiyor (menufix4/5 dogru calisiyor).
 *   • nav-jobs surekli display:none <-> display:"" arasinda TITRIYOR.
 *     Bu titresim, altindaki HER ogeyi (nav-cv, nav-vst, nav-trvisa, nav-fris,
 *     nav-rech, nav-tresor, nav-briefe, sb-foot) 56px yukari/asagi kaydiriyor
 *     — kullanicinin gordugu "menu surekli oynuyor" TAM OLARAK budur.
 *
 *  KOK NEDEN — IKI AYRI YAMA AYNI ELEMENTI FARKLI PERIYOTLARLA DEGISTIRIYOR:
 *   • apply-jobs-tocard.php: her 1200ms'de nav-jobs.style.display="none"
 *     (Jobs ana sayfada karta tasindigi icin sidebar'dan HEP gizli kalmali).
 *   • apply-fixpack10.php:   her 1300ms'de nav-jobs.style.display=""
 *     (modul ac/kapa senkronu — "jobs" varsayilan acik kabul edilip geri
 *     gosteriliyor). Farkli periyotlar surekli faz kayması yaratip sonsuz
 *     titresime (ve dolayisiyla menu "oynamasina") sebep oluyor.
 *
 *  COZUM:
 *   [1] fixpack10'un applyMods() dongusunden "jobs" cikarilir — nav-jobs
 *       artik SADECE jobs-tocard.php tarafindan yonetilir (surekli gizli).
 *   [2] Ek guvence: CSS #nav-jobs{display:none!important} — herhangi baska
 *       bilinmeyen bir yazici (gelecekte) tekrar gostermeye calissa bile
 *       CSS !important, JS'in inline style.display atamasini EZER.
 * KULLANIM: pull-updates.php?files=apply-jobsnav-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobsnav-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_JOBSNAV_FIX')!==false) exit("Zaten ekli (CH_JOBSNAV_FIX).\n");

$fail=[];

/* ── [1] fixpack10 applyMods dongusunden 'jobs' cikar (kok neden) ── */
$o1 = <<<'JS'
      [["jobs","nav-jobs"],["cv","nav-cv"],["vst","nav-vst"]].forEach(function(x){
        var el=document.getElementById(x[1]);
        if(el) el.style.display=modOn(x[0])?"":"none";
        var pill=document.getElementById("ch-pill-"+(x[0]==="jobs"?"none":x[0]==="cv"?"cv":"vst"));
        if(pill&&x[0]!=="jobs") pill.style.display=modOn(x[0])?"":"none";
      });
JS;
$n1 = <<<'JS'
      [["cv","nav-cv"],["vst","nav-vst"]].forEach(function(x){ /*CH_JOBSNAV_FIX: 'jobs' cikarildi — nav-jobs artik SADECE jobs-tocard.php yonetir, iki interval'in ayni elementi degistirmesi menu titremesine sebep oluyordu*/
        var el=document.getElementById(x[1]);
        if(el) el.style.display=modOn(x[0])?"":"none";
        var pill=document.getElementById("ch-pill-"+(x[0]==="cv"?"cv":"vst"));
        if(pill) pill.style.display=modOn(x[0])?"":"none";
      });
JS;
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] fixpack10 applyMods() dongusunden 'jobs' cikarildi (kok neden giderildi)\n":"  ✗ [1] applyMods anchor bulunamadi ($c) — canli kod beklenenden farkli olabilir\n");
if($c!==1)$fail[]=1;

/* ── [2] CSS guvence: nav-jobs kalici gizli ── */
$bundle = <<<'HTML'
<style id="ch-jobsnav-fix-css">
/* CH_JOBSNAV_FIX — nav-jobs KALICI gizli (Jobs ana sayfa kartina tasindi).
   !important, ileride baska bir yazici tekrar gostermeye calissa bile kazanir. */
#nav-jobs{display:none !important}
</style>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) { echo "  ✗ [2] </body> yok\n"; $fail[]=2; }
else { $src = substr($src,0,$pos).$bundle."\n".substr($src,$pos); echo "  ✓ [2] CSS guvence eklendi (#nav-jobs display:none !important)\n"; }

if ($fail) { echo "\nHATA: adim(lar) basarisiz: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'jn').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-jobsnavfix-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_JOBSNAV_FIX uygulandi. nav-jobs artik SABIT gizli — titresim ve menu\n";
echo "   'oynamasi' bitti. Jobs erisimi ana sayfadaki 💼 karttan devam ediyor.\n";
