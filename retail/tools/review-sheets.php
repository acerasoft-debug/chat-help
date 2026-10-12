<?php
/**
 * Toplu aday kolajı — CLI (runner'da)
 * ===================================
 *   php tools/review-sheets.php candidates.json <eldeki kareler kökü> <çıktı klasörü>
 *
 * find-photos-ddg.php çıktısını gözden geçirilebilir sayfalara döker: her
 * satır bir ürün — solda ELDEKİ kare, sağda numaralı adaylar (1, 2, 3 …).
 * Aynı numaralar data/photo-web-report.json'a yazılır; onay her zamanki gibi
 * data/photo-web-approve.json'da (id → [numaralar]), indirme
 * tools/attach-web-photos.php ile.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
[, $in, $curRoot, $outDir] = $argv + [null, null, null, null];
$all = json_decode((string)@file_get_contents((string)$in), true);
if (!is_array($all)) { fwrite(STDERR, "aday listesi yok\n"); exit(1); }
@mkdir((string)$outDir, 0755, true);
$root = dirname(__DIR__);

function rs_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/jpeg,image/png,*/*;q=0.8'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

$report = json_decode((string)@file_get_contents("$root/data/photo-web-report.json"), true) ?: [];
$rows = [];
foreach ($all as $id => $c) {
    if (!is_array($c) || empty($c['sources'])) continue;
    $imgs = []; $pages = [];
    foreach ($c['sources'] as $s) foreach ((array)$s['images'] as $u) { $imgs[] = (string)$u; $pages[] = (string)$s['page']; }
    $rows[] = ['id' => (string)$id, 'c' => $c, 'imgs' => $imgs, 'pages' => $pages];
}
$tw = 132; $th = 165; $maxC = 9; $per = 10;
foreach (array_chunk($rows, $per) as $pi => $chunk) {
    $W = 250 + ($maxC + 1) * ($tw + 4); $H = count($chunk) * ($th + 26) + 6;
    $s = imagecreatetruecolor($W, $H);
    imagefill($s, 0, 0, imagecolorallocate($s, 252, 252, 250));
    $ink = imagecolorallocate($s, 15, 15, 15); $grey = imagecolorallocate($s, 105, 105, 105); $red = imagecolorallocate($s, 190, 0, 0);
    foreach ($chunk as $ri => $r) {
        $y = 4 + $ri * ($th + 26);
        imageline($s, 0, $y - 2, $W, $y - 2, imagecolorallocate($s, 200, 200, 200));
        imagestring($s, 3, 4, $y, substr($r['id'], 0, 34), $ink);
        $note = wordwrap((string)$r['c']['note'], 38, "\n", true);
        foreach (array_slice(explode("\n", $note), 0, 4) as $li => $line) imagestring($s, 2, 4, $y + 16 + $li * 13, $line, $grey);
        $hosts = array_values(array_unique(array_map(fn($p) => (string)parse_url($p, PHP_URL_HOST), $r['pages'])));
        foreach (array_slice($hosts, 0, 3) as $li => $hst) imagestring($s, 1, 4, $y + 72 + $li * 10, substr($hst, 0, 44), $grey);
        $cells = [['ELDE', is_file($curRoot . $r['c']['current']) ? (string)file_get_contents($curRoot . $r['c']['current']) : null]];
        $sizes = [];
        foreach (array_slice($r['imgs'], 0, $maxC) as $i => $u) { $b = rs_get($u); $cells[] = [(string)($i + 1), $b]; }
        foreach ($cells as $ci => [$lab, $b]) {
            $x = 250 + $ci * ($tw + 4);
            $im = $b !== null ? @imagecreatefromstring($b) : false;
            if ($im) {
                $w = imagesx($im); $h = imagesy($im);
                if ($ci > 0) $sizes[$ci - 1] = "{$w}x{$h}";
                $sc = min($tw / $w, $th / $h);
                imagecopyresampled($s, $im, $x + intdiv($tw - (int)($w * $sc), 2), $y, 0, 0, (int)($w * $sc), (int)($h * $sc), $w, $h);
                imagedestroy($im);
                imagestring($s, 1, $x, $y + $th + 12, "{$w}x{$h}", ($w < 600 && $h < 600) ? $red : $grey);
            } elseif ($ci > 0) { $sizes[$ci - 1] = 'HATA'; }
            imagestring($s, 4, $x, $y + $th - 2, $lab, $ci === 0 ? $red : $ink);
        }
        $report[$r['id']] = ['source' => $r['pages'][0] ?? '', 'title' => (string)$r['c']['note'], 'images' => array_slice($r['imgs'], 0, $maxC),
                             'pages' => array_slice($r['pages'], 0, $maxC), 'sizes' => $sizes, 'codes' => [],
                             'brand' => (string)$r['c']['brand'], 'name' => (string)$r['c']['name'], 'colour' => (string)$r['c']['colour']];
    }
    imagejpeg($s, sprintf('%s/r-%02d.jpg', $outDir, $pi + 1), 82);
    fwrite(STDERR, sprintf("sayfa %d: %d ürün\n", $pi + 1, count($chunk)));
}
file_put_contents("$root/data/photo-web-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
printf("%d ürünün adayı var, %d sayfa\n", count($rows), (int)ceil(count($rows) / $per));
