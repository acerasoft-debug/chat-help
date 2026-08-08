<?php
/**
 * Resmî fiyat tablosu — CLI
 * =========================
 *   php tools/official-prices.php --init      # şablonu oluştur/güncelle
 *   php tools/official-prices.php             # durumu göster
 *   php tools/official-prices.php --apply     # doldurulmuş satırları uygula
 *
 * NE İŞE YARIYOR
 * --------------
 * Satış fiyatı = resmî fiyat − %20 (yapılandırmada official_discount_bps).
 * Resmî fiyat ayrıca ürün kartında ve ürün sayfasında ÜSTÜ ÇİZİLİ gösteriliyor,
 * altyapı zaten var (rrp_cents).
 *
 * NEDEN ELLE DOLDURULUYOR
 * -----------------------
 * Kaynak stapellerin 400 satırının HİÇBİRİNDE resmî fiyat yok — yalnızca
 * toptan "list" var, o da parti başına tek değer (Burberry'nin 62 ürününün
 * hepsi aynı rakam). Yani −%20'yi hesaplayacak bir taban elimizde yok.
 *
 * BUNU UYDURMAK SEÇENEK DEĞİL
 * ---------------------------
 * Üstü çizili bir fiyat, gerçekten üreticinin tavsiye ettiği satış fiyatı
 * olmak zorundadır. Uydurulmuş bir referans fiyatı göstermek Almanya'da
 * yanıltıcı reklamdır (§ 5 UWG) ve indirim gösteriminde § 11 PAngV'ye
 * takılır — tipik bir ihtarname sebebi. Bu yüzden tablo boş doğuyor ve
 * doldurulmadığı sürece hiçbir üstü çizili fiyat basılmıyor; o ürünler
 * eski davranışa (toptan × çarpan) düşüyor.
 *
 * TABLONUN BİÇİMİ
 * ---------------
 * data/official-prices.json, marka + kategori kırılımında:
 *
 *   [
 *     {"brand": "BALMAIN", "cat": "T-Shirts", "rrp": 390.00, "n": 12},
 *     {"brand": "BALMAIN", "cat": "Bademode", "rrp": 0,      "n": 9}
 *   ]
 *
 * "rrp" 0 ise o satır yok sayılıyor. "n" yalnızca bilgi: o kırılımda kaç
 * ürün var, hangi satırı doldurmanın ne kadar işe yarayacağı görünsün diye.
 *
 * Tek bir ürün için ayrı fiyat gerekirse "sku" alanı eklenebilir; marka +
 * kategori satırından önce gelir.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';

$mode = 'show';
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--init')  $mode = 'init';
    if ($a === '--apply') $mode = 'apply';
}

$bps = (int)vr_config('official_discount_bps', 2000);
$pct = $bps / 100;

echo "MAXSALES — Offizielle Preise (UVP)\n";
echo str_repeat('=', 72) . "\n";
echo "Verkaufspreis = UVP − {$pct} %\n\n";

// Katalogdaki gerçek marka × kategori kırılımı.
$combos = [];
foreach (vr_catalog() as $p) {
    $key = $p['brand'] . '||' . $p['cat'];
    $combos[$key] = ($combos[$key] ?? 0) + 1;
}
ksort($combos);

$existing = vr_store_read('official-prices.json', []);
$byKey = [];
foreach (is_array($existing) ? $existing : [] as $r) {
    if (!is_array($r) || ($r['sku'] ?? '') !== '') continue;
    $byKey[trim((string)($r['brand'] ?? '')) . '||' . trim((string)($r['cat'] ?? ''))] = (float)($r['rrp'] ?? 0);
}

if ($mode === 'init') {
    $rows = [];
    // Elle girilmiş sku satırlarını koru.
    foreach (is_array($existing) ? $existing : [] as $r) {
        if (is_array($r) && ($r['sku'] ?? '') !== '') $rows[] = $r;
    }
    foreach ($combos as $key => $n) {
        [$brand, $cat] = explode('||', $key, 2);
        $rows[] = ['brand' => $brand, 'cat' => $cat, 'rrp' => $byKey[$key] ?? 0, 'n' => $n];
    }
    vr_store_write('official-prices.json', $rows);
    echo "Vorlage geschrieben: " . vr_store_path('official-prices.json') . "\n";
    echo count($rows) . " Zeilen. Tragen Sie in \"rrp\" den offiziellen Preis in Euro ein.\n";
    echo "Zeilen mit rrp = 0 bleiben unbeachtet.\n";
    exit(0);
}

// --------------------------------------------------------------- Bericht
$filled = $empty = 0;
$affected = 0;

printf("%-20s %-24s %5s %12s %14s\n", 'Marke', 'Kategorie', 'Stk', 'UVP', 'Verkaufspreis');
echo str_repeat('-', 80) . "\n";

foreach ($combos as $key => $n) {
    [$brand, $cat] = explode('||', $key, 2);
    $rrp = (float)($byKey[$key] ?? 0);
    if ($rrp > 0) {
        $filled++; $affected += $n;
        $cents = vr_charm_round((int)round($rrp * 100 * (10000 - $bps) / 10000));
        printf("%-20s %-24s %5d %12s %14s\n", mb_substr($brand, 0, 20), mb_substr($cat, 0, 24), $n,
               number_format($rrp, 2, ',', '.') . ' €', vr_money($cents));
    } else {
        $empty++;
        printf("%-20s %-24s %5d %12s %14s\n", mb_substr($brand, 0, 20), mb_substr($cat, 0, 24), $n, '—', '(unverändert)');
    }
}

echo str_repeat('-', 80) . "\n";
printf("Ausgefüllt : %d Zeilen → %d Artikel bekommen UVP und %s%% Abschlag\n", $filled, $affected, $pct);
printf("Offen      : %d Zeilen — diese Artikel behalten die bisherige Preisbildung\n", $empty);

if ($filled === 0) {
    echo "\nNoch nichts eingetragen. So geht es weiter:\n";
    echo "  1. php tools/official-prices.php --init\n";
    echo "  2. " . vr_store_path('official-prices.json') . " öffnen und \"rrp\" füllen\n";
    echo "  3. php tools/official-prices.php   (Kontrolle)\n";
    echo "\nWICHTIG: Nur echte Herstellerpreise eintragen. Ein erfundener\n";
    echo "durchgestrichener Preis ist irreführende Werbung (§ 5 UWG).\n";
}
