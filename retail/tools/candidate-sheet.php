<?php
/**
 * Elle seçilmiş aday kareler için kontrol kolajı — CLI (runner'da)
 * ================================================================
 *   php tools/candidate-sheet.php data/web-candidates/<ad>.json
 *
 * Girdi: { "<ürün id>[@kod]": { "note": "…", "sources": [
 *            { "page": "<ürün sayfası>", "images": ["<kare>", …] }, … ] }, … }
 *
 * Her id için kaynakların kareleri sırayla numaralanır (1, 2, 3 …), indirilir,
 * boyutuyla birlikte kolaja basılır: uploads/candidates/_web/c-<id>.jpg.
 * Aynı numaralı liste data/photo-web-report.json'a yazılır; onay her zamanki
 * gibi data/photo-web-approve.json'da numarayla verilir, indirme
 * tools/attach-web-photos.php ile. Hiçbir kare onaysız ürüne bağlanmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root = dirname(__DIR__);
$spec = json_decode((string)@file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) { fwrite(STDERR, "aday listesi okunamadı\n"); exit(1); }

function cs_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/jpeg,image/png,*/*;q=0.8'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

$report = json_decode((string)@file_get_contents("$root/data/photo-web-report.json"), true) ?: [];
$dir = "$root/uploads/candidates/_web";
@mkdir($dir, 0755, true);

foreach ($spec as $id => $s) {
    $imgs = []; $pages = [];
    foreach ((array)($s['sources'] ?? []) as $src) {
        foreach ((array)($src['images'] ?? []) as $u) { $imgs[] = (string)$u; $pages[] = (string)($src['page'] ?? ''); }
    }
    $cw = 230; $ch = 300; $cols = min(6, max(1, count($imgs))); $rows = (int)ceil(count($imgs) / $cols);
    $sheet = imagecreatetruecolor($cols * ($cw + 8) + 8, $rows * ($ch + 40) + 30);
    imagefill($sheet, 0, 0, imagecolorallocate($sheet, 250, 250, 248));
    $ink = imagecolorallocate($sheet, 20, 20, 20); $grey = imagecolorallocate($sheet, 110, 110, 110); $red = imagecolorallocate($sheet, 190, 0, 0);
    imagestring($sheet, 3, 8, 8, substr($id . '  ' . (string)($s['note'] ?? ''), 0, 180), $ink);
    $sizes = [];
    foreach ($imgs as $i => $u) {
        $x = 8 + ($i % $cols) * ($cw + 8); $y = 30 + intdiv($i, $cols) * ($ch + 40);
        $b = cs_get($u); $im = $b !== null ? @imagecreatefromstring($b) : false;
        if ($im) {
            $w = imagesx($im); $h = imagesy($im); $sizes[$i] = "{$w}x{$h}";
            $sc = min($cw / $w, $ch / $h);
            imagecopyresampled($sheet, $im, $x + intdiv($cw - (int)($w * $sc), 2), $y, 0, 0, (int)($w * $sc), (int)($h * $sc), $w, $h);
            imagedestroy($im);
        } else { $sizes[$i] = 'HATA'; }
        imagestring($sheet, 5, $x, $y + $ch + 2, (string)($i + 1), $sizes[$i] === 'HATA' ? $red : $ink);
        imagestring($sheet, 2, $x + 22, $y + $ch + 4, $sizes[$i] . '  ' . (string)parse_url($pages[$i], PHP_URL_HOST), $grey);
        usleep(200000);
    }
    $f = sprintf('%s/c-%s.jpg', $dir, preg_replace('/[^a-z0-9_-]/i', '-', $id));
    imagejpeg($sheet, $f, 84);
    $report[$id] = ['source' => $pages[0] ?? '', 'title' => (string)($s['note'] ?? ''), 'images' => $imgs,
                    'pages' => $pages, 'sizes' => array_values($sizes), 'codes' => [], 'brand' => (string)($s['brand'] ?? ''),
                    'name' => (string)($s['name'] ?? ''), 'colour' => (string)($s['colour'] ?? '')];
    printf("%-36s %2d kare  %s\n", $id, count($imgs), implode(' ', $sizes));
}
file_put_contents("$root/data/photo-web-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
