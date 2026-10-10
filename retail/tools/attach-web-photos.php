<?php
/**
 * Gözle onaylanan internet karelerini ürüne bağla — CLI (runner'da)
 * ================================================================
 *   php tools/attach-web-photos.php [--dry]
 *
 * GİRDİ
 *   data/photo-web-report.json   runner'ın bulduğu adaylar (id → images[])
 *   data/photo-web-approve.json  ELLE yazılır: id → [1, 3, …] (kolajdaki
 *                                numaralar) ya da id → [] (hiçbiri)
 *
 * ÇIKTI
 *   uploads/licensed/web/<marka>/<id>-wN.jpg  (en uzun kenar 1600 px)
 *   data/product-images-extra.json            id → [yol, …] (katalog bunu
 *                                             Vestra satırının karelerinin
 *                                             ARKASINA ekliyor)
 *   data/photo-web-attached.json              kaynak defteri: kare → sayfa
 *
 * Vestra'nın listings.json'una dokunulmaz. Onaylanmayan hiçbir şey inmez.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root = dirname(__DIR__);
$dry  = in_array('--dry', $argv, true);

$rd = static fn(string $f) => json_decode((string)@file_get_contents($root . '/data/' . $f), true) ?: [];
$report  = $rd('photo-web-report.json');
$approve = $rd('photo-web-approve.json');
$extra   = $rd('product-images-extra.json');
$ledger  = $rd('photo-web-attached.json');
$src     = $rd('image-sources.json');
if (empty($src['rights_confirmed'])) { fwrite(STDERR, "rights_confirmed=false — hiçbir şey indirilmiyor\n"); exit(1); }
if (!$approve) { echo "Onay listesi boş (data/photo-web-approve.json).\n"; exit(0); }

function awp_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/jpeg,*/*;q=0.8'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

/** En uzun kenar en çok $max px, beyaz zemin üstüne JPEG. */
function awp_save(string $bytes, string $dst, int $max = 1600): bool
{
    $im = @imagecreatefromstring($bytes);
    if (!$im) return false;
    $w = imagesx($im); $h = imagesy($im);
    if ($w < 500 || $h < 500) { imagedestroy($im); return false; }
    $sc = min(1.0, $max / max($w, $h));
    $dw = (int)round($w * $sc); $dh = (int)round($h * $sc);
    $out = imagecreatetruecolor($dw, $dh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $dw, $dh, $w, $h);
    @mkdir(dirname($dst), 0755, true);
    $ok = imagejpeg($out, $dst, 86);
    imagedestroy($im); imagedestroy($out);
    return $ok;
}

$done = 0; $files = 0;
foreach ($approve as $id => $nums) {
    $id = (string)$id;
    if (!preg_match('/^[a-z0-9-]+$/i', $id)) { echo "  geçersiz id: $id\n"; continue; }
    $r = $report[$id] ?? null;
    if (!$r) { echo "  raporda yok: $id\n"; continue; }
    $brand = preg_replace('/[^a-z0-9]+/', '-', strtolower((string)($r['brand'] ?? 'x'))) ?: 'x';
    $paths = [];
    foreach ((array)$nums as $n) {
        $n = (int)$n;
        $url = (string)($r['images'][$n - 1] ?? '');
        if ($url === '') { echo "  $id: #$n yok\n"; continue; }
        $rel = "/uploads/licensed/web/$brand/$id-w$n.jpg";
        if ($dry) { $paths[] = $rel; continue; }
        $b = awp_get($url);
        if ($b === null || !awp_save($b, $root . $rel)) { echo "  $id: #$n indirilemedi ($url)\n"; continue; }
        $paths[] = $rel; $files++;
        $ledger[$rel] = ['id' => $id, 'url' => $url, 'page' => (string)($r['source'] ?? ''), 'at' => gmdate('c')];
    }
    if (!$paths) continue;
    $extra[$id] = array_values(array_unique(array_merge((array)($extra[$id] ?? []), $paths)));
    $done++;
    printf("  %-34s +%d  %s\n", $id, count($paths), parse_url((string)($r['source'] ?? ''), PHP_URL_HOST));
}

if (!$dry) {
    ksort($extra); ksort($ledger);
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    file_put_contents($root . '/data/product-images-extra.json', json_encode($extra, $flags) . "\n");
    file_put_contents($root . '/data/photo-web-attached.json', json_encode($ledger, $flags) . "\n");
}
printf("%s: %d ürün, %d kare\n", $dry ? 'KURU' : 'TAMAM', $done, $dry ? 0 : $files);
