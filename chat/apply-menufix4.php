<?php
/**
 * ChatHelp — apply-menufix4 (CH_MENUFIX4) — sol menu GERCEK NIHAI cozum
 *
 *  TESHIS (dump-sidebar / dump-sidebar2 ile kesinlesti):
 *   • nav'lar #sb'nin DOGRUDAN cocuklari; her modul kendi yuklendiginde ekler.
 *   • GERCEK nav id'leri yalnizca 6: nav-verlauf, nav-jobs, nav-cv, nav-vst,
 *     nav-trvisa, nav-hub. (nav-fris/rech/tresor/briefe HIC YOK — o moduller
 *     Hub icinden acilir; eski yamalar hayalet id ariyordu.)
 *   • Iki enforcer birbirini besliyordu: menufix2 DOM'u insertBefore ile
 *     tasiyor; menufix3 nav-disi cocuklara order=k*100 (CANLI DOM index) veriyor
 *     -> menufix2 tasidikca k degisiyor -> menufix3 yeniden hesaplıyor ->
 *     mutasyon -> menufix2 tekrar tasiyor -> SONSUZ SALINIM = surekli oynama.
 *
 *  COZUM (JS sirasini tamamen birak):
 *   [1] STATIK CSS `!important order` — #sb flex-column; her ogeye kimligine
 *       gore SABIT order. !important CSS hem JS inline order'ini hem DOM
 *       sirasini EZER -> hicbir JS/gozcu gorsel konumu oynatamaz. Gerceklerle
 *       (6 gercek id + chat NC() + Fall openFall) yazildi; bilinmeyen gelecek
 *       nav'lar icin de guvenli yedek order.
 *   [2] menufix3 JS'ini devre disi birak (salinimı baslatan taraf). menufix2
 *       DOM'u tasimaya devam etse bile CSS order gorsel sonucu sabitledigi icin
 *       ARTIK GORUNMEZ (reflow olur, hareket olmaz).
 * KULLANIM: pull-updates.php?files=apply-menufix4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menufix4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENUFIX4')!==false) exit("Zaten ekli (CH_MENUFIX4).\n");

/* ── [2] menufix3 JS'ini erken-return ile devre disi birak (varsa) ── */
$m3old = "try{(function(){\n  var SEQ=[\"nav-verlauf\",\"nav-jobs\",\"nav-cv\",\"nav-vst\",\"nav-trvisa\",\"nav-fris\",\"nav-rech\",\"nav-tresor\",\"nav-briefe\",\"nav-hub\"];";
$m3new = "try{(function(){ return; /*CH_MENUFIX4: menufix3 JS devre disi — sira artik statik CSS !important order ile sabit; salinim durdu*/\n  var SEQ=[\"nav-verlauf\",\"nav-jobs\",\"nav-cv\",\"nav-vst\",\"nav-trvisa\",\"nav-fris\",\"nav-rech\",\"nav-tresor\",\"nav-briefe\",\"nav-hub\"];";
$c3=0; $src=str_replace($m3old,$m3new,$src,$c3);
echo ($c3===1?"  ✓ [2] menufix3 JS devre disi birakildi (salinim kaynagi)\n"
             :"  ~ [2] menufix3 JS anchor bulunamadi (x$c3) — sorun degil, asil cozum CSS\n");

/* ── [1] statik CSS !important order (asil ve garantili cozum) ── */
$bundle = <<<'HTML'
<style id="ch-menufix4-css">
/* CH_MENUFIX4 — sol menu NIHAI sabit sira: statik CSS !important order.
   !important CSS, JS inline order'ini ve DOM sirasini EZER; hicbir gozcu/
   insertBefore/interval gorsel konumu oynatamaz. Yalnizca #sb'nin DOGRUDAN
   cocuklarina uygulanir (> combinator). */
#sb{display:flex !important;flex-direction:column !important}
#sb>.sb-head{order:0 !important}                 /* logo/baslik hep en ust */
#sb>[onclick="NC()"]{order:5 !important}          /* Chat/Yeni sohbet nav'i */
#sb>[onclick*="openFall"]{order:6 !important}     /* Fall eröffnen dugmesi */
#sb>#nav-verlauf{order:10 !important}
#sb>#nav-jobs{order:11 !important}
#sb>#nav-cv{order:12 !important}
#sb>#nav-vst{order:13 !important}
#sb>#nav-trvisa{order:14 !important}
#sb>#nav-hub{order:20 !important}
/* bilinmeyen gelecek nav'lar: bilinenlerin arasinda kalir, en ust/alta ziplamaz */
#sb>[id^="nav-"]{order:15 !important}
</style>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index.php degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [1] statik CSS !important order eklendi (asil kesin cozum)\n";

$tmp = tempnam(sys_get_temp_dir(),'m4').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menufix4-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENUFIX4 uygulandi. Sira artik statik CSS !important order ile SABIT.\n";
echo "   Hicbir JS/gozcu/DOM tasima gorsel konumu oynatamaz — salinim bitti.\n";
