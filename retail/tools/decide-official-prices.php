<?php
/**
 * Model bazında resmî fiyat (UVP) kararı — CLI
 * =============================================
 *   php tools/decide-official-prices.php ../diag/prices/candidates.json [--write]
 *
 * tools/find-prices-ddg.php'nin topladığı fiyatlardan, bir modelin üstü
 * çizili gösterilebilecek resmî fiyatına karar verir. Kural, yasal sınır
 * yüzünden tutucu: Almanya'da üstü çizili UVP o modelin gerçek resmî fiyatı
 * olmak zorunda (UWG §5, PAngV).
 *
 * Kabul edilen kaynak (ürün kodunu sayfada taşıyan, EUR fiyatlı):
 *   a) Markanın kendi sitesi                            → doğrudan
 *   b) Mağazanın indirim öncesi fiyatı (compare_at / ListPrice)
 *   c) İki ayrı mağaza aynı fiyatı veriyor (±%8)
 * Hepsinde değer kategori referansının %55–180'i arasında olmalı (yanlış
 * ürün, set fiyatı, kuruş/avro karışıklığı elenir). Tek mağazanın tek
 * (indirimde olabilecek) fiyatı KABUL EDİLMEZ.
 *
 * --write ile data/official-prices.json'a sku satırları yazılır (kaynak
 * adresi ve tarihiyle); var olan sku satırları korunur, aynı sku güncellenir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
[, $in] = $argv + [null, null];
$write = in_array('--write', $argv, true);
$cand = json_decode((string)@file_get_contents((string)$in), true);
if (!is_array($cand)) { fwrite(STDERR, "aday dosyası yok\n"); exit(1); }

const OFFICIAL = '/^(?!secondemain\.)(.*\.)?(gucci|dolcegabbana|burberry|balmain|givenchy|dsquared2|lacoste|fendi|balenciaga|valentino|versace|fredperry|ralphlauren|gcds|marceloburlon|casablancaparis|jacquemus)\.(com|it|de|eu|fr|co\.uk)$/i';

$decided = []; $why = [];
foreach ($cand as $id => $c) {
    if (!empty($c['blocked'])) { $why['engellendi'] = ($why['engellendi'] ?? 0) + 1; continue; }
    $ref = (int)($c['ref'] ?? 0) / 100;
    $vals = [];                          // [değer, host, adres, tür]
    foreach ((array)($c['sources'] ?? []) as $s) {
        // İkinci el / yeniden satış bölümleri (secondemain.lacoste.com gibi) UVP kaynağı değildir.
        if (preg_match('/secondemain|second-?hand|pre-?owned|resale|vintage|myshopify\.com/i', (string)$s['host'] . ' ' . (string)$s['page'])) continue;
        // Aynı sayfada iki farklı Euro fiyatı varsa (ör. END.: meta'da indirim
        // öncesi, JSON-LD'de indirimli fiyat) büyük olan mağazanın indirim
        // öncesi fiyatıdır.
        $eur = [];
        foreach ((array)($s['prices'] ?? []) as $r) if (strtoupper((string)($r['cur'] ?? '')) === 'EUR' && (float)$r['price'] > 0) $eur[] = (float)$r['price'];
        $pageList = (count(array_unique($eur)) >= 2 && max($eur) >= min($eur) * 1.10) ? max($eur) : null;
        foreach ((array)($s['prices'] ?? []) as $r) {
            if (strtoupper((string)($r['cur'] ?? '')) !== 'EUR') continue;
            if ($pageList !== null && (float)$r['price'] === $pageList && empty($r['list'])) $r['list'] = $pageList;
            $isList = !empty($r['list']);
            $v = (float)($isList ? $r['list'] : $r['price']);
            if ($v <= 0) continue;
            if ($ref > 0 && ($v < $ref * 0.55 || $v > $ref * 1.8)) continue;
            $vals[] = [$v, (string)$s['host'], (string)$s['page'], $isList ? 'list' : 'price'];
        }
    }
    if (!$vals) { $why['uygun EUR fiyatı yok'] = ($why['uygun EUR fiyatı yok'] ?? 0) + 1; continue; }
    $pick = null; $basis = '';
    foreach ($vals as $v) if (preg_match(OFFICIAL, $v[1])) { if (!$pick || $v[0] > $pick[0]) { $pick = $v; $basis = 'resmî site'; } }
    if (!$pick) foreach ($vals as $v) if ($v[3] === 'list') { if (!$pick || $v[0] > $pick[0]) { $pick = $v; $basis = 'mağaza indirimsiz fiyatı'; } }
    if (!$pick) {
        foreach ($vals as $a) foreach ($vals as $b) {
            if ($a[1] === $b[1]) continue;
            if (abs($a[0] - $b[0]) <= 0.08 * max($a[0], $b[0])) { $m = $a[0] >= $b[0] ? $a : $b; if (!$pick || $m[0] > $pick[0]) { $pick = $m; $basis = 'iki mağaza uyumlu'; } }
        }
    }
    if (!$pick) { $why['tek mağaza, tek fiyat'] = ($why['tek mağaza, tek fiyat'] ?? 0) + 1; continue; }
    $why[$basis] = ($why[$basis] ?? 0) + 1;
    $decided[strtoupper(trim((string)$c['sku']))] = [
        'sku' => trim((string)$c['sku']), 'rrp' => round($pick[0]), 'src' => $pick[2], 'basis' => $basis,
        'checked' => gmdate('Y-m-d'), 'id' => (string)$id, 'brand' => (string)$c['brand'],
    ];
}
ksort($why);
foreach ($why as $k => $n) printf("  %-28s %d\n", $k, $n);
printf("Karar: %d model\n", count($decided));
foreach ($decided as $d) printf("  %-22s %-16s %7.0f  %s  %s\n", $d['sku'], $d['brand'], $d['rrp'], $d['basis'], parse_url($d['src'], PHP_URL_HOST));

if ($write) {
    $file = dirname(__DIR__) . '/data/official-prices.json';
    $rows = json_decode((string)file_get_contents($file), true) ?: [];
    $keep = [];
    foreach ($rows as $r) {
        $s = strtoupper(trim((string)($r['sku'] ?? '')));
        if ($s !== '' && isset($decided[$s])) continue;
        $keep[] = $r;
    }
    foreach ($decided as $d) { unset($d['id']); $keep[] = $d; }
    file_put_contents($file, json_encode($keep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    echo "data/official-prices.json yazıldı.\n";
}
