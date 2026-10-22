<?php
/**
 * ChatHelp — #11 DAHA ACIK PREMIUM ANTRASIT TEMA
 * ------------------------------------------------------------------------
 *  Mevcut :root (dump kaniti):
 *    --bg:#07070e; --s1:#0d0d1c; --s2:#131325; --s3:#191930; --s4:#21213a;
 *    body: background:#07070e!important
 *  Bunlar cok koyu ve hafif LACIVERT tonlu. Kullanici: "antrasit ama eski
 *  tonda daha ACIK, premium".
 *  Cozum: </body> oncesine YENI bir <style> enjekte -> :root degiskenlerini
 *  daha acik/notr antrasit degerlerle YENIDEN tanimlar (cascade'de sonra gelen
 *  kazanir, tum var(--sX) kullanan yuzeyler otomatik guncellenir) + body'nin
 *  sabit koyu arka planini var(--bg) ile ezer. Altin (--gold) korunur.
 *
 * KULLANIM: pull-updates.php?files=apply-lighter-theme.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-lighter-theme BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_LIGHTER_THEME")!==false) exit("Zaten ekli (CH_LIGHTER_THEME).\n");
if(strpos($src,"</body>")===false) exit("</body> yok\n");
file_put_contents($file.".bak-lightertheme-".date("Ymd-His"),$src);

$style=<<<'CSS'
<style id="ch-lighter-theme">
/* CH_LIGHTER_THEME — daha acik premium antrasit (notr, hafif sicak grafit) */
:root{
  --bg:#17171d; --s1:#1d1d24; --s2:#25252e; --s3:#2e2e38; --s4:#393943;
  --bdr:rgba(255,255,255,.10);
  --t3:#b2b2cc; --t4:#6d6d92;
}
html,body{ background:var(--bg)!important; }
#sb{ background:var(--s1)!important; }
/* auth/modal koyu kenarliklarini da temaya uydur */
[style*="#2a2a4a"]{ border-color:rgba(255,255,255,.12)!important; }
</style>
CSS;

$src=preg_replace('/<\/body>/', $style."\n</body>", $src, 1, $n);
if($n<1 || $src===$start) exit("degisiklik yok (n=$n)\n");
file_put_contents($file,$src);
echo "✓ Daha acik premium antrasit tema eklendi (CH_LIGHTER_THEME).\n";
echo "  --bg #07070e -> #17171d, --s1..--s4 kademeli acildi, altin korundu.\n";
echo "SIL: rm apply-lighter-theme.php\n";
echo "Test: gizli pencere -> zemin gozle gorulur daha acik/notr antrasit, kutular biraz daha kalkik, altin ayni.\n";
