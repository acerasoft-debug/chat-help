<?php
/**
 * Ürüne ait olmayan kare avı (SALT OKUNUR) — php tools/audit-photo-mismatch.php [--root=DIR]
 * =========================================================================================
 * Operatör, 12 Eki 2026: "bazıları gerçek fotoya ait değil, bunları sil".
 * Bir satırın kareleri tek bir ürünü göstermeli. Toptancı bazı satırlara
 * modelin başka renklerinin ya da takımın öbür parçasının karelerini
 * koymuş; internetten onaylanan karelerde de aynı kodun başka rengi
 * kaçabiliyor.
 *
 * Ölçü data/photo-detail.json'daki ürün rengi ('c'). Bir ürünün kareleri
 * renklerine göre kümelenir (uzaklık ≤ 160); birden çok küme varsa ürün
 * listeye girer. Karar gözle verilir (kolaj), atılacak kare
 * data/photo-exclude.json'a ya da internet karesiyse onay listesinden çıkar.
 * Hiçbir şey yazmaz; JSON basar.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/view.php';

$det = vr_photo_detail_index();
$rgb = static fn(string $h): array => sscanf($h, '#%02x%02x%02x');
$dist = static function (array $a, array $b): float {
    return sqrt(2 * ($a[0] - $b[0]) ** 2 + 4 * ($a[1] - $b[1]) ** 2 + 3 * ($a[2] - $b[2]) ** 2);
};

$out = [];
foreach (vr_catalog() as $id => $p) {
    $imgs = array_values(array_filter((array)($p['images'] ?? []), fn($x) => is_string($x) && str_starts_with($x, '/uploads/')));
    if (count($imgs) < 2) continue;
    $clusters = [];   // [[merkez rgb, [kareler]]]
    $rows = [];
    foreach ($imgs as $img) {
        $m = $det[$img] ?? null;
        $ok = is_array($m) && !empty($m['c']) && (float)($m['m'] ?? 0) >= 0.12;
        $rows[] = ['img' => $img, 'c' => $m['c'] ?? null, 'm' => $m['m'] ?? null, 'light' => $m['light'] ?? null, 'k' => null];
        if (!$ok) continue;
        $c = $rgb((string)$m['c']);
        $hit = null;
        foreach ($clusters as $ki => [$cc]) if ($dist($c, $cc) <= 160) { $hit = $ki; break; }
        if ($hit === null) { $clusters[] = [$c, [$img]]; $hit = count($clusters) - 1; }
        else $clusters[$hit][1][] = $img;
        $rows[count($rows) - 1]['k'] = $hit;
    }
    if (count($clusters) < 2) continue;
    $out[] = ['id' => (string)$id, 'brand' => (string)$p['brand'], 'name' => vr_card_name($p),
              'colours' => vr_product_colours($p), 'card' => vr_card_image($p), 'clusters' => count($clusters), 'rows' => $rows];
}
echo json_encode(['count' => count($out), 'products' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
