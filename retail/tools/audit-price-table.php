<?php
/**
 * Vitrin fiyat tablosu — SALT OKUNUR
 *   php tools/audit-price-table.php [--root=DIR]
 * Marka × kategori başına ürün sayısı, fiyat aralığı ve örnek adlar; resmî
 * fiyat (UVP) tablosu kurulurken ve sonrasında kontrol için. Hiçbir şey yazmaz.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
$t = [];
foreach (vr_catalog() as $p) {
    $k = strtoupper((string)$p['brand']) . '|' . (string)$p['cat'];
    $t[$k]['n'] = ($t[$k]['n'] ?? 0) + 1;
    $c = (int)$p['price_cents'];
    $t[$k]['min'] = min($t[$k]['min'] ?? PHP_INT_MAX, $c);
    $t[$k]['max'] = max($t[$k]['max'] ?? 0, $c);
    if (!empty($p['rrp_cents'])) $t[$k]['rrp'][] = (int)$p['rrp_cents'];
    if (count($t[$k]['names'] ?? []) < 6) $t[$k]['names'][] = (string)$p['name'] . ' [' . ($p['sku'] ?? '') . '] ' . ($c / 100);
}
ksort($t);
echo json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
