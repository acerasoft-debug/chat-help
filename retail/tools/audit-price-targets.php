<?php
/**
 * Resmî fiyat araması için hedef listesi — SALT OKUNUR
 *   php tools/audit-price-targets.php [--root=DIR]
 * Vitrindeki ve GERÇEK model kodu taşıyan ürünler (VS-… iç kodları ve
 * "CASAWAY-WHITE" gibi addan türetilmiş kodlar hariç), kategori referans
 * fiyatıyla birlikte. Hiçbir şey yazmaz; JSON basar.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
$out = [];
foreach (vr_catalog() as $id => $p) {
    $sku = trim((string)($p['sku'] ?? ''));
    $flat = preg_replace('/[^A-Za-z0-9]/', '', $sku) ?? '';
    if (strlen($flat) < 5 || preg_match('/^VS-/i', $sku) || preg_match_all('/\d/', $flat) < 3) continue;
    $ref = vr_official_ref((string)$p['brand'], (string)$p['cat'], '', (string)$p['name']);
    $out[] = ['id' => (string)$id, 'brand' => (string)$p['brand'], 'name' => (string)$p['name'], 'cat' => (string)$p['cat'],
              'sku' => $sku, 'price' => (int)$p['price_cents'], 'ref' => $ref['cents'] ?? 0];
}
echo json_encode(['count' => count($out), 'products' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
