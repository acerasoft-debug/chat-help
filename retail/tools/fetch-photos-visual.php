<?php
/**
 * Eksik açılar — butik kataloglarında GÖRSEL eşleştirme (runner'da, CLI)
 * =====================================================================
 *   php tools/fetch-photos-visual.php ../targets.json [--stores=a.com,b.com]
 *
 * NEDEN
 *   Model kodu olmayan ürünler ("T-Shirt", "Graphic Sweatshirt") metin
 *   aramasıyla bulunamıyor (fetch-photos-web.php: 154 ürün 0 aday). Burada
 *   Shopify altyapılı İtalyan butiklerin TÜM kataloğu (products.json) marka
 *   marka çekiliyor; aynı kategorideki ürünlerin ilk karesi, eldeki karemizle
 *   görsel olarak karşılaştırılıyor (kenarı kırpılmış 256 bit fark özeti +
 *   4×4 renk). En yakın üç ürün kontrol kolajına giriyor.
 *
 * ÇIKTI (hiçbir şey ürüne BAĞLANMAZ — önce gözle onay)
 *   uploads/candidates/_visual/<id>.jpg   kolaj: ELDE | 1.1 1.2 … | 2.1 … | 3.1 …
 *   data/photo-web-report.json            "vis:<id>:<k>" kayıtları (k = aday ürün):
 *                                         images[], source (ürün sayfası)
 *   Onay: data/photo-web-approve.json'a  "<id>": {"from": "vis:<id>:<k>", "n": [1,2,…]}
 *   Bağlama: attach-web-photos.yml (değişmedi).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root    = dirname(__DIR__);
$targets = json_decode((string)@file_get_contents($argv[1] ?? ''), true);
if (!is_array($targets)) { fwrite(STDERR, "hedef listesi yok\n"); exit(1); }
$stores = ['www.nugnes1920.com', 'www.gebnegozionline.com', 'www.biffi.com', 'www.suitnegozi.com'];
foreach ($argv as $a) if (str_starts_with($a, '--stores=')) $stores = array_filter(explode(',', substr($a, 9)));

$approve = json_decode((string)@file_get_contents("$root/data/photo-web-approve.json"), true) ?: [];
$repFile = "$root/data/photo-web-report.json";
$report  = json_decode((string)@file_get_contents($repFile), true) ?: [];
$outDir  = "$root/uploads/candidates/_visual";
@mkdir($outDir, 0755, true);

function fv_get(string $url, int $timeout = 30): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

/** Marka anahtarı: "Dolce & Gabbana" = "dolcegabbana", "DSQUARED2" = "dsquared2". */
function fv_bkey(string $b): string
{
    $k = strtolower(preg_replace('/[^a-z0-9]/i', '', str_replace('&', '', $b)) ?? '');
    return ['dg' => 'dolcegabbana', 'galleryd' => 'gallerydept', 'gallerydept' => 'gallerydept'][$k] ?? $k;
}

/** Kategori kelimeleri: bizim kategori/ad → butik başlığında aranacaklar. */
function fv_kind(string $text): string
{
    $t = strtolower($text);
    foreach ([
        'swim'   => '/swim|costume|bagno|boxer mare|beach short/',
        'polo'   => '/\bpolo\b/',
        'hoodie' => '/hood|felpa con cappuccio/',
        'sweat'  => '/sweat|felpa|crewneck|jumper/',
        'jeans'  => '/jean|denim/',
        'shorts' => '/short|bermuda/',
        'pants'  => '/trouser|pant|jogger|pantalon/',
        'tee'    => '/t-shirt|tee\b|t shirt|tshirt|maglietta/',
        'shirt'  => '/\bshirt|camicia/',
        'jacket' => '/jacket|giubbotto|blouson|gilet|vest/',
    ] as $k => $re) if (preg_match($re, $t)) return $k;
    return '';
}

