<?php
/**
 * ChatHelp — apply-restore-clean (CH_RESTORE_CLEAN)
 * KOK NEDEN: Her apply-*.php, yeni kodu </body>'den once eklerken dosyadaki
 * ILK '</body>' metnini hedef aliyordu. CV Studio / Vertrag Studio / Modulepack
 * kendi yazdirilabilir-PDF HTML'lerini JS string'i icinde kurarken metin olarak
 * '</body></html>' iceriyor (tarayici icin zararsiz — script icindeyken tarayici
 * sadece </script>'i arar). Contract Studio deploy olurken bu SAHTE </body>'yi
 * ilk eslesme sanip kendi bundle'ini CV Studio'nun scriptinin ORTASINA
 * sikistirdi — zincirleme bozulmaya yol acti (Fristen/Rechner/Tresor/Briefe/
 * CV/Vertrage nav'lari hic calismadi). Yerel Playwright testiyle birebir
 * dogrulandi ve kok neden kesinlestirildi.
 *
 * BU SCRIPT: index.php'yi, bozulmanin BASLADIGI andan hemen ONCEKI bilinen-
 * temiz yedege (index.php.bak-vst-*  — Contract Studio'nun kendi backup'i,
 * yani CV Studio basariyla calistiktan HEMEN SONRAKI durum) geri yukler.
 * Guvenlik: mevcut (bozuk) index.php de ayrica yedeklenir, geri yuklenecek
 * icerik php -l ile kontrol edilir.
 *
 * BUNDAN SONRA sirasiyla (fix'li) su dosyalar tekrar calistirilmali:
 *  apply-contract-studio.php, apply-fixpack10.php, apply-modulepack.php,
 *  apply-modhub.php, apply-modhub-nav.php, apply-fabfix.php,
 *  apply-jobs-tocard.php, apply-jobs-cvattach.php, apply-topbar-premium.php,
 *  apply-aichat-frontend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-restore-clean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$cur  = @file_get_contents($file);
if ($cur === false) exit("index.php okunamadi\n");

if (strpos($cur, 'CH_RESTORE_CLEAN_DONE') !== false) exit("Zaten geri yuklendi (CH_RESTORE_CLEAN_DONE).\n");

$candidates = glob(__DIR__ . '/index.php.bak-vst-*');
if (!$candidates) exit("HATA: index.php.bak-vst-* yedegi bulunamadi — geri yukleme yapilamadi.\n");
sort($candidates);
$src_bak = $candidates[0];

$clean = @file_get_contents($src_bak);
if ($clean === false) exit("yedek okunamadi: $src_bak\n");

$opens = substr_count($clean, '<script');
$closes = substr_count($clean, '</script>');
echo "Geri yuklenecek yedek: " . basename($src_bak) . " (" . strlen($clean) . " bayt)\n";
echo "  <script acilis=$opens  kapanis=$closes\n";

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $clean);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — yedek de bozuk, geri yukleme YAPILMADI:\n  " . implode("\n  ", $lo) . "\n"; exit; }

file_put_contents($file . '.bak-precorrupt-' . date('Ymd-His'), $cur);

$clean = rtrim($clean) . "\n<!-- CH_RESTORE_CLEAN_DONE -->\n";
file_put_contents($file, $clean);

echo "\n✓ index.php, '" . basename($src_bak) . "' yedegine geri yuklendi.\n";
echo "Simdi sirasiyla (fix'li) su dosyalari calistir:\n";
echo "  pull-updates.php?files=apply-contract-studio.php,apply-fixpack10.php,apply-modulepack.php,apply-modhub.php,apply-modhub-nav.php,apply-fabfix.php,apply-jobs-tocard.php,apply-jobs-cvattach.php,apply-topbar-premium.php,apply-aichat-frontend.php\n";
