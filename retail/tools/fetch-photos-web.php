<?php
/**
 * Tek fotoğraflı ürünler için internetten aday kare bul — CLI (runner'da)
 * =====================================================================
 *   php tools/fetch-photos-web.php <targets.json> [--limit=N]
 *
 * targets.json: [{id, brand, name, colour, sku, current}] — mağazanın kendi
 * kodu sunucuda üretir (bkz. .github/workflows/fetch-photos-web.yml).
 *
 * NEREDEN ARIYOR
 * --------------
 *   1) Markanın kendi Shopify mağazası (/products.json — herkese açık katalog).
 *      Ad + renkle eşleştirilir; renk varyantının kendi kareleri varsa
 *      yalnızca onlar alınır.
 *   2) Yoksa: DuckDuckGo HTML araması, sonuçlardan ürünü açık sayfada
 *      gösteren mağazalar (JSON-LD Product.image, og:image).
 *
 * NE YAZAR
 * --------
 * Tam boy kareleri depoya YAZMAZ. Her ürün için küçük bir kontrol kolajı
 * (solda ELDEKİ kare, sağda numaralı adaylar) ve data/photo-web-report.json
 * (id → kaynak sayfa + aday adresleri). Onay gözle verilir; onaylanan kareler
 * ayrı bir adımda (tools/attach-web-photos.php) indirilip ürüne bağlanır.
 * Hak meselesi: data/image-sources.json → rights_confirmed (işletmeci beyanı).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$root    = dirname(__DIR__);
$tFile   = $argv[1] ?? '';
$limit   = 0;
foreach ($argv as $a) if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];
if ($tFile === '' || !is_file($tFile)) { fwrite(STDERR, "targets.json yok\n"); exit(1); }

$src = json_decode((string)file_get_contents($root . '/data/image-sources.json'), true);
if (empty($src['rights_confirmed'])) { fwrite(STDERR, "rights_confirmed=false — hiçbir şey indirilmiyor\n"); exit(1); }

$targets = json_decode((string)file_get_contents($tFile), true) ?: [];
if ($limit > 0) $targets = array_slice($targets, 0, $limit);

/** Markanın kendi Shopify mağazası. Liste bilerek kısa: yalnızca doğrulanmış resmi alanlar. */
const FPW_SHOPIFY = [
    'casablanca'    => ['https://casablancaparis.com', 'https://www.casablancaparis.com'],
    'gallerydept'   => ['https://gallerydept.com', 'https://www.gallerydept.com'],
];
/** Ürünü açık sayfada gösteren, bot isteğine cevap veren mağazalar (aramada öncelik). */
const FPW_GOOD_HOSTS = ['endclothing.com', 'thecorner.com', 'antonioli.eu', 'stadiumgoods.com', 'flannels.com',
    'giglio.com', 'italist.com', 'leam.com', 'mytheresa.com', 'breuninger.com', 'ssense.com', 'farfetch.com',
    'modesens.com', 'goat.com', 'lyst.com', 'dsquared2.com', 'jacquemus.com', 'casablancaparis.com', 'gallerydept.com',
    'balmain.com', 'givenchy.com', 'fendi.com', 'gucci.com', 'dolcegabbana.com', 'marceloburlon.eu', 'lacoste.com'];

function fpw_get(string $url, int $timeout = 25): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json,image/avif,image/webp,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

function fpw_norm(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ["'" => ' ', '’' => ' ', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '');
}

function fpw_tokens(string $s, string $brand, string $colour): array
{
    $stop = ['t', 'shirt', 'tee', 'tshirt', 'the', 'de', 'la', 'le', 'l', 'men', 'mens', 'women', 'unisex', 'cotton',
             'organic', 'short', 'sleeve', 'and', 'with', 'in'];
    foreach (explode(' ', fpw_norm($brand . ' ' . $colour)) as $w) $stop[] = $w;
    $out = [];
    foreach (explode(' ', fpw_norm($s)) as $w) if ($w !== '' && !in_array($w, $stop, true)) $out[$w] = true;
    return array_keys($out);
}