/* ---------------------------------------------- görsel imza (index-photo-hash ile aynı) */
function fv_sig(string $raw): ?array
{
    $im = @imagecreatefromstring($raw);
    if (!$im instanceof \GdImage) return null;
    if (!imageistruecolor($im)) imagepalettetotruecolor($im);
    $c = @imagecropauto($im, IMG_CROP_THRESHOLD, 0.10, imagecolorat($im, 0, 0));
    $t = ($c instanceof \GdImage && imagesx($c) > 20 && imagesy($c) > 20) ? $c : $im;
    $g = imagecreatetruecolor(17, 16);
    imagecopyresampled($g, $t, 0, 0, 0, 0, 17, 16, imagesx($t), imagesy($t));
    $bits = '';
    for ($y = 0; $y < 16; $y++) { $prev = null;
        for ($x = 0; $x < 17; $x++) { $p = imagecolorat($g, $x, $y);
            $l = (($p >> 16) & 255) * 299 + (($p >> 8) & 255) * 587 + ($p & 255) * 114;
            if ($prev !== null) $bits .= $l > $prev ? '1' : '0'; $prev = $l; } }
    $s = imagecreatetruecolor(4, 4);
    imagecopyresampled($s, $t, 0, 0, 0, 0, 4, 4, imagesx($t), imagesy($t));
    $col = [];
    for ($y = 0; $y < 4; $y++) for ($x = 0; $x < 4; $x++) { $p = imagecolorat($s, $x, $y); $col[] = ($p >> 16) & 255; $col[] = ($p >> 8) & 255; $col[] = $p & 255; }
    return ['b' => $bits, 'c' => $col, 'ar' => imagesy($t) / max(1, imagesx($t))];
}

/** Düşük = daha benzer. Bit farkı + renk farkı + biçim farkı. */
function fv_score(array $a, array $b): float
{
    $d = 0; for ($i = 0; $i < 256; $i++) if ($a['b'][$i] !== $b['b'][$i]) $d++;
    $cd = 0; for ($i = 0; $i < 48; $i++) $cd += abs($a['c'][$i] - $b['c'][$i]);
    return $d + 1.2 * ($cd / 48) + 25 * abs(log(max(0.2, $a['ar']) / max(0.2, $b['ar'])));
}

/** Shopify CDN: küçük kopya iste. */
function fv_small(string $src, int $w): string
{
    $src = str_starts_with($src, '//') ? 'https:' . $src : $src;
    return $src . (str_contains($src, '?') ? '&' : '?') . 'width=' . $w;
}

/* ------------------------------------------------------------ katalogları çek */
$brands = [];
foreach ($targets as $t) $brands[fv_bkey((string)$t['brand'])] = 1;
$pool = [];   // bkey => [ [store, handle, title, images[], kind], … ]
foreach ($stores as $host) {
    $n = 0;
    for ($page = 1; $page <= 120; $page++) {
        $j = json_decode((string)fv_get("https://$host/products.json?limit=250&page=$page", 40), true);
        $ps = $j['products'] ?? null;
        if (!is_array($ps) || !$ps) break;
        foreach ($ps as $p) {
            $bk = fv_bkey((string)($p['vendor'] ?? ''));
            if (!isset($brands[$bk])) continue;
            $imgs = array_values(array_filter(array_map(fn($i) => (string)($i['src'] ?? ''), (array)($p['images'] ?? []))));
            if (!$imgs) continue;
            $title = (string)($p['title'] ?? '');
            $pool[$bk][] = ['store' => $host, 'handle' => (string)$p['handle'], 'title' => $title,
                            'images' => array_slice($imgs, 0, 8),
                            'kind' => fv_kind($title . ' ' . ($p['product_type'] ?? '') . ' ' . implode(' ', (array)($p['tags'] ?? [])))];
            $n++;
        }
        usleep(400000);
    }
    printf("%-28s %d ürün (hedef markalardan)\n", $host, $n);
}
foreach ($pool as $bk => $list) printf("  %-18s %d aday ürün\n", $bk, count($list));

/* ---------------------------------------------------------------- eşleştir */
$sigCache = [];
$sigOf = function (string $url) use (&$sigCache): ?array {
    if (array_key_exists($url, $sigCache)) return $sigCache[$url];
    $raw = fv_get(fv_small($url, 240), 20);
    return $sigCache[$url] = $raw === null ? null : fv_sig($raw);
};

