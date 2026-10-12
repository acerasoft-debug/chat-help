<?php
/**
 * Model koduyla internette kare adayı bul — CLI (runner'da, salt okunur)
 * ======================================================================
 *   php tools/find-photos-ddg.php targets.json out.json
 *
 * targets.json: [{id, brand, name, colour, sku, current}, …] (bir parça)
 * Her ürün için DuckDuckGo'da "<marka> <kod>" aranır (düz HTML sürümü; bir
 * IP'den beşten fazla sorguda engelleniyor, iş akışı bu yüzden çok parçalı).
 * Sonuç sayfaları:
 *   · Shopify (/products/…) → <adres>.json'daki tüm kareler
 *   · thebs.com → büyük galeri kareleri
 *   · diğerleri → JSON-LD Product.image + og:image
 * Sayfa kodu gerçekten taşımıyorsa atlanır. Çıktı candidate-sheet.php'nin
 * okuduğu biçimde (id → sources[{page, images}]). Hiçbir şey indirilmez;
 * onay gözle, indirme attach-web-photos.php ile.
 *
 * vestrasales.com ve sarvesto.com hiçbir zaman kaynak değildir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
[, $in, $outFile] = $argv + [null, null, null];
$targets = json_decode((string)@file_get_contents((string)$in), true);
if (!is_array($targets)) { fwrite(STDERR, "hedef yok\n"); exit(1); }

const FD_BLOCK = '/vestrasales|sarvesto|duckduckgo|bing\.|google\.|wikipedia|pinterest|youtube|instagram|facebook|tiktok|reddit|ebay\.|vinted|depop|grailed|poshmark|vestiairecollective|therealreal|stockx|goat\.com|libascollective|replica|cheap|aliexpress|dhgate|amazon\./i';

function fd_get(string $url, int $timeout = 25, ?int &$code = null): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: en-GB,en;q=0.9', 'Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

function fd_flat(string $s): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $s) ?? ''); }

/** DuckDuckGo HTML → [[başlık, adres], …] */
function fd_search(string $q, int &$blocked): array
{
    $code = 0;
    $html = fd_get('https://html.duckduckgo.com/html/?q=' . rawurlencode($q), 25, $code);
    if ($html === null || $code === 202 || !str_contains($html, 'result__a')) { $blocked++; return []; }
    $out = [];
    if (preg_match_all('#class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>#s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $h, $t]) {
            $h = html_entity_decode($h);
            if (preg_match('#uddg=([^&]+)#', $h, $mm)) $h = urldecode($mm[1]);
            if (!preg_match('#^https?://#', $h) || str_contains($h, 'y.js?')) continue;
            $host = (string)parse_url($h, PHP_URL_HOST);
            if ($host === '' || preg_match(FD_BLOCK, $host)) continue;
            $out[] = [trim(html_entity_decode(strip_tags($t))), $h];
        }
    }
    return $out;
}

