<?php
/**
 * Burberry resmî kareleri — ürün koduyla (runner'da, CLI)
 * ======================================================
 *   php tools/fetch-burberry.php ../targets.json
 *
 * Burberry ürün sayfası adresinde kodu taşıyor: 7 haneli ürün kodu 8096425
 * → sayfa "…-p80964251" (sona bir hane eklenmiş). Arama sayfası sorguyu
 * sunucuda işlemiyor; bu yüzden erkek listeleri (polo, t-shirt, sweatshirt)
 * sayfa sayfa taranıp kod → ürün sayfası eşlemesi çıkarılıyor. Bulunamayan
 * kodlar için Bing'de "site:burberry.com <kod>" deneniyor.
 *
 * Ürün sayfasından galeri kareleri (assets.burberry.com) sırayla alınır.
 * Sonuç fetch-photos-web.php ile AYNI biçimde: data/photo-web-report.json
 * kaydı + uploads/candidates/_web kontrol kolajı (ELDE | 1 2 3 …). Hiçbir kare
 * gözle onaylanmadan ürüne bağlanmaz (photo-web-approve.json → attach).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root    = dirname(__DIR__);
$targets = json_decode((string)@file_get_contents($argv[1] ?? ''), true);
if (!is_array($targets)) { fwrite(STDERR, "hedef listesi yok\n"); exit(1); }
$targets = array_values(array_filter($targets, fn($t) => stripos((string)$t['brand'], 'burberry') !== false
                                                        && preg_match('/^\d{7}$/', (string)($t['sku'] ?? ''))));
if (!$targets) { echo "Burberry kodlu hedef yok.\n"; exit(0); }

const BB_HOST = 'https://uk.burberry.com';

function bb_get(string $url, int $timeout = 30): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: en-GB,en;q=0.9'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

/** Sayfadaki "…-p8xxxxxxx" ürün bağlantıları → [7 haneli kod => yol]. */
function bb_links(string $html): array
{
    $out = [];
    if (preg_match_all('#href="(/[a-z0-9-]+-p(\d{8})/?)"#', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $path, $d]) $out[substr($d, 0, 7)] ??= rtrim($path, '/') . '/';
    }
    return $out;
}

// 1) Listeler
$want = array_flip(array_map(fn($t) => (string)$t['sku'], $targets));
$map  = []; $allPaths = []; $byColour = [];   // aynı modelin diğer renkleri (kod farklı, sezon)
$lists = ['/l/mens-clothing/polos-t-shirts/', '/l/mens-clothing/polo-shirts/', '/l/mens-clothing/t-shirts/',
          '/l/mens-clothing/sweatshirts-hoodies/', '/l/mens-clothing/', '/l/sale/mens/'];
foreach ($lists as $l) {
    for ($pg = 1; $pg <= 8; $pg++) {
        $html = bb_get(BB_HOST . $l . ($pg > 1 ? '?page=' . $pg : ''));
        if ($html === null) break;
        $found = bb_links($html);
        if (!$found) break;
        if (preg_match_all('#href="(/[a-z0-9-]+-p\d{8})/?"#', $html, $am)) foreach ($am[1] as $ap) $allPaths[$ap . '/'] = 1;
        $new = array_diff_key($found, $map);
        $map += $found;
        if (!array_diff_key($want, $map)) break 2;   // hepsi bulundu
        if (!$new) break;                             // sayfalama ilerlemiyor
        usleep(500000);
    }
}
// 2) Adres tahmini: Burberry sayfayı "<ad>-p<kod><hane>" ile açıyor ve aynı
//    modelin renkleri aynı adı taşıyor (siyah: cotton-polo-shirt-p80964251).
//    Bulunan kodların adları + yaygın adlar, son hane 1–4. Sayfa kodu
//    gerçekten taşıyorsa kabul.
$slugs = [];
foreach (array_intersect_key($map, $want) as $pth) $slugs[] = preg_replace('#^/([a-z0-9-]+)-p\d{8}/$#', '$1', $pth);
$slugs = array_values(array_unique(array_merge($slugs, ['cotton-polo-shirt', 'check-collar-cotton-polo-shirt',
          'cotton-pique-polo-shirt', 'check-collar-cotton-pique-polo-shirt', 'polo-shirt'])));
foreach (array_keys(array_diff_key($want, $map)) as $code) {
    foreach ($slugs as $sl) {
        foreach ([1, 2, 3, 4] as $d) {
            $pth = "/$sl-p$code$d/";
            $html = bb_get(BB_HOST . $pth, 20);
            if ($html !== null && str_contains($html, "p$code$d") && str_contains($html, 'assets.burberry.com')) { $map[$code] = $pth; continue 3; }
            usleep(250000);
        }
    }
}

// 2b) Aynı model, başka sezon kodu: renk sayfaları. Bulunan sayfanın adı
//     (cotton-polo-shirt) ya da yakalı model (check…polo) — sayfadaki renk
//     adıyla eşleşir. Kod farklı olduğu için bu kayıtlar "model" olarak
//     işaretlenir; onay yine gözle (kolajda renk tonu ve ayrıntı).
$modelSlugs = [];
foreach (array_intersect_key($map, $want) as $pth) $modelSlugs[] = preg_replace('#^/([a-z0-9-]+)-p\d{8}/$#', '$1', $pth);
foreach (array_keys($allPaths) as $pth) {
    $slug = preg_replace('#^/([a-z0-9-]+)-p\d{8}/$#', '$1', $pth);
    $isModel = in_array($slug, $modelSlugs, true) || (str_contains($slug, 'check') && str_contains($slug, 'polo') && !str_contains($slug, 'knit'));
    if (!$isModel) continue;
    $html = bb_get(BB_HOST . $pth, 20);
    if ($html === null) continue;
    $col = '';
    if (preg_match('/"colou?r(?:Name)?"\s*:\s*"([A-Za-z \/-]{3,30})"/', $html, $cm)) $col = $cm[1];
    elseif (preg_match('#<title>[^<]*?in ([A-Za-z /-]{3,30})\s*[-|<]#', $html, $cm)) $col = $cm[1];
    $byColour[] = ['path' => $pth, 'slug' => $slug, 'colour' => strtolower(trim($col)), 'check' => str_contains($slug, 'check')];
    usleep(300000);
}
printf("Aynı modelin renk sayfaları: %d\n", count($byColour));
foreach ($byColour as $b) printf("  %-50s %s\n", $b['path'], $b['colour']);