$made = 0; $i = 0;
foreach ($targets as $t) {
    $id = (string)$t['id']; $i++;
    if (isset($approve[$id])) continue;
    $bk = fv_bkey((string)$t['brand']);
    $cands = $pool[$bk] ?? [];
    if (!$cands) { printf("%3d %-40s marka katalogda yok\n", $i, $id); continue; }
    $curAbs = $root . (string)$t['current'];
    $mine = is_file($curAbs) ? fv_sig((string)file_get_contents($curAbs)) : null;
    if (!$mine) { printf("%3d %-40s eldeki kare okunamadı\n", $i, $id); continue; }
    $kind = fv_kind((string)$t['name'] . ' ' . (string)($t['cat'] ?? ''));
    if ($kind !== '') {
        $same = array_values(array_filter($cands, fn($c) => $c['kind'] === $kind));
        if ($same) $cands = $same;
    }
    $colour = strtolower((string)$t['colour']);
    $scored = [];
    foreach ($cands as $c) {
        $best = null;
        foreach (array_slice($c['images'], 0, 2) as $u) {
            $s = $sigOf($u);
            if ($s) $best = min($best ?? INF, fv_score($mine, $s));
        }
        if ($best === null) continue;
        if ($colour !== '' && str_contains(strtolower($c['title']), $colour)) $best -= 8;
        $scored[] = [$best, $c];
    }
    usort($scored, fn($a, $b) => $a[0] <=> $b[0]);
    $top = array_slice($scored, 0, 3);
    if (!$top) { printf("%3d %-40s aday yok\n", $i, $id); continue; }

    // rapor kayıtları + kolaj
    $rows = [];
    foreach ($top as $k => [$sc, $c]) {
        $key = "vis:$id:" . ($k + 1);
        $report[$key] = ['source' => "https://{$c['store']}/products/{$c['handle']}", 'title' => $c['title'],
                         'images' => $c['images'], 'score' => round($sc, 1), 'brand' => (string)$t['brand'],
                         'name' => (string)$t['name'], 'colour' => (string)$t['colour'], 'visual' => true];
        $rows[] = [$k + 1, $c, $sc];
    }
    fv_sheet("$outDir/" . preg_replace('/[^a-z0-9_-]/i', '_', $id) . '.jpg', $curAbs, $rows, $id . ' | ' . $t['brand'] . ' ' . $t['name']);
    $made++;
    printf("%3d %-40s %s\n", $i, $id, implode(' · ', array_map(fn($r) => sprintf('%.0f %s', $r[2], mb_substr($r[1]['title'], 0, 28)), $rows)));
}
file_put_contents($repFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "--- özet ---\nkolaj: $made ürün · $outDir\n";

/** Kolaj: solda ELDE, her satırda bir aday ürünün ilk 6 karesi (k.n numaralı). */
function fv_sheet(string $dst, string $curAbs, array $rows, string $label): void
{
    $cw = 170; $ch = 212; $pad = 8; $lab = 18;
    $W = $cw + 2 * $pad + 6 * ($cw + $pad); $H = count($rows) * ($ch + $lab + $pad) + 26;
    $s = imagecreatetruecolor($W, $H);
    imagefill($s, 0, 0, imagecolorallocate($s, 255, 255, 255));
    $ink = imagecolorallocate($s, 0, 0, 0); $red = imagecolorallocate($s, 190, 0, 0); $grey = imagecolorallocate($s, 110, 110, 110);
    $put = function (string $raw, int $x, int $y) use ($s, $cw, $ch) {
        $im = @imagecreatefromstring($raw); if (!$im) return;
        $sc = min($cw / imagesx($im), $ch / imagesy($im));
        $w = (int)(imagesx($im) * $sc); $h = (int)(imagesy($im) * $sc);
        imagecopyresampled($s, $im, $x + intdiv($cw - $w, 2), $y + intdiv($ch - $h, 2), 0, 0, $w, $h, imagesx($im), imagesy($im));
    };
    if (is_file($curAbs)) $put((string)file_get_contents($curAbs), $pad, 22);
    imagestring($s, 3, $pad, 22 + $ch + 2, 'ELDE', $red);
    imagestring($s, 2, $pad, 4, mb_substr($label, 0, 150), $ink);
    foreach ($rows as $r => [$k, $c, $sc]) {
        $y = 22 + $r * ($ch + $lab + $pad);
        foreach (array_slice($c['images'], 0, 6) as $n => $u) {
            $raw = fv_get(fv_small($u, 400), 20);
            if ($raw !== null) $put($raw, $cw + 2 * $pad + $n * ($cw + $pad), $y);
            imagestring($s, 3, $cw + 2 * $pad + $n * ($cw + $pad), $y + $ch + 2, "$k." . ($n + 1), $ink);
        }
        imagestring($s, 1, $cw + 2 * $pad + 60, $y + $ch + 6, sprintf('skor %.0f  %s  %s', $sc, parse_url('https://' . $c['store'], PHP_URL_HOST), mb_substr($c['title'], 0, 60)), $grey);
    }
    imagejpeg($s, $dst, 82);
}
