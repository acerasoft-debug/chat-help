<?php
/**
 * Model koduyla resmî (indirimsiz) fiyat adayı bul — CLI, runner'da, salt okunur
 * ==========================================================================
 *   php tools/find-prices-ddg.php targets.json out.json
 *
 * Operatör, 14 Eki 2026: indirimler gösterilsin — ama üstü çizili fiyat
 * Almanya'da o modelin gerçek resmî fiyatı (UVP) olmak zorunda. Kategori
 * ortalaması bunun yerine geçmez. Bu araç her ürün için "<marka> <kod>"
 * arar (DuckDuckGo düz HTML; IP başına birkaç sorgudan sonra engellediği için
 * iş akışı çok parçalı) ve kodu GERÇEKTEN taşıyan sayfalardan fiyatı okur:
 *   · Shopify (/products/…) → <adres>.json: price ve compare_at_price
 *   · JSON-LD Product.offers: price, priceCurrency, ListPrice/StrikethroughPrice
 *   · product:price / og:price meta
 * Karar burada verilmez; tools/decide-official-prices.php verir.
 * İkinci el, outlet ve fiyat karşılaştırma siteleri kaynak sayılmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
[, $in, $outFile] = $argv + [null, null, null];
$targets = json_decode((string)@file_get_contents((string)$in), true);
if (!is_array($targets)) { fwrite(STDERR, "hedef yok\n"); exit(1); }

const FP_BLOCK = '/myshopify\.com|vestrasales|sarvesto|duckduckgo|bing\.|google\.|wikipedia|pinterest|youtube|instagram|facebook|tiktok|reddit|ebay\.|vinted|depop|grailed|poshmark|vestiaire|therealreal|stockx|goat\.com|kickscrew|replica|cheap|aliexpress|dhgate|amazon\.|yoox|outlet|lyst\.|modesens|stylight|shopstyle|clothbase|pricena|idealo|prisjagt|kleding|fashiola|editorialist|reversible/i';

function fp_get(string $url, int $timeout = 25, ?int &$code = null): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: de-DE,de;q=0.9,en;q=0.8', 'Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
    ]);
    $b = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

function fp_flat(string $s): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $s) ?? ''); }

function fp_num($v): ?float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $s = trim((string)$v);
    if ($s === '') return null;
    if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $s)) $s = str_replace(['.', ','], ['', '.'], $s);   // 1.234,56
    elseif (preg_match('/^\d+,\d{2}$/', $s)) $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9.]/', '', $s) ?? '';
    return is_numeric($s) ? (float)$s : null;
}

/** DuckDuckGo HTML → [[başlık, adres], …] */
function fp_search(string $q, int &$blocked): array
{
    $code = 0;
    // Bölge (DDG_KL, ör. de-de): Avrupa mağazaları öne çıksın, fiyat Euro gelsin.
    $kl = (string)getenv('DDG_KL');
    $html = fp_get('https://html.duckduckgo.com/html/?q=' . rawurlencode($q) . ($kl !== '' ? '&kl=' . rawurlencode($kl) : ''), 25, $code);
    if ($html === null || $code === 202 || !str_contains($html, 'result__a')) { $blocked++; return []; }
    $out = [];
    if (preg_match_all('#class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)</a>#s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $h, $t]) {
            $h = html_entity_decode($h);
            if (preg_match('#uddg=([^&]+)#', $h, $mm)) $h = urldecode($mm[1]);
            if (!preg_match('#^https?://#', $h) || str_contains($h, 'y.js?')) continue;
            $host = (string)parse_url($h, PHP_URL_HOST);
            if ($host === '' || preg_match(FP_BLOCK, $host)) continue;
            $out[] = [trim(html_entity_decode(strip_tags($t))), $h];
        }
    }
    return $out;
}

