<?php
/**
 * ChatHelp — apply-menufix5 (CH_MENUFIX5) — sol menu: TAM + BENZERSIZ order
 *
 *  CANLI TESHIS (apply-sbprobe) sonucu:
 *   • #sb flex-column, nav'lar dogrudan cocuk, order'lar uygulanmis,
 *     1.5 sn'de HICBIR sey oynamadi -> menufix4 salinimı DURDURDU.
 *   • GERCEK sorun: dort oge ayni order=0 (.sb-head, .sb-nav, .sb-docs-label,
 *     .sb-foot) -> flexbox order=0'lari DOM sirasina gore dizer -> footer/
 *     etiketler ustte kumeleniyor, sira karisik gorunuyor. Ayrica
 *     nav-fris/rech/tresor/briefe hepsi order=15 (esit) -> DOM sirasina kalmis.
 *
 *  COZUM: #sb'nin HER dogrudan cocuguna BENZERSIZ order (cakisma yok);
 *  footer en alta (99) sabit. Bu blok menufix4'ten SONRA gelir -> esitlikte
 *  kazanir. Hepsi !important -> hicbir JS ezemez.
 * KULLANIM: pull-updates.php?files=apply-menufix5.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menufix5 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENUFIX5')!==false) exit("Zaten ekli (CH_MENUFIX5).\n");

$bundle = <<<'HTML'
<style id="ch-menufix5-css">
/* CH_MENUFIX5 — sol menu: her dogrudan cocuga BENZERSIZ order, footer alta sabit.
   menufix4'ten sonra geldigi icin esit ozgullukte KAZANIR; !important -> JS ezemez. */
#sb{display:flex !important;flex-direction:column !important}
/* ust bolge */
#sb>.sb-head{order:0 !important}                  /* logo/baslik */
#sb>.sb-nav{order:1 !important}
#sb>[onclick="NC()"]{order:2 !important}           /* Yeni sohbet / Chat */
#sb>[onclick*="openFall"]{order:3 !important}      /* Dava Aç / Fall eröffnen */
#sb>.sb-docs-label{order:4 !important}             /* bolum etiketi */
/* modul nav'lari — sabit sira */
#sb>#nav-verlauf{order:10 !important}
#sb>#nav-jobs{order:11 !important}
#sb>#nav-cv{order:12 !important}
#sb>#nav-vst{order:13 !important}
#sb>#nav-trvisa{order:14 !important}
#sb>#nav-fris{order:15 !important}
#sb>#nav-rech{order:16 !important}
#sb>#nav-tresor{order:17 !important}
#sb>#nav-briefe{order:18 !important}
#sb>#nav-hub{order:19 !important}
/* bilinmeyen gelecek nav'lar: hub'dan sonra, footer'dan once */
#sb>[id^="nav-"]{order:20 !important}
/* footer HER ZAMAN en altta */
#sb>.sb-foot{order:99 !important}
</style>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index.php degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ her cocuga benzersiz order + footer alta sabit eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'m5').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menufix5-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENUFIX5 uygulandi. Sira: logo → nav → Chat → Dava Aç → etiket →\n";
echo "   Verlauf → Jobs → CV → VST → TR-Visa → Fristen → Rechner → Tresor → Briefe → Hub → (footer alta).\n";
echo "   Cakisma yok, footer sabit, hicbir JS ezemez.\n";