// 3) Bing (listede olmayanlar — eski sezon)
foreach (array_keys(array_diff_key($want, $map)) as $code) {
    $html = bb_get('https://www.bing.com/search?q=' . rawurlencode("site:burberry.com $code"));
    if ($html && preg_match('#https?://[a-z]{2}\.burberry\.com(/[a-z0-9-]+-p' . $code . '\d)/?#', $html, $m)) $map[$code] = $m[1] . '/';
    usleep(800000);
}
printf("Burberry ürün sayfası bulunan kod: %d / %d\n", count(array_intersect_key($map, $want)), count($want));

// 4) Ürün sayfası → galeri kareleri
$report = json_decode((string)@file_get_contents("$root/data/photo-web-report.json"), true) ?: [];
$sheetDir = "$root/uploads/candidates/_web";
@mkdir($sheetDir, 0755, true);
foreach ($targets as $n => $t) {
    $code = (string)$t['sku']; $id = (string)$t['id'];
    $path = $map[$code] ?? '';
    if ($path === '') {
        $tc = strtolower(trim((string)($t['colour'] ?? '')));
        $wantCheck = stripos((string)$t['name'], 'check') !== false;
        foreach ($byColour as $b) {
            if ($b['check'] !== $wantCheck || $tc === '' || !str_contains($b['colour'], $tc)) continue;
            $path = $b['path']; break;
        }
    }
    $imgs = [];
    if ($path !== '') {
        $html = bb_get(BB_HOST . $path) ?? '';
        // Galeri: sayfanın ürün verisindeki ilk kareler. Önerilen ürünlerin
        // kareleri de sayfada var — yalnız ürün bloğu (ilk "images"/"media"
        // dizisi) varsa oradan; yoksa ilk 8 benzersiz kare (kolajda elenir).
        $block = $html;
        if (preg_match('/"(?:images|media|gallery)"\s*:\s*\[(.{50,20000}?)\]/s', $html, $bm)) $block = $bm[1];
        if (preg_match_all('#assets\.burberry\.com/is/image/Burberryltd/([A-F0-9-]{36})#i', $block, $mm)) {
            foreach (array_unique($mm[1]) as $g) $imgs[] = "https://assets.burberry.com/is/image/Burberryltd/$g?\$BBY_V3_SL_1\$&wid=1600&hei=1600";
        }
        $imgs = array_slice($imgs, 0, 8);
    }
    $report[$id] = ['source' => $path !== '' ? BB_HOST . $path : '', 'title' => 'burberry.com ' . $code,
                    'images' => $imgs, 'codes' => [$code], 'brand' => (string)$t['brand'],
                    'name' => (string)$t['name'], 'colour' => (string)($t['colour'] ?? '')];
    bb_sheet($sheetDir . sprintf('/b%02d-%s.jpg', $n + 1, preg_replace('/[^a-z0-9_-]/i', '-', $id)),
             $root . (string)($t['current'] ?? ''), $imgs, $id . ' | ' . $t['name'] . ' | ' . ($path ?: 'bulunamadı'));
    printf("%-34s %d kare  %s\n", $id, count($imgs), $path);
}
file_put_contents("$root/data/photo-web-report.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

function bb_sheet(string $dst, string $curAbs, array $imgs, string $label): void
{
    $cw = 220; $ch = 275;
    $W = (count($imgs) + 1) * ($cw + 6) + 6; $H = $ch + 48;
    $s = imagecreatetruecolor(max($W, 500), $H);
    imagefill($s, 0, 0, imagecolorallocate($s, 250, 250, 248));
    $ink = imagecolorallocate($s, 20, 20, 20); $red = imagecolorallocate($s, 190, 0, 0);
    $put = function (string $raw, int $x) use ($s, $cw, $ch) {
        $im = @imagecreatefromstring($raw); if (!$im) return;
        $sc = min($cw / imagesx($im), $ch / imagesy($im));
        imagecopyresampled($s, $im, $x + intdiv($cw - (int)(imagesx($im) * $sc), 2), 22, 0, 0, (int)(imagesx($im) * $sc), (int)(imagesy($im) * $sc), imagesx($im), imagesy($im));
    };
    imagestring($s, 2, 6, 4, mb_substr($label, 0, 160), $ink);
    if (is_file($curAbs)) $put((string)file_get_contents($curAbs), 6);
    imagestring($s, 3, 6, 22 + $ch + 4, 'ELDE', $red);
    foreach ($imgs as $i => $u) {
        $raw = bb_get(str_replace('wid=1600&hei=1600', 'wid=500&hei=500', $u), 25);
        if ($raw !== null) $put($raw, 6 + ($i + 1) * ($cw + 6));
        imagestring($s, 3, 6 + ($i + 1) * ($cw + 6), 22 + $ch + 4, (string)($i + 1), $ink);
    }
    imagejpeg($s, $dst, 82);
}
