<?php
/**
 * ChatHelp — apply-polish-fix (CH_POLISH_FIX)
 *  CH_PREMIUM_POLISH sonrasi sol menu tasmasi duzeltmesi:
 *   • Ikon cipleri 38px -> 30px, satir dolgusu sikilastirildi (menu yuksekligi/genisligi kuculur)
 *   • Uzun basliklar tek satirda uc nokta (...) ile kesilir — yana tasma biter
 *   • #sb artik kendi icinde dikey kaydirilabilir (cok oge olsa da sitenin disina tasmaz)
 *   • Hover'daki yana kayma efekti kaldirildi (tasma hissini artiriyordu)
 *   • Mobil logo buyumesi olculu hale getirildi (46 -> 40px)
 *  Ayni ozgulluk + !important oldugu icin dokumanda DAHA SONRA gelen bu blok kazanir.
 * KULLANIM: pull-updates.php?files=apply-polish-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-polish-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_POLISH_FIX') !== false) exit("Zaten ekli (CH_POLISH_FIX).\n");
if (strpos($src, 'CH_PREMIUM_POLISH') === false) exit("HATA: CH_PREMIUM_POLISH kurulu degil — once apply-premium-polish.php.\n");
file_put_contents($file . '.bak-polishfix-' . date('Ymd-His'), $src);

$bundle = <<<'PFHTML'
<style id="ch-polish-fix-css">
/* ══ CH_POLISH_FIX — sol menu tasma duzeltmesi (kompakt premium) ══ */

/* menu kendi icinde kaydirilsin, asla disari tasmasin */
#sb{overflow-y:auto !important;overflow-x:hidden !important;scrollbar-width:thin}
#sb::-webkit-scrollbar{width:7px}
#sb::-webkit-scrollbar-thumb{background:rgba(212,168,74,.25);border-radius:100px}
#sb::-webkit-scrollbar-thumb:hover{background:rgba(212,168,74,.45)}

/* satirlar kompakt: cip 30px, dolgu dar */
#sb .sb-photo-btn{gap:10px !important;padding:6px 10px !important;border-radius:11px !important}
#sb .sb-photo-btn:hover{transform:none !important}
#sb .sb-photo-btn>svg{width:30px !important;height:30px !important;padding:6px !important;border-radius:9px !important}

/* yazilar tek satir + uc nokta — yana tasma biter */
#sb .sb-photo-btn>div{min-width:0;flex:1;overflow:hidden}
#sb .sb-photo-btn .sb-photo-t{font-size:12.5px !important;white-space:nowrap !important;
  overflow:hidden !important;text-overflow:ellipsis !important}
#sb .sb-photo-btn .sb-photo-s{font-size:10px !important;white-space:nowrap !important;
  overflow:hidden !important;text-overflow:ellipsis !important}

/* nav satirlari da kompakt */
#sb .sb-nav-item{padding-top:8px !important;padding-bottom:8px !important}

/* mobil logo: buyuk ama olculu */
@media(max-width:820px){
  .sb-logo svg#ch-nlogo2{width:40px !important;height:40px !important}
  .ch-lg-name{font-size:19px !important}
  .ch-lg-sub{font-size:10px !important}
  .sb-logo{padding:8px !important}
}
</style>
PFHTML;

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
echo "✓ CH_POLISH_FIX uygulandi: kompakt menu satirlari, uc-nokta kesme, #sb ic kaydirma, olculu mobil logo.\n";