/** Shopify kataloğunu sayfa sayfa indir (önbellekli). */
function fpw_shopify(string $brandKey): array
{
    static $cache = [];
    if (isset($cache[$brandKey])) return $cache[$brandKey];
    $all = [];
    foreach (FPW_SHOPIFY[$brandKey] ?? [] as $base) {
        for ($page = 1; $page <= 40; $page++) {
            $j = json_decode((string)fpw_get($base . '/products.json?limit=250&page=' . $page), true);
            $ps = $j['products'] ?? [];
            if (!$ps) break;
            foreach ($ps as $p) { $p['_base'] = $base; $all[] = $p; }
        }
        if ($all) break;
    }
    fwrite(STDERR, "shopify $brandKey: " . count($all) . " ürün\n");
    return $cache[$brandKey] = $all;
}

/** Shopify ürününden, mümkünse renk varyantına ait kareleri seç. */
function fpw_shopify_images(array $p, string $colour): array
{
    $imgs = [];
    foreach ((array)($p['images'] ?? []) as $im) $imgs[(int)$im['id']] = ['src' => (string)$im['src'], 'vids' => (array)($im['variant_ids'] ?? [])];
    $c = fpw_norm($colour);
    if ($c !== '') {
        $vids = [];
        foreach ((array)($p['variants'] ?? []) as $v) {
            $opt = fpw_norm(implode(' ', [(string)($v['option1'] ?? ''), (string)($v['option2'] ?? ''), (string)($v['option3'] ?? ''), (string)($v['title'] ?? '')]));
            if ($opt !== '' && str_contains(' ' . $opt . ' ', ' ' . $c . ' ')) $vids[] = (int)$v['id'];
        }
        if ($vids) {
            $sel = array_filter($imgs, fn($i) => array_intersect($i['vids'], $vids));
            if ($sel) return array_values(array_map(fn($i) => $i['src'], $sel));
        }
    }
    return array_values(array_map(fn($i) => $i['src'], $imgs));
}

/** Renk varyantlarının stok kodları (beden eki atılmış) — model kodu adayı. */
function fpw_shopify_codes(array $p, string $colour): array
{
    $c = fpw_norm($colour); $all = []; $col = [];
    foreach ((array)($p['variants'] ?? []) as $v) {
        $sku = trim((string)($v['sku'] ?? ''));
        if ($sku === '') continue;
        $all[] = $sku;
        $opt = fpw_norm(implode(' ', [(string)($v['option1'] ?? ''), (string)($v['option2'] ?? ''), (string)($v['title'] ?? '')]));
        if ($c !== '' && str_contains(' ' . $opt . ' ', ' ' . $c . ' ')) $col[] = $sku;
    }
    return array_values(array_unique($col ?: $all));
}

/** Sayfadan ürün kareleri: JSON-LD Product.image, og:image, Shopify .json.
 *  JSON-LD'deki sku/mpn alanları $GLOBALS['fpw_codes']'a düşer. */
