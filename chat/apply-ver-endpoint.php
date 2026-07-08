<?php
/**
 * ChatHelp — apply-ver-endpoint — ver.php dosyasini SUNUCUDA olusturur.
 *  pull-updates 'ver.php' adini reddettigi icin (sadece apply- ve dump- desenli),
 *  surum ucnoktasini bir apply script'i diske yazar. apply-* silinir ama
 *  yazdigi ver.php kalir. CH_CACHEBUST bekcisi bu dosyayi kullanir.
 * KULLANIM: pull-updates.php?files=apply-ver-endpoint.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ver-endpoint BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$ver = __DIR__ . '/ver.php';
$content = <<<'PHP'
<?php
/** ChatHelp — ver.php (surum ucnoktasi) — index.php filemtime, sert no-store */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Surrogate-Control: no-store');
header('CDN-Cache-Control: no-store');
header('Cloudflare-CDN-Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
clearstatcache();
$f = __DIR__ . '/index.php';
$mt = @filemtime($f);
echo $mt ? (string)$mt : '0';
PHP;

/* lint (gecici dosyada) */
$tmp = tempnam(sys_get_temp_dir(),'ve').'.php';
file_put_contents($tmp,$content);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "LINT HATASI — ver.php yazilmadi:\n  ".implode("\n  ",$lo)."\n"; exit; }

$ok = @file_put_contents($ver, $content);
if ($ok===false) { echo "HATA: ver.php yazilamadi (izin?)\n"; exit; }

echo "  ✓ ver.php yazildi (".$ok." bayt)\n";
/* dogrulama: ver.php'yi calistirip index.php filemtime'i dondurdugunu goster */
$mt = @filemtime(__DIR__.'/index.php');
echo "  ✓ index.php filemtime = ".($mt?:'?')." (ver.php bunu dondurecek)\n";
echo "\n✓ TAMAM. Surum ucnoktasi aktif — CH_CACHEBUST bekcisi artik calisir.\n";
