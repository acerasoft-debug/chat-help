<?php
/** photo-detail.json kapsamı ve Vault fiyat planları — SALT OKUNUR. php tools/audit-detail-coverage.php [--root=DIR] */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/vault.php';
$d = vr_photo_detail_index(); $n = count($d); $hd = 0;
foreach ($d as $m) if (isset($m['hd'])) $hd++;
$c = vr_catalog(); $lots = [];
foreach (vr_vault_all() as $l) {
    $p = $c[(string)($l['product_id'] ?? '')] ?? null;
    if (!$p) continue;
    $lots[] = [(string)$l['product_id'], (string)$p['brand'], (int)$l['open_cents'] / 100, (int)$l['floor_cents'] / 100, (int)$p['price_cents'] / 100];
}
$uvp = 0; foreach ($c as $p) if (!empty($p['rrp_cents'])) $uvp++;
echo json_encode(['detail' => $n, 'with_hd' => $hd, 'uvp_shown' => $uvp, 'lots' => $lots], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