function fpw_page_images(string $url): array
{
    $out = [];
    if (preg_match('#/products/[^/?\#]+#', $url, $m)) {
        $base = preg_replace('#(/products/[^/?\#]+).*#', '$1', $url);
        $j = json_decode((string)fpw_get($base . '.json'), true);
        foreach ((array)($j['product']['images'] ?? []) as $im) if (!empty($im['src'])) $out[] = (string)$im['src'];
        foreach ((array)($j['product']['variants'] ?? []) as $v) if (!empty($v['sku'])) $GLOBALS['fpw_codes'][] = (string)$v['sku'];
        if ($out) return $out;
    }
    $html = fpw_get($url);
    if ($html === null) return [];
    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $mm)) {
        foreach ($mm[1] as $blob) {
            $j = json_decode(trim(html_entity_decode($blob)), true);
            if (!is_array($j)) continue;
            $stack = [$j];
            while ($stack) {
                $n = array_pop($stack);
                if (!is_array($n)) continue;
                foreach ($n as $k => $v) {
                    if ($k === 'image') {
                        foreach ((array)$v as $iv) {
                            if (is_string($iv)) $out[] = $iv;
                            elseif (is_array($iv) && !empty($iv['url'])) $out[] = (string)$iv['url'];
                        }
                    } elseif (($k === 'sku' || $k === 'mpn') && is_scalar($v) && trim((string)$v) !== '') {
                        $GLOBALS['fpw_codes'][] = trim((string)$v);
                    } elseif (is_array($v)) $stack[] = $v;
                }
            }
        }
    }
    if (preg_match_all('#<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)#i', $html, $mm)) {
        foreach ($mm[1] as $u) $out[] = html_entity_decode($u);
    }
    $out = array_map(fn($u) => str_starts_with($u, '//') ? 'https:' . $u : $u, $out);
    return array_values(array_unique(array_filter($out, fn($u) => preg_match('#^https?://#', $u))));
}

function fpw_search(string $q): array
{
    usleep(1800000); // arama motoruna nazik ol
    $html = fpw_get('https://html.duckduckgo.com/html/?q=' . rawurlencode($q));
    if ($html === null) return [];
    $urls = [];
    if (preg_match_all('#class="result__a"[^>]+href="([^"]+)"#', $html, $m)) {
        foreach ($m[1] as $h) {
            $h = html_entity_decode($h);
            if (preg_match('#uddg=([^&]+)#', $h, $mm)) $h = urldecode($mm[1]);
            if (preg_match('#^https?://#', $h)) $urls[] = $h;
        }
    }
    // İyi bilinen mağazalar öne.
    usort($urls, function ($a, $b) {
        $ga = 0; $gb = 0;
        foreach (FPW_GOOD_HOSTS as $i => $hst) { if (!$ga && str_contains($a, $hst)) $ga = 100 - $i; if (!$gb && str_contains($b, $hst)) $gb = 100 - $i; }
        return $gb <=> $ga;
    });
    return array_slice(array_values(array_unique($urls)), 0, 6);
}