/** Sayfa → [kareler, kodu taşıyor mu] */
function fd_page(string $url, string $skuFlat): array
{
    if (preg_match('#^(https?://[^/]+(?:/[a-z]{2}(?:-[a-z]{2})?)?/products/[^/?\#]+)#i', $url, $m)) {
        $j = json_decode((string)fd_get($m[1] . '.json'), true);
        if (is_array($j) && !empty($j['product'])) {
            $p = $j['product'];
            $hay = fd_flat(json_encode([$p['title'] ?? '', $p['handle'] ?? '', $p['body_html'] ?? '', array_column((array)($p['variants'] ?? []), 'sku')]));
            $imgs = [];
            foreach ((array)($p['images'] ?? []) as $im) {
                if (!empty($im['src']) && (int)($im['width'] ?? 1000) >= 500) $imgs[] = (string)$im['src'];
            }
            return [$imgs, $skuFlat !== '' && str_contains($hay, $skuFlat)];
        }
    }
    $html = fd_get($url);
    if ($html === null) return [[], false];
    $has = $skuFlat !== '' && str_contains(fd_flat($html), $skuFlat);
    $imgs = [];
    if (str_contains($url, 'thebs.com') && preg_match_all('#https://images\.thebestshops\.com/product_images/large/[^"\'\s]+\.jpg#', $html, $mm)) {
        // İlk ürün kodunun kareleri (öneriler başka kodla geliyor).
        $first = '';
        foreach (array_unique($mm[0]) as $u) {
            $k = preg_replace('#^.*/large/([^_]+)_.*$#', '$1', $u);
            if ($first === '') $first = $k;
            if ($k === $first) $imgs[] = $u;
        }
    }
    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $mm)) {
        foreach ($mm[1] as $blob) {
            $j = json_decode(trim(html_entity_decode($blob)), true);
            $stack = [$j];
            while ($stack) {
                $n = array_pop($stack);
                if (!is_array($n)) continue;
                foreach ($n as $k => $v) {
                    if ($k === 'image') foreach ((array)$v as $iv) {
                        if (is_string($iv)) $imgs[] = $iv; elseif (is_array($iv) && !empty($iv['url'])) $imgs[] = (string)$iv['url'];
                    } elseif (is_array($v)) $stack[] = $v;
                }
            }
        }
    }
    if (preg_match_all('#<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)#i', $html, $mm)) {
        foreach ($mm[1] as $u) $imgs[] = html_entity_decode($u);
    }
    $imgs = array_map(fn($u) => str_starts_with($u, '//') ? 'https:' . $u : $u, $imgs);
    $imgs = array_values(array_unique(array_filter($imgs, fn($u) => preg_match('#^https?://#', $u) && !preg_match('/logo|placeholder|sprite|icon/i', $u))));
    return [$imgs, $has];
}

$out = []; $blocked = 0;
foreach ($targets as $t) {
    $id = (string)$t['id']; $sku = trim((string)($t['sku'] ?? '')); $brand = (string)$t['brand'];
    $skuFlat = fd_flat($sku);
    if (strlen($skuFlat) < 5) { fwrite(STDERR, "$id: kod yok\n"); continue; }
    if ($blocked >= 2) { fwrite(STDERR, "$id: arama engellendi, atlandı\n"); $out[$id] = ['blocked' => true] + $t; continue; }
    // 1) "<marka> <kod>"; hiçbir sayfa kodu taşımıyorsa 2) yalnız kod, tırnakla
    //    (bazı butikler markayı başlıkta farklı yazıyor: "D&G", "Dsquared²").
    $sources = []; $seen = []; $res = [];
    foreach ([$brand . ' ' . $sku, '"' . $sku . '"'] as $qi => $q) {
        if ($qi > 0 && ($sources || $blocked >= 2)) break;
        if ($qi > 0) sleep(random_int(5, 9));
        $res = fd_search($q, $blocked);
        foreach (array_slice($res, 0, 8) as [$title, $u]) {
            if (count($sources) >= 3) break;
            $host = (string)parse_url($u, PHP_URL_HOST);
            if (isset($seen[$host])) continue;
            [$imgs, $has] = fd_page($u, $skuFlat);
            if (!$has || !$imgs) continue;
            $seen[$host] = 1;
            $sources[] = ['page' => $u, 'title' => $title, 'images' => array_slice($imgs, 0, 6)];
            usleep(400000);
        }
    }
    $out[$id] = ['note' => $brand . ' ' . $sku . ' — ' . (string)$t['name'] . ' ' . (string)($t['colour'] ?? ''),
                 'brand' => $brand, 'name' => (string)$t['name'], 'colour' => (string)($t['colour'] ?? ''),
                 'current' => (string)($t['current'] ?? ''), 'sources' => $sources];
    fwrite(STDERR, sprintf("%-32s %d sonuç, %d kaynak, %d kare\n", $id, count($res), count($sources), array_sum(array_map(fn($s) => count($s['images']), $sources))));
    sleep(random_int(6, 11));   // arama motoruna nazik ol
}
file_put_contents((string)$outFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