/** Sayfa → [fiyat kayıtları, kodu taşıyor mu] */
function fp_page(string $url, string $skuFlat): array
{
    $rows = [];
    $html = fp_get($url);
    if ($html === null) return [[], false];
    $has = $skuFlat !== '' && str_contains(fp_flat(strip_tags($html)) . fp_flat($html), $skuFlat);
    $cur = '';
    if (preg_match('/"currency"\s*:\s*\{\s*"active"\s*:\s*"([A-Z]{3})"/', $html, $m)) $cur = $m[1];
    elseif (preg_match('/<meta[^>]+(?:og|product):price:currency["\'][^>]+content=["\']([A-Z]{3})/i', $html, $m)) $cur = strtoupper($m[1]);
    elseif (preg_match('/"priceCurrency"\s*:\s*"([A-Z]{3})"/', $html, $m)) $cur = $m[1];

    // Shopify
    if (preg_match('#^(https?://[^/]+(?:/[a-z]{2}(?:-[a-z]{2})?)?/products/[^/?\#]+)#i', $url, $m)) {
        $j = json_decode((string)fp_get($m[1] . '.json'), true);
        if (is_array($j) && !empty($j['product'])) {
            $pr = $j['product'];
            $hay = fp_flat(json_encode([$pr['title'] ?? '', $pr['handle'] ?? '', $pr['body_html'] ?? '', array_column((array)($pr['variants'] ?? []), 'sku')]));
            if ($skuFlat !== '' && str_contains($hay, $skuFlat)) $has = true;
            foreach ((array)($pr['variants'] ?? []) as $v) {
                $p = fp_num($v['price'] ?? null); $c = fp_num($v['compare_at_price'] ?? null);
                if ($p) { $rows[] = ['kind' => 'shopify', 'cur' => $cur, 'price' => $p, 'list' => ($c && $c > $p) ? $c : null]; break; }
            }
        }
    }
    // JSON-LD
    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $mm)) {
        foreach ($mm[1] as $blob) {
            $j = json_decode(trim(html_entity_decode($blob)), true);
            $stack = [$j];
            while ($stack) {
                $n = array_pop($stack);
                if (!is_array($n)) continue;
                $isOffer = isset($n['price']) || isset($n['lowPrice']) || isset($n['highPrice']);
                if ($isOffer) {
                    $c = (string)($n['priceCurrency'] ?? $cur);
                    $p = fp_num($n['price'] ?? ($n['lowPrice'] ?? null));
                    $list = null;
                    foreach ((array)($n['priceSpecification'] ?? []) as $ps) {
                        if (is_array($ps) && preg_match('/ListPrice|StrikethroughPrice/i', (string)($ps['priceType'] ?? ''))) $list = fp_num($ps['price'] ?? null);
                    }
                    if ($p) $rows[] = ['kind' => 'jsonld', 'cur' => $c, 'price' => $p, 'list' => ($list && $list > $p) ? $list : null];
                }
                foreach ($n as $v) if (is_array($v)) $stack[] = $v;
            }
        }
    }
    // meta
    if (preg_match('/<meta[^>]+(?:product|og):price:amount["\'][^>]+content=["\']([^"\']+)/i', $html, $m) && ($p = fp_num($m[1]))) {
        $rows[] = ['kind' => 'meta', 'cur' => $cur, 'price' => $p, 'list' => null];
    }
    // Aynı değerleri tekilleştir
    $u = [];
    foreach ($rows as $r) $u[$r['kind'] . '|' . $r['cur'] . '|' . $r['price'] . '|' . $r['list']] = $r;
    return [array_values($u), $has];
}

$out = []; $blocked = 0;
foreach ($targets as $t) {
    $id = (string)$t['id']; $sku = trim((string)($t['sku'] ?? '')); $brand = (string)$t['brand'];
    $skuFlat = fp_flat($sku);
    if (strlen($skuFlat) < 5) continue;
    if ($blocked >= 2) { $out[$id] = ['blocked' => true] + $t; continue; }
    $sources = []; $seen = [];
    foreach ([$brand . ' ' . $sku, '"' . $sku . '"'] as $qi => $q) {
        if ($qi > 0 && ($sources || $blocked >= 2)) break;
        if ($qi > 0) sleep(random_int(5, 9));
        foreach (array_slice(fp_search($q, $blocked), 0, 8) as [$title, $u]) {
            if (count($sources) >= 4) break;
            $host = (string)parse_url($u, PHP_URL_HOST);
            if (isset($seen[$host])) continue;
            [$rows, $has] = fp_page($u, $skuFlat);
            if (!$has || !$rows) continue;
            $seen[$host] = 1;
            $sources[] = ['host' => $host, 'page' => $u, 'title' => $title, 'prices' => $rows];
            usleep(400000);
        }
    }
    $out[$id] = $t + ['sources' => $sources];
    fwrite(STDERR, sprintf("%-32s %d kaynak\n", $id, count($sources)));
    sleep(random_int(6, 11));
}
file_put_contents((string)$outFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