function fpw_thumb(string $bytes, int $w, int $h)
{
    $src = @imagecreatefromstring($bytes);
    if (!$src) return null;
    $sw = imagesx($src); $sh = imagesy($src);
    if ($sw < 400 || $sh < 400) { imagedestroy($src); return null; }
    $sc = min($w / $sw, $h / $sh);
    $dw = (int)($sw * $sc); $dh = (int)($sh * $sc);
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagecopyresampled($im, $src, (int)(($w - $dw) / 2), (int)(($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
    return $im;
}

$sheetDir = $root . '/uploads/candidates/_web';
@mkdir($sheetDir, 0755, true);
$report = [];
foreach ($targets as $n => $t) {
    $id = (string)$t['id']; $brand = (string)$t['brand']; $name = (string)$t['name']; $colour = (string)($t['colour'] ?? '');
    $bk = preg_replace('/[^a-z]/', '', mb_strtolower($brand)) ?? '';
    $want = fpw_tokens($name, $brand, $colour);
    $found = ['source' => '', 'title' => '', 'images' => [], 'codes' => []];
    $GLOBALS['fpw_codes'] = [];

    // 1) Shopify
    if (isset(FPW_SHOPIFY[$bk])) {
        $best = null; $bestS = 0.0;
        foreach (fpw_shopify($bk) as $p) {
            $have = fpw_tokens((string)$p['title'], $brand, '');
            if (!$have || !$want) continue;
            $s = count(array_intersect($want, $have)) / max(count($want), 1);
            $s -= 0.08 * max(0, count($have) - count($want));
            $col = fpw_norm($colour);
            $hay = fpw_norm((string)$p['title'] . ' ' . json_encode($p['variants'] ?? []));
            if ($col !== '' && str_contains($hay, $col)) $s += 0.25;
            if ($s > $bestS) { $bestS = $s; $best = $p; }
        }
        if ($best && $bestS >= 0.6) {
            $found = ['source' => $best['_base'] . '/products/' . $best['handle'], 'title' => (string)$best['title'],
                      'images' => array_slice(fpw_shopify_images($best, $colour), 0, 6), 'score' => round($bestS, 2),
                      'codes' => fpw_shopify_codes($best, $colour)];
        }
    }
    // 2) Arama
    if (!$found['images']) {
        $sku = (string)($t['sku'] ?? '');
        $real = $sku !== '' && !preg_match('/^VS-|^[A-Z]+(-[A-Z0-9]+){2,}/', $sku);
        $byName = trim($brand . ' ' . preg_replace('/\s+—.*$/u', '', $name) . ' ' . $colour);
        // Gerçek model kodu varsa önce yalnızca onunla ara (ad çoğu zaman "T-Shirt" kadar kısa).
        $urls = [];
        $q = $real ? $brand . ' ' . $sku : $byName;
        $urls = fpw_search($q);
        if (!$urls && $real) $urls = fpw_search($q = $byName . ' ' . $sku);
        foreach ($urls as $u) {
            $imgs = fpw_page_images($u);
            if (count($imgs) >= 2) {
                $found = ['source' => $u, 'title' => $q, 'images' => array_slice($imgs, 0, 6),
                          'codes' => array_values(array_unique($GLOBALS['fpw_codes']))];
                break;
            }
            $GLOBALS['fpw_codes'] = [];
        }
    }

    // Kolaj: ELDEKİ + adaylar
    $tw = 220; $th = 275; $cells = [];
    $cur = (string)($t['current'] ?? '');
    $curAbs = $cur !== '' ? $root . $cur : '';
    $cells[] = ['ELDE', ($curAbs !== '' && is_file($curAbs)) ? fpw_thumb((string)file_get_contents($curAbs), $tw, $th) : null];
    $keep = [];
    foreach ($found['images'] as $u) {
        $b = fpw_get($u, 30);
        if ($b === null) continue;
        $im = fpw_thumb($b, $tw, $th);
        if (!$im) continue;
        $keep[] = $u;
        $cells[] = [(string)count($keep), $im];
    }
    $found['images'] = $keep;
    $report[$id] = $found + ['brand' => $brand, 'name' => $name, 'colour' => $colour];

    $W = count($cells) * $tw; $H = $th + 40;
    $sheet = imagecreatetruecolor(max($W, $tw * 2), $H);
    imagefill($sheet, 0, 0, imagecolorallocate($sheet, 250, 250, 248));
    $ink = imagecolorallocate($sheet, 20, 20, 20); $red = imagecolorallocate($sheet, 190, 0, 0);
    foreach ($cells as $i => [$lab, $im]) {
        if ($im) { imagecopy($sheet, $im, $i * $tw, 0, 0, 0, $tw, $th); imagedestroy($im); }
        imagestring($sheet, 5, $i * $tw + 6, $th + 4, $lab, $i === 0 ? $red : $ink);
    }
    imagestring($sheet, 2, 6, $th + 22, substr($id . ' | ' . $brand . ' ' . $name . ' | ' . parse_url((string)$found['source'], PHP_URL_HOST), 0, 160), $ink);
    imagejpeg($sheet, sprintf('%s/%03d-%s.jpg', $sheetDir, $n + 1, preg_replace('/[^a-z0-9-]/', '', $id)), 72);
    imagedestroy($sheet);
    fwrite(STDERR, sprintf("%3d %-34s %d aday  %s\n", $n + 1, $id, count($keep), $found['source']));
}

file_put_contents($root . '/data/photo-web-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
