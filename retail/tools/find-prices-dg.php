<?php
/**
 * D&G modellerinin resmî fiyatı, markanın kendi sitesinden — CLI, runner'da
 *   php tools/find-prices-dg.php ../diag/prices/candidates.json
 *
 * dolcegabbana.com'un arama sayfası ürün kartlarını JSON olarak taşıyor:
 * "price":495 … "productId":"G8PT1TG7F2IS8291". Almanya sayfası (en-de)
 * Almanya fiyat listesini veriyor; bu, o modelin Almanya'daki resmî
 * fiyatıdır. Bulunan fiyat adaya "resmî site" kaynağı olarak eklenir.
 * Yalnız güncel sezonda satılan modeller bulunur; eski sezon kodları
 * sitede yok.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$file = (string)($argv[1] ?? '');
$cand = json_decode((string)@file_get_contents($file), true);
if (!is_array($cand)) exit(1);

function dg_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: de-DE,de;q=0.9,en;q=0.8']]);
    $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($b === false || $code >= 400) ? null : (string)$b;
}

$found = 0; $tried = 0;
foreach ($cand as $id => &$c) {
    if (stripos((string)($c['brand'] ?? ''), 'dolce') === false) continue;
    // Önceki turdan kalan 0 fiyatlı marka kayıtlarını temizle.
    $c['sources'] = array_values(array_filter((array)($c['sources'] ?? []), fn($s) => !(($s['prices'][0]['kind'] ?? '') === 'brand' && (float)($s['prices'][0]['price'] ?? 0) <= 0)));
    $flat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$c['sku']) ?? '');
    if (strlen($flat) < 8) continue;
    $base = substr($flat, 0, 11);
    $tried++;
    $hits = [];
    foreach (array_unique([$flat, $base]) as $q) {
        $html = dg_get('https://www.dolcegabbana.com/en-de/search?q=' . rawurlencode($q));
        usleep(1500000);
        if ($html === null) continue;
        if (preg_match_all('/"price":(\d+(?:\.\d+)?),"pricePerUnit":[\d.]+,"priceRanges":\[[^\]]*"pricebook":"([A-Z0-9]+)"[^\]]*\],"productId":"([A-Z0-9]+)"/', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $price, $book, $pid]) {
                if (!str_starts_with($pid, $base)) continue;
                // Renk kodu verilmişse aynı renk olmalı; verilmemişse aynı modelin her rengi.
                if (strlen($flat) > 11 && !str_starts_with($pid, $flat)) continue;
                $hits[$pid] = [(float)$price, $book];
            }
        }
        if ($hits) break;
    }
    if (!$hits) continue;
    $prices = array_values(array_unique(array_map(fn($h) => $h[0], $hits)));
    if (count($prices) !== 1) continue;                       // renkler farklı fiyatlıysa karar verme
    $pid = array_key_first($hits);
    $c['sources'][] = ['host' => 'www.dolcegabbana.com', 'page' => 'https://www.dolcegabbana.com/en-de/search?q=' . $pid,
                       'title' => 'dolcegabbana.com ' . $pid . ' (' . $hits[$pid][1] . ')',
                       'prices' => [['kind' => 'brand', 'cur' => 'EUR', 'price' => $prices[0], 'list' => null]]];
    $found++;
    fwrite(STDERR, sprintf("%-28s %s %s\n", (string)$c['sku'], $pid, $prices[0]));
}
unset($c);
file_put_contents($file, json_encode($cand, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
fwrite(STDERR, "D&G: $tried model arandı, $found resmî fiyat bulundu\n");
