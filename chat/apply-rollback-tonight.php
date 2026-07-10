<?php
/**
 * ChatHelp — apply-rollback-tonight — BU GECEKI DEGISIKLIKLERI GERI AL
 *  index.php'yi, kullanicinin ekran goruntulerinde SITE CALISIRKEN gordugu
 *  birebir hale dondurur: yedek "index.php.bak-sbprobe3-rm-*" (bu gecenin
 *  v2 turundan ONCEKI son canli durum; izleme paneli DAHIL o an siteydi).
 *  Mevcut hal, geri donebilmek icin index.php.bak-preroll-* olarak saklanir.
 *  NOT: api.php / fall-api.php'ye DOKUNMAZ (dil duzeltmeleri sayfa
 *  gorunumunu etkilemez ve saglamligi kanitli).
 * KULLANIM: pull-updates.php?files=apply-rollback-tonight.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$D = __DIR__;
echo "apply-rollback-tonight BASLADI OK\n\n";

/* bu gecenin v2-oncesi son durumu: sbprobe3-rm yedegi (en yenisi) */
$cands = glob($D.'/index.php.bak-sbprobe3-rm-*');
usort($cands, fn($a,$b)=>filemtime($b)-filemtime($a));
if (!$cands) exit("HATA: index.php.bak-sbprobe3-rm-* yedegi bulunamadi.\n");
$src = $cands[0];

$lo=[];$rc=0; exec('php -l '.escapeshellarg($src).' 2>&1',$lo,$rc);
if ($rc!==0) exit("HATA: secilen yedek lint'ten gecemedi: ".implode(' | ',$lo)."\n");

echo "Secilen yedek: ".basename($src)." (".date('m-d H:i:s',filemtime($src)).", ".number_format(filesize($src))." B)\n";
if (!@copy($D.'/index.php', $D.'/index.php.bak-preroll-'.date('Ymd-His')))
    exit("HATA: mevcut halin guvenlik kopyasi alinamadi — islem iptal.\n");
if (!@copy($src, $D.'/index.php'))
    exit("HATA: geri yukleme kopyalanamadi.\n");

$lo=[];$rc=0; exec('php -l '.escapeshellarg($D.'/index.php').' 2>&1',$lo,$rc);
echo $rc===0 ? "  ✓ geri yuklendi ve lint TEMIZ\n" : "  ✗ lint sorunu: ".implode(' | ',$lo)."\n";

echo "\n✓ index.php, bu gece v2 oncesindeki (ekran goruntulerinde calisan) hale dondu.\n";
echo "  Bilgi: sag alttaki 👁️ izleme dugmesi bu haliyle GERI GELIR (o zaman da vardi).\n";
echo "  Geri almak istersen: index.php.bak-preroll-* kopyasi duruyor.\n";
echo "\nSIMDI KRITIK TEST: cihazinda /chat/ hala 403 veriyorsa bu, sorunun dosyalarda\n";
echo "  DEGIL, GoDaddy edge/guvenlik katmaninda oldugunun KESIN KANITI olur —\n";
echo "  cozum: GoDaddy panel -> Flush Cache (+ Security/Firewall kontrolu).\n";
